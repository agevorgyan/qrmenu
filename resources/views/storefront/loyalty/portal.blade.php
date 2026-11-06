<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $customer->name }} - {{ $vendor->name }} {{ __('Loyalty Club') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .apple-wallet-btn {
            background-color: #000000;
            color: #ffffff;
            border: 1px solid #333333;
        }
        .apple-wallet-btn:hover {
            background-color: #1a1a1a;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen pb-20">

    <!-- Top Bar -->
    <header class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-800">
        <div class="max-w-xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('client.menu', ['vendor_slug' => $vendor->slug]) }}" class="w-9 h-9 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <h1 class="text-sm font-bold text-white leading-tight">{{ $vendor->name }}</h1>
                    <span class="text-xs text-indigo-400 font-medium">{{ __('Member Portal') }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('client.loyalty.logout', ['vendor_slug' => $vendor->slug]) }}" class="text-xs text-slate-400 hover:text-rose-400 transition px-3 py-1.5 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span>{{ __('Exit') }}</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-xl mx-auto px-4 pt-6 space-y-6" x-data="{
        activeTab: 'overview',
        qrModalOpen: false,
        redemptionModalOpen: false,
        activeReward: null,
        redeeming: false,
        feedbackMessage: '',
        feedbackSuccess: true,

        async claimReward(rewardId) {
            this.redeeming = true;
            try {
                const res = await fetch(`/api/m/{{ $vendor->slug }}/loyalty/rewards/${rewardId}/claim`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    }
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Failed to claim reward');
                alert(data.message + '\n\nCode: ' + data.code);
                window.location.reload();
            } catch (e) {
                alert(e.message);
            } finally {
                this.redeeming = false;
            }
        },

        async copyReferral() {
            navigator.clipboard.writeText('{{ $referralCode }}');
            alert('{{ __('Referral code copied to clipboard!') }}');
        }
    }">

        <!-- DIGITAL WALLET CARD COMPONENT -->
        <div class="relative rounded-3xl p-6 shadow-2xl overflow-hidden border border-slate-700/50"
             style="background: linear-gradient(135deg, {{ $walletDesign->background_color }} 0%, #0f172a 100%);">
            
            <div class="flex items-start justify-between relative z-10 mb-8">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-widest px-2.5 py-1 rounded-full bg-white/10 text-white backdrop-blur-md">
                        {{ $overview['tier']['name'] }}
                    </span>
                    <h2 class="text-xl font-black text-white mt-3">{{ $customer->name }}</h2>
                    <p class="text-xs text-slate-300 font-mono">ID: {{ 'MEM-'.str_pad($customer->id, 8, '0', STR_PAD_LEFT) }}</p>
                </div>
                <button type="button" @click="qrModalOpen = true" class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-white hover:bg-white/20 transition shadow-inner">
                    <i class="fa-solid fa-qrcode text-xl"></i>
                </button>
            </div>

            <!-- Balances -->
            <div class="grid grid-cols-2 gap-4 relative z-10 pt-4 border-t border-white/10">
                <div>
                    <span class="text-xs text-slate-300 uppercase tracking-wider">{{ __('Points Balance') }}</span>
                    <div class="text-3xl font-black text-white mt-0.5 flex items-baseline gap-1.5">
                        <span>{{ number_format($overview['points']['balance']) }}</span>
                        <span class="text-xs font-normal text-indigo-300">pts</span>
                    </div>
                </div>
                <div>
                    <span class="text-xs text-slate-300 uppercase tracking-wider">{{ __('Cashback') }}</span>
                    <div class="text-3xl font-black text-emerald-400 mt-0.5 flex items-baseline gap-1.5">
                        <span>{{ number_format($overview['cashback']['balance'], 2) }}</span>
                        <span class="text-xs font-normal text-emerald-300">{{ $overview['cashback']['currency'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Tier progress bar -->
            @if($overview['tier']['next_tier'])
                <div class="mt-5 relative z-10">
                    <div class="flex items-center justify-between text-xs text-slate-300 mb-1.5">
                        <span>{{ __('Next: ') }}{{ $overview['tier']['next_tier']['name'] }}</span>
                        <span>{{ number_format($overview['tier']['next_tier']['points_needed']) }} {{ __('pts needed') }}</span>
                    </div>
                    <div class="w-full h-2 bg-black/40 rounded-full overflow-hidden">
                        @php
                            $minPts = $overview['tier']['next_tier']['min_points'];
                            $pct = $minPts > 0 ? min(100, round(($overview['points']['balance'] / $minPts) * 100)) : 100;
                        @endphp
                        <div class="h-full bg-gradient-to-r from-indigo-400 to-pink-500 rounded-full" style="width: {{ $pct }}%;"></div>
                    </div>
                </div>
            @endif
        </div>

        <!-- ADD TO WALLET BUTTONS -->
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('client.loyalty.wallet.apple', ['vendor_slug' => $vendor->slug]) }}" 
               class="apple-wallet-btn py-3 px-4 rounded-2xl flex items-center justify-center gap-2 font-semibold text-xs shadow-lg transition">
                <i class="fa-brands fa-apple text-base"></i>
                <span>{{ __('Add to Apple Wallet') }}</span>
            </a>
            <a href="{{ route('client.loyalty.wallet.google', ['vendor_slug' => $vendor->slug]) }}" 
               target="_blank"
               class="bg-white text-slate-900 hover:bg-slate-100 py-3 px-4 rounded-2xl flex items-center justify-center gap-2 font-semibold text-xs shadow-lg transition border border-slate-200">
                <i class="fa-brands fa-google text-sm text-indigo-600"></i>
                <span>{{ __('Save to Google Wallet') }}</span>
            </a>
        </div>

        <!-- NAVIGATION TABS -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
            <button type="button" @click="activeTab = 'overview'" :class="activeTab === 'overview' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-400 border border-slate-800'" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition">
                {{ __('Overview') }}
            </button>
            <button type="button" @click="activeTab = 'rewards'" :class="activeTab === 'rewards' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-400 border border-slate-800'" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition">
                {{ __('Rewards') }} ({{ $rewards->count() }})
            </button>
            <button type="button" @click="activeTab = 'stamps'" :class="activeTab === 'stamps' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-400 border border-slate-800'" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition">
                {{ __('Stamp Cards') }}
            </button>
            <button type="button" @click="activeTab = 'tiers'" :class="activeTab === 'tiers' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-400 border border-slate-800'" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition">
                {{ __('Tiers') }}
            </button>
            <button type="button" @click="activeTab = 'referral'" :class="activeTab === 'referral' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-400 border border-slate-800'" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition">
                {{ __('Refer a Friend') }}
            </button>
            <button type="button" @click="activeTab = 'history'" :class="activeTab === 'history' ? 'bg-indigo-600 text-white' : 'bg-slate-900 text-slate-400 border border-slate-800'" class="px-4 py-2 rounded-xl text-xs font-bold whitespace-nowrap transition">
                {{ __('History') }}
            </button>
        </div>

        <!-- TAB: OVERVIEW -->
        <div x-show="activeTab === 'overview'" class="space-y-4">
            <!-- Quick Member Benefits Card -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5">
                <h3 class="text-sm font-bold text-white mb-2 flex items-center gap-2">
                    <i class="fa-solid fa-sparkles text-amber-400"></i>
                    <span>{{ __('Your Membership Benefits') }}</span>
                </h3>
                <ul class="text-xs text-slate-400 space-y-1.5 list-disc list-inside">
                    <li>{{ $overview['tier']['multiplier'] }}x {{ __('Points on all dining and takeaway orders') }}</li>
                    <li>{{ __('Earn and redeem stamps for free dishes') }}</li>
                    <li>{{ __('Access to exclusive member birthday surprises') }}</li>
                </ul>
            </div>

            <!-- Present at counter hint -->
            <div class="bg-indigo-950/40 border border-indigo-900/50 rounded-2xl p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl flex-shrink-0">
                    <i class="fa-solid fa-mobile-screen-button"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-white">{{ __('Scan at Counter or Table') }}</h4>
                    <p class="text-xs text-slate-400 mt-0.5">{{ __('Tap the QR button on your card to show your one-time scan code to the waiter or cashier.') }}</p>
                </div>
            </div>
        </div>

        <!-- TAB: REWARDS -->
        <div x-show="activeTab === 'rewards'" style="display: none;" class="space-y-4">
            <h3 class="text-sm font-bold text-white">{{ __('Available Rewards Catalog') }}</h3>
            @forelse($rewards as $reward)
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        @if($reward->image_url)
                            <img src="{{ $reward->image_url }}" alt="{{ $reward->title }}" class="w-14 h-14 rounded-xl object-cover border border-slate-700 flex-shrink-0">
                        @else
                            <div class="w-14 h-14 rounded-xl bg-slate-800 flex items-center justify-center text-indigo-400 text-xl flex-shrink-0">
                                <i class="fa-solid fa-gift"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-white truncate">{{ $reward->title }}</h4>
                            <p class="text-xs text-slate-400 line-clamp-1 mt-0.5">{{ $reward->description }}</p>
                            <span class="inline-block mt-1 text-xs font-bold text-indigo-400">{{ number_format($reward->points_cost) }} {{ __('pts') }}</span>
                        </div>
                    </div>
                    <button type="button" 
                            @click="claimReward({{ $reward->id }})"
                            :disabled="redeeming || {{ $overview['points']['balance'] < $reward->points_cost ? 'true' : 'false' }}"
                            class="px-4 py-2 rounded-xl text-xs font-bold transition flex-shrink-0 {{ $overview['points']['balance'] >= $reward->points_cost ? 'bg-indigo-600 hover:bg-indigo-500 text-white' : 'bg-slate-800 text-slate-500 cursor-not-allowed' }}">
                        {{ __('Redeem') }}
                    </button>
                </div>
            @empty
                <div class="text-center py-8 text-xs text-slate-500">
                    {{ __('No rewards currently listed. Check back soon!') }}
                </div>
            @endforelse
        </div>

        <!-- TAB: STAMP CARDS -->
        <div x-show="activeTab === 'stamps'" style="display: none;" class="space-y-4">
            <h3 class="text-sm font-bold text-white">{{ __('Your Digital Stamp Cards') }}</h3>
            @forelse($stampCards as $card)
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h4 class="text-sm font-bold text-white">{{ $card->program->name }}</h4>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $card->program->reward_description }}</p>
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $card->status->value === 'completed' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-indigo-500/20 text-indigo-400' }}">
                            {{ $card->status->label() }}
                        </span>
                    </div>

                    <!-- Stamp grid -->
                    <div class="grid grid-cols-5 gap-2.5 my-4">
                        @for($i = 1; $i <= $card->stamps_required; $i++)
                            <div class="aspect-square rounded-xl flex items-center justify-center border {{ $i <= $card->stamps_collected ? 'bg-indigo-600 border-indigo-500 text-white shadow-md shadow-indigo-600/30' : 'bg-slate-800/50 border-slate-700/60 text-slate-600' }}">
                                @if($i <= $card->stamps_collected)
                                    <i class="fa-solid fa-stamp text-sm"></i>
                                @else
                                    <span class="text-xs font-bold">{{ $i }}</span>
                                @endif
                            </div>
                        @endfor
                    </div>

                    <div class="text-xs text-slate-400 text-center">
                        {{ $card->stamps_collected }} / {{ $card->stamps_required }} {{ __('stamps collected') }}
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-xs text-slate-500">
                    {{ __('No active stamp cards yet. Complete an eligible order to get your first card!') }}
                </div>
            @endforelse
        </div>

        <!-- TAB: TIERS -->
        <div x-show="activeTab === 'tiers'" style="display: none;" class="space-y-4">
            <h3 class="text-sm font-bold text-white">{{ __('Membership Tiers') }}</h3>
            @foreach($tiers as $tier)
                <div class="bg-slate-900/80 border rounded-2xl p-4 {{ $customer->loyalty_tier === $tier->name ? 'border-indigo-500 shadow-lg shadow-indigo-500/10' : 'border-slate-800' }}">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold" style="background-color: {{ $tier->color_hex }};">
                                <i class="fa-solid fa-star text-sm"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-white flex items-center gap-2">
                                    <span>{{ $tier->name }}</span>
                                    @if($customer->loyalty_tier === $tier->name)
                                        <span class="text-[10px] bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">{{ __('Current') }}</span>
                                    @endif
                                </h4>
                                <span class="text-xs text-slate-400">{{ number_format($tier->min_points) }} {{ __('points required') }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold text-indigo-400">{{ $tier->points_multiplier }}x</span>
                            <span class="block text-[10px] text-slate-500">{{ __('Multiplier') }}</span>
                        </div>
                    </div>
                    @if($tier->perks_description)
                        <p class="text-xs text-slate-400 mt-3 pt-3 border-t border-slate-800/80">{{ $tier->perks_description }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- TAB: REFERRAL -->
        <div x-show="activeTab === 'referral'" style="display: none;" class="space-y-4">
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 text-center">
                <div class="w-14 h-14 rounded-2xl bg-pink-500/10 border border-pink-500/20 flex items-center justify-center text-pink-400 text-2xl mx-auto mb-4">
                    <i class="fa-solid fa-user-plus"></i>
                </div>
                <h3 class="text-base font-bold text-white">{{ __('Invite Friends & Earn Points') }}</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1 mb-5">{{ __('Share your personal referral code. When your friend completes their first order, both of you receive bonus points!') }}</p>

                <div class="bg-slate-950 border border-slate-800 rounded-xl p-3 flex items-center justify-between max-w-xs mx-auto mb-4">
                    <span class="text-sm font-mono font-bold text-indigo-400 tracking-wider ml-2">{{ $referralCode }}</span>
                    <button type="button" @click="copyReferral" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold transition">
                        {{ __('Copy') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB: HISTORY -->
        <div x-show="activeTab === 'history'" style="display: none;" class="space-y-3">
            <h3 class="text-sm font-bold text-white">{{ __('Recent Loyalty Activity') }}</h3>
            @forelse($recentTransactions as $tx)
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-3.5 flex items-center justify-between text-xs">
                    <div>
                        <div class="font-bold text-white">{{ $tx->type->label() }}</div>
                        <div class="text-[11px] text-slate-400">{{ $tx->created_at->format('M d, Y H:i') }}</div>
                        @if($tx->notes)
                            <div class="text-[11px] text-slate-500 mt-0.5">{{ $tx->notes }}</div>
                        @endif
                    </div>
                    <div class="text-right">
                        <span class="font-bold text-sm {{ $tx->points >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                            {{ $tx->points >= 0 ? '+' : '' }}{{ $tx->points }}
                        </span>
                        <span class="block text-[10px] text-slate-500">{{ __('Bal: ') }}{{ $tx->balance_after }}</span>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-xs text-slate-500">
                    {{ __('No transactions yet.') }}
                </div>
            @endforelse
        </div>

        <!-- EPHEMERAL QR MODAL -->
        <div x-show="qrModalOpen" 
             x-transition 
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" 
             style="display: none;">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full text-center relative shadow-2xl"
                 @click.away="qrModalOpen = false">
                <button type="button" @click="qrModalOpen = false" class="absolute top-4 right-4 text-slate-400 hover:text-white">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
                <h3 class="text-base font-bold text-white mb-1">{{ __('Member Identification') }}</h3>
                <p class="text-xs text-slate-400 mb-6">{{ __('Show this code to the waiter or cashier to identify your membership.') }}</p>

                <div class="bg-white p-4 rounded-2xl inline-block mx-auto mb-4 shadow-lg">
                    {!! app(\App\Services\QrCodeService::class)->generateSvg($overview['qr_token'], 200) !!}
                </div>

                <p class="text-xs font-mono text-indigo-400 font-bold mb-2">{{ 'MEM-'.str_pad($customer->id, 8, '0', STR_PAD_LEFT) }}</p>
                <p class="text-[10px] text-slate-500">{{ __('Refreshes automatically for security') }}</p>
            </div>
        </div>

    </main>

</body>
</html>

