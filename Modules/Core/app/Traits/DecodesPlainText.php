<?php

namespace Modules\Core\Traits;

/**
 * Plain-text fields (titles, names) are escaped once when shown on the page.
 * Text pasted from a web page or the rich-text editor can arrive already
 * encoded ("Living Room &amp; Dining Bar"), which then shows as "&amp;".
 * Decode such entities when saving so the stored value is plain text.
 *
 * Models list the fields in $plainTextFields.
 */
trait DecodesPlainText
{
    public static function bootDecodesPlainText(): void
    {
        static::saving(function ($model) {
            foreach ($model->plainTextFields ?? [] as $field) {
                if (is_string($model->{$field})) {
                    $model->{$field} = self::decodePlainText($model->{$field});
                }
            }
        });
    }

    public static function decodePlainText(string $value): string
    {
        // Decode repeatedly to undo double encoding (&amp;amp;), then tidy whitespace.
        for ($i = 0; $i < 3 && str_contains($value, '&'); $i++) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        return trim(preg_replace('/\s+/u', ' ', $value));
    }
}
