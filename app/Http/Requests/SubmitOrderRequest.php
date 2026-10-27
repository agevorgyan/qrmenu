<?php

namespace App\Http\Requests;

use App\Models\Vendor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitize inputs to prevent XSS.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->customer_phone ? trim(strip_tags((string) $this->customer_phone)) : null;
        $email = $this->customer_email ? strtolower(trim(strip_tags((string) $this->customer_email))) : null;

        $this->merge([
            'notes' => $this->notes ? strip_tags($this->notes) : null,
            'customer_name' => $this->customer_name ? strip_tags($this->customer_name) : null,
            'customer_phone' => $phone ?: null,
            'customer_email' => $email ?: null,
            'table_number' => $this->table_number ? strip_tags($this->table_number) : null,
            'delivery_address' => $this->delivery_address ? strip_tags($this->delivery_address) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $vendorSlug = $this->route('vendor_slug');
        $cachedVendor = $this->attributes->get('resolved_vendor');
        $vendor = ($cachedVendor instanceof Vendor && $cachedVendor->slug === $vendorSlug)
            ? $cachedVendor
            : ($vendorSlug ? Vendor::where('slug', $vendorSlug)->first() : null);
        $vendorId = $vendor?->id;

        return [
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('vendor_id', $vendorId),
            ],
            'table_number' => 'required_if:type,dine_in|nullable|string',
            'delivery_address' => 'required_if:type,delivery|nullable|string|max:500',
            'type' => 'required|string|in:dine_in,takeaway,delivery,whatsapp',
            'customer_name' => 'nullable|string',
            'customer_phone' => [
                'required_if:type,delivery,takeaway',
                'nullable',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        return;
                    }

                    $clean = preg_replace('/[\s\-\(\)\.]/', '', (string) $value);
                    if (! preg_match('/^\+?[0-9]{8,15}$/', $clean)) {
                        $fail(__('menu.invalid_phone_format'));

                        return;
                    }

                    if (str_starts_with($clean, '+374')) {
                        $digits = substr($clean, 4);
                        if (strlen($digits) !== 8) {
                            $fail('Հայկական հեռախոսահամարը պետք է ունենա ճիշտ 8 նիշ (+374-ից հետո)։');
                        }
                    } elseif (str_starts_with($clean, '0') && strlen($clean) !== 9) {
                        $fail('Հայկական տեղական հեռախոսահամարը պետք է ունենա 9 նիշ (օր.՝ 091234567)։');
                    } elseif (str_starts_with($clean, '+7')) {
                        $digits = substr($clean, 2);
                        if (strlen($digits) !== 10) {
                            $fail('Ռուսական հեռախոսահամարը պետք է ունենա ճիշտ 10 նիշ (+7-ից հետո)։');
                        }
                    } elseif (str_starts_with($clean, '+995')) {
                        $digits = substr($clean, 4);
                        if (strlen($digits) !== 9) {
                            $fail('Վրացական հեռախոսահամարը պետք է ունենա ճիշտ 9 նիշ (+995-ից հետո)։');
                        }
                    } elseif (str_starts_with($clean, '+1')) {
                        $digits = substr($clean, 2);
                        if (strlen($digits) !== 10) {
                            $fail('ԱՄՆ/Կանադայի հեռախոսահամարը պետք է ունենա ճիշտ 10 նիշ (+1-ից հետո)։');
                        }
                    }
                },
            ],
            'customer_email' => 'nullable|string|max:100|email:rfc,filter',
            'customer_birthdate' => 'nullable|date',
            'marketing_opt_in' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'payment_method' => 'nullable|string|in:cash,pos_terminal,idram,telcell,fastshift,arca,stripe',
            'active_order_number' => 'nullable|string|max:50',
            'idempotency_key' => 'nullable|string|max:100',
            'tip_amount' => 'nullable|numeric|min:0|max:1000000',
            'tip_type' => 'nullable|string|in:none,percentage,fixed,custom',
            'tip_value' => 'nullable|numeric|min:0',
            'waiter_id' => [
                ($vendor?->getTipRatingSetting()?->allow_waiter_selection && $vendor?->getTipRatingSetting()?->waiter_selection_required) ? 'required' : 'nullable',
                Rule::exists('users', 'id')->where('vendor_id', $vendorId)->where('is_active', true),
            ],
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('vendor_id', $vendorId),
            ],
            'items.*.variation_id' => 'nullable|integer',
            'items.*.variation_name' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_phone.required_if' => __('menu.delivery_phone_required'),
            'customer_email.email' => __('menu.invalid_email_format'),
        ];
    }
}
