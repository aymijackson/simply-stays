<?php

namespace Modules\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'service_category_id' => ['required', 'exists:service_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'in:USD,NGN,GHS,KES,ZAR,GBP,EUR'],
            'audience' => ['required', 'in:guests_only,non_guests_only,both'],
            'properties' => ['nullable', 'array'],
            'properties.*' => ['integer', 'exists:properties,id'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'free_delivery_over' => ['nullable', 'numeric', 'min:0'],
        ];

        if (Auth::user()?->hasRole('Admin')) {
            $rules['commission_type'] = ['nullable', 'in:none,fixed,percentage'];
            $rules['commission_value'] = ['nullable', 'numeric', 'min:0'];
            // Admins pick "in_house" or a vendor's id (checked to be a vendor in the controller).
            $rules['provider_id'] = ['nullable', 'regex:/^(in_house|\d+)$/'];
        }

        return $rules;
    }
}
