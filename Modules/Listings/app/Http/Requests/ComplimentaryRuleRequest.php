<?php

namespace Modules\Listings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplimentaryRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'label' => ['required', 'string', 'max:255'],
            'min_guests' => ['required', 'integer', 'min:1', 'max:50'],
            'max_quantity_per_guest' => ['required', 'integer', 'min:1', 'max:10'],
            'menu_items' => ['required', 'array', 'min:1'],
            'menu_items.*' => ['integer', 'exists:menu_items,id'],
        ];
    }
}
