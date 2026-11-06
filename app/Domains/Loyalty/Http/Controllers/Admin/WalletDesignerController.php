<?php

namespace App\Domains\Loyalty\Http\Controllers\Admin;

use App\Domains\Loyalty\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Security\Permission;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WalletDesignerController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
        protected StorageService $storageService,
    ) {}

    /**
     * Show the visual card designer for Apple & Google Wallet passes.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $vendor = $user->vendor;
        $design = $this->walletService->getOrCreateDesign($vendor);

        return view('admin.loyalty.designer', compact('vendor', 'design'));
    }

    /**
     * Update pass design parameters.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);
        $vendor = $user->vendor;

        $request->validate([
            'card_title' => 'required|string|max:100',
            'background_color' => 'required|string|max:20',
            'foreground_color' => 'required|string|max:20',
            'label_color' => 'required|string|max:20',
            'header_field' => 'required|string|max:50',
            'primary_field' => 'required|string|max:50',
            'barcode_type' => 'required|string|max:30',
            'logo' => 'nullable|image|max:2048',
            'strip_image' => 'nullable|image|max:4096',
            'icon' => 'nullable|image|max:1024',
        ]);

        $design = $this->walletService->getOrCreateDesign($vendor);

        $data = [
            'card_title' => $request->input('card_title'),
            'background_color' => $request->input('background_color'),
            'foreground_color' => $request->input('foreground_color'),
            'label_color' => $request->input('label_color'),
            'header_field' => $request->input('header_field'),
            'primary_field' => $request->input('primary_field'),
            'barcode_type' => $request->input('barcode_type'),
            'version' => $design->version + 1,
        ];

        // Handle uploaded assets using StorageService if present
        if ($request->hasFile('logo')) {
            $file = $this->storageService->storeFile($vendor, $request->file('logo'), 'branding');
            $data['logo_url'] = $file->url;
        }

        if ($request->hasFile('strip_image')) {
            $file = $this->storageService->storeFile($vendor, $request->file('strip_image'), 'branding');
            $data['strip_image_url'] = $file->url;
        }

        if ($request->hasFile('icon')) {
            $file = $this->storageService->storeFile($vendor, $request->file('icon'), 'branding');
            $data['icon_url'] = $file->url;
        }

        $design->update($data);

        return redirect()->route('admin.loyalty.designer')
            ->with('success', 'Wallet pass design updated and published (v'.$design->version.').');
    }

    /**
     * Preview API endpoint returning the compiled design for dynamic iframe / preview renderers.
     */
    public function previewData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $this->authorizeAccess($user);

        $design = $this->walletService->getOrCreateDesign($user->vendor);

        return response()->json([
            'vendor_name' => $user->vendor->name,
            'card_title' => $design->card_title,
            'background_color' => $design->background_color,
            'foreground_color' => $design->foreground_color,
            'label_color' => $design->label_color,
            'logo_url' => $design->logo_url ?: $user->vendor->logo,
            'strip_image_url' => $design->strip_image_url,
            'barcode_type' => $design->barcode_type,
            'points_sample' => 1250,
            'tier_sample' => 'Gold Member',
            'member_code_sample' => 'MEM-00004921',
        ]);
    }

    protected function authorizeAccess($user): void
    {
        if (! $user) {
            abort(401);
        }
        if ($user->role === 'superadmin' || $user->role === 'owner' || $user->role === 'vendor_owner') {
            return;
        }
        if ($user->hasPermission(Permission::LOYALTY_MANAGE)) {
            return;
        }
        abort(403);
    }
}
