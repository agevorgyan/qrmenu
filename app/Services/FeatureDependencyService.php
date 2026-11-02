<?php

namespace App\Services;

use App\Models\Feature;
use App\Models\FeatureDependency;
use InvalidArgumentException;

class FeatureDependencyService
{
    /**
     * Check if adding a dependency (feature -> dependsOnFeature) would create a circular dependency.
     */
    public function wouldCreateCycle(Feature|int|string $feature, Feature|int|string $dependsOn): bool
    {
        $child = $this->resolveFeature($feature);
        $parent = $this->resolveFeature($dependsOn);

        $featureId = $child?->id ?? (is_numeric($feature) ? (int) $feature : -1);
        $dependsOnFeatureId = $parent?->id ?? (is_numeric($dependsOn) ? (int) $dependsOn : -2);

        if ($featureId === $dependsOnFeatureId) {
            return true;
        }

        $edges = FeatureDependency::where('is_active', true)
            ->get(['feature_id', 'depends_on_feature_id']);

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[(int) $edge->feature_id][] = (int) $edge->depends_on_feature_id;
        }

        // Check if there is already a path from dependsOnFeatureId to featureId
        $visited = [];
        $stack = [$dependsOnFeatureId];

        while (! empty($stack)) {
            $current = array_pop($stack);
            if ($current === $featureId) {
                return true;
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            foreach ($adjacency[$current] ?? [] as $neighbor) {
                if (! isset($visited[$neighbor])) {
                    $stack[] = $neighbor;
                }
            }
        }

        return false;
    }

    /**
     * Add a feature dependency after validating against circular dependencies.
     *
     * @throws InvalidArgumentException
     */
    public function addDependency(
        Feature|int|string $feature,
        Feature|int|string $dependsOn,
        ?array $requiredValue = null
    ): FeatureDependency {
        $child = $this->resolveFeature($feature);
        $parent = $this->resolveFeature($dependsOn);

        if (! $child || ! $parent) {
            throw new InvalidArgumentException('Both feature and required parent feature must exist.');
        }

        if ($this->wouldCreateCycle($child->id, $parent->id)) {
            throw new InvalidArgumentException(
                "Circular dependency detected between '{$child->code}' and '{$parent->code}'."
            );
        }

        return FeatureDependency::updateOrCreate(
            [
                'feature_id' => $child->id,
                'depends_on_feature_id' => $parent->id,
            ],
            [
                'feature_code' => $child->code,
                'depends_on_code' => $parent->code,
                'required_value' => $requiredValue,
                'is_active' => true,
            ]
        );
    }

    /**
     * Remove a feature dependency.
     */
    public function removeDependency(int $dependencyId): bool
    {
        return (bool) FeatureDependency::where('id', $dependencyId)->delete();
    }

    /**
     * Return all direct and transitive required parent feature codes for a given feature code.
     *
     * @return array<int, string>
     */
    public function getRequiredFeatureCodes(string $featureCode): array
    {
        $required = [];
        $visited = [];
        $queue = [$featureCode];

        try {
            $allDeps = FeatureDependency::where('is_active', true)->get(['feature_code', 'depends_on_code']);
        } catch (\Throwable) {
            return [];
        }

        $map = [];
        foreach ($allDeps as $dep) {
            $map[$dep->feature_code][] = $dep->depends_on_code;
        }

        while (! empty($queue)) {
            $curr = array_shift($queue);
            if (isset($visited[$curr])) {
                continue;
            }
            $visited[$curr] = true;

            foreach ($map[$curr] ?? [] as $parentCode) {
                if (! in_array($parentCode, $required, true)) {
                    $required[] = $parentCode;
                }
                if (! isset($visited[$parentCode])) {
                    $queue[] = $parentCode;
                }
            }
        }

        return $required;
    }

    /**
     * Return all child feature codes that depend on the given parent feature code.
     *
     * @return array<int, string>
     */
    public function getDependentFeatureCodes(string $parentFeatureCode): array
    {
        try {
            return FeatureDependency::where('is_active', true)
                ->where('depends_on_code', $parentFeatureCode)
                ->pluck('feature_code')
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Validate a plan feature map and return any dependency warnings (child enabled while parent disabled).
     *
     * @param  array<string, bool>  $enabledMap
     * @return array<int, array{feature_code: string, missing_parent_code: string, message: string}>
     */
    public function validateFeatureMap(array $enabledMap): array
    {
        $warnings = [];

        try {
            $deps = FeatureDependency::where('is_active', true)->get();
        } catch (\Throwable) {
            return [];
        }

        foreach ($deps as $dep) {
            $childEnabled = ! empty($enabledMap[$dep->feature_code]);
            $parentEnabled = ! empty($enabledMap[$dep->depends_on_code]);

            if ($childEnabled && ! $parentEnabled) {
                $warnings[] = [
                    'feature_code' => $dep->feature_code,
                    'missing_parent_code' => $dep->depends_on_code,
                    'message' => "Feature '{$dep->feature_code}' requires '{$dep->depends_on_code}' to be enabled.",
                ];
            }
        }

        return $warnings;
    }

    protected function resolveFeature(Feature|int|string $input): ?Feature
    {
        if ($input instanceof Feature) {
            return $input;
        }
        if (is_int($input) || ctype_digit((string) $input)) {
            return Feature::find((int) $input);
        }

        return Feature::where('code', trim((string) $input))->first();
    }
}
