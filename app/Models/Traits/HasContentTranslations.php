<?php

namespace App\Models\Traits;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContentTranslations
{
    public static function bootHasContentTranslations(): void
    {
        static::updating(function ($model) {
            $translatableFields = $model->getTranslatableFields();
            foreach ($translatableFields as $field) {
                if ($model->isDirty($field)) {
                    $newSource = (string) $model->{$field};
                    $newHash = ContentTranslation::computeHash($newSource);

                    // Mark existing translations as outdated if source text changed
                    $model->contentTranslations()
                        ->where('field', $field)
                        ->where('source_hash', '!=', $newHash)
                        ->update([
                            'is_outdated' => true,
                            'status' => ContentTranslation::STATUS_STALE,
                        ]);
                }
            }
        });
    }

    /**
     * Polymorphic relationship to all content translation records for this entity.
     */
    public function contentTranslations(): MorphMany
    {
        return $this->morphMany(ContentTranslation::class, 'translatable');
    }

    /**
     * Get list of translatable attribute names for this model.
     *
     * @return array<int, string>
     */
    public function getTranslatableFields(): array
    {
        return property_exists($this, 'translatableFields') ? $this->translatableFields : ['name', 'description'];
    }

    /**
     * Resolve a translated field value for the current or specified locale.
     */
    public function getTranslatedAttribute(string $field, ?string $locale = null, bool $fallbackToOriginal = true): string
    {
        $locale = $locale ?: app()->getLocale();

        // 1. Try JSON column on model if present (fast path)
        $jsonColumn = "{$field}_translations";
        if (isset($this->{$jsonColumn}) && is_array($this->{$jsonColumn})) {
            if (! empty($this->{$jsonColumn}[$locale])) {
                return $this->{$jsonColumn}[$locale];
            }
        }

        // 2. Try normalized content_translations table
        if ($this->relationLoaded('contentTranslations')) {
            $trans = $this->contentTranslations
                ->where('field', $field)
                ->where('locale', $locale)
                ->where('status', ContentTranslation::STATUS_PUBLISHED)
                ->first();
            if ($trans && ! empty($trans->translation)) {
                return $trans->translation;
            }
        }

        // 3. Fallback to original attribute value
        return $fallbackToOriginal ? (string) ($this->{$field} ?? '') : '';
    }

    /**
     * Set or update a translation record for a specific field and locale.
     */
    public function setTranslation(
        string $field,
        string $locale,
        string $translation,
        string $status = ContentTranslation::STATUS_PUBLISHED,
        string $sourceType = ContentTranslation::SOURCE_MANUAL,
        ?int $userId = null,
        ?string $aiProvider = null,
        ?string $aiModel = null
    ): ContentTranslation {
        $sourceText = (string) ($this->{$field} ?? '');
        $sourceHash = ContentTranslation::computeHash($sourceText);
        $vendorId = (int) ($this->vendor_id ?? 0);

        /** @var ContentTranslation $record */
        $record = $this->contentTranslations()->firstOrNew([
            'vendor_id' => $vendorId,
            'field' => $field,
            'locale' => strtolower($locale),
        ]);

        $record->source_locale = 'en';
        $record->source_text = $sourceText;
        $record->source_hash = $newHash = $sourceHash;
        $record->translation = $translation;
        $record->status = $status;
        $record->source_type = $sourceType;
        $record->is_outdated = false;

        if ($aiProvider) {
            $record->ai_provider = $aiProvider;
            $record->ai_model = $aiModel;
        }

        if ($status === ContentTranslation::STATUS_APPROVED || $status === ContentTranslation::STATUS_PUBLISHED) {
            $record->reviewed_by = $userId;
            $record->reviewed_at = now();
        }

        if ($status === ContentTranslation::STATUS_PUBLISHED) {
            $record->published_at = now();
        }

        $record->save();
        $record->recordHistory($userId);

        // If published, sync to fast-access JSON column on parent model
        if ($status === ContentTranslation::STATUS_PUBLISHED) {
            $this->syncPublishedToJson($field);
        }

        return $record;
    }

    /**
     * Synchronize published translations into model's JSON column (e.g. name_translations).
     */
    public function syncPublishedToJson(string $field): void
    {
        $jsonColumn = "{$field}_translations";
        $table = $this->getTable();

        // Check if column exists on model attributes/schema
        $published = $this->contentTranslations()
            ->where('field', $field)
            ->where('status', ContentTranslation::STATUS_PUBLISHED)
            ->pluck('translation', 'locale')
            ->toArray();

        $this->updateQuietly([
            $jsonColumn => $published,
        ]);

        if (! empty($this->vendor_id)) {
            \App\Services\TenantCache::invalidateMenu((int) $this->vendor_id);
        }
    }
}
