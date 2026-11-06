@extends('layouts.app')

@section('title', __('HACCP Sanitation & Shift Checklists') . ' - ' . Auth::user()->vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto;" x-data="{ checklistModalOpen: false }">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-clipboard-check"></i>
                </span>
                <span>{{ __('HACCP Sanitation & Shift Checklists') }}</span>
            </h1>
        </div>
        <div>
            @can('haccp.log')
                <button type="button" @click="checklistModalOpen = true" class="btn btn-primary" style="display: flex; align-items: center; gap: 0.5rem; border-radius: 12px; font-weight: 700; background: #10b981; border-color: #10b981;">
                    <i class="fa-solid fa-plus"></i> {{ __('Complete Shift Checklist') }}
                </button>
            @endcan
        </div>
    </div>

    <!-- HACCP Sub-Navigation Tabs -->
    <div style="display: flex; gap: 0.5rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem;">
        <a href="{{ route('admin.inventory.haccp.dashboard') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-gauge-high"></i> {{ __('Dashboard') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.temperatures') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-temperature-half"></i> {{ __('Temperature Logs') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.checklists') }}" class="btn btn-sm btn-primary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-clipboard-check"></i> {{ __('Sanitation Checklists') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.ccps') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-sliders"></i> {{ __('Critical Control Points (CCPs)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.batches') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-boxes-packing"></i> {{ __('Batches & Expiry (FEFO)') }}
        </a>
        <a href="{{ route('admin.inventory.haccp.traceability') }}" class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; font-weight: 600;">
            <i class="fa-solid fa-route"></i> {{ __('Traceability & Recall Audit') }}
        </a>
    </div>

    <!-- Checklists Table -->
    <div class="card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; box-shadow: var(--shadow-card);">
        <div class="table-responsive">
            <table class="table" style="margin: 0; vertical-align: middle;">
                <thead style="background: var(--bg-hover); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                    <tr>
                        <th style="padding: 1rem 1.25rem;">{{ __('Checklist Title') }}</th>
                        <th style="padding: 1rem;">{{ __('Type') }}</th>
                        <th style="padding: 1rem;">{{ __('Shift') }}</th>
                        <th style="padding: 1rem; text-align: center;">{{ __('Compliance Rate') }}</th>
                        <th style="padding: 1rem;">{{ __('Completed By') }}</th>
                        <th style="padding: 1rem 1.25rem; text-align: right;">{{ __('Completed At') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($checklists as $cl)
                        @php $rate = $cl->passRate(); @endphp
                        <tr style="border-top: 1px solid var(--border-color);">
                            <td style="padding: 1rem 1.25rem;">
                                <div style="font-weight: 700; color: var(--text-main);">
                                    {{ $cl->title }}
                                </div>
                                @if($cl->notes)
                                    <small style="color: var(--text-muted);">{{ $cl->notes }}</small>
                                @endif
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge" style="background: rgba(107, 114, 128, 0.15); color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase;">
                                    {{ $cl->type }}
                                </span>
                            </td>
                            <td style="padding: 1rem; font-weight: 600; text-transform: capitalize;">
                                {{ $cl->shift }}
                            </td>
                            <td style="padding: 1rem; text-align: center;">
                                <span class="badge" style="background: {{ $rate >= 100 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $rate >= 100 ? '#10b981' : '#ef4444' }}; font-weight: 800; font-size: 0.85rem;">
                                    {{ $rate }}%
                                </span>
                            </td>
                            <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                                {{ $cl->completedBy?->name ?? __('Staff') }}
                            </td>
                            <td style="padding: 1rem 1.25rem; text-align: right; font-size: 0.85rem; color: var(--text-muted);">
                                {{ $cl->completed_at ? $cl->completed_at->toVendorTimezone()->format('M d, Y H:i') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                                <i class="fa-solid fa-clipboard-check" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-main);">{{ __('No checklists recorded yet') }}</div>
                                <p style="margin: 0.5rem 0 1.25rem;">{{ __('Conduct opening, sanitation, and closing food safety audit checklists.') }}</p>
                                @can('haccp.log')
                                    <button type="button" @click="checklistModalOpen = true" class="btn btn-primary" style="border-radius: 10px; background: #10b981; border-color: #10b981;">
                                        <i class="fa-solid fa-plus"></i> {{ __('Complete Shift Checklist') }}
                                    </button>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($checklists->hasPages())
            <div style="padding: 1rem; border-top: 1px solid var(--border-color);">
                {{ $checklists->links() }}
            </div>
        @endif
    </div>

    <!-- Complete Checklist Modal -->
    <div x-show="checklistModalOpen" x-cloak style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1050; display: flex; align-items: center; justify-content: center; padding: 1rem;">
        <div class="card" style="background: var(--bg-card); border-radius: 16px; max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-clipboard-check" style="color: #10b981;"></i> {{ __('Shift Food Safety Checklist') }}
                </h3>
                <button type="button" @click="checklistModalOpen = false" class="btn-close" style="background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer;">
                    &times;
                </button>
            </div>

            <form method="POST" action="{{ route('admin.inventory.haccp.checklists.store') }}">
                @csrf

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label class="form-label" style="font-weight: 600;">{{ __('Checklist Title') }} <span style="color: #ef4444;">*</span></label>
                        <input type="text" name="title" class="form-control" value="Daily Kitchen Sanitation & Hygiene Audit" required>
                    </div>

                    <div>
                        <label class="form-label" style="font-weight: 600;">{{ __('Shift') }}</label>
                        <select name="shift" class="form-select">
                            <option value="morning">{{ __('Morning') }}</option>
                            <option value="afternoon">{{ __('Afternoon') }}</option>
                            <option value="evening">{{ __('Evening') }}</option>
                            <option value="night">{{ __('Night') }}</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" name="type" value="sanitation">

                <div style="font-weight: 700; font-size: 0.9rem; color: var(--text-main); margin-bottom: 0.75rem;">
                    {{ __('HACCP Control Tasks') }}
                </div>

                @php
                    $tasks = [
                        'Refrigeration and freezer temperature logs recorded within limits',
                        'Handwashing stations stocked with anti-bacterial soap & paper towels',
                        'Raw meats, poultry and ready-to-eat produce strictly segregated',
                        'Food contact surfaces, slicers and cutting boards sanitized',
                        'All opened containers dated, labeled and stored following FIFO / FEFO',
                        'No cross-contamination risks or improper allergen handling observed',
                        'Cooked and hot-holding food kept above 63°C',
                        'Waste bins emptied, lids closed and floor drains clean'
                    ];
                @endphp

                <div style="border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; margin-bottom: 1.25rem;">
                    @foreach($tasks as $idx => $task)
                        <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 1rem; background: {{ $loop->odd ? 'var(--bg-hover)' : 'transparent' }};">
                            <input type="hidden" name="items[{{ $idx }}][task]" value="{{ $task }}">
                            <span style="font-size: 0.85rem; color: var(--text-main); flex: 1;">{{ $task }}</span>
                            <div class="form-check form-switch" style="margin: 0;">
                                <input class="form-check-input" type="checkbox" name="items[{{ $idx }}][is_passed]" value="1" checked style="cursor: pointer;">
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <label class="form-label" style="font-weight: 600;">{{ __('Auditor Notes & Observations') }}</label>
                    <textarea name="notes" rows="2" class="form-control" style="border-radius: 10px;" placeholder="{{ __('Any corrective remarks or special notes...') }}"></textarea>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button type="button" @click="checklistModalOpen = false" class="btn btn-outline-secondary" style="border-radius: 10px;">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 700; background: #10b981; border-color: #10b981;">
                        <i class="fa-solid fa-check"></i> {{ __('Submit Checklist') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

