<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Vendor;

class CustomerSyncService
{
    /**
     * Find an existing customer by phone or email for the given vendor,
     * or create a new customer record, updating contact details and consent.
     */
    public function syncCustomerFromOrder(
        Vendor $vendor,
        ?string $name,
        ?string $phone,
        ?string $email,
        bool $marketingOptIn,
        int|string|null $locationId = null,
        ?string $birthdate = null,
        ?string $address = null
    ): ?Customer {
        $phone = ! empty($phone) ? trim($phone) : null;
        $email = ! empty($email) ? strtolower(trim($email)) : null;
        $name = ! empty($name) ? trim($name) : null;

        $customer = null;

        if ($phone || $email) {
            $customer = Customer::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) {
                        $q->where('phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })->first();
        }

        if ($customer) {
            $updateData = [
                'name' => ($name && $name !== 'Guest') ? $name : $customer->name,
                'phone' => $phone ?: $customer->phone,
                'email' => $email ?: $customer->email,
                'birthdate' => $birthdate ?: $customer->birthdate,
                'address' => $address ?: $customer->address,
                'marketing_opt_in' => $marketingOptIn,
                'last_order_at' => now(),
            ];

            if (! $customer->location_id && $locationId) {
                $updateData['location_id'] = $locationId;
            }

            $customer->update($updateData);

            return $customer;
        }

        // Create new customer if there's identifying information
        if ($phone || $email || ($name && $name !== 'Guest')) {
            return Customer::create([
                'vendor_id' => $vendor->id,
                'location_id' => $locationId,
                'name' => $name ?: 'Guest',
                'phone' => $phone,
                'email' => $email,
                'birthdate' => $birthdate,
                'address' => $address,
                'marketing_opt_in' => $marketingOptIn,
                'last_order_at' => now(),
            ]);
        }

        return null;
    }

    /**
     * Find an existing customer by phone or email for the given vendor,
     * or create a new customer record from a table reservation.
     * Enforces strict vendor tenant isolation.
     *
     * @param  array{name?: ?string, phone?: ?string, email?: ?string}  $data
     */
    public function syncCustomerFromReservation(
        Vendor $vendor,
        array $data,
        ?int $locationId = null
    ): ?Customer {
        $phone = ! empty($data['phone']) ? trim($data['phone']) : null;
        $email = ! empty($data['email']) ? strtolower(trim($data['email'])) : null;
        $name = ! empty($data['name']) ? trim($data['name']) : null;

        $customer = null;

        if ($phone || $email) {
            $customer = Customer::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($phone, $email) {
                    if ($phone) {
                        $q->where('phone', $phone);
                    }
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                })->first();
        }

        if ($customer) {
            $updateData = [];

            // Update name if current name is 'Guest' or empty and new name is provided
            if ($name && ($customer->name === 'Guest' || empty($customer->name))) {
                $updateData['name'] = $name;
            }

            // Fill missing phone or email
            if (! $customer->phone && $phone) {
                $updateData['phone'] = $phone;
            }
            if (! $customer->email && $email) {
                $updateData['email'] = $email;
            }

            // Fill missing location
            if (! $customer->location_id && $locationId) {
                $updateData['location_id'] = $locationId;
            }

            if (! empty($updateData)) {
                $customer->update($updateData);
            }

            return $customer;
        }

        // Create new customer if there is identifying information
        if ($phone || $email || ($name && $name !== 'Guest')) {
            return Customer::create([
                'vendor_id' => $vendor->id,
                'location_id' => $locationId,
                'name' => $name ?: 'Guest',
                'phone' => $phone,
                'email' => $email,
                'marketing_opt_in' => true,
            ]);
        }

        return null;
    }

    /**
     * Create a customer directly from admin.
     */
    public function createCustomer(Vendor $vendor, array $data, bool $marketingOptIn = false): Customer
    {
        return Customer::create([
            'vendor_id' => $vendor->id,
            'location_id' => $data['location_id'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'marketing_opt_in' => $marketingOptIn,
        ]);
    }

    /**
     * Update customer details.
     */
    public function updateCustomer(Customer $customer, array $data, bool $marketingOptIn = false): Customer
    {
        return tap($customer)->update([
            'name' => $data['name'],
            'location_id' => $data['location_id'] ?? $customer->location_id,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'birthdate' => array_key_exists('birthdate', $data) ? $data['birthdate'] : $customer->birthdate,
            'address' => array_key_exists('address', $data) ? $data['address'] : $customer->address,
            'notes' => $data['notes'] ?? null,
            'marketing_opt_in' => $marketingOptIn,
        ]);
    }

    /**
     * Delete customer record.
     */
    public function deleteCustomer(Customer $customer): void
    {
        $customer->delete();
    }

    /**
     * Recalculate lifetime statistics for the customer.
     */
    public function recalculateStats(Customer $customer): void
    {
        $customer->recalculateStats();
    }
}
