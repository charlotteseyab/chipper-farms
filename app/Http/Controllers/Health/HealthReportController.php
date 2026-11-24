<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Models\VetExamination;
use App\Services\Health\HealthReportService;
use App\Support\CollectionPage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Health reports on screen and as CSV. Viewing needs view_health_records;
 * downloading needs export_health_reports. Exports stream row by row.
 */
class HealthReportController extends Controller
{
    /** Columns used only to build links on screen, never exported. */
    private const INTERNAL = ['medicine_id', 'pig_id'];

    public function index(Request $request, HealthReportService $reports)
    {
        abort_unless($request->user()->hasPermission('view_health_records'), 403);
        $tab = array_key_exists($request->query('tab'), HealthReportService::TABS) ? $request->query('tab') : 'cases';
        $f = $this->filters($request);
        [$from, $to, $shortened] = $reports->range($f);

        $data = match ($tab) {
            'cases' => ['page' => $reports->cases($f)->paginate(25)->withQueryString(), 'map' => 'caseRow'],
            'doses' => ['page' => $reports->doses($f)->paginate(25)->withQueryString(), 'map' => 'doseRow',
                'notGiven' => $this->rows($reports->notGiven($f)->paginate(25, ['*'], 'not_given_page')->withQueryString(), fn ($d) => $reports->notGivenRow($d))],
            'usage' => ['usage' => ($usage = $reports->usage($f)), 'usageRows' => CollectionPage::make($usage['rows'], 25, 'usage_page')],
            'stock' => ['batches' => CollectionPage::make($reports->batches(), 15, 'batches_page'), 'lowStock' => CollectionPage::make($reports->lowStock(), 15, 'low_page'),
                'withdrawal' => $this->rows($reports->underWithdrawal()->paginate(25, ['*'], 'withdrawal_page')->withQueryString(), fn ($d) => $reports->withdrawalRow($d))],
            'visits' => ['page' => $reports->visits($f)->paginate(25)->withQueryString(), 'map' => 'visitRow'],
        };
        if (isset($data['page'])) {
            $data['rows'] = collect($data['page']->items())->map(fn ($item) => $reports->{$data['map']}($item));
        }

        return view('health.reports', $data + [
            'tab' => $tab, 'filters' => $f, 'from' => $from, 'to' => $to, 'shortened' => $shortened,
            'canExport' => $request->user()->hasPermission('export_health_reports'),
        ]);
    }

    public function export(Request $request, HealthReportService $reports, string $tab): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('export_health_reports'), 403);
        $f = $this->filters($request);

        $rows = match ($tab) {
            'cases' => $reports->cases($f)->lazy(500)->map(fn ($r) => $reports->caseRow($r)),
            // Doses given, then scheduled doses missed or cancelled, in the same columns.
            'doses' => $reports->doses($f)->lazy(500)->map(fn ($r) => $reports->doseRow($r))
                ->concat($reports->notGiven($f)->lazy(500)->map(fn ($d) => $this->notGivenAsDose($reports->notGivenRow($d)))),
            'usage' => $reports->usage($f)['rows'],
            'batches' => $reports->batches(),
            'withdrawal' => $reports->underWithdrawal()->lazy(500)->map(fn ($r) => $reports->withdrawalRow($r)),
            'visits' => $reports->examinations($f)->lazy(500)->map(fn (VetExamination $e) => $reports->examinationRow($e)),
            default => abort(404),
        };

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $header = false;
            foreach ($rows as $row) {
                $row = array_diff_key($row, array_flip(self::INTERNAL));
                if (! $header) {
                    fputcsv($out, array_keys($row), escape: '');
                    $header = true;
                }
                fputcsv($out, array_map(fn ($v) => $this->safe($v), array_values($row)), escape: '');
            }
            if (! $header) {
                fputcsv($out, ['No rows for these filters'], escape: '');
            }
            fclose($out);
        }, "health-{$tab}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Map a page of models to report rows, keeping the paginator for the links. */
    private function rows(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page, \Closure $map): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return $page->through($map);
    }

    /** The old /export/health.csv link: cases with its condition, severity and date filters. */
    public function legacyHealthExport(Request $request, HealthReportService $reports): StreamedResponse
    {
        return $this->export($request, $reports, 'cases');
    }

    /** The old /export/medications.csv link: "q" was the medicine name. */
    public function legacyMedicationExport(Request $request, HealthReportService $reports): StreamedResponse
    {
        if ($request->filled('q') && ! $request->filled('medicine')) {
            $request->merge(['medicine' => $request->query('q')]);
        }

        return $this->export($request, $reports, 'doses');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer'],
            'condition' => ['nullable', 'string', 'max:100'], 'severity' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'], 'scope' => ['nullable', 'in:individual,group'],
            'group' => ['nullable', 'string', 'max:100'], 'pig' => ['nullable', 'string', 'max:50'],
            'medicine' => ['nullable', 'string', 'max:100'], 'purpose' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:50'], 'by' => ['nullable', 'in:medicine,batch,animal,group'],
            'vet' => ['nullable', 'string', 'max:100'], 'assessment' => ['nullable', 'in:'.implode(',', array_keys(VetExamination::ASSESSMENTS))],
        ]);
    }

    private function notGivenAsDose(array $row): array
    {
        return ['Given' => $row['Due'], 'Animal' => $row['Animal'], 'Tag that day' => null, 'Medicine' => $row['Medicine'], 'Dose' => null,
            'Route' => null, 'Purpose' => 'Course '.$row['Course'], 'Batch' => null, 'Withdrawal days' => null, 'Sellable from' => null,
            'Given by' => null, 'Status' => $row['Status'].' (not given)', 'Reason' => $row['Reason']];
    }

    /** Stop spreadsheet formula injection from free-text fields. */
    private function safe(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
