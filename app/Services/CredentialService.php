<?php

namespace App\Services;

use App\Models\Vendor;
use App\Models\VendorCredential;
use Illuminate\Support\Facades\DB;

class CredentialService
{
    /**
     * Set or update a secure vendor credential using application encryption.
     * If the value changes, rotated_at is automatically updated.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function set(
        Vendor $vendor,
        string $provider,
        string $credentialType,
        ?string $value,
        array $metadata = []
    ): ?VendorCredential {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));
        $value = $value !== null ? trim($value) : null;

        if ($value === null || $value === '') {
            return null;
        }

        $result = DB::transaction(function () use ($vendor, $provider, $credentialType, $value, $metadata) {
            /** @var VendorCredential|null $existing */
            $existing = VendorCredential::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('provider', $provider)
                ->where('credential_type', $credentialType)
                ->first();

            if ($existing) {
                $oldValue = null;
                try {
                    $oldValue = $existing->encrypted_value;
                } catch (\Throwable) {
                    // In case of key mismatch or invalid payload, force update
                }

                $hasChanged = ($oldValue !== $value);

                $existing->update([
                    'encrypted_value' => $value, // automatically encrypted via model cast
                    'metadata' => array_merge($existing->metadata ?? [], $metadata),
                    'rotated_at' => $hasChanged ? now() : $existing->rotated_at,
                ]);

                return $existing->fresh();
            }

