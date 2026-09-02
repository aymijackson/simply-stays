<?php

namespace Modules\Core\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    public static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->uuidColumn()})) {
                $model->{$model->uuidColumn()} = (string) Str::uuid();
            }
        });
    }

    public function uuidColumn(): string
    {
        return 'uuid';
    }
}
