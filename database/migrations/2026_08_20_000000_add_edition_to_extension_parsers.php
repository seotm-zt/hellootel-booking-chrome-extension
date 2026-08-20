<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks each parser with the extension edition it ships in.
 *
 * The edition is NOT what gates a parser at runtime — the manifest is: a domain
 * missing from content_scripts[].matches never gets a content script injected,
 * so the parser is unreachable regardless of what the API returns. This column
 * exists so the admin panel shows which build a parser belongs to (the domain
 * alone does not say: b2b.fstravel.com is a Russian operator on a .com zone),
 * and so extension:check-manifest can diff parsers against editions/*.json.
 *
 * See .docs/2ext.md
 */
return new class extends Migration
{
    /** Domains that ship outside the RU build. Everything else defaults to 'ru'. */
    private const NON_RU = [
        'intl' => [
            'online.joinup.ua',
            'online3.anextour.com.ua',
            'b2b.travelone.md',
        ],
        'all' => [
            'demo.velikolepniy-vek.com',
        ],
    ];

    public function up(): void
    {
        Schema::table('extension_parsers', function (Blueprint $table) {
            $table->string('edition', 10)->default('ru')->after('is_active');
        });

        foreach (self::NON_RU as $edition => $domains) {
            DB::table('extension_parsers')
                ->whereIn('domain', $domains)
                ->update(['edition' => $edition]);
        }
    }

    public function down(): void
    {
        Schema::table('extension_parsers', function (Blueprint $table) {
            $table->dropColumn('edition');
        });
    }
};
