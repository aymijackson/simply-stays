<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Core\Traits\DecodesPlainText;

/**
 * Titles pasted with HTML entities ("Living Room &amp; Dining Bar") showed
 * "&amp;" on the site. Store them as plain text. Slugs are left alone so
 * existing links keep working.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'listings' => 'title',
        'properties' => 'name',
        'services' => 'title',
        'events' => 'title',
        'menu_items' => 'name',
    ];

    public function up(): void
    {
        $decoder = new class
        {
            use DecodesPlainText;
        };

        foreach (self::COLUMNS as $table => $column) {
            DB::table($table)->where($column, 'like', '%&%;%')->orderBy('id')->each(function ($row) use ($table, $column, $decoder) {
                $clean = $decoder::decodePlainText($row->{$column});
                if ($clean !== $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $clean]);
                }
            });
        }
    }

    public function down(): void
    {
        // Nothing to undo: decoded text is what was meant all along.
    }
};
