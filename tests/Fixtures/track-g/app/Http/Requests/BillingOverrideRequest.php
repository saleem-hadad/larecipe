<?php

namespace TrackGApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Rules built from configuration, as real apps do: their arguments must never reach a docs page. */
class BillingOverrideRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::in(config('services.billing.internal_plans'))],
            'signature' => 'required|in:'.config('services.webhook.secret'),
            'tenant' => [Rule::exists('tenants', 'id')->where('region', config('services.region'))],
            'notes' => ['nullable', 'string', 'max:500', fn ($attribute, $value, $fail) => null],
        ];
    }
}
