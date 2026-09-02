<?php

namespace App\Services\Stock\Imports;

use App\Models\PigManagement;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Services\Audit\AuditTrailService;
use App\Services\Stock\PigStockService;
use App\Services\Stock\StockValidationException;
use App\Support\CurrentOrganization;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class StockExcelImportService
{
    public const HEADERS = [
        'ear_tag_number',
        'breed',
        'gender',
        'stock_type',
        'date_of_birth',
        'structure',
        'room_name',
        'father_ear_tag',
        'mother_ear_tag',
        'acquisition_source',
        'acquisition_date',
        'acquisition_cost',
        'notes',
    ];

    public function __construct(
        protected PigStockService $stockService,
        protected CurrentOrganization $currentOrganization,
        protected AuditTrailService $audit,
    ) {
    }

    public function preview(UploadedFile $file): array
    {
        $rows = $this->readXlsx($file->getRealPath());
        $headers = array_shift($rows) ?? [];
        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader($header), $headers);
        $missingHeaders = array_values(array_diff(self::HEADERS, $normalizedHeaders));

        $preview = [
            'file_name' => $file->getClientOriginalName(),
            'headers' => $normalizedHeaders,
            'missing_headers' => $missingHeaders,
            'total_rows' => count($rows),
            'valid_count' => 0,
            'invalid_count' => 0,
            'valid_rows' => [],
            'invalid_rows' => [],
        ];

        if ($missingHeaders !== []) {
            foreach ($rows as $index => $row) {
                $preview['invalid_rows'][] = [
                    'row_number' => $index + 2,
                    'data' => $row,
                    'errors' => ['Missing required Excel headers: ' . implode(', ', $missingHeaders)],
                ];
            }
            $preview['invalid_count'] = count($preview['invalid_rows']);
            return $preview;
        }

        $seenEarTags = [];
        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rowNumber = $index + 2;
            $data = $this->mapRow($normalizedHeaders, $row);
            $result = $this->validateRow($data, $seenEarTags);

            if ($result['errors'] === []) {
                $preview['valid_rows'][] = [
                    'row_number' => $rowNumber,
                    'data' => $result['data'],
                ];
                $preview['valid_count']++;
            } else {
                $preview['invalid_rows'][] = [
                    'row_number' => $rowNumber,
                    'data' => $result['data'],
                    'errors' => $result['errors'],
                ];
                $preview['invalid_count']++;
            }

            if (! empty($result['data']['ear_tag_number'])) {
                $seenEarTags[] = $result['data']['ear_tag_number'];
            }
        }

        return $preview;
    }

    public function import(array $preview, $user): array
    {
        if (($preview['invalid_count'] ?? 0) > 0) {
            throw new StockValidationException('import', 'Fix invalid rows before confirming the stock import.');
        }

        $created = [];

        DB::transaction(function () use ($preview, &$created) {
            foreach ($preview['valid_rows'] as $row) {
                $created[] = $this->stockService->create($this->creationPayload($row['data']));
            }
        });

        $this->audit->log('stock', 'imported', [
            'user' => $user,
            'organization_id' => $this->currentOrganization->id(),
            'record_label' => $preview['file_name'] ?? 'Stock Excel import',
            'description' => count($created) . ' stock record(s) imported from Excel.',
            'metadata' => [
                'file_name' => $preview['file_name'] ?? null,
                'imported_rows' => count($created),
                'failed_rows' => $preview['invalid_count'] ?? 0,
                'created_pig_ids' => array_map(fn ($pig) => $pig->id, $created),
            ],
        ]);

        return [
            'created_count' => count($created),
            'created_pigs' => $created,
        ];
    }

    public function sampleRows(): array
    {
        $structureName = '';
        $roomName = '';

        try {
            $room = Room::with('structure')->orderBy('name')->first();
            $structureName = $room?->structure?->name ?? '';
            $roomName = $room?->name ?? '';
        } catch (\Throwable) {
            // The sample should still download even if the database is temporarily unavailable.
        }

        $samplePrefix = 'SAMPLE-' . now()->format('YmdHis');

        $rows = [self::HEADERS];

        foreach (array_slice(Setting::animalBreeds(), 0, 3, true) as $code => $name) {
            $index = count($rows);
            $purchased = $index === 2;

            $rows[] = [
                $samplePrefix . '-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                $code,
                $index % 2 === 0 ? 'M' : 'F',
                $index === 1 ? 'breeding' : 'standard',
                now()->subMonths(8 + $index)->format('Y-m-d'),
                $structureName,
                $roomName,
                '',
                '',
                $purchased ? 'purchased' : 'born_on_farm',
                now()->subMonths($purchased ? 3 : 8 + $index)->format('Y-m-d'),
                $purchased ? '450' : '',
                "Example breed: {$name} ({$code})",
            ];
        }

        return $rows;
    }

    public function buildXlsx(iterable $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'stock-xlsx-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheetXml($rows));
        $zip->close();

        return $path;
    }

    protected function readXlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new StockValidationException('stock_file', 'Could not open the uploaded Excel file.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new StockValidationException('stock_file', 'The Excel file must contain a first worksheet.');
        }

        $xml = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($xml->sheetData->row ?? [] as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                $values[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            if ($values !== []) {
                ksort($values);
                $max = max(array_keys($values));
                $filled = [];
                for ($i = 0; $i <= $max; $i++) {
                    $filled[] = $values[$i] ?? '';
                }
                $rows[] = $filled;
            }
        }

        return $rows;
    }

    protected function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = [];
        $strings = simplexml_load_string($xml);
        foreach ($strings->si ?? [] as $si) {
            if (isset($si->t)) {
                $shared[] = (string) $si->t;
                continue;
            }

            $text = '';
            foreach ($si->r ?? [] as $run) {
                $text .= (string) $run->t;
            }
            $shared[] = $text;
        }

        return $shared;
    }

    protected function cellValue($cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 's') {
            return (string) ($sharedStrings[(int) $cell->v] ?? '');
        }

        if ($type === 'inlineStr') {
            return (string) ($cell->is->t ?? '');
        }

        return (string) ($cell->v ?? '');
    }

    protected function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return max(0, $index - 1);
    }

    protected function normalizeHeader(?string $header): string
    {
        return strtolower(trim((string) $header));
    }

    protected function mapRow(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        foreach (self::HEADERS as $header) {
            $data[$header] = $data[$header] ?? '';
        }

        return $data;
    }

    protected function isEmptyRow(array $row): bool
    {
        return implode('', array_map(fn ($value) => trim((string) $value), $row)) === '';
    }

    protected function validateRow(array $data, array $seenEarTags): array
    {
        $errors = [];
        $data['ear_tag_number'] = $this->normalizeTextInput($data['ear_tag_number'] ?? '');
        $data['breed'] = strtoupper($this->normalizeTextInput($data['breed'] ?? ''));
        $data['structure'] = $this->normalizeTextInput($data['structure'] ?? '');
        $data['room_name'] = $this->normalizeTextInput($data['room_name'] ?? '');
        $data['father_ear_tag'] = $this->normalizeTextInput($data['father_ear_tag'] ?? '');
        $data['mother_ear_tag'] = $this->normalizeTextInput($data['mother_ear_tag'] ?? '');
        $data['gender'] = strtoupper(trim($data['gender'] ?? ''));
        $data['stock_type'] = strtolower(trim($data['stock_type'] ?? ''));
        $data['acquisition_source'] = strtolower(trim($data['acquisition_source'] ?? ''));
        $data['date_of_birth'] = $this->normalizeDateInput($data['date_of_birth'] ?? '');
        $data['acquisition_date'] = $this->normalizeDateInput($data['acquisition_date'] ?? '');

        foreach (['ear_tag_number', 'breed', 'gender', 'stock_type', 'date_of_birth'] as $field) {
            if ($data[$field] === '') {
                $errors[] = str_replace('_', ' ', $field) . ' is required.';
            }
        }

        if ($data['ear_tag_number'] !== '') {
            if (in_array($data['ear_tag_number'], $seenEarTags, true)) {
                $errors[] = 'Ear tag number is duplicated in the uploaded file.';
            }

            if (PigManagement::withTrashed()->where('ear_tag_number', $data['ear_tag_number'])->exists()) {
                $errors[] = 'Ear tag number already exists in the system.';
            }
        }

        if ($data['gender'] !== '' && ! in_array($data['gender'], ['M', 'F'], true)) {
            $errors[] = 'Gender must be M or F.';
        }

        if ($data['stock_type'] !== '' && ! in_array($data['stock_type'], ['breeding', 'standard'], true)) {
            $errors[] = 'Stock type must be breeding or standard.';
        }

        $breeds = Setting::animalBreeds();
        if ($data['breed'] !== '' && ! array_key_exists($data['breed'], $breeds)) {
            $accepted = collect($breeds)
                ->map(fn (string $name, string $code) => "{$code} ({$name})")
                ->implode(', ');
            $errors[] = $accepted === ''
                ? 'No animal breeds have been configured for this farm. Add them under Settings > Setup > Breeds first.'
                : 'Breed must use one of this farm\'s configured codes: ' . $accepted . '.';
        }

        if ($data['date_of_birth'] !== '' && ! $this->validDate($data['date_of_birth'])) {
            $errors[] = 'Date of birth must use YYYY-MM-DD.';
        }

        if ($data['acquisition_source'] !== '' && ! in_array($data['acquisition_source'], ['born_on_farm', 'purchased'], true)) {
            $errors[] = 'Acquisition source must be born_on_farm or purchased.';
        }

        if ($data['acquisition_date'] !== '' && ! $this->validDate($data['acquisition_date'])) {
            $errors[] = 'Acquisition date must use YYYY-MM-DD.';
        }

        if ($data['acquisition_cost'] !== '' && ! is_numeric($data['acquisition_cost'])) {
            $errors[] = 'Acquisition cost must be numeric.';
        }

        $data['room_id'] = null;
        $structure = null;
        if (($data['structure'] ?? '') !== '') {
            $structure = Structure::where('name', $data['structure'])->first();
            if (! $structure) {
                $errors[] = 'Structure does not match an existing structure.';
            }
        }

        if ($data['room_name'] !== '') {
            $roomQuery = Room::where('name', $data['room_name']);
            if ($structure) {
                $roomQuery->where('structure_id', $structure->id);
            }
            $room = $roomQuery->first();
            if (! $room) {
                $errors[] = $structure
                    ? 'Room name does not match an existing room in the selected structure.'
                    : 'Room name does not match an existing room.';
            } else {
                $data['room_id'] = $room->id;
            }
        }

        $data['father_id'] = null;
        if ($data['father_ear_tag'] !== '') {
            $father = PigManagement::active()->where('ear_tag_number', $data['father_ear_tag'])->first();
            if (! $father) {
                $errors[] = 'Father ear tag does not match an existing pig.';
            } elseif ($father->gender !== 'M') {
                $errors[] = 'Father ear tag must belong to a male pig.';
            } else {
                $data['father_id'] = $father->id;
            }
        }

        $data['mother_id'] = null;
        if ($data['mother_ear_tag'] !== '') {
            $mother = PigManagement::active()->where('ear_tag_number', $data['mother_ear_tag'])->first();
            if (! $mother) {
                $errors[] = 'Mother ear tag does not match an existing pig.';
            } elseif ($mother->gender !== 'F') {
                $errors[] = 'Mother ear tag must belong to a female pig.';
            } else {
                $data['mother_id'] = $mother->id;
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    protected function validDate(string $date): bool
    {
        try {
            return Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function normalizeDateInput(string $date): string
    {
        $date = trim($date);

        if ($date === '') {
            return '';
        }

        if (is_numeric($date)) {
            $serial = (int) floor((float) $date);

            if ($serial > 0) {
                return Carbon::create(1899, 12, 30)->addDays($serial)->format('Y-m-d');
            }
        }

        return $date;
    }

    protected function normalizeTextInput(string $value): string
    {
        $value = trim($value);

        if ($value !== '' && is_numeric($value) && floor((float) $value) == (float) $value) {
            return (string) (int) $value;
        }

        return $value;
    }

    protected function creationPayload(array $data): array
    {
        $payload = [
            'ear_tag_number' => $data['ear_tag_number'],
            'breed' => $data['breed'],
            'gender' => $data['gender'],
            'stock_type' => $data['stock_type'],
            'date_of_birth' => $data['date_of_birth'],
            'room_id' => $data['room_id'],
            'father_id' => $data['father_id'],
            'mother_id' => $data['mother_id'],
        ];

        if (Schema::hasColumn('pigs_management', 'notes')) {
            $payload['notes'] = $data['notes'] ?: null;
        }

        if ($data['acquisition_source'] !== '') {
            $payload['acquisition_source'] = $data['acquisition_source'];
        }

        if ($data['acquisition_date'] !== '') {
            $payload['acquisition_date'] = $data['acquisition_date'];
        }

        if ($data['acquisition_cost'] !== '') {
            $payload['acquisition_cost'] = (float) $data['acquisition_cost'];
        }

        return $payload;
    }

    protected function sheetXml(iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>';

        for ($i = 1; $i <= 13; $i++) {
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="18" customWidth="1"/>';
        }

        $xml .= '</cols><sheetData>';

        $rowNumber = 1;
        foreach ($rows as $row) {
            $xml .= '<row r="' . $rowNumber . '">';
            $column = 1;
            foreach ($row as $value) {
                $cellRef = $this->columnName($column) . $rowNumber;
                $style = $rowNumber === 1 ? ' s="1"' : '';
                $xml .= '<c r="' . $cellRef . '"' . $style . ' t="inlineStr"><is><t>' . $this->escapeXml((string) $value) . '</t></is></c>';
                $column++;
            }
            $xml .= '</row>';
            $rowNumber++;
        }

        return $xml . '</sheetData></worksheet>';
    }

    protected function columnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    protected function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    protected function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Stock" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    protected function workbookRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F6F50"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
