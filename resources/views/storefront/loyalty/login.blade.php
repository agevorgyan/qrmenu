<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Loyalty Club') }} - {{ $vendor->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-center items-center p-4">

    <div class="w-full max-w-md bg-slate-900/90 backdrop-blur-xl border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden"
         x-data="{
             step: 'identifier',
             identifier: '',
             name: '',
             code: '',
             loading: false,
             error: '',
             message: '',
             countdown: 0,
             timer: null,

             async sendOtp() {
                 this.error = '';
                 if (!this.identifier.trim()) {
                     this.error = '{{ __('Please enter your phone number or email address.') }}';
                     return;
                 }
                 this.loading = true;
                 try {
                     const res = await fetch('{{ route('client.loyalty.otp.send', ['vendor_slug' => $vendor->slug]) }}', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                         },
                         body: JSON.stringify({ identifier: this.identifier })
                     });
                     const data = await res.json();
                     if (!res.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : '{{ __('Failed to send code') }}'));
                     this.step = 'verify';
                     this.message = '{{ __('Verification code sent!') }}';
                     this.startCountdown(60);
                 } catch (e) {
                     this.error = e.message;
                 } finally {
                     this.loading = false;
                 }
             },

             async verifyOtp() {
                 this.error = '';
                 if (this.code.length !== 6) {
                     this.error = '{{ __('Please enter the 6-digit code.') }}';
                     return;
                 }
                 this.loading = true;
                 try {
                     const res = await fetch('{{ route('client.loyalty.otp.verify', ['vendor_slug' => $vendor->slug]) }}', {
                         method: 'POST',
                         headers: {
                             'Content-Type': 'application/json',
                             'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                         },
                         body: JSON.stringify({
                             identifier: this.identifier,
                             code: this.code,
                             name: this.name
                         })
                     });
                     const data = await res.json();
                     if (!res.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : '{{ __('Verification failed') }}'));
                     window.location.href = data.redirect_url;
                 } catch (e) {
                     this.error = e.message;
                 } finally {
                     this.loading = false;
                 }
             },

             startCountdown(seconds) {
                 this.countdown = seconds;
                 if (this.timer) clearInterval(this.timer);
                 this.timer = setInterval(() => {
                     this.countdown--;
                     if (this.countdown <= 0) clearInterval(this.timer);
                 }, 1000);
             }
         }">

        <!-- Header -->
        <div class="text-center mb-8">
            @if($vendor->logo)
                <img src="{{ $vendor->logo }}" alt="{{ $vendor->name }}" class="w-20 h-20 mx-auto rounded-2xl object-cover mb-4 border border-slate-700 shadow-lg">
            @else
                <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-tr from-indigo-500 to-pink-500 flex items-center justify-center text-2xl text-white font-bold mb-4 shadow-lg shadow-indigo-500/20">
                    <i class="fa-solid fa-gift"></i>
                </div>
            @endif
            <h1 class="text-2xl font-black text-white tracking-tight">{{ $vendor->name }}</h1>
            <p class="text-sm text-slate-400 mt-1">{{ __('Loyalty Club & Digital Membership') }}</p>
        </div>

        <!-- Feedback Alert -->
        <div x-show="error" x-transition class="mb-5 p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-xl text-rose-400 text-xs flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
            <span x-text="error"></span>
        </div>

        <!-- Step 1: Request OTP -->
        <div x-show="step === 'identifier'" x-transition>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">{{ __('Your Name') }} ({{ __('Optional') }})</label>
                    <input type="text" x-model="name" placeholder="{{ __('e.g. David Sahakyan') }}" class="w-full bg-slate-800/80 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">{{ __('Phone Number or Email') }}</label>
                    <input type="text" x-model="identifier" @keydown.enter="sendOtp" placeholder="{{ __('e.g. +374 91 123456 or name@domain.am') }}" class="w-full bg-slate-800/80 border border-slate-700/80 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                </div>
                <button type="button" @click="sendOtp" :disabled="loading" class="w-full mt-4 bg-gradient-to-r from-indigo-500 to-pink-500 hover:from-indigo-600 hover:to-pink-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2 transition disabled:opacity-50">
                    <template x-if="loading">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </template>
                    <span>{{ __('Continue with Verification') }}</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Verify OTP -->
        <div x-show="step === 'verify'" x-transition style="display: none;">
            <div class="space-y-4">
                <div class="text-center mb-4">
                    <p class="text-xs text-slate-400">{{ __('Enter the 6-digit code sent to:') }}</p>
                    <p class="text-sm font-bold text-white mt-0.5" x-text="identifier"></p>
                </div>
                <div>
                    <input type="text" x-model="code" maxlength="6" @keydown.enter="verifyOtp" placeholder="000000" class="w-full bg-slate-800/80 border border-slate-700/80 rounded-xl px-4 py-3.5 text-center text-2xl font-mono tracking-widest text-indigo-400 focus:outline-none focus:border-indigo-500 transition">
                </div>
                <button type="button" @click="verifyOtp" :disabled="loading" class="w-full bg-gradient-to-r from-indigo-500 to-pink-500 hover:from-indigo-600 hover:to-pink-600 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-indigo-500/25 flex items-center justify-center gap-2 transition disabled:opacity-50">
                    <template x-if="loading">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                    </template>
                    <span>{{ __('Verify & Access Club') }}</span>
                </button>
                <div class="flex items-center justify-between text-xs text-slate-400 pt-2">
                    <button type="button" @click="step = 'identifier'" class="hover:text-white transition">
                        <i class="fa-solid fa-arrow-left mr-1"></i> {{ __('Change details') }}
                    </button>
                    <button type="button" @click="sendOtp" :disabled="countdown > 0" class="text-indigo-400 hover:text-indigo-300 disabled:opacity-40 transition">
                        <span x-show="countdown > 0" x-text="'Resend in ' + countdown + 's'"></span>
                        <span x-show="countdown <= 0">{{ __('Resend code') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Storefront Link -->
        <div class="mt-8 pt-6 border-t border-slate-800/80 text-center">
            <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" class="text-xs text-slate-500 hover:text-slate-300 transition flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-utensils"></i>
                <span>{{ __('Return to Menu') }}</span>
            </a>
        </div>
    </div>

</body>
</html>

