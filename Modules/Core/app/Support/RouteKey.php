<?php

namespace Modules\Core\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Looks a record up by the key it shows in URLs (slug or public_id), for
 * values that arrive in query strings like ?property=sunlit-residences.
 * Pass the model itself to route() and Laravel writes this key for you.
 */
final class RouteKey
{
    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @param  Builder<T>|null  $scope  optional query to search within (e.g. only the user's properties)
     * @return T|null
     */
    public static function find(string $class, mixed $value, ?Builder $scope = null): ?Model
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $key = (new $class)->getRouteKeyName();

        return ($scope ?? $class::query())->where($key, $value)->first();
    }

    /** The database id behind a URL key, or null. */
    public static function id(string $class, mixed $value, ?Builder $scope = null): ?int
    {
        return self::find($class, $value, $scope)?->getKey();
    }
}
