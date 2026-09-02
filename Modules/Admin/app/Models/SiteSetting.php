<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SiteSetting extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'contact_email',
        'contact_phone',
        'contact_address',
        'footer_about',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'terms_content',
        'privacy_content',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
    }

    public function logoUrl(): ?string
    {
        return $this->getFirstMedia('logo')?->getUrl();
    }
}
