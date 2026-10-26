@extends('layouts.app')

@section('title', __('Split Bill Details') . ' — ' . $order->order_number . ' | ' . $vendor->name)

@section('content')
<!-- QR Code Generator Lib -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div style="max-width: 1050px; margin: 0 auto; width: 100%; box-sizing: border-box;">

    <!-- Top Breadcrumb & Actions -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; margin-bottom: 0.4rem;">
                <a href="{{ route('admin.orders.index') }}" style="color: var(--text-muted); text-decoration: none;">
                    <i class="fa-solid fa-arrow-left"></i> {{ __('Orders') }}
                </a>
                <span style="color: var(--text-muted);">/</span>
                <span style="color: var(--primary); font-weight: 700;">{{ __('Split Bill') }}</span>
            </div>
            <h1 style="font-size: 1.65rem; font-weight: 800; margin: 0; color: var(--text-main); display: flex; align-items: center; gap: 0.65rem;">
                <span>{{ __('Split Bill') }}: {{ $order->order_number }}</span>
                @if($order->table_number)
                    <span style="font-size: 0.85rem; font-weight: 700; background: var(--bg-card); border: 1px solid var(--border-color); padding: 0.2rem 0.65rem; border-radius: 8px; color: var(--text-muted);">
                        {{ __('Table') }} {{ $order->table_number }}
                    </span>
                @endif
            </h1>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            @if($splitBill)
                @php
                    $guestBillUrl = route('client.bill.show', ['vendor_slug' => $vendor->slug, 'token' => $splitBill->split_token]);
                @endphp
                <a href="{{ $guestBillUrl }}" target="_blank" class="btn btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.15rem; font-size: 0.88rem; border-radius: 12px; text-decoration: none;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>{{ __('Open Customer View') }}</span>
                </a>
            @endif
            <a href="{{ route('admin.orders.index') }}" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; font-size: 0.88rem; border-radius: 12px; text-decoration: none;">
                <i class="fa-solid fa-utensils"></i>
                <span>{{ __('Back to Orders') }}</span>
            </a>
        </div>
    </div>

    @if (session('success'))
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #10b981; display: flex; align-items: center; gap: 0.65rem;">
            <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
            <span style="font-weight: 600; font-size: 0.92rem;">{{ __(session('success')) }}</span>
        </div>
    @endif

    @if (!$splitBill)
        <!-- Split Bill Not Enabled Notice -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 3rem 1.5rem; text-align: center;">
            <i class="fa-solid fa-divide" style="font-size: 3rem; color: var(--text-muted); opacity: 0.5; margin-bottom: 1rem;"></i>
            <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem;">
                {{ __('Split Bill is currently disabled') }}
            </h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 500px; margin: 0 auto 1.5rem auto;">
                {{ __('To allow guests at this table to divide their check, please enable the Split Bill module in your store settings.') }}
            </p>
            <a href="{{ route('admin.settings.split_bill') }}" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.35rem; border-radius: 12px; text-decoration: none; font-weight: 700;">
                <i class="fa-solid fa-gear"></i>
                <span>{{ __('Go to Split Bill Settings') }}</span>
            </a>
        </div>
    @else
        @php
            $isFullyPaid = $splitBill->isFullyPaid();
            $paidPercent = $splitBill->total_amount > 0 ? min(100, round(($splitBill->paid_amount / $splitBill->total_amount) * 100)) : 0;
            $pendingPayments = $splitBill->payments()->where('status', 'pending')->get();
            $hasPending = $pendingPayments->count() > 0;
        @endphp

        <!-- Pending Approval Warning Banner -->
        @if ($hasPending)
            <div style="background: rgba(245, 158, 11, 0.12); border: 1.5px solid #f59e0b; border-radius: 16px; padding: 1.15rem 1.35rem; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.85rem;">
                    <span style="width: 42px; height: 42px; border-radius: 50%; background: #f59e0b; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; animation: pulse 1.8s infinite;">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </span>
                    <div>
                        <div style="font-weight: 800; font-size: 1rem; color: #d97706;">
                            {{ __('Cash Split Payments Awaiting Staff Approval (:count)', ['count' => $pendingPayments->count()]) }}
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.15rem;">
                            {{ __('A guest has requested to pay their share in cash. Please verify and approve the payment below.') }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Metrics Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
            <!-- Card 1: Total Amount -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                    {{ __('Total Bill Amount') }}
                </div>
                <div style="font-family: 'Outfit'; font-size: 1.6rem; font-weight: 800; color: var(--text-main);">
                    {{ number_format($splitBill->total_amount) }} <span style="font-size: 0.9rem; color: var(--text-muted);">{{ $splitBill->currency }}</span>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem;">
                    {{ $order->items->count() }} {{ __('dishes ordered') }}
                </div>
            </div>

            <!-- Card 2: Paid Amount -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                    {{ __('Paid Amount') }}
                </div>
                <div style="font-family: 'Outfit'; font-size: 1.6rem; font-weight: 800; color: #10b981;">
                    {{ number_format($splitBill->paid_amount) }} <span style="font-size: 0.9rem; color: var(--text-muted);">{{ $splitBill->currency }}</span>
                </div>
                <div style="font-size: 0.78rem; color: #10b981; font-weight: 700; margin-top: 0.3rem;">
                    {{ $paidPercent }}% {{ __('settled') }}
                </div>
            </div>

            <!-- Card 3: Remaining Balance -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem;">
                <div style="font-size: 0.78rem; font-weight: 700; color: {{ $isFullyPaid ? '#10b981' : '#f59e0b' }}; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                    {{ __('Remaining to Pay') }}
                </div>
                <div style="font-family: 'Outfit'; font-size: 1.6rem; font-weight: 800; color: {{ $isFullyPaid ? '#10b981' : '#f59e0b' }};">
                    {{ number_format($splitBill->remaining_amount) }} <span style="font-size: 0.9rem; color: var(--text-muted);">{{ $splitBill->currency }}</span>
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem;">
                    {{ $isFullyPaid ? __('Fully Settled') : __('Pending shares') }}
                </div>
            </div>

            <!-- Card 4: Status -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.35rem;">
                    {{ __('Session Status') }}
                </div>
                <div>
                    @if($isFullyPaid)
                        <span style="display: inline-flex; align-items: center; gap: 0.45rem; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1.5px solid #10b981; padding: 0.35rem 0.85rem; border-radius: 9999px; font-weight: 800; font-size: 0.88rem;">
                            <i class="fa-solid fa-circle-check"></i> {{ __('Fully Paid') }}
                        </span>
                    @elseif($hasPending)
                        <span style="display: inline-flex; align-items: center; gap: 0.45rem; background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1.5px solid #f59e0b; padding: 0.35rem 0.85rem; border-radius: 9999px; font-weight: 800; font-size: 0.88rem;">
                            <i class="fa-solid fa-clock"></i> {{ __('Awaiting Cash Approval') }}
                        </span>
                    @else
                        <span style="display: inline-flex; align-items: center; gap: 0.45rem; background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1.5px solid #3b82f6; padding: 0.35rem 0.85rem; border-radius: 9999px; font-weight: 800; font-size: 0.88rem;">
                            <i class="fa-solid fa-spinner fa-spin"></i> {{ __('In Progress') }}
                        </span>
                    @endif
                </div>
                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.3rem;">
                    {{ $splitBill->payments->count() }} {{ __('payments processed') }}
                </div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; font-size: 0.85rem; font-weight: 700;">
                <span style="color: var(--text-main);">{{ __('Payment Progress') }}</span>
                <span style="color: #10b981; font-family: 'Outfit'; font-weight: 800;">{{ $paidPercent }}%</span>
            </div>
            <div style="height: 12px; background: var(--bg-main); border-radius: 999px; overflow: hidden; display: flex; border: 1px solid var(--border-color);">
                <div style="width: {{ $paidPercent }}%; background: linear-gradient(90deg, #10b981, #059669); transition: width 0.4s ease;"></div>
            </div>
        </div>

        <!-- Main Content 2-Column: Left Payments Ledger, Right QR Code & Share -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">

            <!-- Column 1: Payments & Participants Ledger -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-receipt" style="color: #3b82f6;"></i>
                        <span>{{ __('Participants Payments Ledger') }}</span>
                    </div>
                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">
                        {{ $splitBill->payments->count() }} {{ __('entries') }}
                    </span>
                </div>

                @if($splitBill->payments->count() === 0)
                    <div style="text-align: center; padding: 2.5rem 1rem; border: 1px dashed var(--border-color); border-radius: 14px;">
                        <i class="fa-solid fa-users" style="font-size: 2.5rem; color: var(--text-muted); opacity: 0.4; margin-bottom: 0.75rem;"></i>
                        <h4 style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.25rem;">
                            {{ __('No Split Payments Yet') }}
                        </h4>
                        <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">
                            {{ __('Guests can scan the table QR code to pay their share directly from their phones.') }}
                        </p>
                    </div>
                @else
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($splitBill->payments()->orderBy('created_at', 'desc')->get() as $payment)
                            <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 14px; padding: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
                                    <div style="width: 38px; height: 38px; border-radius: 50%; background: var(--bg-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 800; color: #3b82f6; flex-shrink: 0;">
                                        {{ strtoupper(mb_substr($payment->participant_name ?: 'G', 0, 1)) }}
                                    </div>
                                    <div style="min-width: 0;">
                                        <div style="font-weight: 800; font-size: 0.92rem; color: var(--text-main);">
                                            {{ $payment->participant_name ?: __('Guest') }}
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; margin-top: 0.15rem;">
                                            <span><i class="fa-solid fa-credit-card"></i> {{ strtoupper($payment->payment_method) }}</span>
                                            <span>•</span>
                                            <span><i class="fa-solid fa-clock"></i> {{ $payment->created_at->format('H:i') }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div style="text-align: right; display: flex; align-items: center; gap: 0.85rem;">
                                    <div>
                                        <div style="font-family: 'Outfit'; font-size: 1.1rem; font-weight: 800; color: {{ $payment->isPaid() ? '#10b981' : '#f59e0b' }};">
                                            {{ number_format($payment->amount) }} {{ $payment->currency }}
                                        </div>
                                        <div style="font-size: 0.72rem; margin-top: 0.1rem;">
                                            @if($payment->isPaid())
                                                <span style="color: #10b981; font-weight: 700;">✓ {{ __('Paid') }}</span>
                                            @elseif($payment->status === 'pending')
                                                <span style="color: #f59e0b; font-weight: 700;">⏳ {{ __('Pending Approval') }}</span>
                                            @else
                                                <span style="color: #ef4444; font-weight: 700;">{{ ucfirst($payment->status) }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($payment->status === 'pending')
                                        <form action="{{ route('admin.split_bill.approve_payment', $payment->id) }}" method="POST" style="margin: 0;">
                                            @csrf
                                            <button type="submit" class="btn btn-success" style="padding: 0.45rem 0.85rem; font-size: 0.8rem; font-weight: 700; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer; background: #10b981; border: none; color: #fff;" onclick="return confirm('{{ __('Confirm cash received and approve this payment?') }}')">
                                                <i class="fa-solid fa-check"></i> {{ __('Approve') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Column 2: Customer QR Code & Share Link -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 20px; padding: 1.5rem; display: flex; flex-direction: column; align-items: center; text-align: center;">
                <div style="font-family: 'Outfit'; font-size: 1.15rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.35rem;">
                    {{ __('Customer Split Bill QR & Link') }}
                </div>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 1.25rem; max-width: 320px;">
                    {{ __('Present this QR code to the table or copy the link to share via WhatsApp/Telegram.') }}
                </p>

                <!-- QR Code Display Box -->
                <div style="background: #ffffff; padding: 1rem; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); margin-bottom: 1.25rem;">
                    <div id="adminBillQrContainer"></div>
                </div>

                <!-- Direct URL input with Copy button -->
                <div style="width: 100%; display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <input type="text" id="billShareInput" readonly value="{{ $guestBillUrl }}" style="flex: 1; padding: 0.65rem 0.85rem; border-radius: 12px; border: 1px solid var(--border-color); background: var(--bg-main); color: var(--text-muted); font-size: 0.82rem; text-overflow: ellipsis;">
                    <button type="button" onclick="copyBillUrl()" class="btn btn-primary" style="padding: 0.65rem 1rem; font-size: 0.85rem; border-radius: 12px; font-weight: 700; white-space: nowrap;">
                        <i class="fa-solid fa-copy"></i> <span id="copyBtnText">{{ __('Copy') }}</span>
                    </button>
                </div>

                <a href="{{ $guestBillUrl }}" target="_blank" style="font-size: 0.85rem; color: #3b82f6; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    <span>{{ __('Preview customer bill page in new window') }}</span>
                </a>
            </div>

        </div>
    @endif

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    @if($splitBill)
        const qrEl = document.getElementById('adminBillQrContainer');
        if (qrEl && typeof QRCode !== 'undefined') {
            new QRCode(qrEl, {
                text: '{{ $guestBillUrl }}',
                width: 170,
                height: 170,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    @endif
});

function copyBillUrl() {
    const el = document.getElementById('billShareInput');
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(() => {
        const btnText = document.getElementById('copyBtnText');
        if (btnText) {
            btnText.innerText = '{{ __("Copied!") }}';
            setTimeout(() => {
                btnText.innerText = '{{ __("Copy") }}';
            }, 2000);
        }
    });
}
</script>
@endsection