            return VendorCredential::create([
                'vendor_id' => $vendor->id,
                'provider' => $provider,
                'credential_type' => $credentialType,
                'encrypted_value' => $value, // automatically encrypted via model cast
                'metadata' => $metadata,
                'rotated_at' => null,
            ]);
        });

        if ($result) {
            $vendor->unsetRelation('credentials');
            app(SecurityAuditService::class)->logCredentialChange(
                vendor: $vendor,
                provider: $provider,
                credentialType: $credentialType,
                actionType: $result->wasRecentlyCreated ? 'created' : 'updated',
                actor: auth()->user()
            );
        }

        return $result;
    }

    /**
     * Resolve credential record using eager-loaded or memoized vendor credentials collection.
     */
    protected function findCredentialRecord(Vendor $vendor, string $provider, string $credentialType): ?VendorCredential
    {
        if (! $vendor->relationLoaded('credentials')) {
            $vendor->setRelation(
                'credentials',
                VendorCredential::withoutGlobalScopes()
                    ->where('vendor_id', $vendor->id)
                    ->get()
            );
        }

        return $vendor->credentials->first(function (VendorCredential $cred) use ($provider, $credentialType) {
            return $cred->provider === $provider && $cred->credential_type === $credentialType;
        });
    }

    /**
     * Retrieve the decrypted credential value for internal server operations.
     * Falls back to legacy settings columns if not yet migrated.
     */
    public function get(
        Vendor $vendor,
        string $provider,
        string $credentialType,
        ?string $default = null
    ): ?string {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));

        $record = $this->findCredentialRecord($vendor, $provider, $credentialType);

        if ($record && ! empty($record->encrypted_value)) {
            return $record->encrypted_value;
        }

        // Backward compatibility fallback to legacy vendor settings
        return $this->getLegacyFallback($vendor, $provider, $credentialType) ?? $default;
    }

    /**
     * Check if a credential is configured (either in vendor_credentials or legacy).
     */
    public function has(Vendor $vendor, string $provider, string $credentialType): bool
    {
        $val = $this->get($vendor, $provider, $credentialType);

        return ! empty($val);
    }

    /**
     * Get a masked representation of the credential (e.g. sk_test_••••••••a1b2).
     * Never returns the secret itself.
     */
    public function mask(Vendor $vendor, string $provider, string $credentialType): ?string
    {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));

        $record = $this->findCredentialRecord($vendor, $provider, $credentialType);

        if ($record) {
            return $record->maskedValue();
        }

        $legacy = $this->getLegacyFallback($vendor, $provider, $credentialType);
        if (! empty($legacy)) {
            $len = strlen($legacy);
            if ($len <= 8) {
                return '••••••••';
            }
            $prefix = substr($legacy, 0, 4);
            $suffix = substr($legacy, -4);

            return $prefix.'••••••••'.$suffix;
        }

        return null;
    }

    /**
     * Explicitly rotate a credential with a new value.
     */
    public function rotate(
        Vendor $vendor,
        string $provider,
        string $credentialType,
        string $newValue,
        array $metadata = []
    ): VendorCredential {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));
        $newValue = trim($newValue);

        return DB::transaction(function () use ($vendor, $provider, $credentialType, $newValue, $metadata) {
            /** @var VendorCredential|null $existing */
            $existing = VendorCredential::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('provider', $provider)
                ->where('credential_type', $credentialType)
                ->first();

            if ($existing) {
                $existing->update([
                    'encrypted_value' => $newValue,
                    'metadata' => array_merge($existing->metadata ?? [], $metadata),
                    'rotated_at' => now(),
                ]);

                $vendor->unsetRelation('credentials');

                return $existing->fresh();
            }

            $created = VendorCredential::create([
                'vendor_id' => $vendor->id,
                'provider' => $provider,
                'credential_type' => $credentialType,
                'encrypted_value' => $newValue,
                'metadata' => $metadata,
                'rotated_at' => now(),
            ]);

            $vendor->unsetRelation('credentials');

            return $created;
        });
    }

    /**
     * Delete a credential and scrub legacy locations.
     */
    public function delete(Vendor $vendor, string $provider, string $credentialType): bool
    {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));

        DB::transaction(function () use ($vendor, $provider, $credentialType) {
            VendorCredential::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('provider', $provider)
                ->where('credential_type', $credentialType)
                ->delete();

            // Also scrub from legacy vendor JSON columns if present
            $this->scrubLegacyCredential($vendor, $provider, $credentialType);
        });

        $vendor->unsetRelation('credentials');

        app(SecurityAuditService::class)->logCredentialChange(
            vendor: $vendor,
            provider: $provider,
            credentialType: $credentialType,
            actionType: 'deleted',
            actor: auth()->user()
        );

        return true;
    }

    /**
     * Mark a credential as successfully verified by an upstream API.
     */
    public function markVerified(Vendor $vendor, string $provider, string $credentialType): void
    {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));

        VendorCredential::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('provider', $provider)
            ->where('credential_type', $credentialType)
            ->update(['last_verified_at' => now()]);

        $vendor->unsetRelation('credentials');
    }

    /**
     * Get safe status for frontend or public display.
     *
     * @return array{configured: bool, provider: string, credential_type: string, masked: ?string, last_verified_at: ?string, rotated_at: ?string}
     */
    public function getStatus(Vendor $vendor, string $provider, string $credentialType): array
    {
        $provider = strtolower(trim($provider));
        $credentialType = strtolower(trim($credentialType));

        $record = $this->findCredentialRecord($vendor, $provider, $credentialType);

        $isConfigured = $record !== null || $this->hasLegacyFallback($vendor, $provider, $credentialType);
        $masked = $record ? $record->maskedValue() : $this->mask($vendor, $provider, $credentialType);

        return [
            'configured' => $isConfigured,
            'provider' => $provider,
            'credential_type' => $credentialType,
            'masked' => $masked,
            'last_verified_at' => $record?->last_verified_at?->toISOString(),
            'rotated_at' => $record?->rotated_at?->toISOString(),
        ];
    }

    /**
     * Redact sensitive secrets and tokens from strings (logs, error messages, URLs).
     */
    public function redactString(string $text, array $additionalSecrets = []): string
    {
        // 1. Redact Telegram Bot tokens: bot<123456:ABC-DEF...>
        $text = preg_replace('/bot(\d{5,}:[A-Za-z0-9_-]{20,})/i', 'bot[REDACTED_TELEGRAM_TOKEN]', $text);

        // 2. Redact Stripe API keys: sk_live_..., sk_test_..., rk_live_...
        $text = preg_replace('/(sk|rk)_(live|test)_[0-9a-zA-Z]{16,}/', '$1_$2_[REDACTED_STRIPE_KEY]', $text);

        // 3. Redact URL query parameters: ?key=..., &key=..., ?api_key=..., &secret=...
        $text = preg_replace('/([?&](?:key|api_key|secret|secret_key|token|bot_token)=)[^&\\s]+/i', '$1[REDACTED]', $text);

        // 4. Redact additional known vendor secrets passed in
        foreach ($additionalSecrets as $secret) {
            if (! empty($secret) && is_string($secret) && strlen($secret) >= 4) {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        return $text;
    }

    /**
     * Retrieve fallback value from legacy vendor JSON or model attributes.
     */
    protected function getLegacyFallback(Vendor $vendor, string $provider, string $credentialType): ?string
    {
        return match ($provider) {
            'ai' => $vendor->ai_settings['api_key'] ?? null,
            'telegram' => $vendor->telegram_settings['bot_token'] ?? null,
            'sms' => $vendor->crm_settings['sms_api_key'] ?? null,
            'wifi' => $vendor->wifi_password ?? null,
            'stripe' => match ($credentialType) {
                'secret_key' => $vendor->payment_settings['gateways']['stripe']['secret_key'] ?? null,
                'publishable_key' => $vendor->payment_settings['gateways']['stripe']['publishable_key'] ?? null,
                default => null,
            },
            'idram' => match ($credentialType) {
                'secret_key' => $vendor->payment_settings['gateways']['idram']['secret_key'] ?? null,
                default => null,
            },
            'telcell' => match ($credentialType) {
                'key', 'security_key' => $vendor->payment_settings['gateways']['telcell']['key'] ?? null,
                default => null,
            },
            'fastshift' => match ($credentialType) {
                'api_key', 'secret_key' => $vendor->payment_settings['gateways']['fastshift']['api_key'] ?? null,
                default => null,
            },
            'arca' => match ($credentialType) {
                'secret_key', 'password' => $vendor->payment_settings['gateways']['arca']['secret_key'] ?? null,
                default => null,
            },
            default => null,
        };
    }

    /**
     * Check if a legacy setting contains a value.
     */
    protected function hasLegacyFallback(Vendor $vendor, string $provider, string $credentialType): bool
    {
        $val = $this->getLegacyFallback($vendor, $provider, $credentialType);

        return ! empty($val);
    }

    /**
     * Remove secret from legacy vendor JSON columns when deleted.
     */
    protected function scrubLegacyCredential(Vendor $vendor, string $provider, string $credentialType): void
    {
        $updates = [];

        if ($provider === 'ai' && $credentialType === 'api_key') {
            $ai = $vendor->ai_settings ?? [];
            if (isset($ai['api_key'])) {
                unset($ai['api_key']);
                $updates['ai_settings'] = $ai;
            }
        } elseif ($provider === 'telegram' && $credentialType === 'bot_token') {
            $tg = $vendor->telegram_settings ?? [];
            if (isset($tg['bot_token'])) {
                unset($tg['bot_token']);
                $updates['telegram_settings'] = $tg;
            }
        } elseif ($provider === 'sms' && $credentialType === 'api_key') {
            $crm = $vendor->crm_settings ?? [];
            if (isset($crm['sms_api_key'])) {
                unset($crm['sms_api_key']);
                $updates['crm_settings'] = $crm;
            }
        } elseif ($provider === 'wifi' && $credentialType === 'password') {
            $updates['wifi_password'] = null;
        } elseif (in_array($provider, ['stripe', 'idram', 'telcell', 'fastshift', 'arca'])) {
            $payments = $vendor->payment_settings ?? [];
            if (isset($payments['gateways'][$provider])) {
                $targetKey = match ($credentialType) {
                    'key' => 'key',
                    'api_key' => 'api_key',
                    'secret_key' => 'secret_key',
                    default => $credentialType,
                };
                unset($payments['gateways'][$provider][$targetKey]);
                $updates['payment_settings'] = $payments;
            }
        }

        if (! empty($updates)) {
            $vendor->update($updates);
        }
    }
}
