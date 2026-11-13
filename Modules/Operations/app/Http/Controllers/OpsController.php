<?php

namespace Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Listings\Models\Property;
use Modules\Operations\Support\OpsAccess;
use Modules\Operations\Support\OpsContext;

abstract class OpsController extends Controller
{
    /** Amounts are typed in major units, optionally with thousands separators: 12,500 or 12500.50 */
    protected const MONEY = 'regex:/^\d[\d,]*(\.\d{1,2})?$/';

    /** The working property, after checking the user has at least $minimum access to it. */
    protected function property(Request $request, string $minimum = 'basic'): Property
    {
        $property = OpsContext::property($request);

        return $this->enter($request, $property, $minimum);
    }

    /** Authorise against the property a record belongs to, and make it the working property. */
    protected function propertyOf(Request $request, Model $record, string $minimum = 'basic'): Property
    {
        $property = $record->property;
        abort_unless($property, 404);

        OpsContext::remember($request, $property);

        return $this->enter($request, $property, $minimum);
    }

    protected function level(Request $request, Property $property): ?string
    {
        return OpsAccess::level($request->user(), $property);
    }

    protected function can(Request $request, Property $property, string $minimum): bool
    {
        return OpsAccess::allows($request->user(), $property, $minimum);
    }

    /** Turn a domain rule failure into a normal form error on $field. */
    protected function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    /** Parse a money input like "12,500.50" into minor units. */
    protected function toMinor(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) round(((float) str_replace(',', '', (string) $value)) * 100);
    }

    private function enter(Request $request, Property $property, string $minimum): Property
    {
        abort_unless(OpsAccess::allows($request->user(), $property, $minimum), 403);

        // Read by the operations view composer (property switcher, nav, permissions).
        $request->attributes->set('ops.property', $property);
        $request->attributes->set('ops.level', OpsAccess::level($request->user(), $property));

        return $property;
    }
}
