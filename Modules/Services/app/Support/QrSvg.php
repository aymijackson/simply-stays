<?php

namespace Modules\Services\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/** QR codes as SVG: sharp at any print size, no image extensions needed. */
class QrSvg
{
    public static function make(string $text, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        // Medium error correction survives a little wear on printed cards.
        $svg = $writer->writeString($text, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());

        // Drop the XML prolog so the SVG can be inlined in HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
