<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DynamicSubscriptionSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SubscriptionPlanSeeder::class);
    }
}
