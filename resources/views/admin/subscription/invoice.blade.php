@extends('layouts.app')

@section('title', __('Invoice') . ' #' . $invoice->invoice_number . ' - ' . $vendor->name)

@section('content')
<div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <a href="{{ route('admin.subscription') }}" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-700">
            <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ __('Back to Subscriptions') }}
        </a>
        <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-slate-300 rounded-md shadow-sm text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">
            <svg class="mr-2 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            {{ __('Print / Save PDF') }}
        </button>
    </div>

    <div class="bg-white shadow rounded-lg border border-slate-200 overflow-hidden">
        <div class="p-8 border-b border-slate-200">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ config('app.name', 'QR Menu SaaS') }}</h1>
                    <p class="text-sm text-slate-500 mt-1">{{ __('Subscription & Billing Invoice') }}</p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider
                        {{ $invoice->status === 'completed' || $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($invoice->status === 'failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                        {{ strtoupper($invoice->status) }}
                    </span>
                    <p class="text-sm font-mono text-slate-600 mt-2">{{ $invoice->invoice_number }}</p>
                    <p class="text-xs text-slate-400">{{ $invoice->created_at?->format('Y-m-d H:i') }}</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-2 gap-8 text-sm">
                <div>
                    <h3 class="font-semibold text-slate-700 uppercase tracking-wider text-xs">{{ __('Billed To') }}</h3>
                    <p class="mt-2 text-slate-900 font-medium">{{ $vendor->name }}</p>
                    <p class="text-slate-500">{{ $vendor->email }}</p>
                    <p class="text-slate-500">{{ $vendor->phone }}</p>
                </div>
                <div class="text-right">
                    <h3 class="font-semibold text-slate-700 uppercase tracking-wider text-xs">{{ __('Payment Method') }}</h3>
                    <p class="mt-2 text-slate-900 font-medium">{{ strtoupper($invoice->payment_method) }}</p>
                    @if($invoice->latestPaymentAttempt)
                        <p class="text-xs text-slate-400 font-mono mt-1">Ref: {{ $invoice->latestPaymentAttempt->merchant_reference }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-8">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3">{{ __('Description') }}</th>
                        <th class="py-3 text-center">{{ __('Period') }}</th>
                        <th class="py-3 text-right">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <tr>
                        <td class="py-4 font-medium text-slate-900">
                            {{ $invoice->plan?->name ?? __('SaaS Subscription') }}
                            <div class="text-xs text-slate-500 mt-0.5">{{ $invoice->notes }}</div>
                        </td>
                        <td class="py-4 text-center text-slate-600">
                            {{ $invoice->period_start?->format('Y-m-d') }} &rarr; {{ $invoice->period_end?->format('Y-m-d') }}
                        </td>
                        <td class="py-4 text-right font-mono font-semibold text-slate-900">
                            {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-slate-200 font-bold text-slate-900">
                        <td colspan="2" class="py-4 text-right">{{ __('Total Paid') }}:</td>
                        <td class="py-4 text-right font-mono text-base">
                            {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
