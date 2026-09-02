<?php

namespace Modules\Services\Fulfillment;

use Modules\Services\Contracts\ServiceFulfillmentHandler;

class OtherFulfillmentHandler implements ServiceFulfillmentHandler
{
    public function type(): string
    {
        return 'other';
    }

    public function validationRules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function fields(): array
    {
        return [
            ['name' => 'notes', 'label' => 'Notes for the provider', 'type' => 'textarea', 'required' => false],
        ];
    }

    public function summary(array $details): string
    {
        return $details['notes'] ?? 'No additional details provided.';
    }
}
