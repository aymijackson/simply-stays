<?php

namespace Modules\Services\Fulfillment;

use Modules\Services\Contracts\ServiceFulfillmentHandler;

class LaundryFulfillmentHandler implements ServiceFulfillmentHandler
{
    public function type(): string
    {
        return 'laundry';
    }

    public function validationRules(): array
    {
        return [
            'pickup_window' => ['required', 'string', 'max:100'],
            'dropoff_window' => ['required', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'pickup_window', 'label' => 'Pickup window', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 9:00-11:00'],
            ['name' => 'dropoff_window', 'label' => 'Drop-off window', 'type' => 'text', 'required' => true, 'placeholder' => 'e.g. 18:00-20:00'],
            ['name' => 'instructions', 'label' => 'Special instructions', 'type' => 'textarea', 'required' => false],
        ];
    }

    public function summary(array $details): string
    {
        return sprintf(
            'Pickup %s, drop-off %s',
            $details['pickup_window'] ?? '—',
            $details['dropoff_window'] ?? '—',
        );
    }
}
