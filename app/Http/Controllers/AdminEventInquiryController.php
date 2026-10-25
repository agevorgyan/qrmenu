<?php

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use App\Models\EventInquiryNote;
use App\Models\User;
use App\Security\Permission;
use App\Services\TenantCache;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminEventInquiryController extends Controller
{
    /**
     * Display event inquiries list with metrics, search, and filters.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $vendor = $user->vendor;

        $this->authorize('viewAny', EventInquiry::class);

        $adminLocale = session('admin_locale') ?: ($vendor->admin_locale ?: 'hy');
        app()->setLocale($adminLocale);

        $tz = $vendor->timezone ?: 'Asia/Yerevan';
        $todayStr = Carbon::now($tz)->format('Y-m-d');

        $status = $request->input('status');
        $eventType = $request->input('event_type');
        $locationId = $request->input('location_id');
        $search = trim((string) $request->input('search'));
        $assignedTo = $request->input('assigned_to');
        $guestMin = $request->filled('guests_min') ? (int) $request->input('guests_min') : null;
        $guestMax = $request->filled('guests_max') ? (int) $request->input('guests_max') : null;

        // Date filter
        $datePreset = $request->input('date_preset', 'all');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        switch ($datePreset) {
            case 'today':
                $dateFrom = $todayStr;
                $dateTo = $todayStr;
                break;
            case 'tomorrow':
                $dateFrom = Carbon::tomorrow($tz)->format('Y-m-d');
                $dateTo = Carbon::tomorrow($tz)->format('Y-m-d');
                break;
            case 'next_7_days':
                $dateFrom = $todayStr;
                $dateTo = Carbon::now($tz)->addDays(7)->format('Y-m-d');
                break;
            case 'this_month':
                $dateFrom = Carbon::now($tz)->startOfMonth()->format('Y-m-d');
                $dateTo = Carbon::now($tz)->endOfMonth()->format('Y-m-d');
                break;
            case 'upcoming_30':
                $dateFrom = $todayStr;
                $dateTo = Carbon::now($tz)->addDays(30)->format('Y-m-d');
                break;
            case 'custom':
                break;
            case 'all':
            default:
                $dateFrom = null;
                $dateTo = null;
                break;
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sortBy, ['created_at', 'event_date', 'guest_count', 'budget'], true)) {
            $sortBy = 'created_at';
        }

        $query = EventInquiry::where('vendor_id', $vendor->id)
            ->with(['location', 'assignedUser']);

        // Location access control for staff restricted to specific locations
        if ($user->location_id) {
            $query->where(function ($q) use ($user) {
                $q->where('location_id', $user->location_id)
                    ->orWhereNull('location_id');
            });
        } elseif ($locationId) {
            $query->where('location_id', (int) $locationId);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($eventType && $eventType !== 'all') {
            $query->where('event_type', $eventType);
        }

        if ($assignedTo && $assignedTo !== 'all') {
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_user_id');
            } else {
                $query->where('assigned_user_id', (int) $assignedTo);
            }
        }

        if ($dateFrom) {
            $query->where('event_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('event_date', '<=', $dateTo);
        }

        if ($guestMin !== null) {
            $query->where('guest_count', '>=', $guestMin);
        }
        if ($guestMax !== null) {
            $query->where('guest_count', '<=', $guestMax);
        }

        if ($search !== '') {
            $query->search($search);
        }

        $inquiries = $query->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        // Analytics & Reporting Metrics (Strictly tenant scoped)
        $totalCount = EventInquiry::where('vendor_id', $vendor->id)->count();
        $pendingStatuses = [
            EventInquiry::STATUS_NEW,
            EventInquiry::STATUS_CONTACTED,
            EventInquiry::STATUS_PREPARING_OFFER,
            EventInquiry::STATUS_OFFER_SENT,
            EventInquiry::STATUS_NEGOTIATION,
        ];
        $pendingCount = EventInquiry::where('vendor_id', $vendor->id)->whereIn('status', $pendingStatuses)->count();
        $confirmedCount = EventInquiry::where('vendor_id', $vendor->id)->whereIn('status', [
            EventInquiry::STATUS_CONFIRMED,
            EventInquiry::STATUS_COMPLETED,
        ])->count();
        $declinedCount = EventInquiry::where('vendor_id', $vendor->id)->whereIn('status', [
            EventInquiry::STATUS_DECLINED,
            EventInquiry::STATUS_CANCELLED,
        ])->count();

        $conversionRate = $totalCount > 0 ? round(($confirmedCount / $totalCount) * 100, 1) : 0.0;

        $metrics = [
            'total' => $totalCount,
            'pending' => $pendingCount,
            'confirmed' => $confirmedCount,
            'declined' => $declinedCount,
            'conversion_rate' => $conversionRate,
        ];

        $teamMembers = $vendor->users()->orderBy('name')->get();
        $locations = $vendor->locations;
        $eventTypes = $vendor->event_inquiry_settings['event_types'] ?? [
            'birthday' => 'Birthday Party',
            'wedding' => 'Wedding & Engagement',
            'corporate' => 'Corporate Event & Business Dinner',
            'family_celebration' => 'Family Celebration',
            'anniversary' => 'Anniversary',
            'kids_party' => "Children's Party",
            'other' => 'Other Custom Event',
        ];

        return view('admin.reservations.events.index', compact(
            'vendor',
            'inquiries',
            'metrics',
            'teamMembers',
            'locations',
            'eventTypes',
            'datePreset',
            'dateFrom',
            'dateTo',
            'status',
            'eventType',
            'search',
            'sortBy',
            'sortDir'
        ));
    }

    /**
     * Show full inquiry details, internal notes, activity history, and actions.
     */
    public function show(EventInquiry $eventInquiry): View
    {
        $this->authorize('view', $eventInquiry);

        $vendor = Auth::user()->vendor;
        $eventInquiry->load(['notes.user', 'activities.user', 'assignedUser', 'location']);

        $teamMembers = $vendor->users()->orderBy('name')->get();
        $statuses = EventInquiry::getStatuses();

        return view('admin.reservations.events.show', compact(
            'vendor',
            'eventInquiry',
            'teamMembers',
            'statuses'
        ));
    }

    /**
     * Update inquiry status and log activity.
     */
    public function updateStatus(Request $request, EventInquiry $eventInquiry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $eventInquiry);

        $allowedStatuses = [
            EventInquiry::STATUS_NEW,
            EventInquiry::STATUS_CONTACTED,
            EventInquiry::STATUS_PREPARING_OFFER,
            EventInquiry::STATUS_OFFER_SENT,
            EventInquiry::STATUS_NEGOTIATION,
            EventInquiry::STATUS_CONFIRMED,
            EventInquiry::STATUS_COMPLETED,
            EventInquiry::STATUS_DECLINED,
            EventInquiry::STATUS_CANCELLED,
        ];

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($allowedStatuses)],
            'cancellation_reason' => 'nullable|string|max:1000',
            'quoted_price' => 'nullable|numeric|min:0',
        ]);

        $oldStatus = $eventInquiry->status;
        $newStatus = $validated['status'];

        $eventInquiry->status = $newStatus;

        if (in_array($newStatus, [EventInquiry::STATUS_DECLINED, EventInquiry::STATUS_CANCELLED], true) && filled($validated['cancellation_reason'] ?? null)) {
            $eventInquiry->cancellation_reason = trim($validated['cancellation_reason']);
        }

        if (array_key_exists('quoted_price', $validated) && $validated['quoted_price'] !== null) {
            $eventInquiry->quoted_price = (float) $validated['quoted_price'];
        }

        $eventInquiry->save();

        // Record audit activity
        $userName = Auth::user()?->name ?? 'Staff';
        $eventInquiry->recordActivity(
            action: 'status_updated',
            description: __(':user changed status from :old to :new', [
                'user' => $userName,
                'old' => ucfirst(str_replace('_', ' ', $oldStatus)),
                'new' => ucfirst(str_replace('_', ' ', $newStatus)),
            ]),
            metadata: [
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $eventInquiry->cancellation_reason,
            ],
            user: Auth::user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Status updated to :status', ['status' => $eventInquiry->getStatusLabel()]),
                'inquiry' => $eventInquiry,
            ]);
        }

        return redirect()->back()->with('success', __('Status updated successfully.'));
    }

    /**
     * Store internal note for team members.
     */
    public function storeNote(Request $request, EventInquiry $eventInquiry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $eventInquiry);

        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $note = EventInquiryNote::create([
            'vendor_id' => $eventInquiry->vendor_id,
            'event_inquiry_id' => $eventInquiry->id,
            'user_id' => Auth::id(),
            'note' => trim($validated['note']),
        ]);

        $eventInquiry->recordActivity(
            action: 'note_added',
            description: __(':user added an internal note', ['user' => Auth::user()?->name ?? 'Staff']),
            metadata: [
                'note_id' => $note->id,
                'excerpt' => mb_substr($note->note, 0, 100),
            ],
            user: Auth::user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Internal note added.'),
                'note' => $note->load('user'),
            ]);
        }

        return redirect()->back()->with('success', __('Note added successfully.'));
    }

    /**
     * Assign inquiry to a team member.
     */
    public function assign(Request $request, EventInquiry $eventInquiry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $eventInquiry);

        $vendor = Auth::user()->vendor;

        $validated = $request->validate([
            'assigned_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('vendor_id', $vendor->id),
            ],
        ]);

        $assignedUserId = ! empty($validated['assigned_user_id']) ? (int) $validated['assigned_user_id'] : null;
        $eventInquiry->assigned_user_id = $assignedUserId;
        $eventInquiry->save();

        $assignedUser = $assignedUserId ? User::find($assignedUserId) : null;
        $assigneeName = $assignedUser ? $assignedUser->name : __('Unassigned');

        $eventInquiry->recordActivity(
            action: 'assigned',
            description: __(':user assigned inquiry to :assignee', [
                'user' => Auth::user()?->name ?? 'Staff',
                'assignee' => $assigneeName,
            ]),
            metadata: ['assigned_user_id' => $assignedUserId],
            user: Auth::user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Inquiry assigned to :name', ['name' => $assigneeName]),
                'assigned_user' => $assignedUser,
            ]);
        }

        return redirect()->back()->with('success', __('Assignment updated successfully.'));
    }

    /**
     * Record quotation / proposed price.
     */
    public function updateQuote(Request $request, EventInquiry $eventInquiry): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $eventInquiry);

        $validated = $request->validate([
            'quoted_price' => 'nullable|numeric|min:0',
        ]);

        $price = array_key_exists('quoted_price', $validated) && $validated['quoted_price'] !== null
            ? (float) $validated['quoted_price']
            : null;

        $eventInquiry->quoted_price = $price;
        $eventInquiry->save();

        $formatted = $price !== null ? number_format($price).' '.$eventInquiry->budget_currency : __('Cleared');

        $eventInquiry->recordActivity(
            action: 'quote_updated',
            description: __(':user updated proposed quotation to :amount', [
                'user' => Auth::user()?->name ?? 'Staff',
                'amount' => $formatted,
            ]),
            metadata: ['quoted_price' => $price],
            user: Auth::user()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('Quotation updated successfully.'),
                'quoted_price' => $price,
            ]);
        }

        return redirect()->back()->with('success', __('Quotation updated successfully.'));
    }

    /**
     * Soft delete an inquiry.
     */
    public function destroy(EventInquiry $eventInquiry): RedirectResponse
    {
        $this->authorize('delete', $eventInquiry);

        $eventInquiry->delete();

        return redirect()->route('admin.event_inquiries.index')
            ->with('success', __('Event inquiry deleted successfully.'));
    }

    /**
     * Display Event Planning Inquiry settings.
     */
    public function settingsIndex(): View
    {
        $vendor = Auth::user()->vendor;

        if (! Auth::user()->hasPermission(Permission::SETTINGS_MANAGE) && ! Auth::user()->hasPermission(Permission::SETTINGS_VIEW)) {
            abort(403, __('Unauthorized action.'));
        }

        $settings = $vendor->event_inquiry_settings;

        return view('admin.settings.reservations_events', compact('vendor', 'settings'));
    }

    /**
     * Update Event Planning Inquiry settings.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;

        if (! Auth::user()->hasPermission(Permission::SETTINGS_MANAGE)) {
            abort(403, __('Unauthorized action.'));
        }

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'form_title' => 'nullable|string|max:200',
            'form_description' => 'nullable|string|max:1000',
            'optional_fields' => 'nullable|array',
            'optional_fields.*' => 'boolean',
            'event_types' => 'nullable|array',
            'event_types.*' => 'string|max:100',
            'min_guests' => 'nullable|integer|min:1|max:5000',
            'max_guests' => 'nullable|integer|min:1|max:10000',
            'contact_methods' => 'nullable|array',
            'contact_methods.*' => 'string|in:phone,whatsapp,email',
            'email_notifications_enabled' => 'nullable|boolean',
            'notification_recipients' => 'nullable|string|max:500',
            'customer_acknowledgment_enabled' => 'nullable|boolean',
            'confirmation_message' => 'nullable|string|max:1000',
        ]);

        if (! empty($validated['min_guests']) && ! empty($validated['max_guests'])) {
            if ($validated['min_guests'] > $validated['max_guests']) {
                return redirect()->back()
                    ->withErrors(['max_guests' => __('Maximum guests must be greater than or equal to minimum guests.')])
                    ->withInput();
            }
        }

        // Process notification recipients into a clean array
        $recipientsString = $validated['notification_recipients'] ?? '';
        $recipients = array_values(array_filter(array_map('trim', explode(',', $recipientsString))));

        $currentSettings = $vendor->event_inquiry_settings;

        $newSettings = array_merge($currentSettings, [
            'enabled' => $request->boolean('enabled'),
            'form_title' => trim((string) ($validated['form_title'] ?? '')),
            'form_description' => trim((string) ($validated['form_description'] ?? '')),
            'optional_fields' => [
                'email' => $request->boolean('optional_fields.email', true),
                'start_time' => $request->boolean('optional_fields.start_time', true),
                'end_time' => $request->boolean('optional_fields.end_time', true),
                'budget' => $request->boolean('optional_fields.budget', true),
                'venue_arrangement' => $request->boolean('optional_fields.venue_arrangement', true),
                'food_beverage_preferences' => $request->boolean('optional_fields.food_beverage_preferences', true),
                'additional_services' => $request->boolean('optional_fields.additional_services', true),
                'comments' => $request->boolean('optional_fields.comments', true),
            ],
            'event_types' => ! empty($validated['event_types']) ? $validated['event_types'] : $currentSettings['event_types'],
            'min_guests' => ! empty($validated['min_guests']) ? (int) $validated['min_guests'] : 1,
            'max_guests' => ! empty($validated['max_guests']) ? (int) $validated['max_guests'] : null,
            'contact_methods' => ! empty($validated['contact_methods']) ? $validated['contact_methods'] : ['phone', 'whatsapp', 'email'],
            'email_notifications_enabled' => $request->boolean('email_notifications_enabled', true),
            'notification_recipients' => $recipients,
            'customer_acknowledgment_enabled' => $request->boolean('customer_acknowledgment_enabled', true),
            'confirmation_message' => trim((string) ($validated['confirmation_message'] ?? '')),
        ]);

        $vendor->update(['event_inquiry_settings' => $newSettings]);
        TenantCache::invalidateAll($vendor);

        return redirect()->back()->with('success', __('Event planning settings updated successfully.'));
    }
}
