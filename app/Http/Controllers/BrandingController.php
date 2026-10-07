<?php

namespace App\Http\Controllers;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\MenuTemplate;
use App\Services\Security\CssSanitizer;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BrandingController extends Controller
{
    public function index()
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('viewSettings', $vendor);

        $templates = MenuTemplate::where('is_active', true)->get();

        return view('admin.branding.index', compact('vendor', 'templates'));
    }

    public function update(Request $request, StorageService $storageService)
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageSettings', $vendor);

        $imageRule = extension_loaded('fileinfo')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp,svg', 'max:5120']
            : ['nullable', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if ($value instanceof UploadedFile) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['jpeg', 'jpg', 'png', 'gif', 'webp', 'svg'])) {
                        $fail('The '.$attribute.' must be a valid image file (jpeg, png, jpg, gif, webp, svg).');
                    }
                }
            }];

        $validated = $request->validate([
            'menu_template_id' => 'required|exists:menu_templates,id',
            'primary_color' => 'required|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'accent_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'bg_color' => 'nullable|string|max:20',
            'theme_mode' => 'required|string|in:dark,light',
            'desktop_max_width' => 'nullable|string|max:20',
            'logo' => 'nullable|string',
            'logo_file' => $imageRule,
            'cover_image' => 'nullable|string',
            'cover_file' => $imageRule,
            'custom_css' => 'nullable|string|max:10000',
        ]);

        if (array_key_exists('custom_css', $validated) && $validated['custom_css'] !== null) {
            $validated['custom_css'] = app(CssSanitizer::class)->sanitize($validated['custom_css']);
        }

        if (! empty($validated['logo'])) {
            $parsedLogo = parse_url($validated['logo'], PHP_URL_PATH);
            if ($parsedLogo && str_starts_with($parsedLogo, '/storage/')) {
                $validated['logo'] = $parsedLogo;
            }
        }

        if (! empty($validated['cover_image'])) {
            $parsedCover = parse_url($validated['cover_image'], PHP_URL_PATH);
            if ($parsedCover && str_starts_with($parsedCover, '/storage/')) {
                $validated['cover_image'] = $parsedCover;
            }
        }

        if ($request->hasFile('logo_file') && $request->file('logo_file')->isValid()) {
            try {
                $storageFile = $storageService->replace(
                    oldPathOrUuid: $vendor->logo,
                    newFile: $request->file('logo_file'),
                    namespace: 'branding',
                    vendor: $vendor,
                    entity: $vendor
                );
                $validated['logo'] = $storageFile->getUrl();
            } catch (StorageQuotaExceededException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                Log::error('Branding logo upload failed: '.$e->getMessage(), ['exception' => $e]);

                return back()->withInput()->with('error', 'Logo upload failed: '.$e->getMessage());
            }
        }

        if ($request->hasFile('cover_file') && $request->file('cover_file')->isValid()) {
            try {
                $storageFile = $storageService->replace(
                    oldPathOrUuid: $vendor->cover_image,
                    newFile: $request->file('cover_file'),
                    namespace: 'branding',
                    vendor: $vendor,
                    entity: $vendor
                );
                $validated['cover_image'] = $storageFile->getUrl();
            } catch (StorageQuotaExceededException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            } catch (\Throwable $e) {
                Log::error('Branding cover upload failed: '.$e->getMessage(), ['exception' => $e]);

                return back()->withInput()->with('error', 'Cover image upload failed: '.$e->getMessage());
            }
        }

        if (! $request->hasFile('logo_file') && array_key_exists('logo', $validated)) {
            if ($validated['logo'] !== $vendor->logo && ! empty($vendor->logo)) {
                try {
                    $cleanOld = $storageService->cleanPath($vendor->logo);
                    if ($storageService->isVendorScopedPath($cleanOld, $vendor)) {
                        $storageService->delete($cleanOld, $vendor);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete old logo upon url change: '.$e->getMessage());
                }
            }
        }

        if (! $request->hasFile('cover_file') && array_key_exists('cover_image', $validated)) {
            if ($validated['cover_image'] !== $vendor->cover_image && ! empty($vendor->cover_image)) {
                try {
                    $cleanOld = $storageService->cleanPath($vendor->cover_image);
                    if ($storageService->isVendorScopedPath($cleanOld, $vendor)) {
                        $storageService->delete($cleanOld, $vendor);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to delete old cover upon url change: '.$e->getMessage());
                }
            }
        }

        unset($validated['logo_file'], $validated['cover_file']);

        $vendor->update($validated);

        return back()->with('success', 'Storefront branding and theme successfully saved!');
    }
}
