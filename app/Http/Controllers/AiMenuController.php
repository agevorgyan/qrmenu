<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateMenuJob;
use App\Models\Category;
use App\Models\Product;
use App\Services\AiMenuService;
use App\Services\MenuExtractorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AiMenuController extends Controller
{
    public function __construct(
        public AiMenuService $aiService,
        public MenuExtractorService $extractorService
    ) {}

    public function showImportForm()
    {
        $this->authorize('ai.view');

        $vendor = Auth::user()->vendor;

        return view('admin.ai.import', compact('vendor'));
    }

    public function processImport(Request $request)
    {
        $this->authorize('ai.manage');
        $request->validate([
            'import_source' => 'nullable|string|in:file,url,text',
            'website_url' => 'nullable|url|max:1000',
            'menu_file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,csv,txt,json,jpg,jpeg,png,webp|max:20480',
            'menu_text' => 'nullable|string',
        ]);

        if (! $request->hasFile('menu_file') && ! $request->filled('website_url') && ! $request->filled('menu_text')) {
            return back()->withInput()->with('error', 'Please upload a file, provide a website link, or paste menu text.');
        }

        try {
            $vendor = Auth::user()->vendor;
            if ($request->filled('website_url')) {
                $extraction = $this->extractorService->extractStructuredOrTextFromUrl($request->input('website_url'));
                if ($extraction['type'] === 'structured' && ! empty($extraction['categories'])) {
                    $parsedMenu = ['categories' => $extraction['categories']];
                } else {
                    $parsedMenu = $this->aiService->parseMenuFromText($extraction['text'] ?? '', $vendor);
                }
            } elseif ($request->hasFile('menu_file')) {
                $file = $request->file('menu_file');
                $extraction = $this->extractorService->extractFromFile($file);

                if ($extraction['type'] === 'image') {
                    $parsedMenu = $this->aiService->parseMenuFromImage($extraction['base64'], $extraction['mime'], $vendor);
                } elseif ($extraction['type'] === 'pdf') {
                    $parsedMenu = $this->aiService->parseMenuFromPdf($extraction['base64'], $extraction['text'] ?? null, $vendor);
                } elseif ($extraction['type'] === 'structured' && ! empty($extraction['categories'])) {
                    $parsedMenu = ['categories' => $extraction['categories']];
                } else {
                    $parsedMenu = $this->aiService->parseMenuFromText($extraction['text'] ?? '', $vendor);
                }
            } else {
                $parsedMenu = $this->aiService->parseMenuFromText($request->input('menu_text', ''), $vendor);
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'Menu extraction failed: '.$e->getMessage());
        }

        if (empty($parsedMenu['categories'])) {
            $parsedMenu = [
                'categories' => [
                    [
                        'name' => 'Imported Category',
                        'products' => [],
                    ],
                ],
            ];
        }

        return view('admin.ai.preview_import', [
            'vendor' => Auth::user()->vendor,
            'parsedData' => $parsedMenu,
        ]);
    }

    public function confirmImport(Request $request)
    {
        $this->authorize('ai.manage');

        $vendor = Auth::user()->vendor;
        $categoriesData = $request->input('categories', []);

        foreach ($categoriesData as $cIdx => $catData) {
            if (empty($catData['name'])) {
                continue;
            }

            $category = Category::create([
                'vendor_id' => $vendor->id,
                'name' => $catData['name'],
                'name_translations' => ['en' => $catData['name']],
                'sort_order' => Category::where('vendor_id', $vendor->id)->max('sort_order') + 1,
                'is_active' => true,
            ]);

            if (isset($catData['products']) && is_array($catData['products'])) {
                foreach ($catData['products'] as $pIdx => $prodData) {
                    if (empty($prodData['name'])) {
                        continue;
                    }

                    $image = ! empty($prodData['image'])
                        ? trim((string) $prodData['image'])
                        : null;

                    Product::create([
                        'vendor_id' => $vendor->id,
                        'category_id' => $category->id,
                        'name' => $prodData['name'],
                        'name_translations' => ['en' => $prodData['name']],
                        'description' => $prodData['description'] ?? '',
                        'price' => (float) ($prodData['price'] ?? 0),
                        'image' => $image,
                        'dietary_tags' => $prodData['dietary_tags'] ?? [],
                        'calories' => $prodData['calories'] ?? rand(300, 700),
                        'is_available' => true,
                        'sort_order' => $pIdx + 1,
                    ]);
                }
            }
        }

        return redirect()->route('admin.menu.index')->with('success', 'AI Menu successfully imported and published to your menu!');
    }

    public function translateMenu(Request $request)
    {
        $this->authorize('ai.manage');

        $vendor = Auth::user()->vendor;
        $validated = $request->validate([
            'target_language' => 'required|string|max:10',
            'overwrite_existing' => 'nullable',
        ]);

        $targetLang = strtolower(trim($validated['target_language']));
        $overwrite = $request->boolean('overwrite_existing', false);

        // Allow sufficient execution time for LLM multi-batch translation
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        try {
            // Execute synchronously so that menu items, categories, and descriptions are translated immediately
            TranslateMenuJob::dispatchSync($vendor->id, $targetLang, $overwrite);

            $langLabels = [
                'hy' => '🇦🇲 Armenian',
                'en' => '🇬🇧 English',
                'ru' => '🇷🇺 Russian',
                'fr' => '🇫🇷 French',
                'de' => '🇩🇪 German',
                'es' => '🇪🇸 Spanish',
                'it' => '🇮🇹 Italian',
                'ge' => '🇬🇪 Georgian',
                'ar' => '🇦🇪 Arabic',
                'fa' => '🇮🇷 Persian',
            ];
            $selectedLabel = $langLabels[$targetLang] ?? strtoupper($targetLang);

            return back()->with('success', "✨ All menu items, descriptions, and categories were successfully translated to [{$selectedLabel}] via AI.");
        } catch (\Throwable $e) {
            Log::error('AI Menu Translation failed: '.$e->getMessage());

            return back()->with('error', 'An error occurred during translation: '.$e->getMessage());
        }
    }

    /**
     * Add or update a supported language for this partner.
     */
    public function saveLanguage(Request $request)
    {
        $user = Auth::user();
        abort_unless(
            $user->isVendorOwner() || $user->can('translations.manage') || $user->can('settings.manage') || $user->can('menu.manage'),
            403,
            'Unauthorized access.'
        );

        $vendor = $user->vendor;
        abort_unless($vendor, 404, 'Vendor not found.');

        $validated = $request->validate([
            'code' => ['required', 'string', 'min:2', 'max:10', 'alpha_dash'],
            'name' => ['required', 'string', 'max:60'],
            'flag' => ['nullable', 'string', 'max:20'],
            'original_code' => ['nullable', 'string', 'max:10'],
        ]);

        $code = strtolower(trim($validated['code']));
        $name = trim($validated['name']);
        $flag = trim($validated['flag'] ?? '') ?: '🌐';
        $originalCode = ! empty($validated['original_code']) ? strtolower(trim($validated['original_code'])) : null;

        if ($originalCode && $originalCode !== $code) {
            $vendor->removeLanguage($originalCode);
        }

        $vendor->syncLanguage($code, $name, $flag, isActive: true);

        return back()->with('success', "Language '{$name}' ({$code}) saved successfully.");
    }

    /**
     * Remove a supported language for this partner.
     */
    public function deleteLanguage(string $code)
    {
        $user = Auth::user();
        abort_unless(
            $user->isVendorOwner() || $user->can('translations.manage') || $user->can('settings.manage') || $user->can('menu.manage'),
            403,
            'Unauthorized access.'
        );

        $vendor = $user->vendor;
        abort_unless($vendor, 404, 'Vendor not found.');

        $success = $vendor->removeLanguage($code);
        if (! $success) {
            return back()->with('error', 'Cannot remove the last active language.');
        }

        return back()->with('success', 'Language removed successfully.');
    }
}
