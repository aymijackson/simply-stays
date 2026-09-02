<?php

namespace Modules\Payments\Support;

use App\Models\User;

final class PlatformAccount
{
    public static function user(): User
    {
        return User::firstOrCreate(
            ['email' => config('payments.platform_account_email')],
            [
                'name' => 'Platform Account',
                'password' => bcrypt(bin2hex(random_bytes(16))),
                'status' => 'active',
            ],
        );
    }
}
