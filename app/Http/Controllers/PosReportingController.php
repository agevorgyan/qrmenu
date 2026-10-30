<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Security\Permission;
use App\Services\PosReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosReportingController extends Controller
{
    public function __construct(
        protected PosReportingService $reportingService
    ) {}

    /**
     * Authorize user access to POS reporting.
     */
    protected function authorizeReporting(Request $request): Vendor
    {
        $user = $request->user();

        if (! in_array($user->role, ['vendor_owner', 'manager', 'cashier'], true) && ! $user->hasPermission(Permission::POS_REPORTS)) {
            abort(403, 'Unauthorized access to POS reports.');
        }

        return $user->vendor ?: Vendor::find($user->vendor_id);
    }

    /**
     * Sales analytics report.
     */
    public function sales(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $data = $this->reportingService->getSalesAnalytics($vendor, $locationId, $start, $end);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Employee performance report.
     */
    public function employees(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $data = $this->reportingService->getEmployeeReports($vendor, $locationId, $start, $end);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Product performance report.
     */
    public function products(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $limit = $request->query('limit', 50);

        $data = $this->reportingService->getProductReports($vendor, $locationId, $start, $end, (int) $limit);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Category performance report.
     */
    public function categories(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $data = $this->reportingService->getCategoryReports($vendor, $locationId, $start, $end);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Cash and shift reconciliation report.
     */
    public function cash(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $data = $this->reportingService->getCashReports($vendor, $locationId, $start, $end);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Tip summary and distribution report.
     */
    public function tips(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $data = $this->reportingService->getTipReports($vendor, $locationId, $start, $end);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Discount and refund supervision report.
     */
    public function discountsAndRefunds(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;
        $start = $request->query('start_date');
        $end = $request->query('end_date');

        $data = $this->reportingService->getDiscountAndRefundReports($vendor, $locationId, $start, $end);

        return response()->json([
            'success' => true,
            'report' => $data,
        ]);
    }

    /**
     * Live operational dashboard for manager mode.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $vendor = $this->authorizeReporting($request);
        $locationId = $request->query('location_id') ? (int) $request->query('location_id') : null;

        $data = $this->reportingService->getOperationalDashboard($vendor, $locationId);

        return response()->json([
            'success' => true,
            'dashboard' => $data,
        ]);
    }
}
