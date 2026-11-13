<?php

namespace Modules\Operations\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Listings\Models\Property;
use Modules\Operations\Models\StaffMember;

/**
 * Who may do what in a property's operations area.
 *
 * Admins and the property's host are "owners". Registered staff get the
 * access level set on their staff record (basic < inventory < manager).
 */
class OpsAccess
{
    private const RANK = ['basic' => 1, 'inventory' => 2, 'manager' => 3, 'owner' => 4];

    public static function level(User $user, Property $property): ?string
    {
        if ($user->hasRole('Admin') || ($property->host_id !== null && $property->host_id === $user->id)) {
            return 'owner';
        }

        return self::staffMember($user, $property)?->access_level;
    }

    public static function allows(User $user, Property $property, string $minimum): bool
    {
        $level = self::level($user, $property);

        return $level !== null && self::RANK[$level] >= self::RANK[$minimum];
    }

    public static function staffMember(User $user, Property $property): ?StaffMember
    {
        return StaffMember::query()
            ->where('property_id', $property->id)
            ->where('user_id', $user->id)
            ->active()
            ->first();
    }

    /** Every property the user can open in the operations area. */
    public static function properties(User $user): Collection
    {
        return Property::query()
            ->when(! $user->hasRole('Admin'), fn (Builder $q) => $q->where(function (Builder $q) use ($user) {
                $q->where('host_id', $user->id)
                    ->orWhereIn('id', StaffMember::query()->where('user_id', $user->id)->active()->select('property_id'));
            }))
            ->orderBy('name')
            ->get();
    }
}
