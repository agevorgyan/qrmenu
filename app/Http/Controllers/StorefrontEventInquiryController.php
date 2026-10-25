<?php

namespace App\Http\Controllers;

use App\Models\EventInquiry;
use App\Models\Location;
use App\Models\Vendor;
use App\Services\EventInquiryNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StorefrontEventInquiryController extends Controller
{
    public function __construct(
        protected EventInquiryNotificationService $notificationService,
    ) {}

    /**
     * Display the public event planning inquiry form.
     */
    public function showInquiryForm(Request $request, string $vendor_slug, ?string $location_slug = null): View
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->with('locations')->firstOrFail();

        if (! $vendor->isEventInquiryFeatureEnabled()) {
            abort(404, __('Event planning inquiries are not available for this restaurant.'));
        }

        $lang = $request->query('lang') ?: $request->query('locale');
        if ($lang && in_array(strtolower((string) $lang), ['hy', 'en', 'ru'], true)) {
            $locale = strtolower((string) $lang);
            app()->setLocale($locale);
            session(['app_locale' => $locale, 'locale' => $locale]);
        }
        Carbon::setLocale(app()->getLocale());

        $location = null;
        if ($location_slug) {
            $location = $vendor->locations->firstWhere('slug', $location_slug);
        }
        if (! $location) {
            $location = $vendor->locations->first();
        }

        $settings = $vendor->event_inquiry_settings;

        return view('storefront.event_inquiry', compact(
            'vendor',
            'location',
            'settings'
        ));
    }

    /**
     * Submit an event planning inquiry from the public form.
     */
    public function submitInquiry(Request $request, string $vendor_slug): JsonResponse
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->with('locations')->firstOrFail();

        if (! $vendor->isEventInquiryFeatureEnabled()) {
            return response()->json([
                'status' => 'error',
                'message' => __('Event planning inquiries are not available for this restaurant.'),
            ], 403);
        }

        $settings = $vendor->event_inquiry_settings;
        $minGuests = isset($settings['min_guests']) && $settings['min_guests'] > 0 ? (int) $settings['min_guests'] : 1;
        $maxGuests = ! empty($settings['max_guests']) ? (int) $settings['max_guests'] : 5000;

        $rules = [
            'event_type' => 'required|string|max:64',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'preferred_contact_method' => 'required|string|in:phone,whatsapp,email',
            'event_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'guest_count' => "required|integer|min:{$minGuests}|max:{$maxGuests}",
            'start_time' => 'nullable|string|max:16',
            'end_time' => 'nullable|string|max:16',
            'estimated_duration_hours' => 'nullable|numeric|min:0.5|max:24',
            'budget' => 'nullable|numeric|min:0',
            'budget_range' => 'nullable|string|max:100',
            'venue_arrangement' => 'nullable|string|in:indoor,outdoor,private_room,no_preference',
            'food_beverage_preferences' => 'nullable|string|max:2000',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'string|max:64',
            'comments' => 'nullable|string|max:3000',
            'location_id' => 'nullable|integer',
            'locale' => 'nullable|string|max:10',
        ];

        // If email is selected as preferred contact method, email address must be provided
        if ($request->input('preferred_contact_method') === 'email') {
            $rules['customer_email'] = 'required|email|max:255';
        }

        $validated = $request->validate($rules);

        // Verify location belongs to vendor if provided
        $locationId = null;
        if (! empty($validated['location_id'])) {
            $location = $vendor->locations->firstWhere('id', (int) $validated['location_id']);
            $locationId = $location?->id;
        }

        $requestedLocale = $validated['locale'] ?? ($request->query('lang') ?: (session('app_locale') ?: (app()->getLocale() ?: 'hy')));
        $customerLocale = in_array(strtolower((string) $requestedLocale), ['hy', 'en', 'ru'], true)
            ? strtolower((string) $requestedLocale)
            : ($vendor->getDefaultLanguageCode() ?: 'hy');

        $inquiry = DB::transaction(function () use ($vendor, $locationId, $validated, $customerLocale) {
            $inquiry = new EventInquiry([
                'vendor_id' => $vendor->id,
                'location_id' => $locationId,
                'event_type' => trim($validated['event_type']),
                'customer_name' => trim($validated['customer_name']),
                'customer_phone' => trim($validated['customer_phone']),
                'customer_email' => ! empty($validated['customer_email']) ? strtolower(trim($validated['customer_email'])) : null,
                'preferred_contact_method' => $validated['preferred_contact_method'],
                'event_date' => $validated['event_date'],
                'start_time' => ! empty($validated['start_time']) ? trim($validated['start_time']) : null,
                'end_time' => ! empty($validated['end_time']) ? trim($validated['end_time']) : null,
                'estimated_duration_hours' => ! empty($validated['estimated_duration_hours']) ? (float) $validated['estimated_duration_hours'] : null,
                'guest_count' => (int) $validated['guest_count'],
                'budget' => ! empty($validated['budget']) ? (float) $validated['budget'] : null,
                'budget_range' => ! empty($validated['budget_range']) ? trim($validated['budget_range']) : null,
                'budget_currency' => $vendor->currency ?: 'AMD',
                'venue_arrangement' => $validated['venue_arrangement'] ?? EventInquiry::VENUE_NO_PREFERENCE,
                'food_beverage_preferences' => ! empty($validated['food_beverage_preferences']) ? trim($validated['food_beverage_preferences']) : null,
                'additional_services' => $validated['additional_services'] ?? null,
                'comments' => ! empty($validated['comments']) ? trim($validated['comments']) : null,
                'status' => EventInquiry::STATUS_NEW,
                'locale' => $customerLocale,
            ]);

            $inquiry->save();

            $inquiry->recordActivity(
                action: 'created',
                description: __('Event planning inquiry submitted online by customer.'),
                metadata: [
                    'ip' => request()->ip(),
                    'user_agent' => substr((string) request()->userAgent(), 0, 255),
                    'guest_count' => $inquiry->guest_count,
                    'event_date' => $inquiry->event_date,
                ]
            );

            return $inquiry;
        });

        // Dispatch notifications safely
        $this->notificationService->notifyNewInquiry($inquiry);

        $defaultConfirmation = __('Thank you for your inquiry! Our team will review your event details and get back to you shortly.');
        $customConfirmation = $settings['confirmation_message'] ?? null;
        $confirmationMessage = filled($customConfirmation) ? $customConfirmation : $defaultConfirmation;

        return response()->json([
            'status' => 'success',
            'message' => $confirmationMessage,
            'reference' => $inquiry->reference,
            'redirect_url' => route('client.event_inquiry.status', [
                'vendor_slug' => $vendor->slug,
                'reference' => $inquiry->reference,
            ]),
        ], 201);
    }

    /**
     * Display confirmation / status page for a submitted inquiry.
     */
    public function showInquiryStatus(Request $request, string $vendor_slug, string $reference): View
    {
        $vendor = Vendor::where('slug', $vendor_slug)->where('is_active', true)->firstOrFail();

        $inquiry = EventInquiry::where('vendor_id', $vendor->id)
            ->where('reference', $reference)
            ->firstOrFail();

        $lang = $request->query('lang') ?: $request->query('locale');
        if ($lang && in_array(strtolower((string) $lang), ['hy', 'en', 'ru'], true)) {
            $locale = strtolower((string) $lang);
            app()->setLocale($locale);
        }

        return view('storefront.event_inquiry_status', compact('vendor', 'inquiry'));
    }
}
