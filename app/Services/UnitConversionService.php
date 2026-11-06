<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Unit;
use App\Models\UnitConversion;
use InvalidArgumentException;

class UnitConversionService
{
    /**
     * Convert quantity from one unit to another.
     *
     * @param  float  $quantity  Quantity in fromUnit
     * @param  Unit|int  $fromUnit  Source unit
     * @param  Unit|int  $toUnit  Target unit
     * @param  Ingredient|null  $ingredient  Optional ingredient for cross-dimension or density-based conversion
     * @return float Converted quantity
     *
     * @throws InvalidArgumentException
     */
    public function convert(float $quantity, Unit|int $fromUnit, Unit|int $toUnit, ?Ingredient $ingredient = null): float
    {
        $from = $fromUnit instanceof Unit ? $fromUnit : Unit::findOrFail($fromUnit);
        $to = $toUnit instanceof Unit ? $toUnit : Unit::findOrFail($toUnit);

        // 1. Same unit identity
        if ($from->id === $to->id || $from->code === $to->code) {
            return $quantity;
        }

        // 2. Check for explicit item-specific conversion in unit_conversions
        if ($ingredient) {
            $itemConversion = UnitConversion::where('ingredient_id', $ingredient->id)
                ->where('from_unit_id', $from->id)
                ->where('to_unit_id', $to->id)
                ->first();

            if ($itemConversion) {
                return $quantity * (float) $itemConversion->factor;
            }

            // Reverse check
            $reverseItemConversion = UnitConversion::where('ingredient_id', $ingredient->id)
                ->where('from_unit_id', $to->id)
                ->where('to_unit_id', $from->id)
                ->first();

            if ($reverseItemConversion && (float) $reverseItemConversion->factor > 0) {
                return $quantity / (float) $reverseItemConversion->factor;
            }
        }

        // 3. Same physical dimension (mass <-> mass, volume <-> volume, etc.)
        if ($from->type === $to->type && in_array($from->type, ['mass', 'volume'], true)) {
            $fromFactor = (float) $from->base_factor;
            $toFactor = (float) $to->base_factor;

            if ($toFactor <= 0) {
                throw new InvalidArgumentException("Invalid base factor for unit [{$to->code}].");
            }

            // Convert to base unit, then to target unit
            $baseQuantity = $quantity * $fromFactor;

            return $baseQuantity / $toFactor;
        }

        // 4. Cross-dimension (mass <-> volume) using ingredient density (kg/l)
        if ($ingredient && (float) $ingredient->density_kg_per_l > 0) {
            $density = (float) $ingredient->density_kg_per_l; // kg per 1 Liter

            // Mass to Volume: (quantity -> kg) -> (/ density -> Liters) -> target volume unit
            if ($from->type === 'mass' && $to->type === 'volume') {
                $kg = $quantity * (float) $from->base_factor;
                $liters = $kg / $density;

                return $liters / (float) $to->base_factor;
            }

            // Volume to Mass: (quantity -> Liters) -> (* density -> kg) -> target mass unit
            if ($from->type === 'volume' && $to->type === 'mass') {
                $liters = $quantity * (float) $from->base_factor;
                $kg = $liters * $density;

                return $kg / (float) $to->base_factor;
            }
        }

        // 5. Check global unit_conversions table
        $globalConversion = UnitConversion::whereNull('ingredient_id')
            ->where('from_unit_id', $from->id)
            ->where('to_unit_id', $to->id)
            ->first();

        if ($globalConversion) {
            return $quantity * (float) $globalConversion->factor;
        }

        // Reverse global conversion
        $reverseGlobalConversion = UnitConversion::whereNull('ingredient_id')
            ->where('from_unit_id', $to->id)
            ->where('to_unit_id', $from->id)
            ->first();

        if ($reverseGlobalConversion && (float) $reverseGlobalConversion->factor > 0) {
            return $quantity / (float) $reverseGlobalConversion->factor;
        }

        throw new InvalidArgumentException(
            "Cannot convert between unit [{$from->code}] ({$from->type}) and [{$to->code}] ({$to->type}) without an explicit conversion rate or ingredient density."
        );
    }
}
