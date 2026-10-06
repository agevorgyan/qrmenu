<?php

namespace App\Http\Controllers;

use App\Models\ContentTranslation;
use App\Models\Language;
use App\Services\Localization\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LanguageController extends Controller
{
    public function __construct(
        protected LocaleManager $localeManager
    ) {}

    public function index(): View
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Unauthorized access.');

        try {
            $languages = Language::query()
                ->orderBy('is_default', 'desc')
                ->orderBy('sort_order', 'asc')
                ->orderBy('name', 'asc')
                ->get();
        } catch (\Throwable $e) {
            Log::error('LanguageController index Language query error: '.$e->getMessage());
            $languages = collect();
        }

        // Calculate translation coverage metrics per language
        $translationCounts = [];
        try {
            $translationCounts = ContentTranslation::withoutGlobalScopes()
                ->selectRaw('locale, count(*) as total, count(case when status = ? then 1 end) as published_count', [
                    ContentTranslation::STATUS_PUBLISHED,
                ])
                ->groupBy('locale')
                ->pluck('total', 'locale')
                ->all();
        } catch (\Throwable $e) {
            Log::warning('LanguageController index ContentTranslation query error: '.$e->getMessage());
        }

        $stats = [
            'total' => $languages->count(),
            'active' => $languages->where('is_active', true)->count(),
            'default' => $languages->firstWhere('is_default', true)?->name ?? 'English',
            'translations_count' => array_sum($translationCounts),
        ];

        return view('superadmin.languages.index', compact('languages', 'stats', 'translationCounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Unauthorized access.');

        try {
            $validated = $request->validate([
                'code' => ['required', 'string', 'min:2', 'max:10', 'alpha_dash'],
                'name' => ['required', 'string', 'max:60'],
                'native_name' => ['required', 'string', 'max:60'],
                'flag' => ['nullable', 'string', 'max:20'],
                'direction' => ['required', 'in:ltr,rtl'],
                'is_active' => ['nullable', 'boolean'],
            ]);

            $code = strtolower(trim($validated['code']));
            $existing = Language::where('code', $code)->first();

            if ($existing) {
                $existing->update([
                    'name' => $validated['name'],
                    'native_name' => $validated['native_name'],
                    'flag' => $validated['flag'] ?? $existing->flag,
                    'direction' => $validated['direction'],
                    'is_active' => $request->boolean('is_active', true),
                ]);
                $this->localeManager->clearCache();

                return redirect()->route('superadmin.languages.index')
                    ->with('success', "Language '{$existing->name}' ({$code}) updated and activated.");
            }

            $validated['code'] = $code;
            $validated['is_active'] = $request->boolean('is_active', true);
            $validated['is_default'] = false;
            $validated['sort_order'] = (Language::max('sort_order') ?? 0) + 1;

            Language::create($validated);
            $this->localeManager->clearCache();

            return redirect()->route('superadmin.languages.index')
                ->with('success', "Language '{$validated['name']}' ({$code}) successfully registered.");
        } catch (ValidationException $e) {
            return redirect()->route('superadmin.languages.index')
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Throwable $e) {
            Log::error('LanguageController store error: '.$e->getMessage());

            return redirect()->route('superadmin.languages.index')
                ->with('error', 'Failed to register language: '.$e->getMessage())
                ->withInput();
        }
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Unauthorized access.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'native_name' => ['required', 'string', 'max:60'],
            'flag' => ['nullable', 'string', 'max:10'],
            'direction' => ['required', 'in:ltr,rtl'],
        ]);

        $language->update($validated);
        $this->localeManager->clearCache();

        return redirect()->route('superadmin.languages.index')
            ->with('success', "Language '{$language->name}' updated successfully.");
    }

    public function toggleActive(Language $language): RedirectResponse
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Unauthorized access.');

        if ($language->is_default && $language->is_active) {
            return redirect()->route('superadmin.languages.index')
                ->with('error', 'The system default language cannot be deactivated.');
        }

        $language->is_active = ! $language->is_active;
        $language->save();

        $this->localeManager->clearCache();

        $status = $language->is_active ? 'activated' : 'deactivated';

        return redirect()->route('superadmin.languages.index')
            ->with('success', "Language '{$language->name}' is now {$status}.");
    }

    public function setDefault(Language $language): RedirectResponse
    {
        abort_unless(Auth::user()?->isSuperAdmin(), 403, 'Unauthorized access.');

        Language::query()->update(['is_default' => false]);

        $language->is_default = true;
        $language->is_active = true;
        $language->save();

        $this->localeManager->clearCache();

        return redirect()->route('superadmin.languages.index')
            ->with('success', "Language '{$language->name}' is now the system default.");
    }
}
