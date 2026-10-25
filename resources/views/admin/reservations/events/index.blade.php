@extends('layouts.app')

@section('title', __('Event Planning Inquiries') . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 1400px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="adminEventsApp()">
    
    <!-- Top Action Bar -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: clamp(1.4rem, 2.5vw, 1.85rem); font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.65rem;">
                <span style="background: rgba(168, 85, 247, 0.12); color: #a855f7; width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                    <i class="fa-solid fa-champagne-glasses"></i>
                </span>
                <span>{{ __('Event Planning Inquiries') }}</span>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">
                {{ __('Track, manage, and respond to private banquets, corporate events, birthdays, and celebrations.') }}
            </p>
        </div>

        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <a href="{{ route('admin.reservations.index') }}" class="btn btn-secondary">
                <i class="fa-solid fa-chair" style="color: #ec4899;"></i> {{ __('Table Reservations') }}
            </a>
            <a href="{{ route('admin.settings.reservations.events') }}" class="btn btn-secondary">
                <i class="fa-solid fa-sliders" style="color: #a855f7;"></i> {{ __('Event Settings') }}
            </a>
            <a href="{{ route('client.event_inquiry', ['vendor_slug' => $vendor->slug]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--primary);"></i> {{ __('Public Form') }}
            </a>
        </div>
    </div>

    <!-- Metrics Row (Strictly Scoped) -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <!-- Total Inquiries -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(59, 130, 246, 0.12); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.55rem; font-weight: 800; color: var(--text-main); line-height: 1.2;">{{ $metrics['total'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Total Inquiries') }}
                </div>
            </div>
        </div>

        <!-- Pending / Active Pipeline -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(245, 158, 11, 0.12); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.55rem; font-weight: 800; color: #f59e0b; line-height: 1.2;">{{ $metrics['pending'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Pending & In Progress') }}
                </div>
            </div>
        </div>

        <!-- Confirmed Events -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(16, 185, 129, 0.12); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.55rem; font-weight: 800; color: #10b981; line-height: 1.2;">{{ $metrics['confirmed'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Confirmed Events') }}
                </div>
            </div>
        </div>

        <!-- Declined / Cancelled -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(239, 68, 68, 0.12); color: #ef4444; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.55rem; font-weight: 800; color: #ef4444; line-height: 1.2;">{{ $metrics['declined'] }}</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Declined / Cancelled') }}
                </div>
            </div>
        </div>

        <!-- Conversion Rate -->
        <div class="card" style="padding: 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0;">
            <div style="width: 48px; height: 48px; border-radius: 14px; background: rgba(168, 85, 247, 0.12); color: #a855f7; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
            <div style="min-width: 0;">
                <div style="font-size: 1.55rem; font-weight: 800; color: #a855f7; line-height: 1.2;">{{ $metrics['conversion_rate'] }}%</div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600; margin-top: 0.15rem;">
                    {{ __('Conversion Rate') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Toolbar -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="{{ route('admin.event_inquiries.index') }}">
            <!-- Period Quick Switcher -->
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-bottom: 1rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border-color);">
                <span style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); display: inline-flex; align-items: center; gap: 0.35rem; margin-right: 0.25rem;">
                    <i class="fa-solid fa-calendar-days" style="color: var(--primary);"></i> {{ __('Date Filter:') }}
                </span>

                @php
                    $presets = [
                        'all' => __('All Inquiries'),
                        'upcoming_30' => __('Upcoming 30 Days'),
                        'next_7_days' => __('Next 7 Days'),
                        'this_month' => __('This Month'),
                        'today' => __('Today'),
                    ];
                @endphp

                @foreach($presets as $pKey => $pLabel)
                    <a href="{{ route('admin.event_inquiries.index', array_merge(request()->except(['date_preset', 'date_from', 'date_to', 'page']), ['date_preset' => $pKey])) }}"
                       class="btn {{ $datePreset === $pKey ? 'btn-primary' : 'btn-secondary' }}"
                       style="padding: 0.35rem 0.85rem; font-size: 0.82rem; border-radius: 9999px;">
                        {{ $pLabel }}
                    </a>
                @endforeach
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.85rem; align-items: flex-end;">
                <!-- Search Input -->
                <div style="min-width: 0;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem; display: block;">
                        {{ __('Search by Name, Phone, Email, Reference') }}
                    </label>
                    <div style="position: relative;">
                        <input type="text" 
                               name="search" 
                               value="{{ $search }}" 
                               placeholder="{{ __('e.g. Aram, +374, EV-1234') }}" 
                               class="form-control" 
                               style="width: 100%; padding-left: 2.2rem; height: 38px; font-size: 0.88rem; box-sizing: border-box;">
                        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                    </div>
                </div>

                <!-- Status Filter -->
                <div style="min-width: 0;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem; display: block;">
                        {{ __('Status') }}
                    </label>
                    <select name="status" class="form-control" style="width: 100%; height: 38px; font-size: 0.88rem;">
                        <option value="all">{{ __('All Statuses') }}</option>
                        @foreach(\App\Models\EventInquiry::getStatuses() as $sKey => $sLabel)
                            <option value="{{ $sKey }}" {{ $status === $sKey ? 'selected' : '' }}>
                                {{ $sLabel }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Event Type Filter -->
                <div style="min-width: 0;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem; display: block;">
                        {{ __('Event Type') }}
                    </label>
                    <select name="event_type" class="form-control" style="width: 100%; height: 38px; font-size: 0.88rem;">
                        <option value="all">{{ __('All Event Types') }}</option>
                        @foreach($eventTypes as $tKey => $tTitle)
                            <option value="{{ $tKey }}" {{ $eventType === $tKey ? 'selected' : '' }}>
                                {{ __($tTitle) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort Order -->
                <div style="min-width: 0;">
                    <label style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.35rem; display: block;">
                        {{ __('Sort By') }}
                    </label>
                    <select name="sort_by" class="form-control" style="width: 100%; height: 38px; font-size: 0.88rem;">
                        <option value="created_at" {{ $sortBy === 'created_at' ? 'selected' : '' }}>{{ __('Submission Date (Newest first)') }}</option>
                        <option value="event_date" {{ $sortBy === 'event_date' ? 'selected' : '' }}>{{ __('Requested Event Date') }}</option>
                        <option value="guest_count" {{ $sortBy === 'guest_count' ? 'selected' : '' }}>{{ __('Guest Count') }}</option>
                    </select>
                </div>

                <!-- Filter Actions -->
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="height: 38px; flex: 1;">
                        <i class="fa-solid fa-filter"></i> {{ __('Filter') }}
                    </button>
                    @if(request()->hasAny(['search', 'status', 'event_type', 'date_preset', 'sort_by']))
                        <a href="{{ route('admin.event_inquiries.index') }}" class="btn btn-secondary" style="height: 38px;" title="{{ __('Clear filters') }}">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>

    <!-- Inquiries List Table -->
    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 1.5rem;">
        <div style="padding: 1.15rem 1.25rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                {{ __('Inquiries List') }} ({{ $inquiries->total() }})
            </div>
            <div style="font-size: 0.8rem; color: var(--text-muted);">
                {{ __('Showing :from - :to of :total inquiries', ['from' => $inquiries->firstItem() ?: 0, 'to' => $inquiries->lastItem() ?: 0, 'total' => $inquiries->total()]) }}
            </div>
        </div>

        @if($inquiries->isEmpty())
            <div style="text-align: center; padding: 4rem 1.5rem;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(168, 85, 247, 0.1); color: #a855f7; display: inline-flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem;">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.4rem; color: var(--text-main);">
                    {{ __('No inquiries found') }}
                </h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 420px; margin: 0 auto 1.25rem;">
                    {{ __('There are no event inquiries matching your current search or date criteria.') }}
                </p>
                @if(request()->hasAny(['search', 'status', 'event_type', 'date_preset']))
                    <a href="{{ route('admin.event_inquiries.index') }}" class="btn btn-secondary">
                        <i class="fa-solid fa-rotate-left"></i> {{ __('Reset Filters') }}
                    </a>
                @endif
            </div>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.88rem;">
                    <thead>
                        <tr style="background: var(--bg-color); border-bottom: 1px solid var(--border-color); color: var(--text-muted); font-size: 0.76rem; text-transform: uppercase; letter-spacing: 0.04em;">
                            <th style="padding: 0.85rem 1.25rem;">{{ __('Reference') }}</th>
                            <th style="padding: 0.85rem 1rem;">{{ __('Customer & Contact') }}</th>
                            <th style="padding: 0.85rem 1rem;">{{ __('Event Details') }}</th>
                            <th style="padding: 0.85rem 1rem;">{{ __('Guests & Budget') }}</th>
                            <th style="padding: 0.85rem 1rem;">{{ __('Assigned Staff') }}</th>
                            <th style="padding: 0.85rem 1rem;">{{ __('Status') }}</th>
                            <th style="padding: 0.85rem 1.25rem; text-align: right;">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody style="divide-y: 1px solid var(--border-color);">
                        @foreach($inquiries as $inquiry)
                            @php
                                $colors = $inquiry->getStatusBadgeColors();
                            @endphp
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.15s;" onmouseover="this.style.background='rgba(59, 130, 246, 0.03)'" onmouseout="this.style.background='transparent'">
                                <!-- Reference & Date -->
                                <td style="padding: 1rem 1.25rem; vertical-align: top;">
                                    <a href="{{ route('admin.event_inquiries.show', $inquiry) }}" style="font-weight: 800; color: var(--primary); text-decoration: none; font-size: 0.92rem;">
                                        {{ $inquiry->reference }}
                                    </a>
                                    <div style="font-size: 0.74rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        {{ $inquiry->created_at->format('M d, Y H:i') }}
                                    </div>
                                </td>

                                <!-- Customer Info -->
                                <td style="padding: 1rem 1rem; vertical-align: top;">
                                    <div style="font-weight: 700; color: var(--text-main);">
                                        {{ $inquiry->customer_name }}
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fa-solid fa-phone" style="font-size: 0.75rem;"></i>
                                        <a href="tel:{{ $inquiry->customer_phone }}" style="color: inherit; text-decoration: none;">
                                            {{ $inquiry->customer_phone }}
                                        </a>
                                        @if($inquiry->preferred_contact_method === 'whatsapp')
                                            <span style="color: #25d366;" title="Preferred: WhatsApp"><i class="fa-brands fa-whatsapp"></i></span>
                                        @endif
                                    </div>
                                    @if($inquiry->customer_email)
                                        <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 0.1rem;">
                                            {{ $inquiry->customer_email }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Event Details -->
                                <td style="padding: 1rem 1rem; vertical-align: top;">
                                    <div style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.2rem 0.55rem; border-radius: 6px; background: rgba(168, 85, 247, 0.1); color: #9333ea; font-size: 0.78rem; font-weight: 700; margin-bottom: 0.35rem;">
                                        <i class="fa-solid fa-sparkles"></i>
                                        <span>{{ $inquiry->getEventTypeLabel() }}</span>
                                    </div>
                                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                                        {{ $inquiry->getFormattedDate() }}
                                    </div>
                                    <div style="font-size: 0.76rem; color: var(--text-muted);">
                                        {{ $inquiry->getFormattedTime() }} • {{ $inquiry->getVenueArrangementLabel() }}
                                    </div>
                                </td>

                                <!-- Guests & Budget -->
                                <td style="padding: 1rem 1rem; vertical-align: top;">
                                    <div style="font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 0.35rem;">
                                        <i class="fa-solid fa-users" style="color: var(--text-muted); font-size: 0.8rem;"></i>
                                        <span>{{ $inquiry->guest_count }} {{ __('guests') }}</span>
                                    </div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                                        @if($inquiry->budget)
                                            <span style="color: #10b981; font-weight: 700;">{{ number_format((float)$inquiry->budget) }} {{ $inquiry->budget_currency }}</span>
                                        @elseif($inquiry->budget_range)
                                            <span>{{ $inquiry->budget_range }}</span>
                                        @else
                                            <span style="opacity: 0.6;">{{ __('Budget: flexible') }}</span>
                                        @endif
                                    </div>
                                    @if($inquiry->quoted_price)
                                        <div style="font-size: 0.74rem; color: #8b5cf6; font-weight: 700; margin-top: 0.15rem;">
                                            {{ __('Quote:') }} {{ number_format((float)$inquiry->quoted_price) }} {{ $inquiry->budget_currency }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Assigned Staff -->
                                <td style="padding: 1rem 1rem; vertical-align: top;">
                                    @if($inquiry->assignedUser)
                                        <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.84rem; font-weight: 600; color: var(--text-main);">
                                            <i class="fa-solid fa-user-check" style="color: #10b981;"></i>
                                            <span>{{ $inquiry->assignedUser->name }}</span>
                                        </div>
                                    @else
                                        <span style="font-size: 0.78rem; color: var(--text-muted); font-style: italic;">
                                            {{ __('Unassigned') }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Status Badge -->
                                <td style="padding: 1rem 1rem; vertical-align: top;">
                                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.7rem; border-radius: 9999px; font-size: 0.76rem; font-weight: 700; background: {{ $colors['bg'] }}; color: {{ $colors['color'] }}; border: 1px solid {{ $colors['border'] }};">
                                        <i class="{{ $colors['icon'] }}" style="font-size: 0.7rem;"></i>
                                        <span>{{ $inquiry->getStatusLabel() }}</span>
                                    </span>
                                </td>

                                <!-- Actions -->
                                <td style="padding: 1rem 1.25rem; vertical-align: top; text-align: right;">
                                    <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                        <a href="{{ route('admin.event_inquiries.show', $inquiry) }}" 
                                           class="btn btn-secondary" 
                                           style="padding: 0.4rem 0.75rem; font-size: 0.8rem;" 
                                           title="{{ __('View Inquiry Details') }}">
                                            <i class="fa-solid fa-eye"></i> {{ __('Details') }}
                                        </a>

                                        @can('delete', $inquiry)
                                            <form method="POST" action="{{ route('admin.event_inquiries.destroy', $inquiry) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this event inquiry?') }}');" style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.6rem; font-size: 0.8rem; color: #ef4444;" title="{{ __('Delete') }}">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($inquiries->hasPages())
                <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color);">
                    {{ $inquiries->links() }}
                </div>
            @endif
        @endif
    </div>

</div>

<script>
function adminEventsApp() {
    return {
        // Alpine state if needed for inline interaction
    };
}
</script>
@endsection
