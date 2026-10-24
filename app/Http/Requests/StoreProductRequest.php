<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! Auth::check() || ! Auth::user()->vendor_id) {
            return false;
        }

        if ($this->filled('category_id')) {
            $category = Category::find($this->input('category_id'));
            if (! $category || (int) $category->vendor_id !== (int) Auth::user()->vendor_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('ai_tags') && is_string($this->input('ai_tags'))) {
            $merge['ai_tags'] = array_values(array_filter(array_map('trim', explode(',', $this->input('ai_tags')))));
        }
        if ($this->has('ai_priority')) {
            $merge['ai_priority'] = $this->boolean('ai_priority');
        }
        if ($this->has('ai_enabled')) {
            $merge['ai_enabled'] = $this->boolean('ai_enabled');
        }
        if ($this->has('available_for_dine_in')) {
            $merge['available_for_dine_in'] = $this->boolean('available_for_dine_in');
        }
        if ($this->has('available_for_takeaway')) {
            $merge['available_for_takeaway'] = $this->boolean('available_for_takeaway');
        }
        if ($this->has('available_for_delivery')) {
            $merge['available_for_delivery'] = $this->boolean('available_for_delivery');
        }
        if ($this->has('is_available')) {
            $merge['is_available'] = $this->boolean('is_available');
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $imageRule = extension_loaded('fileinfo')
            ? ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,avif', 'max:5120']
            : ['nullable', 'file', 'max:5120', function ($attribute, $value, $fail) {
                if ($value instanceof UploadedFile) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['jpeg', 'jpg', 'png', 'webp', 'avif'], true)) {
                        $fail('The '.$attribute.' must be a valid image file (jpeg, png, jpg, webp, avif).');
                    }
                }
            }];

        return [
            'category_id' => ['required', Rule::exists('categories', 'id')->where('vendor_id', Auth::user()?->vendor_id)],
            'name' => 'required|string|max:255',
            'hy_name' => 'nullable|string',
            'ru_name' => 'nullable|string',
            'name_translations' => 'nullable|array',
            'name_translations.*' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'hy_description' => 'nullable|string',
            'ru_description' => 'nullable|string',
            'description_translations' => 'nullable|array',
            'description_translations.*' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'discount_days' => 'nullable|array',
            'discount_days.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'discount_start_time' => 'nullable|date_format:H:i',
            'discount_end_time' => 'nullable|date_format:H:i',
            'is_discount_active' => 'nullable|boolean',
            'available_start_time' => 'nullable|date_format:H:i',
            'available_end_time' => 'nullable|date_format:H:i',
            'available_days' => 'nullable|array',
            'available_days.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'available_for_dine_in' => 'nullable|boolean',
            'available_for_takeaway' => 'nullable|boolean',
            'available_for_delivery' => 'nullable|boolean',
            'is_available' => 'nullable|boolean',
            'locations' => 'nullable|array',
            'locations.*' => ['integer', Rule::exists('locations', 'id')->where('vendor_id', Auth::user()?->vendor_id)],
            'image' => 'nullable|string',
            'image_file' => $imageRule,
            'dietary_tags' => 'nullable|array',
            'allergens' => 'nullable|array',
            'calories' => 'nullable|integer',
            'protein_g' => 'nullable|numeric',
            'carbs_g' => 'nullable|numeric',
            'fat_g' => 'nullable|numeric',
            'preparation_time_min' => 'nullable|integer',
            'is_featured' => 'nullable|boolean',
            'is_daily_offer' => 'nullable|boolean',
            'ai_priority' => 'nullable|boolean',
            'ai_priority_level' => 'nullable|integer|min:0|max:100',
            'ai_group' => 'nullable|string|max:50',
            'ai_tags' => 'nullable|array',
            'ai_spicy_level' => 'nullable|integer|min:0|max:3',
            'ai_pairs_with' => 'nullable|array',
            'ai_enabled' => 'nullable|boolean',
            'variations' => 'nullable|array',
            'variations.*.id' => 'nullable|integer',
            'variations.*.name' => 'nullable|string|max:255',
            'variations.*.hy_name' => 'nullable|string|max:255',
            'variations.*.ru_name' => 'nullable|string|max:255',
            'variations.*.name_translations' => 'nullable|array',
            'variations.*.price' => 'nullable|numeric|min:0',
            'variations.*.is_default' => 'nullable',
        ];
    }
}
