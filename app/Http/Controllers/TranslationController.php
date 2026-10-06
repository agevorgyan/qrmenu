<?php

namespace App\Http\Controllers;

use App\Jobs\TranslateContentJob;
use App\Models\Category;
use App\Models\ContentTranslation;
use App\Models\Language;
use App\Models\Product;
use App\Models\TranslationGlossary;
use App\Models\VendorLanguage;
use App\Services\Localization\LocaleManager;
use App\Services\Localization\TranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TranslationController extends Controller
{
    public function __construct(
        protected TranslationService $translationService,
        protected LocaleManager $localeManager
    ) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;
        abort_unless($vendor, 404, 'Vendor not found.');

        // Retrieve vendor supported languages
        $supportedLanguages = $vendor->getSupportedLanguages();
        $supportedCodes = $vendor->getSupportedLanguageCodes();
        $defaultLangCode = $vendor->getDefaultLanguageCode();

        // Selected target locale for editing
        $targetLocale = $request->get('locale');
        if (! $targetLocale || ! in_array($targetLocale, $supportedCodes, true)) {
            // Pick first non-default language or fallback to default
            $nonDefault = array_values(array_diff($supportedCodes, [$defaultLangCode]));
            $targetLocale = $nonDefault[0] ?? $defaultLangCode;
        }

        $status = $request->get('status', 'all');
        $type = $request->get('type', 'all');
        $search = $request->get('search');

        // Query Translations
        $query = ContentTranslation::where('vendor_id', $vendor->id)
            ->where('locale', $targetLocale)
            ->with(['translatable', 'reviewer']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($type !== 'all') {
            $modelClass = match ($type) {
                'product', 'Product' => Product::class,
                'category', 'Category' => Category::class,
                default => null,
            };
            if ($modelClass) {
                $query->where('translatable_type', $modelClass);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('source_text', 'ilike', "%{$search}%")
                    ->orWhere('translation', 'ilike', "%{$search}%");
            });
        }

        $translations = $query->orderBy('updated_at', 'desc')->paginate(20)->withQueryString();

        // Total entities to calculate coverage
        $totalProducts = Product::where('vendor_id', $vendor->id)->count();
        $totalCategories = Category::where('vendor_id', $vendor->id)->count();
        // Each product has 2 translatable fields (name, description); each category has 1 (name)
        $totalFields = ($totalProducts * 2) + $totalCategories;

        $stats = [
            'published' => ContentTranslation::where('vendor_id', $vendor->id)->where('locale', $targetLocale)->where('status', ContentTranslation::STATUS_PUBLISHED)->count(),
            'approved' => ContentTranslation::where('vendor_id', $vendor->id)->where('locale', $targetLocale)->where('status', ContentTranslation::STATUS_APPROVED)->count(),
            'draft' => ContentTranslation::where('vendor_id', $vendor->id)->where('locale', $targetLocale)->where('status', ContentTranslation::STATUS_DRAFT)->count(),
            'needs_review' => ContentTranslation::where('vendor_id', $vendor->id)->where('locale', $targetLocale)->where(function ($q) {
                $q->where('status', ContentTranslation::STATUS_NEEDS_REVIEW)->orWhere('is_outdated', true);
            })->count(),
            'total_fields' => max(1, $totalFields),
        ];

        $stats['percentage'] = min(100, (int) round(($stats['published'] / max(1, $totalFields)) * 100));

        $allSystemLanguages = Language::where('is_active', true)->get();

        return view('admin.translations.index', compact(
            'vendor',
            'translations',
            'supportedLanguages',
            'supportedCodes',
            'defaultLangCode',
            'targetLocale',
            'status',
            'type',
            'search',
            'stats',
            'allSystemLanguages'
        ));
    }

    public function edit(ContentTranslation $translation): View
    {
        $user = Auth::user();
        abort_unless((int) $user->vendor_id === (int) $translation->vendor_id, 403, 'Unauthorized.');

        $translation->load(['translatable', 'reviewer', 'histories.creator']);

        return view('admin.translations.edit', compact('translation'));
    }

    public function handleAction(Request $request, ContentTranslation $translation): RedirectResponse
    {
        $user = Auth::user();
        abort_unless((int) $user->vendor_id === (int) $translation->vendor_id, 403, 'Unauthorized.');

        $action = $request->input('action');

        try {
            switch ($action) {
                case 'save_draft':
                    $newText = (string) $request->input('translated_text');
                    $notes = (string) $request->input('notes');
                    $this->translationService->saveDraft($translation, $newText, $user, $notes);
                    $msg = 'Translation draft updated successfully.';
                    break;

                case 'approve':
                    $this->translationService->approve($translation, $user);
                    $msg = 'Translation approved.';
                    break;

                case 'publish':
                    $this->translationService->publish($translation, $user);
                    $msg = 'Translation published and synchronized to live menu.';
                    break;

                case 'reject':
                    $reason = (string) $request->input('reason', 'Quality check failed.');
                    $this->translationService->reject($translation, $reason, $user);
                    $msg = 'Translation marked as rejected.';
                    break;

                case 'retranslate_ai':
                    $entity = $translation->translatable;
                    if ($entity) {
                        $this->translationService->translateModelField(
                            $entity,
                            $translation->field,
                            $translation->locale,
                            $translation->source_locale,
                            $user
                        );
                        $msg = 'AI translation generated.';
                    } else {
                        $msg = 'Parent entity missing.';
                    }
                    break;

                case 'apply_proposal':
                    // Apply pending AI proposal over existing text
                    $meta = $translation->metadata ?? [];
                    $proposalText = $meta['ai_proposal']['proposed_text'] ?? null;
                    if ($proposalText) {
                        $this->translationService->saveDraft($translation, $proposalText, $user, 'Applied AI proposed re-translation.');
                        unset($meta['ai_proposal']);
                        $translation->metadata = $meta;
                        $translation->needs_review = false;
                        $translation->save();
                        $msg = 'AI proposal applied into draft.';
                    } else {
                        $msg = 'No pending proposal found.';
                    }
                    break;

                default:
                    return back()->with('error', 'Unknown action.');
            }

            return redirect()->route('admin.translations.edit', $translation)->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function restore(Request $request, ContentTranslation $translation, int $historyId): RedirectResponse
    {
        $user = Auth::user();
        abort_unless((int) $user->vendor_id === (int) $translation->vendor_id, 403, 'Unauthorized.');

        try {
            $this->translationService->restoreVersion($translation, $historyId, $user);

            return redirect()->route('admin.translations.edit', $translation)
                ->with('success', "Restored version from history #{$historyId}.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function batchAiTranslate(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;
        abort_unless($vendor, 404, 'Vendor not found.');

        $targetLocale = $request->validate([
            'target_locale' => ['required', 'string', 'min:2', 'max:10'],
        ])['target_locale'];

        // Dispatch background queue job
        TranslateContentJob::dispatch(
            vendorId: $vendor->id,
            targetLocale: $targetLocale,
            sourceLocale: $vendor->getDefaultLanguageCode(),
            userId: $user->id
        );

        return redirect()->route('admin.translations.index', ['locale' => $targetLocale])
            ->with('success', "Batch AI translation job queued for {$targetLocale}. Items are being translated in the background.");
    }

    public function syncVendorLanguages(Request $request): RedirectResponse
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
            'active_locales' => ['nullable', 'array'],
            'active_locales.*' => ['string', 'exists:languages,code'],
            'default_locale' => ['required', 'string', 'exists:languages,code'],
            'new_code' => ['nullable', 'string', 'min:2', 'max:10', 'alpha_dash'],
            'new_name' => ['nullable', 'string', 'max:60'],
            'new_flag' => ['nullable', 'string', 'max:20'],
        ]);

        $activeCodes = (array) ($validated['active_locales'] ?? []);
        $defaultCode = $validated['default_locale'];

        // If a new custom language was submitted simultaneously
        if (! empty($validated['new_code']) && ! empty($validated['new_name'])) {
            $newCode = strtolower(trim($validated['new_code']));
            $newName = trim($validated['new_name']);
            $newFlag = trim($validated['new_flag'] ?? '') ?: '🌐';

            $vendor->syncLanguage($newCode, $newName, $newFlag, isActive: true);
            if (! in_array($newCode, $activeCodes, true)) {
                $activeCodes[] = $newCode;
            }
        }

        if (! in_array($defaultCode, $activeCodes, true)) {
            $activeCodes[] = $defaultCode;
        }

        $allSystemLangs = Language::whereIn('code', $activeCodes)->get()->keyBy('code');

        foreach ($activeCodes as $code) {
            $langModel = $allSystemLangs->get($code);
            if ($langModel) {
                VendorLanguage::updateOrCreate([
                    'vendor_id' => $vendor->id,
                    'language_id' => $langModel->id,
                ], [
                    'is_active' => true,
                    'is_default' => ($code === $defaultCode),
                ]);
            }
        }

        // Deactivate unselected
        VendorLanguage::where('vendor_id', $vendor->id)
            ->whereNotIn('language_id', $allSystemLangs->pluck('id'))
            ->update(['is_active' => false, 'is_default' => false]);

        $vendor->refreshSupportedLanguagesJson();
        $this->localeManager->clearCache();

        return redirect()->route('admin.translations.index')
            ->with('success', 'Restaurant language settings updated successfully.');
    }

    public function glossaryIndex(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;
        abort_unless($vendor, 404, 'Vendor not found.');

        $glossary = TranslationGlossary::where('vendor_id', $vendor->id)
            ->orderBy('term', 'asc')
            ->get();

        return view('admin.translations.glossary', compact('vendor', 'glossary'));
    }

    public function glossaryStore(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $vendor = $user->vendor;
        abort_unless($vendor, 404, 'Vendor not found.');

        $validated = $request->validate([
            'term' => ['required', 'string', 'max:120'],
            'translated_term' => ['nullable', 'string', 'max:120'],
            'target_locale' => ['nullable', 'string', 'max:10'],
            'is_verbatim' => ['nullable', 'boolean'],
            'is_forbidden' => ['nullable', 'boolean'],
            'forbidden_term' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['vendor_id'] = $vendor->id;
        $validated['is_verbatim'] = $request->boolean('is_verbatim');
        $validated['is_forbidden'] = $request->boolean('is_forbidden');
        $validated['is_active'] = true;

        TranslationGlossary::create($validated);

        return redirect()->route('admin.translations.glossary.index')
            ->with('success', "Glossary rule for '{$validated['term']}' added.");
    }

    public function glossaryDestroy(TranslationGlossary $glossary): RedirectResponse
    {
        $user = Auth::user();
        abort_unless((int) $user->vendor_id === (int) $glossary->vendor_id, 403, 'Unauthorized.');

        $glossary->delete();

        return redirect()->route('admin.translations.glossary.index')
            ->with('success', 'Glossary rule deleted.');
    }
}
