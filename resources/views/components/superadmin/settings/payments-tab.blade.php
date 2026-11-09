@props(['settings', 'paymentGatewaySettings'])

<div x-show="activeTab === 'payments'" x-cloak>
    <form action="{{ route('superadmin.settings.payments.update') }}" method="POST">
        @csrf

        <!-- Introduction Banner -->
        <div style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(16, 185, 129, 0.08)); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h4 style="font-family: 'Outfit'; font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-credit-card" style="color: var(--primary);"></i>
                    <span>{{ __('SaaS Platform Payment Gateways & Requisites') }}</span>
                </h4>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">
                    {{ __('Configure credentials for online subscription renewals and platform-level gateway processing. If a partner does not have dedicated credentials, platform gateways can also be used as fallback.') }}
                </p>
            </div>
            <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; font-size: 0.85rem; padding: 0.6rem 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem; box-shadow: 0 2px 8px var(--primary-glow);">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save All Payment Settings') }}
            </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 480px), 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- 1. Idram Wallet / QR -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.2rem;">🇦🇲</span>
                        <i class="fa-solid fa-wallet" style="color: #f97316;"></i>
                        <span>Idram Wallet & QR</span>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="payment_idram_enabled" value="1" {{ !empty($paymentGatewaySettings['idram']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Merchant ID (EDP_REC_ACCOUNT) *</label>
                        <input type="text" name="payment_idram_merchant_id" class="form-input" value="{{ old('payment_idram_merchant_id', $paymentGatewaySettings['idram']['merchant_id'] ?? '') }}" placeholder="e.g. 110000001">
                        <span class="input-hint">Ստացողի Իդրամ հաշվեհամար (EDP_REC_ACCOUNT)</span>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Secret Key (EDP_KEY / Գաղտնաբառ)</label>
                        <input type="password" name="payment_idram_secret_key" class="form-input" value="{{ old('payment_idram_secret_key', $paymentGatewaySettings['idram']['secret_key'] ?? '') }}" placeholder="••••••••••••••••">
                        <span class="input-hint">Օգտագործվում է callback հեշի (checksum) ստուգման համար</span>
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 0;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; color: var(--text-muted);">
                            <input type="checkbox" name="payment_idram_sandbox" value="1" {{ !empty($paymentGatewaySettings['idram']['sandbox']) ? 'checked' : '' }}>
                            <span>{{ __('Sandbox / Test Mode') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 2. Telcell Wallet -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.2rem;">🇦🇲</span>
                        <i class="fa-solid fa-mobile-screen-button" style="color: #eab308;"></i>
                        <span>Telcell Wallet</span>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="payment_telcell_enabled" value="1" {{ !empty($paymentGatewaySettings['telcell']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Shop ID *</label>
                        <input type="text" name="payment_telcell_shop_id" class="form-input" value="{{ old('payment_telcell_shop_id', $paymentGatewaySettings['telcell']['shop_id'] ?? '') }}" placeholder="e.g. 1001">
                        <span class="input-hint">Թելսել համակարգում գրանցված խանութի նույնականացուցիչ (Issuer)</span>
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Security Key (Անվտանգության Բանալի)</label>
                        <input type="password" name="payment_telcell_key" class="form-input" value="{{ old('payment_telcell_key', $paymentGatewaySettings['telcell']['key'] ?? '') }}" placeholder="••••••••••••••••">
                        <span class="input-hint">HMAC-SHA256 թվային ստորագրության գաղտնի բանալի</span>
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 0;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; color: var(--text-muted);">
                            <input type="checkbox" name="payment_telcell_sandbox" value="1" {{ !empty($paymentGatewaySettings['telcell']['sandbox']) ? 'checked' : '' }}>
                            <span>{{ __('Sandbox / Test Mode') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 3. FastShift -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.2rem;">🇦🇲</span>
                        <i class="fa-solid fa-bolt" style="color: #06b6d4;"></i>
                        <span>FastShift</span>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="payment_fastshift_enabled" value="1" {{ !empty($paymentGatewaySettings['fastshift']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Merchant ID *</label>
                        <input type="text" name="payment_fastshift_merchant_id" class="form-input" value="{{ old('payment_fastshift_merchant_id', $paymentGatewaySettings['fastshift']['merchant_id'] ?? '') }}" placeholder="e.g. FS-12345">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">API Key / Secret</label>
                        <input type="password" name="payment_fastshift_api_key" class="form-input" value="{{ old('payment_fastshift_api_key', $paymentGatewaySettings['fastshift']['api_key'] ?? '') }}" placeholder="••••••••••••••••">
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 0;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; color: var(--text-muted);">
                            <input type="checkbox" name="payment_fastshift_sandbox" value="1" {{ !empty($paymentGatewaySettings['fastshift']['sandbox']) ? 'checked' : '' }}>
                            <span>{{ __('Sandbox / Test Mode') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 4. ArCa / Ameriabank vPOS -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.2rem;">🇦🇲</span>
                        <i class="fa-solid fa-credit-card" style="color: #2563eb;"></i>
                        <span>ArCa / Ameriabank vPOS</span>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="payment_arca_enabled" value="1" {{ !empty($paymentGatewaySettings['arca']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Merchant ID</label>
                        <input type="text" name="payment_arca_merchant_id" class="form-input" value="{{ old('payment_arca_merchant_id', $paymentGatewaySettings['arca']['merchant_id'] ?? '') }}" placeholder="e.g. 12345678">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Terminal ID</label>
                        <input type="text" name="payment_arca_terminal_id" class="form-input" value="{{ old('payment_arca_terminal_id', $paymentGatewaySettings['arca']['terminal_id'] ?? '') }}" placeholder="e.g. 12345678">
                    </div>
                    <div class="form-group">
                        <label class="form-label">vPOS API Username *</label>
                        <input type="text" name="payment_arca_username" class="form-input" value="{{ old('payment_arca_username', $paymentGatewaySettings['arca']['username'] ?? '') }}" placeholder="e.g. 12345678_api">
                    </div>
                    <div class="form-group">
                        <label class="form-label">vPOS API Password *</label>
                        <input type="password" name="payment_arca_password" class="form-input" value="{{ old('payment_arca_password', $paymentGatewaySettings['arca']['password'] ?? '') }}" placeholder="••••••••••••••••">
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 0;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; color: var(--text-muted);">
                            <input type="checkbox" name="payment_arca_sandbox" value="1" {{ !empty($paymentGatewaySettings['arca']['sandbox']) ? 'checked' : '' }}>
                            <span>{{ __('Sandbox / Test Mode') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 5. Stripe -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.2rem;">🌐</span>
                        <i class="fa-brands fa-stripe" style="color: #6366f1; font-size: 1.4rem;"></i>
                        <span>Stripe (International Cards / Apple Pay)</span>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="payment_stripe_enabled" value="1" {{ !empty($paymentGatewaySettings['stripe']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Publishable Key *</label>
                        <input type="text" name="payment_stripe_publishable_key" class="form-input" value="{{ old('payment_stripe_publishable_key', $paymentGatewaySettings['stripe']['publishable_key'] ?? '') }}" placeholder="pk_live_... կամ pk_test_...">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Secret Key *</label>
                        <input type="password" name="payment_stripe_secret_key" class="form-input" value="{{ old('payment_stripe_secret_key', $paymentGatewaySettings['stripe']['secret_key'] ?? '') }}" placeholder="sk_live_... կամ sk_test_...">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">Webhook Secret (whsec_...)</label>
                        <input type="password" name="payment_stripe_webhook_secret" class="form-input" value="{{ old('payment_stripe_webhook_secret', $paymentGatewaySettings['stripe']['webhook_secret'] ?? '') }}" placeholder="whsec_...">
                    </div>
                    <div class="form-group full-width" style="margin-bottom: 0;">
                        <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.82rem; color: var(--text-muted);">
                            <input type="checkbox" name="payment_stripe_sandbox" value="1" {{ !empty($paymentGatewaySettings['stripe']['sandbox']) ? 'checked' : '' }}>
                            <span>{{ __('Sandbox / Test Mode') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 6. Bank Transfer Requisites -->
            <div class="settings-card" style="margin-bottom: 0;">
                <div class="settings-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <span style="font-size: 1.2rem;">🏛️</span>
                        <i class="fa-solid fa-building-columns" style="color: #10b981;"></i>
                        <span>{{ __('Bank Transfer & Invoice Requisites') }}</span>
                    </div>
                    <label style="display: inline-flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                        <input type="checkbox" name="payment_bank_transfer_enabled" value="1" {{ !empty($paymentGatewaySettings['bank_transfer']['enabled']) ? 'checked' : '' }} style="width: 18px; height: 18px; accent-color: var(--primary);">
                        <span>{{ __('Active') }}</span>
                    </label>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">{{ __('Beneficiary Company') }} *</label>
                        <input type="text" name="payment_bank_transfer_company_name" class="form-input" value="{{ old('payment_bank_transfer_company_name', $paymentGatewaySettings['bank_transfer']['company_name'] ?? '') }}" placeholder="«ԷԼԱԲ» ՍՊԸ">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('TIN / Tax ID (ՀՎՀՀ)') }} *</label>
                        <input type="text" name="payment_bank_transfer_tin" class="form-input" value="{{ old('payment_bank_transfer_tin', $paymentGatewaySettings['bank_transfer']['tin'] ?? '') }}" placeholder="02859273">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Bank Name') }} *</label>
                        <input type="text" name="payment_bank_transfer_bank_name" class="form-input" value="{{ old('payment_bank_transfer_bank_name', $paymentGatewaySettings['bank_transfer']['bank_name'] ?? '') }}" placeholder="«Ամերիաբանկ» ՓԲԸ">
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('Account Number (AMD) / IBAN') }} *</label>
                        <input type="text" name="payment_bank_transfer_account_number" class="form-input" value="{{ old('payment_bank_transfer_account_number', $paymentGatewaySettings['bank_transfer']['account_number'] ?? '') }}" placeholder="1570055555550100">
                    </div>
                    <div class="form-group full-width">
                        <label class="form-label">{{ __('Invoice Instructions / Notes') }}</label>
                        <textarea name="payment_bank_transfer_notes" rows="2" class="form-textarea" placeholder="Վճարման նպատակ՝ QR Menu բաժանորդագրություն (նշել հաշվի համարը)">{{ old('payment_bank_transfer_notes', $paymentGatewaySettings['bank_transfer']['notes'] ?? '') }}</textarea>
                        <span class="input-hint">Այս տեղեկությունը կտպագրվի բոլոր հաշիվ-ապրանքագրերի վրա</span>
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; padding: 1.5rem 0;">
            <button type="submit" class="btn btn-primary" style="border-radius: 12px; font-size: 0.95rem; font-weight: 700; padding: 0.75rem 2rem; box-shadow: 0 4px 14px var(--primary-glow);">
                <i class="fa-solid fa-floppy-disk"></i> {{ __('Save All Payment Settings') }}
            </button>
        </div>
    </form>
</div>

