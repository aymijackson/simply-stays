<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Photo extends Component
{
    private const ICON_BY_TYPE = [
        'apartment' => 'building',
        'house' => 'home',
        'villa' => 'home',
        'studio' => 'door',
        'room' => 'bed',
        'laundry' => 'washing-machine',
        'logistics' => 'truck',
        'other' => 'sparkles',
    ];

    public function __construct(
        public ?string $url = null,
        public string $type = 'other',
        public int|string $seed = 1,
        public string $alt = '',
    ) {}

    public function icon(): string
    {
        return self::ICON_BY_TYPE[$this->type] ?? 'sparkles';
    }

    public function gradientClass(): string
    {
        $variant = (crc32((string) $this->seed) % 5) + 1;

        return 'ph-grad-'.$variant;
    }

    public function render(): View
    {
        return view('components.photo');
    }
}
