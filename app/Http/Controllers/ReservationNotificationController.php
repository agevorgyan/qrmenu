<?php

namespace App\Http\Controllers;

use App\Mail\RestaurantReservationMail;
use App\Models\EmailLog;
use App\Models\EmailRoutingRule;
use App\Models\EmailTemplate;
use App\Models\Reservation;
use App\Services\EmailTemplateService;
use App\Services\ReservationNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReservationNotificationController extends Controller
{
    public function __construct(
        protected ReservationNotificationService $notificationService,
        protected EmailTemplateService $templateService
    ) {}

    /**
     * Display Partner Notification Settings: Settings → Reservations & Pre-Orders → Notifications
     */
    public function index(Request $request): View
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('viewSettings', $vendor);

        $adminLocale = session('admin_locale') ?: ($vendor->admin_locale ?: 'hy');
        app()->setLocale($adminLocale);

        $settings = $vendor->reservation_notification_settings;
        $restrictions = $vendor->superadmin_notification_restrictions;

        // Fetch recent delivery logs for this vendor's reservations and pre-orders
        $logs = EmailLog::where('vendor_id', $vendor->id)
            ->whereIn('category', [EmailRoutingRule::CATEGORY_RESERVATIONS, EmailRoutingRule::CATEGORY_PREORDERS])
            ->latest('created_at')
            ->take(15)
            ->get();

        $templates = EmailTemplate::whereIn('category', [
            EmailRoutingRule::CATEGORY_RESERVATIONS,
            EmailRoutingRule::CATEGORY_PREORDERS,
        ])->get();

        $customerEvents = [
            'request_received' => [
                'name' => __('Reservation Request Received'),
                'desc' => __('Sent immediately when a customer submits a booking request'),
                'template_key' => 'reservation_request_received',
            ],
            'confirmed' => [
                'name' => __('Reservation Confirmed'),
                'desc' => __('Sent when the restaurant staff approves the booking'),
                'template_key' => 'reservation_confirmed',
            ],
            'rejected' => [
                'name' => __('Reservation Declined / Unavailable'),
                'desc' => __('Sent when the request cannot be accommodated due to full capacity'),
                'template_key' => 'reservation_rejected',
            ],
            'modified' => [
                'name' => __('Reservation Details Modified'),
                'desc' => __('Sent when arrival date, time, or guest count is updated'),
                'template_key' => 'reservation_modified',
            ],
            'cancelled' => [
                'name' => __('Reservation Cancelled'),
                'desc' => __('Sent upon cancellation by customer or restaurant'),
                'template_key' => 'reservation_cancelled',
            ],
            'reminder' => [
                'name' => __('Upcoming Arrival Reminder'),
                'desc' => __('Automatic reminder dispatched before arrival (e.g. 24h, 3h)'),
                'template_key' => 'reservation_reminder',
            ],
            'preorder_received' => [
                'name' => __('Pre-Order Received'),
                'desc' => __('Confirmation sent when customer attaches pre-ordered dishes'),
                'template_key' => 'preorder_received',
            ],
            'preorder_confirmed' => [
                'name' => __('Pre-Order Confirmed'),
                'desc' => __('Kitchen confirmation that selected dishes are acknowledged'),
                'template_key' => 'preorder_confirmed',
            ],
            'preorder_status_changed' => [
                'name' => __('Pre-Order Status Changed'),
                'desc' => __('Notifies customer as kitchen prepares their pre-ordered dishes'),
                'template_key' => 'preorder_status_changed',
            ],
            'preorder_cancelled' => [
                'name' => __('Pre-Order Cancelled'),
                'desc' => __('Sent if the attached pre-order is cancelled while keeping table'),
                'template_key' => 'preorder_cancelled',
            ],
        ];

        $restaurantEvents = [
            'received' => [
                'name' => __('New Reservation Received'),
                'desc' => __('Alerts restaurant staff of a new incoming customer reservation'),
                'template_key' => 'restaurant_reservation_received',
            ],
            'awaiting_confirmation' => [
                'name' => __('Reservation Awaiting Confirmation'),
                'desc' => __('Reminder to staff when pending bookings need prompt approval'),
                'template_key' => 'restaurant_reservation_awaiting_confirmation',
            ],
            'action' => [
                'name' => __('Customer Confirmed or Cancelled'),
                'desc' => __('Immediate notice when a customer confirms attendance or cancels'),
                'template_key' => 'restaurant_reservation_action',
            ],
            'modified' => [
                'name' => __('Reservation Details Changed'),
                'desc' => __('Alert when booking time, party size, or special requests change'),
                'template_key' => 'restaurant_reservation_modified',
            ],
            'preorder_received' => [
                'name' => __('New Pre-Order Attached'),
                'desc' => __('Notifies kitchen team of dishes attached to an upcoming table'),
                'template_key' => 'restaurant_preorder_received',
            ],
            'preorder_updated' => [
                'name' => __('Pre-Order Modified/Cancelled'),
                'desc' => __('Informs chef and kitchen staff of changes to pre-ordered dishes'),
                'template_key' => 'restaurant_preorder_updated',
            ],
            'reminder' => [
                'name' => __('Staff Shift Arrival Reminder'),
                'desc' => __('Alerts floor staff ahead of incoming guest arrival (e.g. 1h, 30m)'),
                'template_key' => 'restaurant_reservation_reminder',
            ],
        ];

        return view('admin.settings.reservations_notifications', compact(
            'vendor',
            'settings',
            'restrictions',
            'customerEvents',
            'restaurantEvents',
            'templates',
            'logs'
        ));
    }

    /**
     * Update Partner Notification Settings
     */
    public function update(Request $request): RedirectResponse
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageSettings', $vendor);

        $validated = $request->validate([
            'customer_notifications_enabled' => 'nullable|boolean',
            'customer_events' => 'nullable|array',
            'customer_reminder_enabled' => 'nullable|boolean',
            'customer_reminder_intervals' => 'nullable|string', // comma-separated minutes e.g. "1440, 180"
            'customer_reminder_only_confirmed' => 'nullable|boolean',

            'restaurant_notifications_enabled' => 'nullable|boolean',
            'restaurant_recipients' => 'nullable|string', // newline or comma-separated emails
            'restaurant_events' => 'nullable|array',
            'restaurant_reminder_enabled' => 'nullable|boolean',
            'restaurant_reminder_intervals' => 'nullable|string',
            'restaurant_reminder_only_confirmed' => 'nullable|boolean',
        ]);

        // Parse customer reminder intervals
        $customerIntervals = [];
        if (! empty($validated['customer_reminder_intervals'])) {
            $parts = preg_split('/[\s,]+/', $validated['customer_reminder_intervals']);
            foreach ($parts as $p) {
                $val = intval(trim($p));
                if ($val > 0) {
                    $customerIntervals[] = $val;
                }
            }
        }
        if (empty($customerIntervals)) {
            $customerIntervals = [1440, 180];
        }

        // Parse restaurant reminder intervals
        $restaurantIntervals = [];
        if (! empty($validated['restaurant_reminder_intervals'])) {
            $parts = preg_split('/[\s,]+/', $validated['restaurant_reminder_intervals']);
            foreach ($parts as $p) {
                $val = intval(trim($p));
                if ($val > 0) {
                    $restaurantIntervals[] = $val;
                }
            }
        }
        if (empty($restaurantIntervals)) {
            $restaurantIntervals = [180, 60];
        }

        // Parse and validate recipient email addresses
        $recipients = [];
        if (! empty($validated['restaurant_recipients'])) {
            $emails = preg_split('/[\r\n,;]+/', $validated['restaurant_recipients']);
            foreach ($emails as $email) {
                $cleaned = trim(strtolower($email));
                if (filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $cleaned;
                }
            }
        }
        $recipients = array_values(array_unique($recipients));

        $updatedSettings = [
            'customer_notifications_enabled' => $request->boolean('customer_notifications_enabled', true),
            'customer_events' => $request->input('customer_events', []),
            'customer_reminder_enabled' => $request->boolean('customer_reminder_enabled', true),
            'customer_reminder_intervals' => $customerIntervals,
            'customer_reminder_only_confirmed' => $request->boolean('customer_reminder_only_confirmed', true),

            'restaurant_notifications_enabled' => $request->boolean('restaurant_notifications_enabled', true),
            'restaurant_recipients' => $recipients,
            'restaurant_events' => $request->input('restaurant_events', []),
            'restaurant_reminder_enabled' => $request->boolean('restaurant_reminder_enabled', true),
            'restaurant_reminder_intervals' => $restaurantIntervals,
            'restaurant_reminder_only_confirmed' => $request->boolean('restaurant_reminder_only_confirmed', true),
        ];

        $vendor->update([
            'reservation_notification_settings' => $updatedSettings,
        ]);

        if ($request->has('telegram_notify_reservations')) {
            $tgSettings = $vendor->telegram_settings ?? [];
            $tgSettings['notify_reservations'] = $request->boolean('telegram_notify_reservations');
            $vendor->update(['telegram_settings' => $tgSettings]);
        }

        return back()->with('success', __('Reservation & pre-order notification settings successfully saved.'));
    }

    /**
     * Dispatch an authorized test email to verify restaurant mail routing.
     */
    public function sendTestEmail(Request $request): JsonResponse
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('manageSettings', $vendor);

        $validated = $request->validate([
            'recipient' => 'required|email|max:255',
            'event' => 'nullable|string|max:50',
        ]);

        $recipient = strtolower(trim($validated['recipient']));
        $event = $validated['event'] ?: 'received';

        try {
            // Build mock reservation for testing
            $mockReservation = new Reservation([
                'uuid' => (string) Str::uuid(),
                'reference' => 'RES-TEST99',
                'vendor_id' => $vendor->id,
                'customer_name' => 'John Doe (Test Guest)',
                'customer_email' => $recipient,
                'customer_phone' => '+374 99 001122',
                'locale' => session('admin_locale') ?: ($vendor->admin_locale ?? 'hy'),
                'guest_count' => 4,
                'table_number' => 'Table 10 (Main Hall)',
                'reservation_date' => now()->addDay()->format('Y-m-d'),
                'reservation_time' => '19:30',
                'reservation_datetime' => now()->addDay()->setHour(19)->setMinute(30),
                'status' => Reservation::STATUS_CONFIRMED,
                'notes' => 'Diagnostic test dispatch from QRMenu notification panel.',
                'special_requests' => 'Window seating preferred.',
            ]);
            $mockReservation->id = 0;
            $mockReservation->setRelation('vendor', $vendor);

            $mailable = new RestaurantReservationMail($mockReservation, $event, $mockReservation->locale);
            Mail::to($recipient)->send($mailable);

            return response()->json([
                'success' => true,
                'message' => __('Test notification successfully sent to :email via active SMTP configuration.', ['email' => $recipient]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('Failed to send test email: :error', ['error' => $e->getMessage()]),
            ], 422);
        }
    }

    /**
     * Preview template rendered content safely with localized mock data.
     */
    public function previewTemplate(Request $request): JsonResponse
    {
        $vendor = Auth::user()->vendor;
        $this->authorize('viewSettings', $vendor);

        $templateKey = $request->query('key', 'reservation_confirmed');
        $locale = $request->query('locale', app()->getLocale());

        $template = $this->templateService->getTemplate($templateKey);
        if (! $template) {
            return response()->json(['success' => false, 'message' => 'Template not found.'], 404);
        }

        $sampleData = $template->getPreviewSampleData();
        $sampleData['restaurant_name'] = $vendor->name;
        $sampleData['restaurant_phone'] = $vendor->phone ?? '+374 10 000000';
        $sampleData['restaurant_address'] = $vendor->operating_address ?? 'Yerevan, Armenia';

        $rendered = $this->templateService->render($templateKey, $sampleData, $locale);

        return response()->json([
            'success' => true,
            'template' => [
                'name' => $template->name,
                'category' => $template->category,
                'subject' => $rendered['subject'] ?? '',
                'intro' => $rendered['intro'] ?? '',
                'cta_text' => $rendered['cta_text'] ?? '',
                'cta_url' => $rendered['cta_url'] ?? '',
                'footer' => $rendered['footer'] ?? '',
                'support_contact' => $rendered['support_contact'] ?? '',
                'allowed_variables' => $template->allowed_variables ?? [],
            ],
        ]);
    }
}
