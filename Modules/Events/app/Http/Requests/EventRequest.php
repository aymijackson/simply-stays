<?php

namespace Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_ticketed' => ['nullable', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'in:USD,NGN,GHS,KES,ZAR,GBP,EUR'],
            'recurrence_type' => ['required', 'in:none,daily,weekly,monthly'],
            'recurrence_days_of_week' => ['nullable', 'array'],
            'recurrence_days_of_week.*' => ['integer', 'min:0', 'max:6'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'start_time' => ['required'],
            'end_time' => ['nullable'],
            'photos' => ['nullable', 'array'],
            'photos.*' => ['image', 'max:5120'],
        ];

        if (Auth::user()?->hasRole('Admin')) {
            $rules['commission_type'] = ['nullable', 'in:none,fixed,percentage'];
            $rules['commission_value'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }
}
