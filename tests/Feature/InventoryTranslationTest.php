<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Tests\TestCase;

class InventoryTranslationTest extends TestCase
{
    /**
     * Test that all inventory blade views have complete translations in Armenian and Russian.
     */
    public function test_all_inventory_view_strings_are_translated_in_hy_and_ru(): void
    {
        $files = array_merge(
            glob(resource_path('views/admin/inventory/**/*.blade.php')),
            glob(resource_path('views/admin/inventory/*.blade.php'))
        );

        $this->assertNotEmpty($files, 'Inventory view files should exist.');

        $hy = json_decode(file_get_contents(base_path('lang/hy.json')), true) ?: [];
        $ru = json_decode(file_get_contents(base_path('lang/ru.json')), true) ?: [];
        $en = json_decode(file_get_contents(base_path('lang/en.json')), true) ?: [];

        $missingInHy = [];
        $missingInRu = [];
        $missingInEn = [];
        $allowedUntranslated = [
            'SKU', 'Food Cost %', 'DIO', 'HACCP', 'CCP', 'FEFO', 'FIFO', 'BOM', 'WAC', 'VAT', 'EAN', 'UPC', 'PO',
        ];

        $totalKeys = 0;
        foreach ($files as $file) {
            $content = file_get_contents($file);
            preg_match_all('/(?:__|@lang|trans)\s*\(\s*[\x27"](.*?)[\x27"]\s*[\),]/u', $content, $matches);

            if (! empty($matches[1])) {
                foreach ($matches[1] as $key) {
                    $totalKeys++;

                    // Check HY
                    if (! isset($hy[$key]) || trim($hy[$key]) === '') {
                        $missingInHy[] = $key;
                    } elseif ($hy[$key] === $key && preg_match('/[a-zA-Z]{3,}/', $key) && ! in_array($key, $allowedUntranslated)) {
                        $missingInHy[] = $key;
                    }

                    // Check RU
                    if (! isset($ru[$key]) || trim($ru[$key]) === '') {
                        $missingInRu[] = $key;
                    } elseif ($ru[$key] === $key && preg_match('/[a-zA-Z]{3,}/', $key) && ! in_array($key, $allowedUntranslated)) {
                        $missingInRu[] = $key;
                    }

                    // Check EN
                    if (! isset($en[$key])) {
                        $missingInEn[] = $key;
                    }
                }
            }
        }

        $this->assertGreaterThan(500, $totalKeys, 'Should have scanned more than 500 translation occurrences.');
        $this->assertEmpty(array_unique($missingInHy), 'Missing or untranslated keys in hy.json: '.implode(', ', array_unique($missingInHy)));
        $this->assertEmpty(array_unique($missingInRu), 'Missing or untranslated keys in ru.json: '.implode(', ', array_unique($missingInRu)));
        $this->assertEmpty(array_unique($missingInEn), 'Missing keys in en.json: '.implode(', ', array_unique($missingInEn)));
    }

    /**
     * Test runtime localization translation resolution in Armenian and Russian.
     */
    public function test_runtime_translation_resolution(): void
    {
        App::setLocale('hy');
        $this->assertEquals('Պահեստի ընդհանուր արժեք', __('Total Inventory Value'));
        $this->assertEquals('HACCP և Սննդի անվտանգություն', __('HACCP & Food Safety'));
        $this->assertEquals('Թափոն և Կորուստներ', __('Waste & Loss'));
        $this->assertEquals('Ավելացնել նոր բաղադրիչ', __('Add New Ingredient'));
        $this->assertEquals('Ստեղծել պահեստ', __('Create Warehouse'));

        App::setLocale('ru');
        $this->assertEquals('Общая стоимость склада', __('Total Inventory Value'));
        $this->assertEquals('HACCP и Пищевая безопасность', __('HACCP & Food Safety'));
        $this->assertEquals('Списания и Отходы', __('Waste & Loss'));
        $this->assertEquals('Добавить новый ингредиент', __('Add New Ingredient'));
        $this->assertEquals('Создать склад', __('Create Warehouse'));

        App::setLocale('en');
        $this->assertEquals('Total Inventory Value', __('Total Inventory Value'));
    }
}
