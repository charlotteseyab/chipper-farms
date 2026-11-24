<?php

namespace App\Services\Stock\Imports;

use App\Models\PigManagement;
use App\Models\PigWeight;
use App\Models\EarTagColor;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Structure;
use App\Services\Audit\AuditTrailService;
use App\Services\Stock\PigStockService;
use App\Services\Stock\EarTagNumberService;
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
        'Ear Tag',
        'Tag Colour',
        'Breed',
        'Type',
        'Gender',
        'DOB',
        'Location',
        'Weight',
    ];

    /**
     * Columns older exports carried that the import never used. A file that
     * still has one imports normally; the column is left out of the preview.
     */
    private const DROPPED_HEADERS = ['actions'];

    /** Rows one file may hold; larger herds import in several files. */
    public const MAX_ROWS = 1000;

    /** Largest unpacked worksheet or string table read (a 4 MB upload can unpack far larger). */
    private const MAX_XML_BYTES = 20 * 1024 * 1024;

    /** The same limits the add-a-pig form and weighings use. */
    private const TAG_MAX_LENGTH = 50;
    private const WEIGHT_MIN_KG = 0.1;
    private const WEIGHT_MAX_KG = 500;
    private const MAX_AGE_YEARS = 15;

    /** What Excel shows in a cell whose formula failed. */
    private const EXCEL_ERRORS = ['#N/A', '#REF!', '#VALUE!', '#DIV/0!', '#NAME?', '#NUM!', '#NULL!', '#SPILL!', '#CALC!'];

    private const FIELD_BY_HEADER = [
        'ear tag' => 'ear_tag_number',
        'tag colour' => 'tag_color',
        'breed' => 'breed',
        'type' => 'stock_type',
        'gender' => 'gender',
        'dob' => 'date_of_birth',
        'location' => 'location',
        'weight' => 'weight',
    ];

    public function __construct(
        protected PigStockService $stockService,
        protected EarTagNumberService $earTags,
        protected CurrentOrganization $currentOrganization,
        protected AuditTrailService $audit,
    ) {
    }

    public function preview(UploadedFile $file): array
    {
        $rows = $this->readXlsx($file->getRealPath());
        $headers = array_shift($rows) ?? [];
        $dataRows = count(array_filter($rows, fn ($row) => ! $this->isEmptyRow($row)));
        if ($dataRows > self::MAX_ROWS) {
            throw new StockValidationException('stock_file', 'The file has ' . number_format($dataRows) . ' rows; import at most '
                . number_format(self::MAX_ROWS) . ' at a time. Split it into smaller files.');
        }
        $normalizedHeaders = array_map(fn ($header) => $this->normalizeHeader($header), $headers);
        $expectedHeaders = array_values(self::FIELD_BY_HEADER);
        $missingFields = array_values(array_diff($expectedHeaders, $normalizedHeaders));
        $missingHeaders = array_values(array_map(
            fn (string $field) => self::HEADERS[array_search($field, $expectedHeaders, true)],
            $missingFields,
        ));

        // A column given twice is ambiguous: which one holds the pig's data?
        $duplicateHeaders = array_values(array_map(
            fn (string $field) => self::HEADERS[array_search($field, $expectedHeaders, true)],
            array_keys(array_filter(array_count_values(array_filter($normalizedHeaders)), fn ($n) => $n > 1)),
        ));

        // The file's own columns, as titled in it, so the preview can show
        // every cell exactly as uploaded (including columns the import does
        // not use).
        $columns = [];
        foreach ($headers as $index => $header) {
            $title = trim((string) $header);
            if ($title !== '' && ! in_array(strtolower($title), self::DROPPED_HEADERS, true)) {
                $columns[$index] = $title;
            }
        }

        $preview = [
            // Confirm must name this preview and come from the same farm.
            'token' => \Illuminate\Support\Str::random(40),
            'organization_id' => $this->currentOrganization->id(),
            'file_name' => $file->getClientOriginalName(),
            'headers' => $normalizedHeaders,
            'columns' => array_values($columns),
            'missing_headers' => $missingHeaders,
            'duplicate_headers' => $duplicateHeaders,
            'total_rows' => $dataRows,
            'valid_count' => 0,
            'invalid_count' => 0,
            'valid_rows' => [],
            'invalid_rows' => [],
        ];

        if ($missingHeaders !== [] || $duplicateHeaders !== []) {
            $problem = $missingHeaders !== []
                ? 'Missing required Excel headers: ' . implode(', ', $missingHeaders)
                : 'These columns appear more than once: ' . implode(', ', $duplicateHeaders) . '. Keep one of each.';
            foreach ($rows as $index => $row) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }
                $preview['invalid_rows'][] = [
                    'row_number' => $index + 2,
                    'data' => $row,
                    'cells' => $this->cells($columns, $row),
                    'errors' => [$problem],
                ];
            }
            $preview['invalid_count'] = count($preview['invalid_rows']);
            return $preview;
        }

        $seenEarTags = [];
        $roomFree = [];
        $roomPlaced = [];
        foreach ($rows as $index => $row) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $rowNumber = $index + 2;
            $data = $this->mapRow($normalizedHeaders, $row);
            $result = $this->validateRow($data, $seenEarTags);

            // Room capacity, as saving checks it, counting the pigs this file
            // has already put in the room: the first rows fill the free
            // places, and the rows after them are refused here rather than
            // failing the whole import on confirm.
            if (! empty($result['data']['room_id'])) {
                $roomId = $result['data']['room_id'];
                $roomFree[$roomId] ??= Room::find($roomId)?->freePlaces();
                if ($roomFree[$roomId] !== null) {
                    $roomPlaced[$roomId] = ($roomPlaced[$roomId] ?? 0) + 1;
                    if ($roomPlaced[$roomId] > $roomFree[$roomId]) {
                        $where = $result['data']['location'];
                        $result['errors'][] = $roomFree[$roomId] === 0
                            ? "Room {$where} is full. Choose another room."
                            : "Room {$where} has space for {$roomFree[$roomId]} more pig(s), and earlier rows in this file already fill it. Choose another room.";
                    }
                }
            }

            if ($result['errors'] === []) {
                $preview['valid_rows'][] = [
                    'row_number' => $rowNumber,
                    'data' => $result['data'],
                    'cells' => $this->cells($columns, $row),
                ];
                $preview['valid_count']++;
            } else {
                $preview['invalid_rows'][] = [
                    'row_number' => $rowNumber,
                    'data' => $result['data'],
                    'cells' => $this->cells($columns, $row),
                    'errors' => $result['errors'],
                ];
                $preview['invalid_count']++;
            }

            if (! empty($result['data']['ear_tag_number'])) {
                $seenEarTags[] = EarTagColor::normalizeName($result['data']['tag_color']).'|'.\App\Models\EarTag::normalizeNumber($result['data']['ear_tag_number']);
            }
        }

        return $preview;
    }

    public function import(array $preview, $user): array
    {
        if (($preview['invalid_count'] ?? 0) > 0) {
            throw new StockValidationException('import', 'Fix invalid rows before confirming the stock import.');
        }
        if (($preview['organization_id'] ?? null) !== $this->currentOrganization->id()) {
            throw new StockValidationException('import', 'This preview belongs to another farm. Upload the file again.');
        }

        $created = [];

        DB::transaction(function () use ($preview, &$created) {
            foreach ($preview['valid_rows'] as $row) {
                // Things can change between preview and confirm (another pig
                // put in the room); say which row, and nothing is imported.
                try {
                    $pig = $this->stockService->create($this->creationPayload($row['data']));
                } catch (StockValidationException $e) {
                    throw new StockValidationException('stock_file', "Row {$row['row_number']}: {$e->getMessage()} Nothing was imported; upload the file again.");
                }

                if (($row['data']['weight'] ?? '') !== '') {
                    PigWeight::create([
                        'pig_id' => $pig->id,
                        'weight' => (float) $row['data']['weight'],
                        'weight_unit' => 'kg',
                        'weigh_date' => now()->toDateString(),
                        'actual_age_weeks' => $pig->date_of_birth?->diffInWeeks(now()),
                        'purpose' => 'other',
                        'notes' => 'Initial weight imported from the All Stock spreadsheet.',
                        'weighed_by' => auth()->user()?->name ?? 'System',
                        'is_estimated' => false,
                    ]);
                }

                $created[] = $pig;
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
            // A room with space for every sample row, so the sample imports as is.
            $needed = min(3, count(Setting::animalBreeds()));
            $room = Room::with('structure')->whereHas('structure')->orderBy('name')->get()
                ->first(fn (Room $room) => ($free = $room->freePlaces()) === null || $free >= $needed);
            $structureName = $room?->structure?->name ?? '';
            $roomName = $room?->name ?? '';
        } catch (\Throwable) {
            // The sample should still download even if the database is temporarily unavailable.
        }

        $rows = [self::HEADERS];

        $breeds = array_slice(Setting::animalBreeds(), 0, 3, true);
        $proposedTags = $this->earTags->propose(count($breeds));

        $sampleColor = EarTagColor::where('is_active', true)->orderBy('name')->value('name') ?? 'Unspecified';
        foreach ($breeds as $code => $name) {
            $index = count($rows);

            $rows[] = [
                $proposedTags[$index - 1] ?? (string) $index,
                $sampleColor,
                $name,
                $index === 1 ? 'Breeding' : 'Standard',
                $index % 2 === 0 ? 'Male' : 'Female',
                now()->subMonths(8 + $index)->format('Y-m-d'),
                $structureName !== '' && $roomName !== '' ? "{$structureName} / {$roomName}" : '',
                '',
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
        $sheetXml = $this->readPart($zip, $this->firstSheetPath($zip));
        $zip->close();

        if ($sheetXml === false) {
            throw new StockValidationException('stock_file', 'The Excel file must contain a first worksheet.');
        }

        $xml = $this->parseXml($sheetXml);
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

    /**
     * The first worksheet as the workbook orders it. Excel names sheet files
     * by creation, so after sheets are added, removed or reordered the first
     * tab need not be sheet1.xml.
     */
    protected function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';
        $workbook = $this->readPart($zip, 'xl/workbook.xml');
        $rels = $this->readPart($zip, 'xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            return $fallback;
        }

        $book = $this->parseXml($workbook);
        $sheet = $book->sheets->sheet[0] ?? null;
        $relId = $sheet ? (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '') : '';
        foreach ($this->parseXml($rels)->Relationship ?? [] as $rel) {
            if ((string) $rel['Id'] === $relId) {
                $target = ltrim((string) $rel['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return $fallback;
    }

    /**
     * One part of the workbook, refusing parts that unpack to more than
     * MAX_XML_BYTES: a small upload can hide a huge file (a "zip bomb").
     */
    protected function readPart(ZipArchive $zip, string $name): string|false
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return false;
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new StockValidationException('stock_file', 'The Excel file is too large to read. Remove unused rows, columns and sheets, or split it.');
        }

        return $zip->getFromName($name);
    }

    /**
     * Parse workbook XML. Spreadsheet parts never declare a DOCTYPE; one that
     * does is refused, which shuts out entity tricks (external files,
     * "billion laughs" expansion) whatever the XML library's defaults.
     */
    protected function parseXml(string $xml): \SimpleXMLElement
    {
        if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
            throw new StockValidationException('stock_file', 'The Excel file could not be read. Save it again from Excel and retry.');
        }

        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($parsed === false) {
            throw new StockValidationException('stock_file', 'The Excel file could not be read. Save it again from Excel and retry.');
        }

        return $parsed;
    }

    protected function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $this->readPart($zip, 'xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $shared = [];
        $strings = $this->parseXml($xml);
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
        return self::FIELD_BY_HEADER[strtolower(trim((string) $header))] ?? '';
    }

    protected function mapRow(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            $data[$header] = trim((string) ($row[$index] ?? ''));
        }

        foreach (array_values(self::FIELD_BY_HEADER) as $field) {
            $data[$field] = $data[$field] ?? '';
        }

        return $data;
    }

    /**
     * A row's cells under the file's own column titles, as uploaded. Dates
     * Excel stored as serial numbers are shown as dates.
     *
     * @param  array<int, string>  $columns  column index => title
     * @return list<string>
     */
    protected function cells(array $columns, array $row): array
    {
        $cells = [];
        foreach ($columns as $index => $title) {
            $value = trim((string) ($row[$index] ?? ''));
            if ($this->normalizeHeader($title) === 'date_of_birth') {
                $value = $this->normalizeDateInput($value);
            }
            $cells[] = $value;
        }

        return $cells;
    }

    protected function isEmptyRow(array $row): bool
    {
        return implode('', array_map(fn ($value) => trim((string) $value), $row)) === '';
    }

    protected function validateRow(array $data, array $seenEarTags): array
    {
        $errors = [];

        // A failed formula in Excel leaves its error text in the cell.
        $flagged = [];
        foreach (self::FIELD_BY_HEADER as $header => $field) {
            if (in_array(strtoupper(trim((string) ($data[$field] ?? ''))), self::EXCEL_ERRORS, true)) {
                $errors[] = ucfirst($header) . ' holds an Excel error (' . trim($data[$field]) . '). Fix the cell in Excel.';
                $data[$field] = '';
                $flagged[] = $field;
            }
        }
        $data['ear_tag_number'] = $this->normalizeTextInput($data['ear_tag_number'] ?? '');
        $data['tag_color'] = trim($data['tag_color'] ?? '');
        $data['breed'] = $this->normalizeBreed($data['breed'] ?? '');
        [$data['structure'], $data['room_name']] = $this->parseLocation($data['location'] ?? '');
        $data['father_ear_tag'] = '';
        $data['mother_ear_tag'] = '';
        $data['gender'] = match (strtolower(trim($data['gender'] ?? ''))) {
            'male', 'm' => 'M',
            'female', 'f' => 'F',
            default => strtoupper(trim($data['gender'] ?? '')),
        };
        $data['stock_type'] = strtolower(trim($data['stock_type'] ?? ''));
        $data['acquisition_source'] = 'born_on_farm';
        $data['date_of_birth'] = $this->normalizeDateInput($data['date_of_birth'] ?? '');
        $data['acquisition_date'] = $data['date_of_birth'];
        $data['acquisition_cost'] = '';
        $data['notes'] = '';
        $data['weight'] = $this->normalizeWeight($data['weight'] ?? '');

        foreach (['ear_tag_number', 'tag_color', 'breed', 'gender', 'stock_type', 'date_of_birth'] as $field) {
            if ($data[$field] === '' && ! in_array($field, $flagged, true)) {
                $errors[] = str_replace('_', ' ', $field) . ' is required.';
            }
        }

        if (mb_strlen($data['ear_tag_number']) > self::TAG_MAX_LENGTH) {
            $errors[] = 'Ear tag must be at most ' . self::TAG_MAX_LENGTH . ' characters.';
        }

        if ($data['ear_tag_number'] !== '') {
            $combination = EarTagColor::normalizeName($data['tag_color']).'|'.\App\Models\EarTag::normalizeNumber($data['ear_tag_number']);
            if (in_array($combination, $seenEarTags, true)) {
                $errors[] = 'This colour and tag number are duplicated in the uploaded file.';
            }

        }

        $data['ear_tag_color_id'] = null;
        if (strcasecmp($data['tag_color'], 'Unspecified') === 0) {
            $data['ear_tag_color_id'] = null;
        } elseif ($data['tag_color'] !== '') {
            $color = EarTagColor::where('normalized_name', EarTagColor::normalizeName($data['tag_color']))
                ->where('is_active', true)->first();
            if (! $color) {
                $errors[] = 'Tag colour must match an active colour configured for this farm.';
            } else {
                $data['ear_tag_color_id'] = $color->id;
            }
        }

        if ($data['ear_tag_number'] !== '' && $data['tag_color'] !== '') {
            $resolvedColorId = $data['ear_tag_color_id'] ?: EarTagColor::where(
                'normalized_name',
                EarTagColor::normalizeName($data['tag_color'])
            )->value('id');
            if ($resolvedColorId) {
                $registeredTag = \App\Models\EarTag::where('ear_tag_color_id', $resolvedColorId)->where('normalized_tag_number', \App\Models\EarTag::normalizeNumber($data['ear_tag_number']))
                    ->first();
                if ($registeredTag && ($registeredTag->status !== \App\Models\EarTag::AVAILABLE
                    || $registeredTag->currentAssignment()->exists())) {
                    $errors[] = 'This tag number is already in use.';
                }
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
        } elseif ($data['date_of_birth'] !== '') {
            $born = Carbon::createFromFormat('Y-m-d', $data['date_of_birth'])->startOfDay();
            if ($born->isAfter(today())) {
                $errors[] = 'Date of birth cannot be in the future.';
            } elseif ($born->isBefore(today()->subYears(self::MAX_AGE_YEARS))) {
                $errors[] = 'Date of birth is more than ' . self::MAX_AGE_YEARS . ' years ago. Check the year.';
            }
        }

        if ($data['acquisition_source'] !== '' && ! in_array($data['acquisition_source'], ['born_on_farm', 'purchased'], true)) {
            $errors[] = 'Acquisition source must be born_on_farm or purchased.';
        }

        if ($data['acquisition_date'] !== '' && ! $this->validDate($data['acquisition_date'])) {
            $errors[] = 'Acquisition date must use YYYY-MM-DD.';
        }

        if ($data['weight'] !== '' && (! is_numeric($data['weight']) || (float) $data['weight'] <= 0)) {
            $errors[] = 'Weight must be a number greater than zero, expressed in kg.';
        } elseif ($data['weight'] !== '' && ((float) $data['weight'] < self::WEIGHT_MIN_KG || (float) $data['weight'] > self::WEIGHT_MAX_KG)) {
            $errors[] = 'Weight must be between ' . self::WEIGHT_MIN_KG . ' and ' . self::WEIGHT_MAX_KG . ' kg.';
        }

        // A location has to name a room, written as "Structure / Room";
        // otherwise the pig would be imported with no room, silently.
        if (trim($data['location'] ?? '') !== '' && strcasecmp(trim($data['location']), 'N/A') !== 0
            && ($data['structure'] === '' || $data['room_name'] === '')) {
            $errors[] = 'Location must be written as Structure / Room, for example "Structure one / sty 1".';
        }

        $data['room_id'] = null;
        $structure = null;
        if (($data['structure'] ?? '') !== '') {
            $structure = Structure::where('name', $data['structure'])->first();
            if (! $structure) {
                $errors[] = 'Structure does not match an existing structure.';
            }
        }

        if ($data['room_name'] !== '' && $data['structure'] !== '' && $structure) {
            $room = Room::where('name', $data['room_name'])->where('structure_id', $structure->id)->first();
            if (! $room) {
                $errors[] = 'Room name does not match an existing room in the selected structure.';
            } else {
                $data['room_id'] = $room->id;
            }
        }

        $data['father_id'] = null;
        if ($data['father_ear_tag'] !== '') {
            $fatherMatches = PigManagement::active()->where('code', $data['father_ear_tag'])->get();
            if ($fatherMatches->isEmpty()) {
                $fatherMatches = PigManagement::active()->where('ear_tag_number', $data['father_ear_tag'])->get();
            }
            $father = $fatherMatches->first();
            if ($fatherMatches->count() > 1) {
                $errors[] = 'Father tag number matches multiple colours. Enter the permanent animal code instead.';
            } elseif (! $father) {
                $errors[] = 'Father ear tag does not match an existing pig.';
            } elseif ($father->gender !== 'M') {
                $errors[] = 'Father ear tag must belong to a male pig.';
            } else {
                $data['father_id'] = $father->id;
            }
        }

        $data['mother_id'] = null;
        if ($data['mother_ear_tag'] !== '') {
            $motherMatches = PigManagement::active()->where('code', $data['mother_ear_tag'])->get();
            if ($motherMatches->isEmpty()) {
                $motherMatches = PigManagement::active()->where('ear_tag_number', $data['mother_ear_tag'])->get();
            }
            $mother = $motherMatches->first();
            if ($motherMatches->count() > 1) {
                $errors[] = 'Mother tag number matches multiple colours. Enter the permanent animal code instead.';
            } elseif (! $mother) {
                $errors[] = 'Mother ear tag does not match an existing pig.';
            } elseif ($mother->gender !== 'F') {
                $errors[] = 'Mother ear tag must belong to a female pig.';
            } else {
                $data['mother_id'] = $mother->id;
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }

    protected function normalizeBreed(string $value): string
    {
        $value = trim($value);
        $breeds = Setting::animalBreeds();

        foreach ($breeds as $code => $name) {
            if (strcasecmp($value, (string) $code) === 0 || strcasecmp($value, (string) $name) === 0) {
                return strtoupper((string) $code);
            }
        }

        return strtoupper($value);
    }

    /** @return array{0:string,1:string} */
    protected function parseLocation(string $value): array
    {
        $value = trim($value);
        if ($value === '' || strcasecmp($value, 'N/A') === 0) {
            return ['', ''];
        }

        $parts = preg_split('/\s*(?:\/|—|–|\|)\s*/', $value, 2) ?: [];

        return [trim($parts[0] ?? ''), trim($parts[1] ?? '')];
    }

    protected function normalizeWeight(string $value): string
    {
        return trim((string) preg_replace('/\s*kg\s*$/i', '', trim($value)));
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

    /**
     * Tag numbers as typed. Only a whole number Excel stored as a decimal
     * ("12.0") is tidied; leading zeros ("004") are kept, as the tag reads.
     */
    protected function normalizeTextInput(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^(\d+)\.0+$/', $value, $m)) {
            return $m[1];
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
            'ear_tag_color_id' => $data['ear_tag_color_id'],
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

        if ($data['weight'] !== '') {
            $payload['current_weight_kg_cache'] = (float) $data['weight'];
        }

        return $payload;
    }

    protected function sheetXml(iterable $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>';

        for ($i = 1; $i <= count(self::HEADERS); $i++) {
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
