<?php

namespace App\Services;

use App\Services\Security\SsrfProtectionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class MenuExtractorService
{
    /**
     * Extract menu text or structured multimodal payload from an uploaded file.
     *
     * @return array{type: string, text?: string, mime?: string, base64?: string, items?: array, categories?: array}
     */
    public function extractFromFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = $file->getMimeType();
        $realPath = $file->getRealPath();

        $disallowed = ['php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'vbs', 'html', 'htm'];
        if (in_array($extension, $disallowed, true)) {
            throw new \InvalidArgumentException("Execution-risk file extension [{$extension}] is strictly prohibited.");
        }

        $head = @file_get_contents($realPath, false, null, 0, 4096) ?: '';
        if (preg_match('/<\?php|<\?=|eval\s*\(|system\s*\(|shell_exec\s*\(|<script[\s>]/i', $head)) {
            throw new \InvalidArgumentException('Executable script content detected in menu file. Processing aborted.');
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $img = @imagecreatefromstring(file_get_contents($realPath));
            if ($img === false) {
                throw new \InvalidArgumentException('Uploaded menu image is corrupted or invalid.');
            }
            imagedestroy($img);
        }

        return match ($extension) {
            'csv' => $this->extractTabularOrTextFromCsv($realPath),
            'xlsx' => $this->extractTabularOrTextFromXlsx($realPath),
            'xls' => $this->extractTabularOrTextFromLegacyXls($realPath),
            'docx' => [
                'type' => 'text',
                'text' => $this->extractFromDocx($realPath),
            ],
            'doc' => [
                'type' => 'text',
                'text' => $this->extractFromLegacyDoc($realPath),
            ],
            'png', 'jpg', 'jpeg', 'webp' => [
                'type' => 'image',
                'mime' => $mime ?: "image/{$extension}",
                'base64' => base64_encode(file_get_contents($realPath)),
            ],
            'pdf' => [
                'type' => 'pdf',
                'mime' => 'application/pdf',
                'base64' => base64_encode(file_get_contents($realPath)),
                'text' => $this->extractTextFromPdfStream($realPath),
            ],
            'json' => [
                'type' => 'text',
                'text' => $this->extractFromJsonFile($realPath),
            ],
            default => [
                'type' => 'text',
                'text' => file_get_contents($realPath) ?: '',
            ],
        };
    }

    /**
     * Extract array of rows from CSV file preserving dense column indexes.
     *
     * @return array<int, array<int, string>>
     */
    public function extractRowsFromCsv(string $filePath): array
    {
        if (! file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return [];
        }

        // Auto-detect delimiter from first line
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiters = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxCount = 0;

        foreach ($delimiters as $delim) {
            $count = substr_count($firstLine, $delim);
            if ($count > $maxCount) {
                $maxCount = $count;
                $bestDelimiter = $delim;
            }
        }

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, $bestDelimiter, '"', '')) !== false) {
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
     * Extract menu text from a CSV file.
     */
    public function extractFromCsv(string $filePath): string
    {
        return $this->rowsToText($this->extractRowsFromCsv($filePath));
    }

    /**
     * Extract structured or fallback text from CSV.
     */
    public function extractTabularOrTextFromCsv(string $filePath): array
    {
        $rows = $this->extractRowsFromCsv($filePath);
        $tabular = $this->parseTabularData($rows);
        if ($tabular !== null && ! empty($tabular['categories'])) {
            return [
                'type' => 'structured',
                'categories' => $tabular['categories'],
                'text' => $this->rowsToText($rows),
            ];
        }

        return [
            'type' => 'text',
            'text' => $this->rowsToText($rows),
        ];
    }

    /**
     * Extract array of rows from XLSX preserving dense column indexes.
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

        // 2. Extract sheet rows preserving cell column coordinates
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
     * Extract menu text from modern Excel (.xlsx).
     */
    public function extractFromXlsx(string $filePath): string
    {
        return $this->rowsToText($this->extractRowsFromXlsx($filePath));
    }

    /**
     * Extract structured or fallback text from modern Excel (.xlsx).
     */
    public function extractTabularOrTextFromXlsx(string $filePath): array
    {
        $rows = $this->extractRowsFromXlsx($filePath);
        $tabular = $this->parseTabularData($rows);
        if ($tabular !== null && ! empty($tabular['categories'])) {
            return [
                'type' => 'structured',
                'categories' => $tabular['categories'],
                'text' => $this->rowsToText($rows),
            ];
        }

        return [
            'type' => 'text',
            'text' => $this->rowsToText($rows),
        ];
    }

    /**
     * Convert Excel column letter (e.g. A, B, Z, AA) to zero-based index.
     */
    public function colLetterToIndex(string $cellRef): int
    {
        preg_match('/^[A-Z]+/i', $cellRef, $matches);
        if (empty($matches)) {
            return 0;
        }
        $letters = strtoupper($matches[0]);
        $len = strlen($letters);
        $idx = 0;
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $idx - 1;
    }

    /**
     * Convert rows array to pipe-separated text lines.
     */
    public function rowsToText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $cells = array_filter(array_map('trim', $row), fn ($v) => $v !== '');
            if (! empty($cells)) {
                $lines[] = implode(' | ', $cells);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Normalize header string for resilient matching.
     */
    public function normalizeHeader(string $header): string
    {
        $str = mb_strtolower(trim($header), 'UTF-8');
        $str = preg_replace('/[\x{FEFF}\x{200B}-\x{200D}]/u', '', $str);

        return preg_replace('/[\s_\-\.\:\#\(\)\[\]\/\\\'",;]+/u', '', $str);
    }

    /**
     * Detect column index mapping from a header row regardless of column order.
     *
     * Roles: category, image, regular_price, sale_price, price, description, short_description, name
     *
     * @return array<string, int>
     */
    public function detectHeaderMap(array $headers): array
    {
        $roles = [
            'name' => [
                'exact' => [
                    'name', 'names', 'productname', 'dishname', 'itemname', 'item', 'dish', 'product', 'title', 'label',
                    'անվանում', 'անվանումը', 'անուն', 'անունը', 'ուտեստ', 'ուտեստիանվանում', 'ապրանք', 'ապրանքիանվանում', 'կերակրատեսակ',
                    'наименование', 'название', 'названиеблюда', 'названиетовара', 'товар', 'блюдо', 'позиция', 'имя',
                ],
                'contains' => ['dishname', 'productname', 'itemname', 'ուտեստ', 'անվան', 'наименов', 'назван', 'name'],
                'disallowed' => ['file', 'sheet', 'user', 'author', 'company', 'brand', 'customer', 'vendor', 'short', 'parent', 'grouped', 'upsell', 'cross', 'button'],
            ],
            'category' => [
                'exact' => [
                    'categories', 'category', 'productcategory', 'productcategories', 'cat', 'cats', 'categoryname', 'menusection', 'menucategory',
                    'ապրանքախումբ', 'ապրանքախումբը', 'կատեգորիա', 'կատեգորիաներ', 'կատեգորիան', 'բաժին', 'բաժիններ', 'խումբ', 'խմբեր', 'դասակարգում',
                    'категория', 'категории', 'категориятовара', 'раздел', 'разделы', 'группа', 'группы', 'группатоваров',
                ],
                'contains' => ['categor', 'կատեգոր', 'ապրանքախումբ', 'категор', 'раздел', 'խումբ', 'բաժին'],
                'disallowed' => ['type', 'տիպ', 'тип', 'date', 'visibility', 'tax', 'shipping', 'download', 'stock', 'inventory', 'weight', 'height', 'length', 'width', 'tag', 'tags', 'parent', 'sku', 'button', 'status', 'review'],
            ],
            'regular_price' => [
                'exact' => [
                    'regularprice', 'price', 'prices', 'unitprice', 'sellingprice', 'cost', 'amount', 'rate', 'retailprice',
                    'գին', 'գինը', 'արժեք', 'արժեքը',
                    'цена', 'стоимость', 'тариф', 'сумма',
                ],
                'contains' => ['regularprice', 'unitprice', 'sellingprice', 'price', 'գին', 'արժեք', 'цена', 'стоим'],
                'disallowed' => ['date', 'time', 'start', 'starts', 'end', 'ends', 'tax', 'class', 'status', 'range', 'period', 'sale', 'discount', 'special', 'promo', 'զեղչ', 'скидк'],
            ],
            'sale_price' => [
                'exact' => [
                    'saleprice', 'discountprice', 'specialprice', 'promoprice', 'offerprice',
                    'զեղչվածգին', 'զեղչգին', 'զեղչիգին', 'զեղչվածարժեք',
                    'акционнаяцена', 'скидочнаяцена', 'ценасоскидкой',
                ],
                'contains' => ['saleprice', 'discountprice', 'specialprice', 'զեղչված', 'акцион'],
                'disallowed' => ['date', 'time', 'start', 'starts', 'end', 'ends', 'tax', 'class', 'status', 'range', 'period'],
            ],
            'description' => [
                'exact' => [
                    'description', 'descriptions', 'details', 'ingredients', 'about', 'summary', 'info', 'information',
                    'նկարագրություն', 'նկարագրությունը', 'նկարագիր', 'բաղադրություն', 'բաղադրիչներ', 'մանրամասներ', 'տեղեկություն',
                    'описание', 'состав', 'детали', 'ингредиенты', 'информация', 'описаниетовара', 'описаниеблюда',
                ],
                'contains' => ['description', 'նկարագր', 'բաղադր', 'описан', 'состав', 'detail', 'ingred'],
                'disallowed' => ['short', 'brief', 'կարճ', 'հակիրճ', 'кратк'],
            ],
            'short_description' => [
                'exact' => [
                    'shortdescription', 'shortdesc', 'briefdescription', 'brief',
                    'կարճնկարագրություն', 'հակիրճնկարագրություն',
                    'краткоеописание', 'короткоеописание',
                ],
                'contains' => ['shortdesc', 'shortdescription', 'կարճնկարագր', 'краткоеопис'],
                'disallowed' => ['date', 'time', 'tax'],
            ],
            'image' => [
                'exact' => [
                    'images', 'image', 'img', 'imgs', 'photo', 'photos', 'picture', 'pictures', 'pic', 'pics',
                    'imageurl', 'imageurls', 'photourl', 'photourls', 'picurl', 'imgurl', 'pictureurl', 'url',
                    'նկար', 'նկարներ', 'նկարները', 'նկարիհղում', 'պատկեր', 'պատկերներ', 'լուսանկար', 'լուսանկարներ',
                    'изображение', 'изображения', 'фото', 'фотография', 'фотографии', 'картинка', 'картинки', 'ссылканафото',
                ],
                'contains' => ['image', 'photo', 'picture', 'նկար', 'լուսանկար', 'պատկեր', 'фото', 'картинк', 'изображ'],
                'disallowed' => ['file', 'doc', 'pdf', 'date', 'tax', 'width', 'height'],
            ],
        ];

        $normalized = [];
        foreach ($headers as $colIdx => $raw) {
            $normalized[$colIdx] = $this->normalizeHeader((string) $raw);
        }

        $map = [];
        $used = [];

        // Pass 1: Exact matches respecting disallowed negative keywords
        foreach ($roles as $role => $cfg) {
            foreach ($normalized as $colIdx => $norm) {
                if (isset($used[$colIdx]) || $norm === '') {
                    continue;
                }

                $disallowed = false;
                foreach ($cfg['disallowed'] as $bad) {
                    if (str_contains($norm, $bad)) {
                        $disallowed = true;
                        break;
                    }
                }
                if ($disallowed) {
                    continue;
                }

                if (in_array($norm, $cfg['exact'], true)) {
                    $map[$role] = (int) $colIdx;
                    $used[$colIdx] = true;
                    break;
                }
            }
        }

        // Pass 2: Substring matches for remaining roles respecting disallowed keywords
        foreach ($roles as $role => $cfg) {
            if (isset($map[$role])) {
                continue;
            }

            foreach ($normalized as $colIdx => $norm) {
                if (isset($used[$colIdx]) || $norm === '') {
                    continue;
                }

                $disallowed = false;
                foreach ($cfg['disallowed'] as $bad) {
                    if (str_contains($norm, $bad)) {
                        $disallowed = true;
                        break;
                    }
                }
                if ($disallowed) {
                    continue;
                }

                foreach ($cfg['contains'] as $substr) {
                    if (str_contains($norm, $substr)) {
                        $map[$role] = (int) $colIdx;
                        $used[$colIdx] = true;
                        break 2;
                    }
                }
            }
        }

        // Set fallback 'price' alias if regular_price is present
        if (isset($map['regular_price']) && ! isset($map['price'])) {
            $map['price'] = $map['regular_price'];
        } elseif (isset($map['sale_price']) && ! isset($map['price'])) {
            $map['price'] = $map['sale_price'];
        }

        return $map;
    }

    /**
     * Clean and parse price string/float into a clean float value.
     */
    public function cleanPrice(mixed $val): float
    {
        if (is_numeric($val)) {
            return (float) $val;
        }

        $str = trim((string) $val);
        if ($str === '') {
            return 0.0;
        }

        $str = preg_replace('/[^\d.,]/', '', $str);
        if ($str === '') {
            return 0.0;
        }

        if (str_contains($str, ',') && str_contains($str, '.')) {
            if (strrpos($str, '.') > strrpos($str, ',')) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            }
        } elseif (str_contains($str, ',')) {
            $parts = explode(',', $str);
            if (count($parts) === 2 && strlen($parts[1]) === 3) {
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        }

        return (float) $str;
    }

    /**
     * Parse 2D tabular rows into categories and dishes by detecting arbitrary header layout.
     *
     * @return array{categories: array}|null
     */
    public function parseTabularData(array $rows): ?array
    {
        if (count($rows) < 2) {
            return null;
        }

        $headerRowIndex = null;
        $headerMap = [];

        // Scan the first 5 rows to locate the header row
        foreach (array_slice($rows, 0, 5, true) as $rIdx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $map = $this->detectHeaderMap($row);
            if (isset($map['name']) || (isset($map['price']) && (isset($map['category']) || isset($map['image'])))) {
                $headerRowIndex = $rIdx;
                $headerMap = $map;
                break;
            }
        }

        if ($headerRowIndex === null || empty($headerMap)) {
            return null;
        }

        // If 'name' was not detected but 'price' was, assign first available unmapped column to 'name'
        if (! isset($headerMap['name'])) {
            $mappedIndices = array_values($headerMap);
            $firstRow = $rows[$headerRowIndex];
            foreach (array_keys($firstRow) as $colIdx) {
                if (! in_array($colIdx, $mappedIndices, true)) {
                    $headerMap['name'] = $colIdx;
                    break;
                }
            }
        }

        if (! isset($headerMap['name'])) {
            return null;
        }

        $categoriesMap = [];
        $dataRows = array_slice($rows, $headerRowIndex + 1);
        $lastCategoryName = 'General Menu';
        $hasSeenCategory = false;

        foreach ($dataRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = isset($headerMap['name'], $row[$headerMap['name']]) ? trim((string) $row[$headerMap['name']]) : '';
            if ($name === '') {
                continue;
            }

            // Category resolution with section carry-over
            if (isset($headerMap['category'], $row[$headerMap['category']])) {
                $catVal = trim((string) $row[$headerMap['category']]);
                if ($catVal !== '') {
                    $lastCategoryName = $catVal;
                    $hasSeenCategory = true;
                }
            }

            $categoryName = $hasSeenCategory ? $lastCategoryName : 'General Menu';

            // Description resolution (main desc fallback to short_desc)
            $desc = '';
            if (isset($headerMap['description'], $row[$headerMap['description']])) {
                $desc = trim((string) $row[$headerMap['description']]);
            }
            if ($desc === '' && isset($headerMap['short_description'], $row[$headerMap['short_description']])) {
                $desc = trim((string) $row[$headerMap['short_description']]);
            }

            // Price resolution (sale_price > regular_price > price)
            $price = 0.0;
            if (isset($headerMap['sale_price'], $row[$headerMap['sale_price']])) {
                $saleVal = $this->cleanPrice($row[$headerMap['sale_price']]);
                if ($saleVal > 0) {
                    $price = $saleVal;
                }
            }
            if ($price <= 0.0 && isset($headerMap['regular_price'], $row[$headerMap['regular_price']])) {
                $price = $this->cleanPrice($row[$headerMap['regular_price']]);
            }
            if ($price <= 0.0 && isset($headerMap['price'], $row[$headerMap['price']])) {
                $price = $this->cleanPrice($row[$headerMap['price']]);
            }

            // Image URL resolution (take first valid URL if comma-separated)
            $image = '';
            if (isset($headerMap['image'], $row[$headerMap['image']])) {
                $imgVal = trim((string) $row[$headerMap['image']]);
                if ($imgVal !== '') {
                    $imgParts = explode(',', $imgVal);
                    $image = trim($imgParts[0]);
                }
            }

            if (! isset($categoriesMap[$categoryName])) {
                $categoriesMap[$categoryName] = [
                    'name' => $categoryName,
                    'products' => [],
                ];
            }

            $categoriesMap[$categoryName]['products'][] = [
                'name' => $name,
                'description' => $desc ?: 'Freshly prepared specialty.',
                'price' => $price,
                'image' => $image,
                'dietary_tags' => [],
                'calories' => rand(250, 750),
            ];
        }

        if (empty($categoriesMap)) {
            return null;
        }

        return [
            'categories' => array_values($categoriesMap),
        ];
    }

    /**
     * Extract structured or fallback text from legacy Excel (.xls).
     */
    public function extractTabularOrTextFromLegacyXls(string $filePath): array
    {
        $text = $this->extractFromLegacyXls($filePath);
        $lines = explode("\n", $text);
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            if (str_contains($line, "\t")) {
                $rows[] = array_map('trim', explode("\t", $line));
            } elseif (str_contains($line, ' | ')) {
                $rows[] = array_map('trim', explode(' | ', $line));
            }
        }

        if (count($rows) >= 2) {
            $tabular = $this->parseTabularData($rows);
            if ($tabular !== null && ! empty($tabular['categories'])) {
                return [
                    'type' => 'structured',
                    'categories' => $tabular['categories'],
                    'text' => $text,
                ];
            }
        }

        return [
            'type' => 'text',
            'text' => $text,
        ];
    }

    /**
     * Extract menu text from legacy Excel (.xls).
     */
    public function extractFromLegacyXls(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }

        // Extract printable UTF-8 strings
        preg_match_all('/[\x20-\x7E\x{0400}-\x{04FF}\x{0530}-\x{058F}]{3,}/u', $raw, $matches);

        return implode("\n", array_unique($matches[0] ?? []));
    }

    /**
     * Extract menu text from modern Word (.docx) using native ZipArchive + SimpleXML.
     */
    public function extractFromDocx(string $filePath): string
    {
        if (! file_exists($filePath) || ! class_exists(ZipArchive::class)) {
            return '';
        }

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($documentXml === false) {
            return '';
        }

        // Convert paragraph and table tags to formatted text delimiters
        $formatted = str_replace(['</w:p>', '</w:tr>'], "\n", $documentXml);
        $formatted = str_replace('</w:tc>', ' | ', $formatted);
        $text = strip_tags($formatted);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $lines = explode("\n", $text);
        $cleanLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $cleanLines[] = $line;
            }
        }

        return implode("\n", $cleanLines);
    }

    /**
     * Extract menu text from legacy Word (.doc).
     */
    public function extractFromLegacyDoc(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return '';
        }

        // Extract printable text sequences
        preg_match_all('/[\x20-\x7E\x{0400}-\x{04FF}\x{0530}-\x{058F}]{3,}/u', $raw, $matches);

        return implode("\n", $matches[0] ?? []);
    }

    /**
     * Extract JSON menu structure into readable text.
     */
    public function extractFromJsonFile(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $content = file_get_contents($filePath);
        if (! $content) {
            return '';
        }

        $json = json_decode($content, true);
        if (! is_array($json)) {
            return $content;
        }

        $lines = [];
        $this->flattenJsonToText($json, $lines);

        return implode("\n", $lines);
    }

    private function flattenJsonToText(array $array, array &$lines, string $prefix = ''): void
    {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $lines[] = $prefix.$k.':';
                $this->flattenJsonToText($v, $lines, $prefix.'  ');
            } else {
                $lines[] = $prefix."{$k}: {$v}";
            }
        }
    }

    /**
     * Best-effort local PDF stream extraction for offline mode.
     */
    public function extractTextFromPdfStream(string $filePath): string
    {
        if (! file_exists($filePath)) {
            return '';
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return '';
        }

        $text = '';
        // Look for PDF text show operations: (Some text) Tj or [(Some) -20 (text)] TJ
        if (preg_match_all('/\((.*?)\)\s*Tj/s', $content, $matches)) {
            $text .= implode("\n", $matches[1]);
        }

        // Fallback printable text
        if (strlen(trim($text)) < 50) {
            preg_match_all('/[\x20-\x7E\x{0400}-\x{04FF}\x{0530}-\x{058F}]{4,}/u', $content, $matches);
            $text = implode("\n", array_slice($matches[0] ?? [], 0, 200));
        }

        return trim($text);
    }

    /**
     * Extract structured menu data or semantic text from a website URL.
     * Supports WooCommerce, Shopify, Schema.org JSON-LD, HTML tables, and semantic fallback.
     *
     * @return array{type: string, categories?: array, text?: string}
     */
    public function extractStructuredOrTextFromUrl(string $url): array
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Անվավեր URL հասցե: Խնդրում ենք տրամադրել լիարժեք հասցե (օր. https://example.com/menu)');
        }

        app(SsrfProtectionService::class)->assertSafeUrl($url, 'Menu extraction URL');

        $visited = [];
        $toVisit = [$url];
        $allCategories = [];
        $maxPages = 5;
        $parsedUrl = parse_url($url);
        $baseHost = $parsedUrl['host'] ?? '';
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $firstHtml = '';

        while (! empty($toVisit) && count($visited) < $maxPages) {
            $currentUrl = array_shift($toVisit);
            if (isset($visited[$currentUrl])) {
                continue;
            }
            $visited[$currentUrl] = true;

            try {
                app(SsrfProtectionService::class)->assertSafeUrl($currentUrl, 'Crawl URL');

                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                        'Accept-Language' => 'hy,en-US,en;q=0.9,ru;q=0.8',
                    ])
                    ->get($currentUrl);

                if (! $response->successful()) {
                    continue;
                }

                $html = $response->body();
                if ($firstHtml === '') {
                    $firstHtml = $html;
                }

                $pageCategories = $this->parseHtmlMenuProducts($html, $currentUrl);
                if (! empty($pageCategories)) {
                    foreach ($pageCategories as $cat) {
                        $catName = $cat['name'];
                        if (! isset($allCategories[$catName])) {
                            $allCategories[$catName] = [
                                'name' => $catName,
                                'products' => [],
                            ];
                        }
                        foreach ($cat['products'] as $prod) {
                            $prodName = $prod['name'];
                            $allCategories[$catName]['products'][$prodName] = $prod;
                        }
                    }

                    // Look for pagination links (e.g. /page/2/, ?page=2)
                    $dom = new \DOMDocument;
                    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                    $xpath = new \DOMXPath($dom);
                    $pageLinks = $xpath->query('//a[contains(@href, "page/") or contains(@href, "page=") or contains(@class, "page-number") or contains(@class, "next")]');
                    foreach ($pageLinks as $pl) {
                        $href = $pl->getAttribute('href');
                        if (empty($href)) {
                            continue;
                        }
                        $p = parse_url($href);
                        if (isset($p['host']) && $p['host'] !== $baseHost) {
                            continue;
                        }

                        if (str_starts_with($href, '/')) {
                            $href = $scheme.'://'.$baseHost.$href;
                        } elseif (! str_starts_with($href, 'http')) {
                            $href = rtrim($url, '/').'/'.ltrim($href, '/');
                        }

                        if (! isset($visited[$href]) && ! in_array($href, $toVisit, true)) {
                            $toVisit[] = $href;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("URL fetch failed for {$currentUrl}: ".$e->getMessage());
            }
        }

        if (! empty($allCategories)) {
            $formatted = [];
            foreach ($allCategories as $cat) {
                $formatted[] = [
                    'name' => $cat['name'],
                    'products' => array_values($cat['products']),
                ];
            }

            return [
                'type' => 'structured',
                'categories' => $formatted,
                'text' => $firstHtml ? $this->convertHtmlToMenuText($firstHtml) : '',
            ];
        }

        // Fallback: Check for Schema.org JSON-LD
        if (! empty($firstHtml)) {
            $schemaText = $this->extractSchemaJsonLd($firstHtml);
            if (! empty($schemaText)) {
                return [
                    'type' => 'text',
                    'text' => "STRUCTURED SCHEMA.ORG MENU DATA FOUND:\n".$schemaText,
                ];
            }

            return [
                'type' => 'text',
                'text' => $this->convertHtmlToMenuText($firstHtml),
            ];
        }

        throw new \RuntimeException('Հնարավոր չեղավ բացել կայքը։ Ստուգեք հասցեն կամ հասանելիությունը։');
    }

    /**
     * Parse structured products and categories from HTML DOM.
     *
     * @return array<int, array{name: string, products: array}>
     */
    public function parseHtmlMenuProducts(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        $parsedUrl = parse_url($baseUrl);
        $baseHost = $parsedUrl['host'] ?? '';
        $scheme = $parsedUrl['scheme'] ?? 'https';

        $categories = [];

        // 1. Check for product cards/containers (WooCommerce, Shopify, Storefronts, Restaurant dishes)
        $itemQuery = '//div[contains(@class, "type-product") or contains(@class, "product-small") or contains(@class, "product-card") or contains(@class, "product-item") or contains(@class, "menu-item") or contains(@class, "dish-item") or contains(@class, "dish-card") or contains(@class, "food-item")] | //li[contains(@class, "product") or contains(@class, "menu-item") or contains(@class, "food-item")] | //article[contains(@class, "product")]';
        $nodes = $xpath->query($itemQuery);

        if ($nodes->length > 0) {
            foreach ($nodes as $node) {
                // Category: inside card first, then closest preceding heading
                $catNode = $xpath->query('.//p[contains(@class, "product-cat") or contains(@class, "category")] | .//span[contains(@class, "posted_in")] | .//a[contains(@rel, "tag")]', $node);
                $category = '';
                if ($catNode->length > 0) {
                    $category = trim($catNode->item(0)->textContent);
                }
                if ($category === '') {
                    $precedingHeading = $xpath->query('preceding::*[self::h1 or self::h2 or self::h3 or self::h4][1]', $node);
                    if ($precedingHeading->length > 0) {
                        $category = trim($precedingHeading->item(0)->textContent);
                    }
                }
                if ($category === '') {
                    $category = 'General Menu';
                }

                // Name
                $nameNode = $xpath->query('.//p[contains(@class, "product-title")] | .//h2[contains(@class, "product-title") or contains(@class, "woocommerce-loop-product__title")] | .//h3[contains(@class, "product-title") or contains(@class, "menu-item-title")] | .//h4 | .//a[contains(@class, "woocommerce-LoopProduct-link") or contains(@class, "product-title")]', $node);
                $name = '';
                if ($nameNode->length > 0) {
                    $name = trim($nameNode->item(0)->textContent);
                }

                // Price
                $priceNode = $xpath->query('.//span[contains(@class, "price")] | .//span[contains(@class, "amount")] | .//div[contains(@class, "price")]', $node);
                $price = 0.0;
                if ($priceNode->length > 0) {
                    $price = $this->cleanPrice($priceNode->item(0)->textContent);
                }

                // Description
                $descNode = $xpath->query('.//div[contains(@class, "short-description")] | .//p[contains(@class, "description")] | .//div[contains(@class, "excerpt")]', $node);
                $desc = '';
                if ($descNode->length > 0) {
                    $desc = trim($descNode->item(0)->textContent);
                }

                // Fallback to node text parsing if name or price not found
                if ($name === '' || $price <= 0.0) {
                    $fullText = trim(preg_replace('/\s+/', ' ', $node->textContent));
                    if (preg_match('/^(.*?)(?:[\-—:\t]|\s{2,}|\s+)([0-9]{1,3}(?:[\s\xc2\xa0,][0-9]{3})*|[0-9]+)\s*(?:AMD|֏|դր|դրամ|USD|\$|€|RUB|руб)?(?:\s*[\-—:]\s*(.*))?$/iu', $fullText, $m)) {
                        if ($name === '') {
                            $name = trim($m[1], " -:•*\t");
                        }
                        if ($price <= 0.0) {
                            $price = $this->cleanPrice($m[2]);
                        }
                        if ($desc === '' && isset($m[3])) {
                            $desc = trim($m[3]);
                        }
                    }
                }

                // Image (prioritize real image URLs over data: SVG placeholders)
                $imgNode = $xpath->query('.//img', $node);
                $img = '';
                if ($imgNode->length > 0) {
                    $imgEl = $imgNode->item(0);
                    foreach (['data-src', 'data-lazy-src', 'data-original', 'src'] as $attr) {
                        $val = trim($imgEl->getAttribute($attr));
                        if ($val !== '' && ! str_starts_with($val, 'data:')) {
                            $img = $val;
                            break;
                        }
                    }

                    if ($img === '') {
                        $srcSet = trim($imgEl->getAttribute('srcset'));
                        if ($srcSet !== '') {
                            $firstPart = explode(',', $srcSet)[0] ?? '';
                            $srcUrl = trim(explode(' ', trim($firstPart))[0] ?? '');
                            if ($srcUrl !== '' && ! str_starts_with($srcUrl, 'data:')) {
                                $img = $srcUrl;
                            }
                        }
                    }

                    if ($img !== '' && str_starts_with($img, '/')) {
                        $img = $scheme.'://'.$baseHost.$img;
                    }
                }

                if ($name !== '') {
                    $categories[$category][$name] = [
                        'name' => $name,
                        'description' => $desc ?: 'Freshly prepared specialty.',
                        'price' => $price,
                        'image' => $img,
                        'dietary_tags' => [],
                        'calories' => rand(250, 750),
                    ];
                }
            }
        }

        // 2. Check for HTML tables if no product cards were found
        if (empty($categories)) {
            $tables = $xpath->query('//table');
            foreach ($tables as $table) {
                $rows = [];
                foreach ($xpath->query('.//tr', $table) as $tr) {
                    $cells = [];
                    foreach ($xpath->query('.//th | .//td', $tr) as $td) {
                        $cells[] = trim($td->textContent);
                    }
                    if (! empty(array_filter($cells))) {
                        $rows[] = $cells;
                    }
                }
                if (count($rows) >= 2) {
                    $tabular = $this->parseTabularData($rows);
                    if ($tabular !== null && ! empty($tabular['categories'])) {
                        return $tabular['categories'];
                    }
                }
            }
        }

        $formatted = [];
        foreach ($categories as $catName => $products) {
            $formatted[] = [
                'name' => $catName,
                'products' => array_values($products),
            ];
        }

        return $formatted;
    }

    /**
     * Extract menu content from a website URL as text.
     *
     * @throws \Exception
     */
    public function extractFromUrl(string $url): string
    {
        $res = $this->extractStructuredOrTextFromUrl($url);
        if ($res['type'] === 'structured' && ! empty($res['categories'])) {
            $rows = [];
            $rows[] = ['Categories', 'Name', 'Price', 'Images', 'Description'];
            foreach ($res['categories'] as $cat) {
                foreach ($cat['products'] as $p) {
                    $rows[] = [
                        $cat['name'],
                        $p['name'],
                        (string) $p['price'],
                        $p['image'] ?? '',
                        $p['description'] ?? '',
                    ];
                }
            }

            return $this->rowsToText($rows);
        }

        return $res['text'] ?? '';
    }

    /**
     * Extract Schema.org JSON-LD microdata from HTML.
     */
    private function extractSchemaJsonLd(string $html): string
    {
        if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $extractedItems = [];
            foreach ($matches[1] as $jsonString) {
                $data = json_decode(trim($jsonString), true);
                if (! is_array($data)) {
                    continue;
                }

                // Check for Menu, MenuItem, Restaurant or array of entities
                $dataList = isset($data['@graph']) ? $data['@graph'] : [$data];
                foreach ($dataList as $entry) {
                    $type = $entry['@type'] ?? '';
                    if (in_array($type, ['Menu', 'MenuItem', 'MenuSection', 'Restaurant', 'FoodEstablishment'], true)) {
                        $extractedItems[] = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }
                }
            }

            if (! empty($extractedItems)) {
                return implode("\n\n", $extractedItems);
            }
        }

        return '';
    }

    /**
     * Convert raw HTML into clean, semantic text for menu extraction.
     */
    private function convertHtmlToMenuText(string $html): string
    {
        // Strip out scripts, styles, iframes, svgs, header/footer/nav elements
        $cleanHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $cleanHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $cleanHtml);
        $cleanHtml = preg_replace('/<noscript\b[^>]*>(.*?)<\/noscript>/is', '', $cleanHtml);
        $cleanHtml = preg_replace('/<svg\b[^>]*>(.*?)<\/svg>/is', '', $cleanHtml);
        $cleanHtml = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $cleanHtml);
        $cleanHtml = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $cleanHtml);

        // Convert structural elements to readable text
        $cleanHtml = preg_replace('/<h[1-3][^>]*>(.*?)<\/h[1-3]>/is', "\n\n# $1\n", $cleanHtml);
        $cleanHtml = preg_replace('/<h[4-6][^>]*>(.*?)<\/h[4-6]>/is', "\n## $1\n", $cleanHtml);
        $cleanHtml = preg_replace('/<(?:p|div|tr|li)[^>]*>/is', "\n", $cleanHtml);
        $cleanHtml = preg_replace('/<(?:td|th)[^>]*>(.*?)<\/(?:td|th)>/is', ' $1 | ', $cleanHtml);

        // Decode HTML entities and strip tags
        $text = strip_tags($cleanHtml);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up whitespace
        $lines = explode("\n", $text);
        $filtered = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));
            // Keep meaningful lines, omit very short UI noise
            if (mb_strlen($line) >= 2) {
                $filtered[] = $line;
            }
        }

        // Limit size to avoid overflow (max 1000 lines)
        $filtered = array_slice($filtered, 0, 1000);

        return implode("\n", $filtered);
    }
}
