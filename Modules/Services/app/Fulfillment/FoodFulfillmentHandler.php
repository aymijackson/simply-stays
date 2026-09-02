<?php

namespace Modules\Services\Fulfillment;

use Modules\Services\Contracts\ServiceFulfillmentHandler;

class FoodFulfillmentHandler implements ServiceFulfillmentHandler
{
    public function type(): string
    {
        return 'food';
    }

    public function validationRules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:255'],
            'requested_time' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'delivery_address', 'label' => 'Delivery address', 'type' => 'text', 'required' => true, 'placeholder' => 'Room / unit, or full address'],
            ['name' => 'requested_time', 'label' => 'Requested time', 'type' => 'text', 'required' => false, 'placeholder' => 'e.g. 19:00'],
            ['name' => 'instructions', 'label' => 'Special instructions', 'type' => 'textarea', 'required' => false],
        ];
    }

    public function summary(array $details): string
    {
        return sprintf(
            'Deliver to %s%s',
            $details['delivery_address'] ?? '—',
            isset($details['requested_time']) && $details['requested_time'] !== '' ? ' at '.$details['requested_time'] : '',
        );
    }
}
