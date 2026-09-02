<?php

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Model;

class EventSetting extends Model
{
    protected $fillable = [
        'hosts_can_create',
        'providers_can_create',
    ];

    protected function casts(): array
    {
        return [
            'hosts_can_create' => 'boolean',
            'providers_can_create' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'hosts_can_create' => true,
            'providers_can_create' => true,
        ]);
    }

    public function allowsRole(string $role): bool
    {
        return match ($role) {
            'Host' => $this->hosts_can_create,
            'ServiceProvider' => $this->providers_can_create,
            'Admin' => true,
            default => false,
        };
    }
}
