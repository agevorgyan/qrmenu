<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AmeriaBank vPOS / ArCa Sandbox Gateway - {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .payment-card-shell {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.1);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(12px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .payment-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 1.75rem 2rem;
            position: relative;
        }

        .sandbox-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(245, 158, 11, 0.25);
            border: 1px solid rgba(245, 158, 11, 0.5);
            color: #fef3c7;
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
        }

        .payment-provider-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-provider-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-amount-box {
            margin-top: 1.25rem;
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            padding: 0.85rem 1.15rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-amount-label {
            font-size: 0.78rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .payment-amount-value {
            font-family: 'Outfit', sans-serif;
            font-size: 1.5rem;
            font-weight: 900;
            color: #ffffff;
        }

        .payment-body {
            padding: 1.75rem 2rem 2rem 2rem;
        }

        .order-meta-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.82rem;
            color: #475569;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.4rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
            outline: none;
            transition: all 0.15s ease;
            background: #ffffff;
        }

        .form-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-pay {
            width: 100%;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            border: none;
            padding: 0.9rem 1.5rem;
            border-radius: 12px;
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.35);
            transition: all 0.2s ease;
            margin-top: 1.75rem;
        }

        .btn-pay:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.45);
        }

        .btn-cancel {
            width: 100%;
            background: transparent;
            color: #64748b;
            border: 1px solid #cbd5e1;
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            transition: all 0.15s ease;
            margin-top: 0.65rem;
        }

        .btn-cancel:hover {
            color: #ef4444;
            border-color: #fca5a5;
            background: #fef2f2;
        }

        .security-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="payment-card-shell">
    <div class="payment-header">
        <div class="sandbox-badge">
            <i class="fa-solid fa-flask"></i>
            <span>Sandbox Mode / Թեստային միջավայր</span>
        </div>

        <div class="payment-provider-row">
            <div class="payment-provider-title">
                <i class="fa-solid fa-credit-card"></i>
                <span>ArCa / Ameriabank vPOS</span>
            </div>
            <div style="font-size: 1.4rem; opacity: 0.9;">
                <i class="fa-brands fa-cc-visa"></i>
                <i class="fa-brands fa-cc-mastercard"></i>
            </div>
        </div>

        <div class="payment-amount-box">
            <div>
                <span class="payment-amount-label">{{ __('Total Due') }}</span>
                <div style="font-size: 0.78rem; color: rgba(255,255,255,0.7); margin-top: 1px;">
                    {{ $attempt->vendor?->name ?? config('app.name') }}
                </div>
            </div>
            <div class="payment-amount-value">
                {{ number_format($attempt->amount, 0, '.', ' ') }} {{ $attempt->currency }}
            </div>
        </div>
    </div>

    <div class="payment-body">
        <div class="order-meta-info">
            <span><strong>Ref:</strong> {{ $attempt->merchant_reference }}</span>
            <span><i class="fa-solid fa-shield-halved" style="color: #10b981;"></i> 3D Secure 2.0</span>
        </div>

        <form action="{{ route('payment.arca.sandbox.process', $attempt->merchant_reference) }}" method="POST">
            @csrf

            <div>
                <label class="form-label">{{ __('Card Number') }}</label>
                <div style="position: relative;">
                    <input type="text" name="card_number" class="form-input font-mono" value="4111 1111 1111 1111" required style="letter-spacing: 0.08em; padding-left: 2.75rem;">
                    <i class="fa-solid fa-credit-card" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 1.1rem;"></i>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label">{{ __('Expiration Date') }}</label>
                    <input type="text" name="expiry" class="form-input font-mono" value="12/28" placeholder="MM/YY" required>
                </div>
                <div>
                    <label class="form-label">CVV / CVC</label>
                    <input type="password" name="cvv" class="form-input font-mono" value="123" maxlength="4" placeholder="123" required>
                </div>
            </div>

            <div style="margin-top: 1rem;">
                <label class="form-label">{{ __('Cardholder Name') }}</label>
                <input type="text" name="cardholder" class="form-input" value="TEST RESTAURANT OWNER" required>
            </div>

            <input type="hidden" name="action" id="paymentAction" value="pay">

            <button type="submit" class="btn-pay" onclick="document.getElementById('paymentAction').value='pay';">
                <i class="fa-solid fa-lock"></i>
                <span>{{ __('Pay') }} {{ number_format($attempt->amount, 0, '.', ' ') }} {{ $attempt->currency }}</span>
            </button>

            <button type="submit" class="btn-cancel" onclick="document.getElementById('paymentAction').value='cancel';">
                <i class="fa-solid fa-xmark"></i>
                <span>{{ __('Cancel Payment') }}</span>
            </button>
        </form>

        <div class="security-footer">
            <i class="fa-solid fa-lock"></i>
            <span>{{ __('Encrypted 256-bit SSL transaction simulated in sandbox environment.') }}</span>
        </div>
    </div>
</div>

</body>
</html>

