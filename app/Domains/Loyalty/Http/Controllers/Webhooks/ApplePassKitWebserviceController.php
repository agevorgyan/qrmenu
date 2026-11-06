<?php

namespace App\Domains\Loyalty\Http\Controllers\Webhooks;

use App\Domains\Loyalty\Models\WalletDeviceRegistration;
use App\Domains\Loyalty\Models\WalletPass;
use App\Domains\Loyalty\Wallet\AppleWalletService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ApplePassKitWebserviceController extends Controller
{
    public function __construct(
        protected AppleWalletService $appleWalletService
    ) {}

    /**
     * Register a device to receive push notifications for a pass.
     */
    public function registerDevice(
        Request $request,
        string $deviceLibraryIdentifier,
        string $passTypeIdentifier,
        string $serialNumber
    ): Response|JsonResponse {
        $pass = $this->authenticatePassRequest($request, $passTypeIdentifier, $serialNumber);
        if (! $pass) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $pushToken = $request->input('pushToken');
        if (! $pushToken) {
            return response()->json(['error' => 'pushToken required'], 400);
        }

        $registration = WalletDeviceRegistration::where('pass_id', $pass->id)
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->first();

        if ($registration) {
            $registration->update(['push_token' => $pushToken]);

            return response('', 200);
        }

        WalletDeviceRegistration::create([
            'vendor_id' => $pass->vendor_id,
            'pass_id' => $pass->id,
            'device_library_identifier' => $deviceLibraryIdentifier,
            'push_token' => $pushToken,
        ]);

        return response('', 201);
    }

    /**
     * Unregister a device from receiving push notifications for a pass.
     */
    public function unregisterDevice(
        Request $request,
        string $deviceLibraryIdentifier,
        string $passTypeIdentifier,
        string $serialNumber
    ): Response|JsonResponse {
        $pass = $this->authenticatePassRequest($request, $passTypeIdentifier, $serialNumber);
        if (! $pass) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        WalletDeviceRegistration::where('pass_id', $pass->id)
            ->where('device_library_identifier', $deviceLibraryIdentifier)
            ->delete();

        return response('', 200);
    }

    /**
     * Get the serial numbers of passes associated with a device.
     */
    public function getSerialNumbersForDevice(
        Request $request,
        string $deviceLibraryIdentifier,
        string $passTypeIdentifier
    ): Response|JsonResponse {
        $passesUpdatedSince = $request->query('passesUpdatedSince');

        $registrations = WalletDeviceRegistration::where('device_library_identifier', $deviceLibraryIdentifier)
            ->whereHas('pass', function ($q) use ($passTypeIdentifier, $passesUpdatedSince) {
                $q->where('pass_type_identifier', $passTypeIdentifier);
                if ($passesUpdatedSince) {
                    $q->where('updated_at', '>', $passesUpdatedSince);
                }
            })
            ->with('pass')
            ->get();

        if ($registrations->isEmpty()) {
            return response('', 204);
        }

        $serials = $registrations->pluck('pass.serial_number')->all();

        return response()->json([
            'lastUpdated' => (string) now()->timestamp,
            'serialNumbers' => $serials,
        ]);
    }

    /**
     * Deliver the latest version of a pass (.pkpass).
     */
    public function deliverPass(
        Request $request,
        string $passTypeIdentifier,
        string $serialNumber
    ): Response|JsonResponse {
        $pass = $this->authenticatePassRequest($request, $passTypeIdentifier, $serialNumber);
        if (! $pass) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $pkpassBinary = $this->appleWalletService->generatePkPass($pass);

            return response($pkpassBinary, 200, [
                'Content-Type' => 'application/vnd.apple.pkpass',
                'Content-Disposition' => "attachment; filename=\"pass_{$pass->serial_number}.pkpass\"",
                'Last-Modified' => $pass->updated_at->toRfc7231String(),
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to generate .pkpass for {$serialNumber}: ".$e->getMessage());

            return response()->json(['error' => 'Pass generation failed'], 500);
        }
    }

    /**
     * Log messages sent by Apple devices.
     */
    public function log(Request $request): Response
    {
        $logs = $request->input('logs', []);
        Log::info('Apple PassKit Device Log: '.json_encode($logs));

        return response('', 200);
    }

    /**
     * Authenticate pass request by checking ApplePass authorization header.
     */
    protected function authenticatePassRequest(Request $request, string $passTypeIdentifier, string $serialNumber): ?WalletPass
    {
        $authHeader = $request->header('Authorization', '');
        if (! str_starts_with($authHeader, 'ApplePass ')) {
            return null;
        }

        $token = substr($authHeader, 10);

        return WalletPass::where('pass_type_identifier', $passTypeIdentifier)
            ->where('serial_number', $serialNumber)
            ->where('authentication_token', $token)
            ->first();
    }
}
