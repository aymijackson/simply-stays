<?php

namespace Modules\Services\Fulfillment;

use Modules\Services\Contracts\ServiceFulfillmentHandler;

class LogisticsFulfillmentHandler implements ServiceFulfillmentHandler
{
    public function type(): string
    {
        return 'logistics';
    }

    public function validationRules(): array
    {
        return [
            'pickup_location' => ['required', 'string', 'max:255'],
            'dropoff_location' => ['required', 'string', 'max:255'],
            'scheduled_time' => ['required', 'date'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'pickup_location', 'label' => 'Pickup location', 'type' => 'text', 'required' => true],
            ['name' => 'dropoff_location', 'label' => 'Drop-off location', 'type' => 'text', 'required' => true],
            ['name' => 'scheduled_time', 'label' => 'Pickup time', 'type' => 'datetime-local', 'required' => true],
            ['name' => 'passenger_count', 'label' => 'Passengers', 'type' => 'number', 'required' => true],
        ];
    }

    public function summary(array $details): string
    {
        return sprintf(
            '%s → %s at %s',
            $details['pickup_location'] ?? '—',
            $details['dropoff_location'] ?? '—',
            $details['scheduled_time'] ?? '—',
        );
    }
}
