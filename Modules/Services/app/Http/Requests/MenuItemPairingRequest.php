<?php

namespace Modules\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuItemPairingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paired_menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'type' => ['required', 'in:upsell,pairing'],
        ];
    }
}
