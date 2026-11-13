<?php

namespace Modules\Operations\Support;

use Illuminate\Http\Request;
use Modules\Listings\Models\Property;

/**
 * The property the operations screens are currently working on.
 *
 * Picked with ?property=ID (the switcher in the page header) and remembered
 * in the session, so sidebar links need no property parameter.
 */
class OpsContext
{
    private const SESSION_KEY = 'ops.property_id';

    public static function property(Request $request): Property
    {
        $properties = OpsAccess::properties($request->user());
        abort_if($properties->isEmpty(), 403, 'You are not linked to any property yet.');

        $wanted = $request->integer('property') ?: (int) $request->session()->get(self::SESSION_KEY);
        $property = $properties->firstWhere('id', $wanted) ?? $properties->first();

        $request->session()->put(self::SESSION_KEY, $property->id);

        return $property;
    }

    public static function remember(Request $request, Property $property): void
    {
        $request->session()->put(self::SESSION_KEY, $property->id);
    }

    public static function currency(): string
    {
        return (string) config('operations.currency', 'NGN');
    }
}
