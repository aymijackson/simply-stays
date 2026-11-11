<?php

namespace Modules\Core\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitises and renders user-authored rich text (WYSIWYG output).
 *
 * Only a small formatting allowlist survives — no scripts, styles, images,
 * iframes or event handlers — so stored HTML is always safe to echo raw.
 */
class RichText
{
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a'];

    private static ?HtmlSanitizer $sanitizer = null;

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = trim(self::sanitizer()->sanitize($html));

        // An editor left "empty" still submits <p><br></p>.
        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    /**
     * Value to persist: HTML is sanitised, plain text is kept verbatim
     * (it is escaped at render time, so sanitising it would double-encode).
     */
    public static function normalize(?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        return self::looksLikeHtml($content) ? self::sanitize($content) : $content;
    }

    /** Renders stored content; legacy plain-text descriptions are escaped and split into paragraphs. */
    public static function toHtml(?string $content): string
    {
        if ($content === null || trim($content) === '') {
            return '';
        }

        if (! self::looksLikeHtml($content)) {
            return collect(preg_split("/\R{2,}/", trim($content)))
                ->map(fn (string $para) => '<p>'.nl2br(e($para)).'</p>')
                ->implode('');
        }

        return self::sanitize($content) ?? '';
    }

    public static function toPlainText(?string $content): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</li>'], ' ', (string) $content)))));
    }

    private static function looksLikeHtml(string $content): bool
    {
        return (bool) preg_match('/<\s*(p|br|strong|b|em|i|u|s|h[1-6]|ul|ol|li|blockquote|a)\b[^>]*>/i', $content);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(50_000);

        foreach (self::ALLOWED as $tag) {
            $config = $config->allowElement($tag, $tag === 'a' ? ['href'] : []);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
