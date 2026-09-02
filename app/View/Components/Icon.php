<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Icon extends Component
{
    /**
     * Path data for a small curated set of 24x24 stroke-style icons.
     * Keeping these inline avoids depending on an icon font or CDN.
     */
    private const PATHS = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        'users' => '<circle cx="9" cy="8" r="3.25"/><path d="M2.75 20a6.25 6.25 0 0 1 12.5 0"/><path d="M16.5 5.5a3.25 3.25 0 0 1 0 6.4M21.25 20a6 6 0 0 0-4.75-7.6"/>',
        'map-pin' => '<path d="M12 21s-7-6.1-7-11a7 7 0 0 1 14 0c0 4.9-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
        'star' => '<path d="m12 3 2.6 5.6 6.1.6-4.6 4.1 1.3 6-5.4-3.2L6.6 19.3l1.3-6-4.6-4.1 6.1-.6L12 3Z"/>',
        'bed' => '<path d="M3 18v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6M3 18v2M21 18v2M3 13V8a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M13 10h6"/>',
        'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'building' => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 8h1M14 8h1M9 12h1M14 12h1M9 16h1M14 16h1"/>',
        'door' => '<rect x="5" y="3" width="14" height="18" rx="1"/><circle cx="14.5" cy="12" r="0.9" fill="currentColor" stroke="none"/>',
        'wifi' => '<path d="M2 8.5a15.5 15.5 0 0 1 20 0M5.5 12.3a10.9 10.9 0 0 1 13 0M9 16a6 6 0 0 1 6 0"/><circle cx="12" cy="19.5" r="1" fill="currentColor" stroke="none"/>',
        'snowflake' => '<path d="M12 2v20M4.9 5 19.1 19M19.1 5 4.9 19M12 8l-2.5-1.5M12 8l2.5-1.5M12 16l-2.5 1.5M12 16l2.5 1.5M6 9l.5-2.9L4 5M6 15l.5 2.9L4 19M18 9l-.5-2.9L20 5M18 15l-.5 2.9L20 19"/>',
        'utensils' => '<path d="M6 2v8a2 2 0 0 0 4 0V2M8 10v12M17 2c-1.7 0-3 2.2-3 5s1.3 5 3 5v10"/>',
        'parking' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 16V7h3.5a2.75 2.75 0 0 1 0 5.5H9"/>',
        'tv' => '<rect x="3" y="5" width="18" height="13" rx="2"/><path d="M8 21h8M12 18v3"/>',
        'waves' => '<path d="M2 8c1.5-1.5 3-1.5 4.5 0s3 1.5 4.5 0 3-1.5 4.5 0 3 1.5 4.5 0M2 14c1.5-1.5 3-1.5 4.5 0s3 1.5 4.5 0 3-1.5 4.5 0 3 1.5 4.5 0M2 20c1.5-1.5 3-1.5 4.5 0s3 1.5 4.5 0 3-1.5 4.5 0 3 1.5 4.5 0"/>',
        'coffee' => '<path d="M4 8h13a3 3 0 0 1 0 6h-1M4 8v7a4 4 0 0 0 4 4h4a4 4 0 0 0 4-4v-1M8 2v2M11 2v2"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18"/>',
        'paw' => '<circle cx="7" cy="9" r="1.7"/><circle cx="12" cy="6.5" r="1.7"/><circle cx="17" cy="9" r="1.7"/><path d="M12 12c-3.5 0-5.5 2.2-5.5 4.5S8 20 12 20s5.5-1.2 5.5-3.5S15.5 12 12 12Z"/>',
        'washing-machine' => '<rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="7" cy="6" r="0.8" fill="currentColor" stroke="none"/><circle cx="10" cy="6" r="0.8" fill="currentColor" stroke="none"/><circle cx="12" cy="14" r="5"/><circle cx="12" cy="14" r="2"/>',
        'truck' => '<rect x="2" y="7" width="13" height="10" rx="1"/><path d="M15 10h3.5L21 13v4h-6"/><circle cx="7" cy="18.5" r="1.6"/><circle cx="17.5" cy="18.5" r="1.6"/>',
        'sparkles' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M18 6l-2.5 2.5M8.5 15.5 6 18"/>',
        'shield-check' => '<path d="M12 3 4.5 6v6c0 4.6 3.2 8 7.5 9 4.3-1 7.5-4.4 7.5-9V6L12 3Z"/><path d="m9 12 2 2 4-4"/>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'headset' => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="2.5" y="13" width="4" height="6" rx="1.5"/><rect x="17.5" y="13" width="4" height="6" rx="1.5"/><path d="M20 19v1a3 3 0 0 1-3 3h-3"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.5 3.8 5.7 3.8 9s-1.3 6.5-3.8 9c-2.5-2.5-3.8-5.7-3.8-9s1.3-6.5 3.8-9Z"/>',
        'check' => '<path d="M5 13.5 9.5 18 19 7"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'arrow-right' => '<path d="M4 12h16M14 6l6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'car' => '<path d="M3 13l1.5-4.5A2 2 0 0 1 6.4 7h11.2a2 2 0 0 1 1.9 1.5L21 13"/><rect x="2.5" y="13" width="19" height="6" rx="2"/><circle cx="7" cy="19" r="1.5"/><circle cx="17" cy="19" r="1.5"/>',
        'wallet' => '<rect x="2.5" y="6" width="19" height="13" rx="2"/><path d="M2.5 10h19"/><circle cx="17" cy="14" r="1.2" fill="currentColor" stroke="none"/>',
        'gift' => '<rect x="3" y="9" width="18" height="12" rx="1"/><path d="M3 9h18M12 9v12M12 9C9 9 8 6.5 9.5 5S12 5 12 9ZM12 9c3 0 4-2.5 2.5-4S12 5 12 9Z"/>',
        'play' => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 5.5 3.5-5.5 3.5v-7Z" fill="currentColor" stroke="none"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9.5" r="1.5"/><path d="m4 17 5-5 3.5 3.5L17 11l3 3"/>',
        'expand' => '<path d="M8 3H4v4M16 3h4v4M8 21H4v-4M16 21h4v-4"/>',
    ];

    public function __construct(public string $name, public string $class = '') {}

    public function path(): string
    {
        return self::PATHS[$this->name] ?? self::PATHS['sparkles'];
    }

    public function render(): View
    {
        return view('components.icon');
    }
}
