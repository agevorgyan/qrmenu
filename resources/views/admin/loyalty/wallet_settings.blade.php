@extends('layouts.app')

@section('content')
<div class="content-wrapper">

    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-wallet" style="color: #6366f1;"></i>
                <span>{{ __('Digital Wallet Integration & Credentials') }}</span>
            </h1>
            <p style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                {{ __('Manage Apple Developer certificates (PassKit PKCS#7) and Google Wallet API service credentials.') }}
            </p>
        </div>
        <div>
            <a href="{{ route('admin.loyalty.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Overview') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); color: #34d399; font-size: 0.875rem; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-circle-check mr-1"></i> {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.loyalty.wallet_settings.update') }}" enctype="multipart/form-data">
        @csrf

        <div style="display: flex; flex-direction: column; gap: 2rem;">
            
            <!-- Issuer Mode Selection Card -->
            <div class="card" style="padding: 1.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin-bottom: 1rem;">
                    {{ __('Pass Issuance Mode') }}
                </h3>
                <p style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 1.25rem;">
                    {{ __('Choose whether your restaurant passes use the shared SaaS platform certificates or your own branded Apple/Google developer accounts.') }}
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <label style="border: 2px solid {{ $issuer->issuer_mode->value === 'platform' ? '#6366f1' : 'var(--border-color)' }}; background: var(--bg-main); border-radius: 14px; padding: 1.25rem; cursor: pointer; display: flex; gap: 1rem; align-items: flex-start;">
                        <input type="radio" name="issuer_mode" value="platform" {{ $issuer->issuer_mode->value === 'platform' ? 'checked' : '' }} style="margin-top: 0.25rem;">
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                {{ __('Platform Managed (Default)') }}
                            </div>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                                {{ __('Fastest setup. Passes are signed with verified platform certificates and work instantly out of the box.') }}
                            </p>
                        </div>
                    </label>

                    <label style="border: 2px solid {{ $issuer->issuer_mode->value === 'custom' ? '#6366f1' : 'var(--border-color)' }}; background: var(--bg-main); border-radius: 14px; padding: 1.25rem; cursor: pointer; display: flex; gap: 1rem; align-items: flex-start;">
                        <input type="radio" name="issuer_mode" value="custom" {{ $issuer->issuer_mode->value === 'custom' ? 'checked' : '' }} style="margin-top: 0.25rem;">
                        <div>
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                                {{ __('Custom Enterprise Accounts') }}
                            </div>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.25rem 0 0 0;">
                                {{ __('Use your own Apple Developer Pass Type ID and Google Pay Business Console Issuer credentials for 100% white-label passes.') }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

                <!-- Apple PassKit Card -->
                <div class="card" style="padding: 1.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="width: 40px; height: 40px; border-radius: 10px; background: #000000; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                                <i class="fa-brands fa-apple"></i>
                            </span>
                            <div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0;">Apple Wallet (PassKit)</h3>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Cryptographic .pkpass PKCS#7 signing</span>
                            </div>
                        </div>

                        <div>
                            @if($issuer->apple_status === 'active')
                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 20px;">
                                    <i class="fa-solid fa-shield-check mr-1"></i> Active
                                </span>
                            @else
                                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 20px;">
                                    <i class="fa-solid fa-clock mr-1"></i> Pending Setup
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($issuer->apple_cert_expires_at)
                        <div style="padding: 0.75rem 1rem; border-radius: 10px; background: var(--bg-main); border: 1px solid var(--border-color); font-size: 0.8rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between;">
                            <span style="color: var(--text-muted);">Pass Certificate Validity:</span>
                            <span style="font-weight: 700; color: #10b981;">
                                Expires {{ $issuer->apple_cert_expires_at->format('M d, Y') }}
                            </span>
                        </div>
                    @endif

                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        <div>
                            <label class="form-label">{{ __('Pass Type Identifier') }}</label>
                            <input type="text" name="apple_pass_type_id" value="{{ old('apple_pass_type_id', $issuer->apple_pass_type_id) }}" class="form-control" placeholder="pass.com.yourrestaurant.loyalty">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Registered in your Apple Developer account.</small>
                        </div>

                        <div>
                            <label class="form-label">{{ __('Apple Team ID') }}</label>
                            <input type="text" name="apple_team_id" value="{{ old('apple_team_id', $issuer->apple_team_id) }}" class="form-control" placeholder="e.g. 9JA6ND4A28">
                        </div>

                        <div style="background: var(--bg-main); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                            <span style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
                                <i class="fa-solid fa-key mr-1 text-primary"></i> Upload Certificates (Encrypted at Rest)
                            </span>

                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem;">Pass Signing Certificate (.pem or .cer)</label>
                                    <input type="file" name="apple_cert_file" class="form-control form-control-sm" accept=".pem,.cer,.crt">
                                </div>
                                <div>
                                    <label class="form-label" style="font-size: 0.75rem;">Private Key (.pem or .key)</label>
                                    <input type="file" name="apple_key_file" class="form-control form-control-sm" accept=".pem,.key">
                                </div>
                            </div>
                        </div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                            <i class="fa-solid fa-lock mr-1" style="color: #10b981;"></i>
                            Private keys are AES-256 encrypted before insertion and never exposed in web responses or logs.
                        </div>
                    </div>
                </div>

                <!-- Google Wallet Card -->
                <div class="card" style="padding: 1.75rem; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <span style="width: 40px; height: 40px; border-radius: 10px; background: #ffffff; color: #4285f4; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; border: 1px solid var(--border-color);">
                                <i class="fa-brands fa-google"></i>
                            </span>
                            <div>
                                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-main); margin: 0;">Google Wallet</h3>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">Google Pay API RS256 Signed JWT Passes</span>
                            </div>
                        </div>

                        <div>
                            @if($issuer->google_status === 'active')
                                <span class="badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 20px;">
                                    <i class="fa-solid fa-shield-check mr-1"></i> Active
                                </span>
                            @else
                                <span class="badge" style="background: rgba(245, 158, 11, 0.15); color: #fbbf24; font-weight: 700; padding: 0.35rem 0.75rem; border-radius: 20px;">
                                    <i class="fa-solid fa-clock mr-1"></i> Pending Setup
                                </span>
                            @endif
                        </div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                        <div>
                            <label class="form-label">{{ __('Google Pay Issuer ID') }}</label>
                            <input type="text" name="google_issuer_id" value="{{ old('google_issuer_id', $issuer->google_issuer_id) }}" class="form-control" placeholder="e.g. 3388000000022345678">
                            <small style="color: var(--text-muted); font-size: 0.75rem;">Issued in Google Pay & Wallet Business Console.</small>
                        </div>

                        <div style="background: var(--bg-main); padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color);">
                            <span style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.75rem;">
                                <i class="fa-solid fa-file-code mr-1 text-primary"></i> Service Account Key Credentials (JSON)
                            </span>

                            <div>
                                <label class="form-label" style="font-size: 0.75rem;">Google Cloud IAM Service Account JSON File</label>
                                <input type="file" name="google_service_account_file" class="form-control form-control-sm" accept=".json">
                                <small style="color: var(--text-muted); font-size: 0.75rem; display: block; margin-top: 0.25rem;">Must have Wallet API Developer / Service Account role.</small>
                            </div>
                        </div>

                        <div style="padding: 1rem; border-radius: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.2); font-size: 0.8rem; color: #60a5fa;">
                            <i class="fa-solid fa-circle-info mr-1"></i>
                            {{ __('Once configured, customers can tap the official "Add to Google Wallet" button in the customer storefront to save passes instantly without installing any app.') }}
                        </div>
                    </div>
                </div>

            </div>

            <!-- Submit Button Bar -->
            <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 1rem; font-weight: 700;">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> {{ __('Save Credentials & Settings') }}
                </button>
            </div>

        </div>
    </form>

</div>
@endsection

