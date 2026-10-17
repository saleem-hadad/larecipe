<?php

namespace TrackGApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'total' => ['required', 'integer', 'min:1'],
            'currency' => ['required', Rule::in(['usd', 'eur'])],
        ];
    }
}
