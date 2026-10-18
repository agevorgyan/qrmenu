<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationAvailabilityService
{
    /**
     * Get available dates within booking window.
     *
     * @return array<int, array{date: string, formatted: string, day_name: string}>
     */
    public function getAvailableDates(Vendor $vendor, ?Location $location = null): array
    {
        $settings = $vendor->reservation_settings;
        if (! ($settings['enabled'] ?? true)) {
            return [];
        }

        $tz = $vendor->timezone ?: 'Asia/Yerevan';
        $today = Carbon::now($tz)->startOfDay();
        $advanceDays = max(1, (int) ($settings['advance_booking_days'] ?? 30));
        $operatingDays = (array) ($settings['operating_days'] ?? [0, 1, 2, 3, 4, 5, 6]);

        $dates = [];
        for ($i = 0; $i < $advanceDays; $i++) {
            $candidate = $today->copy()->addDays($i);
            $dayOfWeek = $candidate->dayOfWeek; // 0 (Sunday) to 6 (Saturday)

            if (! in_array($dayOfWeek, $operatingDays, false)) {
                continue;
            }

            $dates[] = [
                'date' => $candidate->format('Y-m-d'),
                'formatted' => $candidate->translatedFormat('D, d M'),
                'day_name' => $candidate->translatedFormat('l'),
            ];
        }

        return $dates;
    }

    /**
     * Get available time slots for a given date and party size.
     *
     * @return array<int, array{time: string, available: bool, available_tables_count: int, eligible_tables: array}>
     */
    public function getAvailableTimeSlots(
        Vendor $vendor,
        string $date,
        int $guestCount,
        ?Location $location = null,
        ?int $preferredTableId = null
    ): array {
        $settings = $vendor->reservation_settings;
        if (! ($settings['enabled'] ?? true)) {
            return [];
        }

        $minParty = (int) ($settings['min_party_size'] ?? 1);
        $maxParty = (int) ($settings['max_party_size'] ?? 20);

        if ($guestCount < $minParty || $guestCount > $maxParty) {
            return [];
        }

        $tz = $vendor->timezone ?: 'Asia/Yerevan';
        $now = Carbon::now($tz);
        $candidateDate = Carbon::parse($date, $tz)->startOfDay();

        // Check if date is in the past
        if ($candidateDate->lt($now->copy()->startOfDay())) {
            return [];
        }

        $openTimeStr = $settings['opening_time'] ?? '10:00';
        $closeTimeStr = $settings['closing_time'] ?? '23:00';
        $intervalMins = max(15, (int) ($settings['slot_interval_minutes'] ?? 30));
        $durationMins = max(30, (int) ($settings['default_duration_minutes'] ?? 90));
        $bufferMins = max(0, (int) ($settings['buffer_minutes'] ?? 15));
        $cutoffMins = max(0, (int) ($settings['booking_cutoff_minutes'] ?? 60));

        $openDt = Carbon::parse("{$date} {$openTimeStr}", $tz);
        $closeDt = Carbon::parse("{$date} {$closeTimeStr}", $tz);

        // Ensure tables exist for vendor/location
        $this->ensureTablesExistForVendor($vendor, $location);

        // Fetch candidate active tables for this party size
        $tablesQuery = RestaurantTable::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('is_active', true)
            ->where('capacity', '>=', $guestCount)
            ->where('min_capacity', '<=', $guestCount)
            ->when($location, fn ($q) => $q->where(function ($sub) use ($location) {
                $sub->where('location_id', $location->id)
                    ->orWhereNull('location_id');
            }))
            ->when($preferredTableId, fn ($q) => $q->where('id', $preferredTableId));

        $candidateTables = $tablesQuery->get();
        if ($candidateTables->isEmpty()) {
            return [];
        }

        // Fetch all active reservations for candidate tables on this date window
        $dateStartUtc = $openDt->copy()->subHours(2)->utc();
        $dateEndUtc = $closeDt->copy()->addHours(3)->utc();

        $activeReservations = Reservation::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->whereIn('restaurant_table_id', $candidateTables->pluck('id'))
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED, Reservation::STATUS_SEATED])
            ->whereBetween('reservation_datetime', [$dateStartUtc, $dateEndUtc])
            ->get();

        $slots = [];
        $currentSlot = $openDt->copy();

        while ($currentSlot->copy()->addMinutes($durationMins)->lte($closeDt)) {
            $timeStr = $currentSlot->format('H:i');
            $slotStartUtc = $currentSlot->copy()->utc();
            $slotEndUtc = $slotStartUtc->copy()->addMinutes($durationMins);
            $bufferedStartUtc = $slotStartUtc->copy()->subMinutes($bufferMins);
            $bufferedEndUtc = $slotEndUtc->copy()->addMinutes($bufferMins);

            // Cutoff check for today
            $isCutoff = $currentSlot->copy()->subMinutes($cutoffMins)->lt($now);

            $availableTables = [];
            if (! $isCutoff) {
                foreach ($candidateTables as $table) {
                    $hasOverlap = $activeReservations->contains(function (Reservation $res) use ($table, $bufferedStartUtc, $bufferedEndUtc) {
                        if ((int) $res->restaurant_table_id !== (int) $table->id) {
                            return false;
                        }

                        $resStartUtc = $res->reservation_datetime;
                        $resDuration = $res->duration_minutes ?: 90;
                        $resEndUtc = $resStartUtc ? $resStartUtc->copy()->addMinutes($resDuration) : null;

                        if (! $resStartUtc || ! $resEndUtc) {
                            return false;
                        }

                        // Overlap condition
                        return $resStartUtc->lt($bufferedEndUtc) && $resEndUtc->gt($bufferedStartUtc);
                    });

                    if (! $hasOverlap) {
                        $availableTables[] = [
                            'id' => $table->id,
                            'number' => $table->table_number,
                            'name' => $table->name,
                            'display_name' => $table->display_name,
                            'capacity' => $table->capacity,
                            'section' => $table->section,
                        ];
                    }
                }
            }

            $slots[] = [
                'time' => $timeStr,
                'available' => count($availableTables) > 0,
                'available_tables_count' => count($availableTables),
                'eligible_tables' => $availableTables,
            ];

            $currentSlot->addMinutes($intervalMins);
        }

        return $slots;
    }

    /**
     * Atomically allocate and lock a table for a reservation using PostgreSQL row-level locks.
     *
     * @throws ValidationException
     */
    public function allocateTableAtomic(
        Vendor $vendor,
        string $date,
        string $time,
        int $guestCount,
        ?int $preferredTableId = null,
        ?Location $location = null,
        int $durationMinutes = 90,
        int $bufferMinutes = 15
    ): RestaurantTable {
        $tz = $vendor->timezone ?: 'Asia/Yerevan';
        $localStart = Carbon::parse("{$date} {$time}", $tz);
        $slotStartUtc = $localStart->copy()->utc();
        $slotEndUtc = $slotStartUtc->copy()->addMinutes($durationMinutes);
        $bufferedStartUtc = $slotStartUtc->copy()->subMinutes($bufferMinutes);
        $bufferedEndUtc = $slotEndUtc->copy()->addMinutes($bufferMinutes);

        // Must run within an active transaction to preserve row locks
        $allocatedTable = DB::transaction(function () use (
            $vendor,
            $location,
            $guestCount,
            $preferredTableId,
            $bufferedStartUtc,
            $bufferedEndUtc
        ) {
            // 1. Query candidate tables with capacity and lock them exclusively
            $query = RestaurantTable::where('vendor_id', $vendor->id)
                ->where('is_active', true)
                ->where('capacity', '>=', $guestCount)
                ->where('min_capacity', '<=', $guestCount)
                ->when($location, fn ($q) => $q->where(function ($sub) use ($location) {
                    $sub->where('location_id', $location->id)
                        ->orWhereNull('location_id');
                }))
                ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$preferredTableId ?? 0])
                ->orderBy('capacity', 'asc')
                ->orderBy('id', 'asc')
                ->lockForUpdate();

            $candidates = $query->get();

            if ($candidates->isEmpty()) {
                throw ValidationException::withMessages([
                    'guest_count' => "No tables available with capacity for {$guestCount} guests.",
                ]);
            }

            // 2. Iterate through locked candidate tables to find first non-overlapping table
            foreach ($candidates as $candidate) {
                // Check if candidate table has overlapping active reservations in PostgreSQL
                $hasConflict = Reservation::where('restaurant_table_id', $candidate->id)
                    ->whereIn('status', [
                        Reservation::STATUS_PENDING,
                        Reservation::STATUS_CONFIRMED,
                        Reservation::STATUS_SEATED,
                    ])
                    ->where(function ($q) use ($bufferedStartUtc, $bufferedEndUtc) {
                        $q->where('reservation_datetime', '<', $bufferedEndUtc)
                            ->whereRaw('(reservation_datetime + (duration_minutes || \' minutes\')::interval) > ?', [$bufferedStartUtc]);
                    })
                    ->exists();

                if (! $hasConflict) {
                    return $candidate;
                }
            }

            throw ValidationException::withMessages([
                'reservation_time' => 'All suitable tables are already booked for this time slot. Please select another time.',
            ]);
        });

        return $allocatedTable;
    }

    /**
     * Auto-sync or generate restaurant_tables from vendor floor plan or table count if empty.
     */
    public function ensureTablesExistForVendor(Vendor $vendor, ?Location $location = null): void
    {
        if (! $location && $vendor->locations()->exists()) {
            $location = $vendor->locations()->first();
        }

        $hasTables = RestaurantTable::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->exists();

        if ($hasTables) {
            return;
        }

        $floorPlan = $vendor->getFloorPlanData();
        $halls = collect($floorPlan['halls'] ?? [])->keyBy('id');
        $rawTables = $floorPlan['tables'] ?? [];

        if (! empty($rawTables)) {
            foreach ($rawTables as $t) {
                $hall = $halls->get($t['hall_id'] ?? '')['name'] ?? 'Main Hall';
                RestaurantTable::firstOrCreate([
                    'vendor_id' => $vendor->id,
                    'location_id' => $location?->id,
                    'table_number' => (string) ($t['number'] ?? $t['id'] ?? '1'),
                ], [
                    'name' => (string) ($t['name'] ?? 'Table '.($t['number'] ?? '1')),
                    'capacity' => intval($t['capacity'] ?? 4),
                    'min_capacity' => 1,
                    'section' => $hall,
                    'is_active' => true,
                    'sort_order' => intval($t['id'] ?? 0),
                ]);
            }
        } else {
            $tableCount = $location?->table_count ?? 12;
            for ($i = 1; $i <= $tableCount; $i++) {
                RestaurantTable::firstOrCreate([
                    'vendor_id' => $vendor->id,
                    'location_id' => $location?->id,
                    'table_number' => (string) $i,
                ], [
                    'name' => "Table {$i}",
                    'capacity' => ($i % 3 === 0) ? 6 : 4,
                    'min_capacity' => 1,
                    'section' => 'Main Hall',
                    'is_active' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
