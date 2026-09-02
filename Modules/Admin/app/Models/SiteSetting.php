<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class SiteSetting extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const COLOR_THEMES = [
        'navy' => 'Navy (default)',
        'sunset' => 'Sunset',
        'forest' => 'Forest',
        'slate' => 'Slate',
        'gold' => 'Gold Luxe',
    ];

    public const LAYOUT_STYLES = [
        'standard' => 'Standard',
        'minimal' => 'Minimal',
        'bold' => 'Bold',
    ];

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
        'color_theme',
        'layout_style',
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
