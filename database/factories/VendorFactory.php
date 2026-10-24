<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.uniqid(),
            'type' => 'restaurant',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'legal_name' => $name.' LLC',
            'legal_address' => fake()->address(),
            'operating_address' => fake()->address(),
            'tax_id' => (string) fake()->numberBetween(10000000, 99999999),
            'director_name' => fake()->name(),
            'contact_person_name' => fake()->name(),
            'is_active' => true,
            'subscription_status' => 'active',
        ];
    }
}
