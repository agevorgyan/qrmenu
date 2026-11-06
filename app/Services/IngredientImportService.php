<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class IngredientImportService
{
    /**
     * Common unit aliases mapping localized and colloquial terms to canonical unit codes.
     *
     * @var array<string, array<string>>
     */
    protected array $unitAliases = [
        'kg' => ['kg', 'կգ', 'кг', 'kilogram', 'կիլոգրամ', 'килограмм', 'kilo'],
        'g' => ['g', 'գ', 'գր', 'г', 'гр', 'gram', 'գրամ', 'грамм'],
        'ton' => ['ton', 'տ', 'տոննա', 'т', 'тн', 'тонна', 'tonne'],
        'l' => ['l', 'լ', 'լիտր', 'л', 'литр', 'liter', 'litre'],
        'ml' => ['ml', 'մլ', 'միլիլիտր', 'мл', 'миллилитр'],
        'piece' => ['piece', 'հատ', 'шт', 'штук', 'штука', 'pc', 'pcs', 'item'],
        'portion' => ['portion', 'բաժին', 'պորցիա', 'порция'],
        'pack' => ['pack', 'փաթեթ', 'տուփ', 'пачка', 'упаковка', 'пакет'],
        'box' => ['box', 'արկղ', 'коробка', 'box'],
        'bottle' => ['bottle', 'շիշ', 'бутылка'],
    ];

    /**
     * Common type aliases mapping localized terms to canonical ingredient types.
     *
     * @var array<string, string>
     */
    protected array $typeAliases = [
        'raw_material' => Ingredient::TYPE_RAW_MATERIAL,
        'հումք' => Ingredient::TYPE_RAW_MATERIAL,
        'сырье' => Ingredient::TYPE_RAW_MATERIAL,
        'raw' => Ingredient::TYPE_RAW_MATERIAL,

        'ingredient' => Ingredient::TYPE_INGREDIENT,
        'բաղադրիչ' => Ingredient::TYPE_INGREDIENT,
        'ингредиент' => Ingredient::TYPE_INGREDIENT,

        'semi_finished' => Ingredient::TYPE_SEMI_FINISHED,
        'կիսաֆաբրիկատ' => Ingredient::TYPE_SEMI_FINISHED,
        'полуфабрикат' => Ingredient::TYPE_SEMI_FINISHED,
        'prep' => Ingredient::TYPE_SEMI_FINISHED,

        'packaging' => Ingredient::TYPE_PACKAGING,
        'փաթեթավորում' => Ingredient::TYPE_PACKAGING,
        'տուփ' => Ingredient::TYPE_PACKAGING,
        'упаковка' => Ingredient::TYPE_PACKAGING,

        'consumable' => Ingredient::TYPE_CONSUMABLE,
        'ծախսանյութ' => Ingredient::TYPE_CONSUMABLE,
        'расходник' => Ingredient::TYPE_CONSUMABLE,
        'хозяйственный' => Ingredient::TYPE_CONSUMABLE,
    ];

    /**
     * Generate template for ingredient import.
     *
     * @return array{filename: string, content: string, mime: string}
     */
    public function generateTemplate(string $format = 'xlsx'): array
    {
        $headers = [
            'Անվանում (Name)*',
            'Արտիկուլ (SKU)',
            'Տեսակ (Type)',
            'Կատեգորիա (Category)',
            'Չափման միավոր (Unit)*',
            'Գնման գին (Cost)',
            'Մին. մնացորդ (Min Stock)',
            'Շտրիխ կոդ (Barcode)',
            'Պահպանման պայմաններ (Storage)',
            'Պիտանիություն օրերով (Shelf Life)',
        ];

        $sampleRows = [
            [
                'Ցորենի ալյուր բարձր տեսակի',
                'FLOUR-001',
                'raw_material',
                'Հացահատիկ և ալյուր',
                'kg',
                '450',
                '20',
                '485000100001',
                'Չոր և զով տեղում',
                '180',
            ],
            [
                'Կարագ 82.5%',
                'BUTTER-01',
                'raw_material',
                'Կաթնամթերք',
                'kg',
                '4200',
                '5',
                '485000100002',
                '+2°C - +6°C',
                '35',
            ],
            [
                'Սոուս Պեստո տնական',
                'PESTO-001',
                'semi_finished',
                'Սոուսներ',
                'kg',
                '3800',
                '2',
                '',
                '+2°C - +4°C',
                '7',
            ],
            [
                'Տուփ 500մլ Takeaway',
                'BOX-500',
                'packaging',
                'Փաթեթավորում',
                'piece',
                '120',
                '100',
                '',
                'Չոր պահեստում',
                '',
            ],
            [
                'Ձիթապտղի յուղ Extra Virgin',
                'OLIVE-OIL',
                'ingredient',
                'Յուղեր և քացախներ',
                'l',
                '5600',
                '4',
                '485000100003',
                'Մութ և զով տեղում',
                '365',
            ],
        ];

        if (strtolower($format) === 'csv') {
            return [
                'filename' => 'ingredients_import_template.csv',
                'content' => $this->generateCsvContent($headers, $sampleRows),
                'mime' => 'text/csv; charset=UTF-8',
            ];
        }

        return [
            'filename' => 'ingredients_import_template.xlsx',
            'content' => $this->generateXlsxContent($headers, $sampleRows),
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    /**
     * Generate CSV content with UTF-8 BOM.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    protected function generateCsvContent(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        // UTF-8 BOM so Excel opens it with proper UTF-8 decoding
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content ?: '';
    }

    /**
     * Generate XLSX content natively using ZipArchive.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    protected function generateXlsxContent(array $headers, array $rows): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_tpl_');
        $zip = new ZipArchive;

        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create temporary Excel archive.');
        }

        $zip->addFromString('[Content_Types].xml', "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"xml\" ContentType=\"application/xml\"/><Override PartName=\"/xl/workbook.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml\"/><Override PartName=\"/xl/worksheets/sheet1.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/><Override PartName=\"/xl/sharedStrings.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml\"/></Types>");
        $zip->addFromString('_rels/.rels', "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet1.xml\"/><Relationship Id=\"rId2\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings\" Target=\"sharedStrings.xml\"/></Relationships>");
        $zip->addFromString('xl/workbook.xml', "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<workbook xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets><sheet name=\"Ingredients\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>");

        $allRows = array_merge([$headers], $rows);
        $strings = [];
        $stringIndexMap = [];
        $sheetRowsXml = '';

        foreach ($allRows as $rIdx => $row) {
            $rowNum = $rIdx + 1;
            $sheetRowsXml .= "<row r=\"{$rowNum}\">";

            foreach ($row as $cIdx => $val) {
                $colLetter = $this->indexToColLetter($cIdx);
                $cellCoord = $colLetter.$rowNum;
                $valStr = (string) $val;

                if ($valStr === '') {
                    continue;
                }

                if ($rIdx > 0 && is_numeric($valStr) && in_array($cIdx, [5, 6, 9], true)) {
                    $sheetRowsXml .= "<c r=\"{$cellCoord}\"><v>{$valStr}</v></c>";
                } else {
                    if (! isset($stringIndexMap[$valStr])) {
                        $stringIndexMap[$valStr] = count($strings);
                        $strings[] = $valStr;
                    }
                    $sIdx = $stringIndexMap[$valStr];
                    $sheetRowsXml .= "<c r=\"{$cellCoord}\" t=\"s\"><v>{$sIdx}</v></c>";
                }
            }

            $sheetRowsXml .= '</row>';
        }

        $ssXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<sst xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" count=\"".count($strings).'" uniqueCount="'.count($strings).'">';
        foreach ($strings as $s) {
            $ssXml .= '<si><t>'.htmlspecialchars($s, ENT_XML1, 'UTF-8').'</t></si>';
        }
        $ssXml .= '</sst>';
        $zip->addFromString('xl/sharedStrings.xml', $ssXml);

        $colsXml = '<cols><col min="1" max="1" width="32"/><col min="2" max="2" width="18"/><col min="3" max="3" width="16"/><col min="4" max="4" width="24"/><col min="5" max="5" width="18"/><col min="6" max="7" width="18"/><col min="8" max="8" width="20"/><col min="9" max="9" width="26"/><col min="10" max="10" width="18"/></cols>';
        $sheetXml = "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\">{$colsXml}<sheetData>{$sheetRowsXml}</sheetData></worksheet>";
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();
        $content = file_get_contents($tempPath) ?: '';
        @unlink($tempPath);

        return $content;
    }

    /**
     * Import raw materials and ingredients from uploaded file.
     *
     * @return array{
     *     success: bool,
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     errors: array<int, string>,
     *     total_rows: int
     * }
     */
    public function import(UploadedFile|string $file, int $vendorId, bool $updateExisting = true): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = strtolower(pathinfo($file instanceof UploadedFile ? $file->getClientOriginalName() : $file, PATHINFO_EXTENSION));

        $rows = match ($extension) {
            'xlsx' => $this->extractRowsFromXlsx($filePath),
            'csv', 'txt' => $this->extractRowsFromCsv($filePath),
            default => $this->extractRowsFromCsv($filePath),
        };

        if (empty($rows)) {
            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [__('The uploaded file is empty or could not be read.')],
                'total_rows' => 0,
            ];
        }

        // Detect header row and column mapping
        $headerMap = $this->detectHeaderMapping($rows);
        $dataRows = array_slice($rows, $headerMap['data_start_index']);

        if (empty($dataRows)) {
            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [__('No data rows found below the header row.')],
                'total_rows' => 0,
            ];
        }

        // Cache available units for vendor
        $units = Unit::where(function ($q) use ($vendorId) {
            $q->whereNull('vendor_id')->orWhere('vendor_id', $vendorId);
        })->where('is_active', true)->get();

        $defaultUnit = $units->firstWhere('code', 'kg') ?? $units->firstWhere('code', 'piece') ?? $units->first();

        // Cache categories for vendor
        $categories = IngredientCategory::where('vendor_id', $vendorId)->get()->keyBy(function ($c) {
            return mb_strtolower(trim($c->name));
        });

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($dataRows as $offset => $row) {
                $rowNumber = $headerMap['data_start_index'] + $offset + 1;

                // Extract fields by detected column indexes
                $name = trim($this->getVal($row, $headerMap['columns']['name'] ?? 0));

                if ($name === '') {
                    $skippedCount++;

                    continue; // Skip empty row
                }

                $sku = trim($this->getVal($row, $headerMap['columns']['sku'] ?? -1));
                $typeRaw = trim($this->getVal($row, $headerMap['columns']['type'] ?? -1));
                $categoryName = trim($this->getVal($row, $headerMap['columns']['category'] ?? -1));
                $unitRaw = trim($this->getVal($row, $headerMap['columns']['unit'] ?? -1));
                $costRaw = trim($this->getVal($row, $headerMap['columns']['cost'] ?? -1));
                $minStockRaw = trim($this->getVal($row, $headerMap['columns']['min_stock'] ?? -1));
                $barcode = trim($this->getVal($row, $headerMap['columns']['barcode'] ?? -1));
                $storage = trim($this->getVal($row, $headerMap['columns']['storage'] ?? -1));
                $shelfLifeRaw = trim($this->getVal($row, $headerMap['columns']['shelf_life'] ?? -1));

                // 1. Resolve Item Type
                $type = $this->resolveType($typeRaw);

                // 2. Resolve Unit
                $unit = $this->resolveUnit($unitRaw, $units, $defaultUnit);
                if (! $unit) {
                    $errors[] = __('Row :row: Invalid or unrecognized unit of measure ":unit".', ['row' => $rowNumber, 'unit' => $unitRaw]);
                    $skippedCount++;

                    continue;
                }

                // 3. Resolve or Create Category
                $categoryId = null;
                if ($categoryName !== '') {
                    $catKey = mb_strtolower($categoryName);
                    if ($categories->has($catKey)) {
                        $categoryId = $categories->get($catKey)->id;
                    } else {
                        $newCat = IngredientCategory::create([
                            'vendor_id' => $vendorId,
                            'name' => $categoryName,
                            'is_active' => true,
                        ]);
                        $categories->put($catKey, $newCat);
                        $categoryId = $newCat->id;
                    }
                }

                // 4. Parse Numeric Values
                $cost = $this->cleanNumeric($costRaw);
                $minStock = $this->cleanNumeric($minStockRaw);
                $shelfLife = is_numeric($shelfLifeRaw) ? (int) $shelfLifeRaw : null;

                // 5. Match existing ingredient (by SKU if provided, or by exact Name)
                $existing = null;
                if ($sku !== '') {
                    $existing = Ingredient::where('vendor_id', $vendorId)->where('sku', $sku)->first();
                }

                if (! $existing) {
                    $existing = Ingredient::where('vendor_id', $vendorId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->first();
                }

                if ($existing) {
                    if ($updateExisting) {
                        $existing->update([
                            'name' => $name,
                            'type' => $type ?: $existing->type,
                            'category_id' => $categoryId ?? $existing->category_id,
                            'unit_id' => $unit->id,
                            'current_cost' => $cost !== null ? $cost : $existing->current_cost,
                            'last_purchase_price' => $cost !== null ? $cost : $existing->last_purchase_price,
                            'min_stock' => $minStock !== null ? $minStock : $existing->min_stock,
                            'barcode' => $barcode !== '' ? $barcode : $existing->barcode,
                            'storage_conditions' => $storage !== '' ? $storage : $existing->storage_conditions,
                            'shelf_life_days' => $shelfLife !== null ? $shelfLife : $existing->shelf_life_days,
                            'is_active' => true,
                        ]);
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }
                } else {
                    $finalSku = $sku !== '' ? $sku : 'ING-'.strtoupper(Str::random(6));

                    // Ensure unique SKU for vendor
                    $counter = 1;
                    $origSku = $finalSku;
                    while (Ingredient::where('vendor_id', $vendorId)->where('sku', $finalSku)->exists()) {
                        $finalSku = $origSku.'-'.$counter;
                        $counter++;
                    }

                    Ingredient::create([
                        'vendor_id' => $vendorId,
                        'name' => $name,
                        'sku' => $finalSku,
                        'type' => $type,
                        'category_id' => $categoryId,
                        'unit_id' => $unit->id,
                        'current_cost' => $cost ?? 0,
                        'last_purchase_price' => $cost ?? 0,
                        'min_stock' => $minStock ?? 0,
                        'barcode' => $barcode !== '' ? $barcode : null,
                        'storage_conditions' => $storage !== '' ? $storage : null,
                        'shelf_life_days' => $shelfLife,
                        'is_active' => true,
                    ]);
                    $createdCount++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Ingredient import error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [__('Import failed due to database error: :msg', ['msg' => $e->getMessage()])],
                'total_rows' => count($dataRows),
            ];
        }

        return [
            'success' => ($createdCount + $updatedCount) > 0,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'errors' => $errors,
            'total_rows' => count($dataRows),
        ];
    }

    /**
     * Detect header row and column indexes.
     *
     * @param  array<int, array<int, string>>  $rows
     * @return array{data_start_index: int, columns: array<string, int>}
     */
    protected function detectHeaderMapping(array $rows): array
    {
        $maxScan = min(5, count($rows));
        $bestRowIdx = 0;
        $bestMatches = 0;
        $bestColumns = [];

        for ($r = 0; $r < $maxScan; $r++) {
            $cols = [];
            $matches = 0;

            foreach ($rows[$r] as $cIdx => $cell) {
                $header = mb_strtolower(trim((string) $cell));
                if ($header === '') {
                    continue;
                }

                // 1. Barcode checked before SKU so 'Շտրիխ կոդ' is recognized as barcode
                if ($this->matchesKeyword($header, ['barcode', 'շտրիխ', 'штрих'])) {
                    $cols['barcode'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['name', 'անվանում', 'название', 'հումք', 'բաղադրիչ', 'նյութ', 'наименование'])) {
                    $cols['name'] = $cIdx;
                    $matches += 3;
                } elseif ($this->matchesKeyword($header, ['sku', 'արտիկուլ', 'артикул', 'կոդ', 'код'])) {
                    $cols['sku'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['type', 'տեսակ', 'тип'])) {
                    $cols['type'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['category', 'կատեգորիա', 'категория'])) {
                    $cols['category'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['unit', 'միավոր', 'չափման միավոր', 'ед. изм.', 'единица'])) {
                    $cols['unit'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['cost', 'price', 'գին', 'ինքնարժեք', 'գնման գին', 'цена', 'себестоимость', 'закупка'])) {
                    $cols['cost'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['min_stock', 'min stock', 'minstock', 'մին. մնացորդ', 'մինիմալ մնացորդ', 'մնացորդ', 'мин. остаток', 'мин остаток', 'мин_остаток', 'остаток'])) {
                    $cols['min_stock'] = $cIdx;
                    $matches += 2;
                } elseif ($this->matchesKeyword($header, ['storage', 'պայմաններ', 'պահպանման պայմաններ', 'условия хранения'])) {
                    $cols['storage'] = $cIdx;
                    $matches++;
                } elseif ($this->matchesKeyword($header, ['shelf_life', 'shelf life', 'shelflife', 'պիտանիություն', 'срок годности', 'օրերով'])) {
                    $cols['shelf_life'] = $cIdx;
                    $matches++;
                }
            }

            if ($matches > $bestMatches) {
                $bestMatches = $matches;
                $bestRowIdx = $r;
                $bestColumns = $cols;
            }
        }

        // If no header detected, default to sequential columns
        if ($bestMatches < 3) {
            $bestColumns = [
                'name' => 0,
                'sku' => 1,
                'type' => 2,
                'category' => 3,
                'unit' => 4,
                'cost' => 5,
                'min_stock' => 6,
                'barcode' => 7,
                'storage' => 8,
                'shelf_life' => 9,
            ];

            return ['data_start_index' => 0, 'columns' => $bestColumns];
        }

        return ['data_start_index' => $bestRowIdx + 1, 'columns' => $bestColumns];
    }

    /**
     * Check if string contains any of the search keywords.
     *
     * @param  array<int, string>  $keywords
     */
    protected function matchesKeyword(string $text, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($text, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve Unit model from text representation.
     */
    protected function resolveUnit(string $raw, $units, ?Unit $defaultUnit): ?Unit
    {
        if ($raw === '') {
            return $defaultUnit;
        }

        $clean = mb_strtolower(trim($raw));

        // 1. Direct code match
        $matched = $units->first(fn ($u) => mb_strtolower($u->code) === $clean);
        if ($matched) {
            return $matched;
        }

        // 2. Direct name match
        $matched = $units->first(fn ($u) => mb_strtolower($u->name) === $clean);
        if ($matched) {
            return $matched;
        }

        // 3. Match via known aliases
        foreach ($this->unitAliases as $canonicalCode => $aliases) {
            if (in_array($clean, $aliases, true)) {
                $found = $units->firstWhere('code', $canonicalCode);
                if ($found) {
                    return $found;
                }
            }
        }

        return $defaultUnit;
    }

    /**
     * Resolve canonical ingredient type from string.
     */
    protected function resolveType(string $raw): string
    {
        if ($raw === '') {
            return Ingredient::TYPE_RAW_MATERIAL;
        }

        $clean = mb_strtolower(trim($raw));

        return $this->typeAliases[$clean] ?? Ingredient::TYPE_RAW_MATERIAL;
    }

    /**
     * Clean numeric input removing currency signs and spaces.
     */
    protected function cleanNumeric(string $val): ?float
    {
        if ($val === '') {
            return null;
        }

        // Replace Armenian and Russian comma decimal separator with dot
        $val = str_replace(',', '.', $val);
        // Remove spaces, currency symbols, and non-numeric characters except dot and minus
        $clean = preg_replace('/[^\d.-]/', '', $val);

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Extract rows from modern XLSX file using ZipArchive.
     *
     * @return array<int, array<int, string>>
     */
    public function extractRowsFromXlsx(string $filePath): array
    {
        if (! file_exists($filePath) || ! class_exists(ZipArchive::class)) {
            return [];
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return [];
        }

        // 1. Extract shared strings table
        $sharedStrings = [];
        $stringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($stringsXml !== false) {
            try {
                $xml = simplexml_load_string($stringsXml);
                if ($xml) {
                    foreach ($xml->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string) $si->t;
                        } elseif (isset($si->r)) {
                            $runText = '';
                            foreach ($si->r as $r) {
                                $runText .= (string) $r->t;
                            }
                            $sharedStrings[] = $runText;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('XLSX sharedStrings parse warning: '.$e->getMessage());
            }
        }

        // 2. Extract sheet1 rows preserving cell column coordinates
        $rows = [];
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml !== false) {
            try {
                $xml = simplexml_load_string($sheetXml);
                if ($xml && isset($xml->sheetData->row)) {
                    foreach ($xml->sheetData->row as $row) {
                        $rowCells = [];
                        $maxCol = 0;
                        $hasContent = false;

                        foreach ($row->c as $cell) {
                            $ref = (string) $cell['r'];
                            $colIdx = $this->colLetterToIndex($ref);
                            $type = (string) $cell['t'];
                            $val = (string) $cell->v;

                            if ($type === 's' && isset($sharedStrings[(int) $val])) {
                                $cellVal = $sharedStrings[(int) $val];
                            } elseif ($type === 'inlineStr' && isset($cell->is->t)) {
                                $cellVal = (string) $cell->is->t;
                            } else {
                                $cellVal = $val;
                            }

                            $cellVal = trim($cellVal);
                            if ($cellVal !== '') {
                                $hasContent = true;
                            }

                            $rowCells[$colIdx] = $cellVal;
                            if ($colIdx > $maxCol) {
                                $maxCol = $colIdx;
                            }
                        }

                        if ($hasContent) {
                            $denseRow = [];
                            for ($i = 0; $i <= $maxCol; $i++) {
                                $denseRow[$i] = $rowCells[$i] ?? '';
                            }
                            $rows[] = $denseRow;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('XLSX sheet1 parse warning: '.$e->getMessage());
            }
        }

        $zip->close();

        return $rows;
    }

    /**
     * Extract rows from CSV file supporting multiple delimiters and UTF-8 BOM.
     *
     * @return array<int, array<int, string>>
     */
    public function extractRowsFromCsv(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $sample = @file_get_contents($filePath, false, null, 0, 8192) ?: '';
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($sample, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, $bestDelimiter, '"', '\\')) !== false) {
                $row = array_map('trim', $row);
                $hasContent = false;
                foreach ($row as $val) {
                    if ($val !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if ($hasContent) {
                    $rows[] = array_values($row);
                }
            }
            fclose($handle);
        }

        if (! empty($rows) && isset($rows[0][0])) {
            $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0]);
        }

        return $rows;
    }

    /**
     * Convert cell coordinate (e.g. "AB12") to zero-based column index.
     */
    protected function colLetterToIndex(string $cellRef): int
    {
        $letters = preg_replace('/[0-9]/', '', strtoupper($cellRef));
        $len = strlen($letters);
        $idx = 0;

        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }

        return max(0, $idx - 1);
    }

    /**
     * Convert zero-based column index to letter (e.g. 0 -> "A", 26 -> "AA").
     */
    protected function indexToColLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex >= 0) {
            $letter = chr($colIndex % 26 + 65).$letter;
            $colIndex = intdiv($colIndex, 26) - 1;
        }

        return $letter;
    }

    /**
     * Safe array getter helper.
     */
    protected function getVal(array $row, int $colIdx): string
    {
        return $colIdx >= 0 && isset($row[$colIdx]) ? (string) $row[$colIdx] : '';
    }
}
