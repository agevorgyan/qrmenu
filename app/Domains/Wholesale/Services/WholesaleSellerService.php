<?php

namespace App\Domains\Wholesale\Services;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Domains\Wholesale\Models\WholesaleSeller;
use App\Domains\Wholesale\Models\WholesaleSellerBankAccount;
use App\Domains\Wholesale\Models\WholesaleSellerLocation;
use App\Domains\Wholesale\Models\WholesaleSellerTaxProfile;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WholesaleSellerService
{
    /**
     * Get all sellers for a vendor.
     *
     * @return Collection<int, WholesaleSeller>
     */
    public function listSellers(Vendor $vendor, bool $activeOnly = false): Collection
    {
        $query = WholesaleSeller::where('vendor_id', $vendor->id)
            ->with(['activeTaxProfile', 'defaultBankAccount', 'defaultLocation'])
            ->orderBy('is_active', 'desc')
            ->orderBy('name', 'asc');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Create a new legal seller entity with its initial tax profile, default location, and bank account.
     *
     * @param array{
     *     name: string,
     *     legal_name: string,
     *     tin: string,
     *     vat_number?: string|null,
     *     tax_regime?: string|SellerTaxRegime,
     *     address: string,
     *     default_supply_location?: string|null,
     *     pek_username?: string|null,
     *     phone?: string|null,
     *     email?: string|null,
     *     is_active?: bool,
     *     bank_name?: string|null,
     *     account_number?: string|null,
     *     iban?: string|null,
     *     currency?: string,
     *     warehouse_id?: int|null
     * } $data
     */
    public function createSeller(Vendor $vendor, array $data): WholesaleSeller
    {
        $tin = preg_replace('/\D/', '', (string) ($data['tin'] ?? ''));
        if (strlen($tin) !== 8) {
            throw new InvalidArgumentException(__('Armenian Taxpayer Identification Number (TIN / ՀՎՀՀ) must be exactly 8 numeric digits.'));
        }

        $regime = $data['tax_regime'] ?? SellerTaxRegime::VAT_PAYER;
        if (is_string($regime)) {
            $regime = SellerTaxRegime::from($regime);
        }

        return DB::transaction(function () use ($vendor, $data, $tin, $regime) {
            $seller = WholesaleSeller::create([
                'vendor_id' => $vendor->id,
                'name' => trim($data['name']),
                'legal_name' => trim($data['legal_name']),
                'tin' => $tin,
                'vat_number' => ! empty($data['vat_number']) ? trim($data['vat_number']) : ($regime->isVatPayer() ? "{$tin}/1" : null),
                'tax_regime' => $regime,
                'address' => trim($data['address']),
                'default_supply_location' => ! empty($data['default_supply_location']) ? trim($data['default_supply_location']) : trim($data['address']),
                'pek_username' => ! empty($data['pek_username']) ? trim($data['pek_username']) : null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Initial effective tax profile
            WholesaleSellerTaxProfile::create([
                'seller_id' => $seller->id,
                'tax_regime' => $regime,
                'is_vat_payer' => $regime->isVatPayer(),
                'vat_rate' => $regime->isVatPayer() ? 20.00 : 0.00,
                'effective_from' => Carbon::today()->toDateString(),
                'document_series' => $regime->defaultDocumentSeries(),
            ]);

            // Initial default location
            WholesaleSellerLocation::create([
                'seller_id' => $seller->id,
                'name' => __('Main Office / Supply Facility'),
                'address' => $seller->default_supply_location ?: $seller->address,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'is_default' => true,
            ]);

            // Initial default bank account if provided
            if (! empty($data['bank_name']) && ! empty($data['account_number'])) {
                $bankAccount = WholesaleSellerBankAccount::create([
                    'seller_id' => $seller->id,
                    'bank_name' => trim($data['bank_name']),
                    'account_number' => preg_replace('/\s+/', '', (string) $data['account_number']),
                    'iban' => ! empty($data['iban']) ? trim($data['iban']) : null,
                    'currency' => $data['currency'] ?? 'AMD',
                    'is_default' => true,
                ]);

                $seller->update(['default_bank_account_id' => $bankAccount->id]);
            }

            return $seller->fresh(['activeTaxProfile', 'defaultBankAccount', 'locations']);
        });
    }

    /**
     * Update an existing seller company.
     */
    public function updateSeller(WholesaleSeller $seller, array $data): WholesaleSeller
    {
        if (isset($data['tin'])) {
            $tin = preg_replace('/\D/', '', (string) $data['tin']);
            if (strlen($tin) !== 8) {
                throw new InvalidArgumentException(__('Armenian Taxpayer Identification Number (TIN / ՀՎՀՀ) must be exactly 8 numeric digits.'));
            }
            $data['tin'] = $tin;
        }

        $regime = null;
        if (isset($data['tax_regime'])) {
            $regime = is_string($data['tax_regime']) ? SellerTaxRegime::from($data['tax_regime']) : $data['tax_regime'];
            $data['tax_regime'] = $regime;
        }

        DB::transaction(function () use ($seller, $data, $regime) {
            $oldRegime = $seller->tax_regime;
            $seller->update($data);

            // If regime changed, create an updated tax profile starting today
            if ($regime !== null && $oldRegime !== $regime) {
                $today = Carbon::today()->toDateString();
                $yesterday = Carbon::yesterday()->toDateString();

                $seller->taxProfiles()
                    ->whereNull('effective_to')
                    ->update(['effective_to' => $yesterday]);

                WholesaleSellerTaxProfile::create([
                    'seller_id' => $seller->id,
                    'tax_regime' => $regime,
                    'is_vat_payer' => $regime->isVatPayer(),
                    'vat_rate' => $regime->isVatPayer() ? 20.00 : 0.00,
                    'effective_from' => $today,
                    'document_series' => $regime->defaultDocumentSeries(),
                ]);
            }
        });

        return $seller->fresh(['activeTaxProfile', 'defaultBankAccount', 'locations']);
    }

    /**
     * Add a bank account to a seller.
     */
    public function addBankAccount(WholesaleSeller $seller, array $data): WholesaleSellerBankAccount
    {
        return DB::transaction(function () use ($seller, $data) {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $seller->bankAccounts()->update(['is_default' => false]);
            }

            $bankAccount = WholesaleSellerBankAccount::create([
                'seller_id' => $seller->id,
                'bank_name' => trim($data['bank_name']),
                'account_number' => preg_replace('/\s+/', '', (string) $data['account_number']),
                'iban' => ! empty($data['iban']) ? trim($data['iban']) : null,
                'currency' => $data['currency'] ?? 'AMD',
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'is_default' => $isDefault || $seller->bankAccounts()->count() === 0,
            ]);

            if ($bankAccount->is_default) {
                $seller->update(['default_bank_account_id' => $bankAccount->id]);
            }

            return $bankAccount;
        });
    }

    /**
     * Add a supply location to a seller.
     */
    public function addLocation(WholesaleSeller $seller, array $data): WholesaleSellerLocation
    {
        return DB::transaction(function () use ($seller, $data) {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $seller->locations()->update(['is_default' => false]);
            }

            return WholesaleSellerLocation::create([
                'seller_id' => $seller->id,
                'name' => trim($data['name']),
                'address' => trim($data['address']),
                'city' => $data['city'] ?? null,
                'region' => $data['region'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'is_default' => $isDefault || $seller->locations()->count() === 0,
            ]);
        });
    }
}

