<?php

namespace Modules\Services\Contracts;

interface ServiceFulfillmentHandler
{
    /**
     * The fulfillment_type discriminator this handler answers for (laundry, logistics, other).
     */
    public function type(): string;

    /**
     * Validation rules for the fields inside fulfillment_details, keyed by field name.
     */
    public function validationRules(): array;

    /**
     * Field definitions used to render the order form dynamically.
     * Each entry: ['name' => ..., 'label' => ..., 'type' => 'text'|'textarea'|'number'|'datetime-local', 'required' => bool].
     */
    public function fields(): array;

    /**
     * A short, human-readable one-line summary of the fulfillment details for lists/detail pages.
     */
    public function summary(array $details): string;
}
