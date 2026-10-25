@extends('layouts.app')

@section('title', __('Event Inquiry :ref', ['ref' => $eventInquiry->reference]) . ' — ' . $vendor->name)

@section('content')
<div style="max-width: 1200px; margin: 0 auto; width: 100%; box-sizing: border-box;" x-data="eventDetailApp()">

    <!-- Top Navigation & Action Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 0.85rem;">
            <a href="{{ route('admin.event_inquiries.index') }}" class="btn btn-secondary" style="padding: 0.5rem 0.85rem;">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <div style="display: flex; align-items: center; gap: 0.65rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin: 0;">
                        {{ $eventInquiry->reference }}
                    </h1>
                    @php $colors = $eventInquiry->getStatusBadgeColors(); @endphp
                    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.8rem; font-weight: 700; background: {{ $colors['bg'] }}; color: {{ $colors['color'] }}; border: 1px solid {{ $colors['border'] }};">
                        <i class="{{ $colors['icon'] }}" style="font-size: 0.72rem;"></i>
                        <span>{{ $eventInquiry->getStatusLabel() }}</span>
                    </span>
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted); margin-top: 0.2rem;">
                    {{ __('Submitted on :date', ['date' => $eventInquiry->created_at->format('M d, Y H:i')]) }}
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            @can('delete', $eventInquiry)
                <form method="POST" action="{{ route('admin.event_inquiries.destroy', $eventInquiry) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this event inquiry?') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary" style="color: #ef4444;">
                        <i class="fa-solid fa-trash-can"></i> {{ __('Delete') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert alert-success" style="padding: 0.85rem 1rem; border-radius: 10px; margin-bottom: 1.25rem; background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; font-size: 0.9rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: flex-start;">
        
        <!-- Left Column: Details, Requirements, Notes, Timeline -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Event & Customer Overview Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class="fa-solid fa-calendar-star" style="color: #a855f7;"></i>
                    <span>{{ __('Event Details') }}</span>
                </h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Event Type') }}</div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                            {{ $eventInquiry->getEventTypeLabel() }}
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Requested Date') }}</div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                            {{ $eventInquiry->getFormattedDate() }}
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Time / Duration') }}</div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-top: 0.2rem;">
                            {{ $eventInquiry->getFormattedTime() }}
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Estimated Guests') }}</div>
                        <div style="font-size: 1.05rem; font-weight: 800; color: var(--primary); margin-top: 0.2rem;">
                            {{ $eventInquiry->guest_count }} {{ __('people') }}
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Venue Arrangement') }}</div>
                        <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-main); margin-top: 0.2rem;">
                            {{ $eventInquiry->getVenueArrangementLabel() }}
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Customer Budget') }}</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: #10b981; margin-top: 0.2rem;">
                            @if($eventInquiry->budget)
                                {{ number_format((float)$eventInquiry->budget) }} {{ $eventInquiry->budget_currency }}
                            @elseif($eventInquiry->budget_range)
                                {{ $eventInquiry->budget_range }}
                            @else
                                <span style="color: var(--text-muted);">{{ __('Flexible') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                @if(!empty($eventInquiry->additional_services))
                    <div style="padding-top: 1rem; border-top: 1px solid var(--border-color); margin-bottom: 1rem;">
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem;">
                            {{ __('Requested Additional Services') }}
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            @foreach((array)$eventInquiry->additional_services as $srv)
                                <span style="background: rgba(16, 185, 129, 0.1); color: #059669; border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 6px; padding: 0.25rem 0.65rem; font-size: 0.8rem; font-weight: 700;">
                                    ✓ {{ ucfirst(str_replace('_', ' ', $srv)) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(filled($eventInquiry->food_beverage_preferences))
                    <div style="padding-top: 1rem; border-top: 1px solid var(--border-color); margin-bottom: 1rem;">
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                            {{ __('Food & Beverage Preferences') }}
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-main); line-height: 1.5; background: var(--bg-color); padding: 0.75rem 1rem; border-radius: 8px;">
                            {{ $eventInquiry->food_beverage_preferences }}
                        </div>
                    </div>
                @endif

                @if(filled($eventInquiry->comments))
                    <div style="padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.35rem;">
                            {{ __('Customer Comments / Requirements') }}
                        </div>
                        <div style="font-size: 0.9rem; color: var(--text-main); line-height: 1.5; background: var(--bg-color); padding: 0.75rem 1rem; border-radius: 8px;">
                            {{ $eventInquiry->comments }}
                        </div>
                    </div>
                @endif
            </div>

            <!-- Customer Contact Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class="fa-solid fa-id-card" style="color: #3b82f6;"></i>
                    <span>{{ __('Customer Information') }}</span>
                </h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Full Name') }}</div>
                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-top: 0.15rem;">
                            {{ $eventInquiry->customer_name }}
                        </div>
                    </div>

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Phone Number') }}</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.15rem;">
                            <a href="tel:{{ $eventInquiry->customer_phone }}" style="font-size: 1rem; font-weight: 700; color: var(--primary); text-decoration: none;">
                                {{ $eventInquiry->customer_phone }}
                            </a>
                            @php
                                $cleanPhone = preg_replace('/[^0-9]/', '', $eventInquiry->customer_phone);
                            @endphp
                            <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" rel="noopener noreferrer" class="btn btn-secondary" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; color: #25d366;" title="Open WhatsApp">
                                <i class="fa-brands fa-whatsapp"></i> WhatsApp
                            </a>
                        </div>
                    </div>

                    @if($eventInquiry->customer_email)
                        <div>
                            <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Email Address') }}</div>
                            <div style="margin-top: 0.15rem;">
                                <a href="mailto:{{ $eventInquiry->customer_email }}" style="font-size: 0.95rem; color: var(--text-main); text-decoration: none;">
                                    {{ $eventInquiry->customer_email }}
                                </a>
                            </div>
                        </div>
                    @endif

                    <div>
                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">{{ __('Preferred Contact Method') }}</div>
                        <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 0.15rem;">
                            {{ ucfirst($eventInquiry->preferred_contact_method) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Internal Notes Section -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; color: var(--text-main);">
                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-comment-dots" style="color: #f59e0b;"></i>
                        <span>{{ __('Internal Notes') }} ({{ $eventInquiry->notes->count() }})</span>
                    </span>
                </h3>

                <!-- Add Note Form -->
                <form method="POST" action="{{ route('admin.event_inquiries.notes.store', $eventInquiry) }}" style="margin-bottom: 1.25rem;">
                    @csrf
                    <div style="margin-bottom: 0.75rem;">
                        <textarea name="note" 
                                  rows="2" 
                                  class="form-control" 
                                  placeholder="{{ __('Write an internal note for your team (e.g. called customer, requested menu variation)...') }}" 
                                  required 
                                  style="width: 100%; box-sizing: border-box;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">
                        <i class="fa-solid fa-plus"></i> {{ __('Add Note') }}
                    </button>
                </form>

                <!-- Existing Notes List -->
                @if($eventInquiry->notes->isEmpty())
                    <p style="font-size: 0.85rem; color: var(--text-muted); font-style: italic; margin: 0;">
                        {{ __('No internal notes added yet.') }}
                    </p>
                @else
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($eventInquiry->notes as $note)
                            <div style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 10px; padding: 0.85rem 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                                    <span style="font-weight: 700; font-size: 0.85rem; color: var(--text-main);">
                                        <i class="fa-solid fa-user" style="font-size: 0.75rem; color: var(--text-muted); margin-right: 0.25rem;"></i>
                                        {{ $note->user?->name ?? __('Staff') }}
                                    </span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                                        {{ $note->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <div style="font-size: 0.88rem; color: var(--text-subtle); line-height: 1.5; white-space: pre-line;">
                                    {{ $note->note }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Chronological Activity History (Audit Trail) -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class="fa-solid fa-timeline" style="color: #6366f1;"></i>
                    <span>{{ __('Activity History') }}</span>
                </h3>

                <div style="display: flex; flex-direction: column; gap: 0.85rem; position: relative; padding-left: 1.25rem; border-left: 2px solid var(--border-color); margin-left: 0.5rem;">
                    @foreach($eventInquiry->activities as $act)
                        <div style="position: relative;">
                            <div style="position: absolute; left: -1.7rem; top: 0.2rem; width: 10px; height: 10px; border-radius: 50%; background: var(--primary);"></div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 0.5rem;">
                                <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                                    {{ $act->description }}
                                </div>
                                <div style="font-size: 0.74rem; color: var(--text-muted); white-space: nowrap;">
                                    {{ $act->created_at->format('M d, H:i') }}
                                </div>
                            </div>
                            @if(!empty($act->metadata['reason']))
                                <div style="font-size: 0.8rem; color: #ef4444; margin-top: 0.2rem; font-style: italic;">
                                    {{ __('Reason:') }} {{ $act->metadata['reason'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        <!-- Right Column: Status & Operations Panel -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Update Status Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class="fa-solid fa-arrows-rotate" style="color: var(--primary);"></i>
                    <span>{{ __('Update Inquiry Status') }}</span>
                </h3>

                <form method="POST" action="{{ route('admin.event_inquiries.status', $eventInquiry) }}">
                    @csrf
                    @method('PATCH')

                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                            {{ __('Status') }}
                        </label>
                        <select name="status" class="form-control" x-model="selectedStatus" style="width: 100%; font-size: 0.9rem;">
                            @foreach($statuses as $stKey => $stLabel)
                                <option value="{{ $stKey }}" {{ $eventInquiry->status === $stKey ? 'selected' : '' }}>
                                    {{ $stLabel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Cancellation/Rejection Reason -->
                    <div style="margin-bottom: 1rem;" x-show="['declined', 'cancelled'].includes(selectedStatus)">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #ef4444; display: block; margin-bottom: 0.35rem;">
                            {{ __('Reason for Rejection / Cancellation') }}
                        </label>
                        <textarea name="cancellation_reason" 
                                  rows="2" 
                                  class="form-control" 
                                  placeholder="{{ __('e.g. Fully booked on that date, customer changed mind...') }}" 
                                  style="width: 100%; box-sizing: border-box;">{{ $eventInquiry->cancellation_reason }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fa-solid fa-check"></i> {{ __('Save Status') }}
                    </button>
                </form>
            </div>

            <!-- Assigned Team Member Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class="fa-solid fa-user-check" style="color: #10b981;"></i>
                    <span>{{ __('Assigned Staff') }}</span>
                </h3>

                <form method="POST" action="{{ route('admin.event_inquiries.assign', $eventInquiry) }}">
                    @csrf
                    @method('PATCH')

                    <div style="margin-bottom: 1rem;">
                        <select name="assigned_user_id" class="form-control" style="width: 100%; font-size: 0.9rem;">
                            <option value="">{{ __('— Unassigned —') }}</option>
                            @foreach($teamMembers as $member)
                                <option value="{{ $member->id }}" {{ (int)$eventInquiry->assigned_user_id === (int)$member->id ? 'selected' : '' }}>
                                    {{ $member->name }} ({{ ucfirst($member->role ?: 'staff') }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn btn-secondary" style="width: 100%;">
                        <i class="fa-solid fa-user-pen"></i> {{ __('Update Assignee') }}
                    </button>
                </form>
            </div>

            <!-- Quotation / Proposed Price Card -->
            <div class="card" style="padding: 1.5rem;">
                <h3 style="font-size: 1.05rem; font-weight: 800; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-main);">
                    <i class="fa-solid fa-tag" style="color: #8b5cf6;"></i>
                    <span>{{ __('Proposed Quotation') }}</span>
                </h3>

                <form method="POST" action="{{ route('admin.event_inquiries.quote', $eventInquiry) }}">
                    @csrf
                    @method('PATCH')

                    <div style="margin-bottom: 1rem;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 0.35rem;">
                            {{ __('Offered Price') }} ({{ $eventInquiry->budget_currency }})
                        </label>
                        <input type="number" 
                               name="quoted_price" 
                               step="1000" 
                               min="0" 
                               value="{{ $eventInquiry->quoted_price }}" 
                               placeholder="{{ __('e.g. 200000') }}" 
                               class="form-control" 
                               style="width: 100%; box-sizing: border-box;">
                    </div>

                    <button type="submit" class="btn btn-secondary" style="width: 100%;">
                        <i class="fa-solid fa-floppy-disk"></i> {{ __('Save Quotation') }}
                    </button>
                </form>
            </div>

        </div>

    </div>

</div>

<script>
function eventDetailApp() {
    return {
        selectedStatus: '{{ $eventInquiry->status }}',
    };
}
</script>
@endsection
