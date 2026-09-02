<?php

namespace Modules\Core\Traits;

use Illuminate\Support\Str;

trait Sluggable
{
    public static function bootSluggable(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->slugColumn()})) {
                $model->{$model->slugColumn()} = $model->generateUniqueSlug();
            }
        });
    }

    public function slugColumn(): string
    {
        return 'slug';
    }

    public function slugSourceColumn(): string
    {
        return 'title';
    }

    protected function generateUniqueSlug(): string
    {
        $base = Str::slug($this->{$this->slugSourceColumn()});
        $slug = $base;
        $suffix = 1;

        while (static::where($this->slugColumn(), $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
