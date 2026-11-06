<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\Vendor;
use App\Services\UnitConversionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use Tests\TestCase;

class UnitConversionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected UnitConversionService $service;

    protected Unit $kg;

    protected Unit $g;

    protected Unit $ton;

    protected Unit $liter;

    protected Unit $ml;

    protected Unit $piece;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UnitConversionService::class);

        $this->kg = Unit::whereNull('vendor_id')->where('code', 'kg')->firstOrFail();
        $this->g = Unit::whereNull('vendor_id')->where('code', 'g')->firstOrFail();
        $this->ton = Unit::whereNull('vendor_id')->where('code', 'ton')->firstOrFail();
        $this->liter = Unit::whereNull('vendor_id')->where('code', 'l')->firstOrFail();
        $this->ml = Unit::whereNull('vendor_id')->where('code', 'ml')->firstOrFail();
        $this->piece = Unit::whereNull('vendor_id')->where('code', 'piece')->firstOrFail();
    }

    public function test_same_unit_returns_identical_quantity(): void
    {
        $result = $this->service->convert(15.5, $this->kg, $this->kg);
        $this->assertEquals(15.5, $result);
    }

    public function test_metric_mass_conversions(): void
    {
        // 2.5 kg -> g
        $grams = $this->service->convert(2.5, $this->kg, $this->g);
        $this->assertEquals(2500.0, $grams);

        // 750 g -> kg
        $kg = $this->service->convert(750, $this->g, $this->kg);
        $this->assertEquals(0.75, $kg);

        // 2 tons -> kg
        $tonToKg = $this->service->convert(2, $this->ton, $this->kg);
        $this->assertEquals(2000.0, $tonToKg);
    }

    public function test_metric_volume_conversions(): void
    {
        // 1.5 l -> ml
        $ml = $this->service->convert(1.5, $this->liter, $this->ml);
        $this->assertEquals(1500.0, $ml);

        // 250 ml -> l
        $liters = $this->service->convert(250, $this->ml, $this->liter);
        $this->assertEquals(0.25, $liters);
    }

    public function test_density_based_conversion_between_mass_and_volume(): void
    {
        $vendor = Vendor::firstOrCreate(
            ['email' => 'unit-test@example.com'],
            ['name' => 'Unit Test Vendor', 'slug' => 'unit-test', 'password' => bcrypt('password'), 'currency' => 'AMD']
        );

        // Olive oil with density 0.92 kg / Liter
        $oil = Ingredient::create([
            'vendor_id' => $vendor->id,
            'name' => 'Extra Virgin Olive Oil',
            'unit_id' => $this->liter->id,
            'density_kg_per_l' => 0.9200,
            'type' => 'ingredient',
        ]);

        // 1 Liter of olive oil should convert to 0.92 kg
        $weightInKg = $this->service->convert(1.0, $this->liter, $this->kg, $oil);
        $this->assertEquals(0.92, round($weightInKg, 4));

        // 1.84 kg of olive oil should convert to 2.0 Liters
        $volumeInLiters = $this->service->convert(1.84, $this->kg, $this->liter, $oil);
        $this->assertEquals(2.0, round($volumeInLiters, 4));

        // 500 ml of olive oil -> kg: 0.5 l * 0.92 = 0.46 kg = 460 g
        $grams = $this->service->convert(500, $this->ml, $this->g, $oil);
        $this->assertEquals(460.0, round($grams, 2));
    }

    public function test_custom_item_specific_conversion_via_table(): void
    {
        $vendor = Vendor::firstOrCreate(
            ['email' => 'unit-test-2@example.com'],
            ['name' => 'Unit Test Vendor 2', 'slug' => 'unit-test-2', 'password' => bcrypt('password'), 'currency' => 'AMD']
        );

        $boxUnit = Unit::firstOrCreate(
            ['vendor_id' => $vendor->id, 'code' => 'box_12'],
            ['name' => 'Box of 12', 'type' => 'packaging', 'base_factor' => 1.0, 'is_system' => false]
        );

        $eggs = Ingredient::create([
            'vendor_id' => $vendor->id,
            'name' => 'Farm Fresh Eggs',
            'unit_id' => $this->piece->id,
            'type' => 'ingredient',
        ]);

        // 1 box_12 = 12 pieces
        UnitConversion::create([
            'vendor_id' => $vendor->id,
            'ingredient_id' => $eggs->id,
            'from_unit_id' => $boxUnit->id,
            'to_unit_id' => $this->piece->id,
            'factor' => 12.0,
        ]);

        $pieces = $this->service->convert(3, $boxUnit, $this->piece, $eggs);
        $this->assertEquals(36.0, $pieces);

        // Reverse: 24 pieces -> 2 boxes
        $boxes = $this->service->convert(24, $this->piece, $boxUnit, $eggs);
        $this->assertEquals(2.0, $boxes);
    }

    public function test_illegal_cross_dimension_conversion_without_density_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert between unit');

        // Cannot convert kg to piece without conversion rule
        $this->service->convert(5, $this->kg, $this->piece);
    }
}
