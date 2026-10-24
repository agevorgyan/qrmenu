<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || ! $user->vendor) {
            return false;
        }

        return $user->can('manageBilling', $user->vendor);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('billing_interval') && ! $this->has('period_months')) {
            $this->merge([
                'period_months' => strtolower((string) $this->input('billing_interval')) === 'yearly' ? 12 : 1,
            ]);
        }
        if ($this->has('period_months') && ! $this->has('billing_interval')) {
            $this->merge([
                'billing_interval' => ((int) $this->input('period_months') >= 12) ? 'yearly' : 'monthly',
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subscription_plan_id' => [
                'required',
                'integer',
                Rule::exists('subscription_plans', 'id')->where('is_active', true),
            ],
            'period_months' => 'required|integer|in:1,3,6,12',
            'billing_interval' => 'nullable|string|in:monthly,yearly',
            'subscription_plan_price_id' => 'nullable|integer|exists:subscription_plan_prices,id',
            'payment_method' => 'required|string|in:idram,telcell,fastshift,arca,stripe,bank_transfer,cash',
        ];
    }
}
