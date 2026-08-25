<?php

namespace App\Console\Commands;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Console\Command;

/**
 * Diffs the parsers in the database against the domain patterns each extension
 * edition ships with.
 *
 * The same site list lives in two places that cannot see each other: the DB and
 * chrome-extension/editions/*.json (match patterns that end up in the manifest).
 * On the DB side a domain can be served two ways — by a parser's own `domain`, or
 * by an extension_parser_rules row pointing at that parser (which is how one config
 * covers several hosts, e.g. a site and its demo stand). Both count as coverage.
 * A parser without a matching pattern is
 * dead code — Chrome never injects a content script on that page, so no button
 * appears and nobody finds out until an agent complains. This command is what
 * makes that drift loud.
 *
 * See .docs/2ext.md §5.5
 */
class CheckExtensionManifest extends Command
{
    protected $signature = 'extension:check-manifest
                            {--edition= : ru|intl — по умолчанию проверяются все}';

    protected $description = 'Сверяет парсеры в БД со списками доменов в chrome-extension/editions/*.json';

    public function handle(): int
    {
        $dir = base_path('chrome-extension/editions');

        $editions = $this->option('edition')
            ? [$this->option('edition')]
            : collect(glob("$dir/*.json"))->map(fn ($f) => basename($f, '.json'))->all();

        if (!$editions) {
            $this->error("Файлы редакций не найдены: $dir");
            return self::FAILURE;
        }

        $failed = false;

        foreach ($editions as $edition) {
            $file = "$dir/$edition.json";

            if (!is_file($file)) {
                $this->error("Нет файла редакции: $file");
                $failed = true;
                continue;
            }

            $sites = json_decode(file_get_contents($file), true)['sites'] ?? null;

            if (!is_array($sites)) {
                $this->error("editions/$edition.json: отсутствует или испорчен ключ \"sites\"");
                $failed = true;
                continue;
            }

            $failed = $this->checkEdition($edition, $sites) || $failed;
        }

        if ($failed) {
            $this->newLine();
            $this->error('Найдены расхождения — правь editions/*.json или редакции парсеров в админке.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Расхождений нет.');
        return self::SUCCESS;
    }

    /** @return bool true если найдены расхождения */
    private function checkEdition(string $edition, array $sites): bool
    {
        $this->newLine();
        $this->line("<options=bold>Редакция $edition</> — " . count($sites) . ' паттернов');

        $parsers = ExtensionParser::query()
            ->where('is_active', true)
            ->whereIn('edition', [$edition, 'all'])
            ->orderBy('domain')
            ->get(['name', 'domain', 'path_match', 'edition']);

        $patterns = array_map([$this, 'parsePattern'], $sites);
        $failed   = false;

        // 1. Парсер без паттерна = кнопка не появится: content script не инжектится.
        foreach ($parsers as $parser) {
            if (!$parser->domain) {
                $this->warn("  • «{$parser->name}» без домена — проверить вручную");
                continue;
            }

            $covered = collect($patterns)->contains(
                fn ($p) => $p['host'] === $parser->domain
                    && $this->pathsOverlap($p['path'], (string) $parser->path_match)
            );

            if (!$covered) {
                $this->error("  ✗ парсер «{$parser->name}» ({$parser->domain}{$parser->path_match}) не покрыт ни одним паттерном");
                $failed = true;
            }
        }

        // 2. Паттерн без парсера = лишнее разрешение в карточке Chrome Web Store.
        //    Проверяем по хосту: у одного домена бывает несколько паттернов
        //    (/cl_refer* — точка входа SAMO-редиректа, /default.php* — сама страница),
        //    и лишь один из них соответствует path_match парсера.
        //
        //    Домен считается покрытым и тогда, когда на него указывает правило
        //    маршрутизации: один парсер может обслуживать несколько хостов, не
        //    дублируя конфиг (сайт и его демо-стенд).
        $parserNames   = $parsers->pluck('name');
        $parserDomains = $parsers->pluck('domain')->filter()->unique();

        $ruleDomains = ExtensionParserRule::query()
            ->whereIn('parser', $parserNames)
            ->pluck('domain')
            ->unique();

        $covered = $parserDomains->merge($ruleDomains)->unique();

        foreach ($patterns as $p) {
            if (!$covered->contains($p['host'])) {
                $this->error("  ✗ паттерн {$p['raw']} — нет активного парсера этой редакции на {$p['host']}");
                $failed = true;
            }
        }

        // 3. Правило, указывающее на несуществующий парсер, — мёртвая маршрутизация.
        $orphans = ExtensionParserRule::all()
            ->reject(fn ($r) => ExtensionParser::where('name', $r->parser)->exists());

        foreach ($orphans as $r) {
            $this->warn("  • правило {$r->domain} → «{$r->parser}»: такого парсера нет");
        }

        if (!$failed) {
            $viaRules = $ruleDomains->diff($parserDomains)->count();
            $note = $viaRules ? " (из них {$viaRules} домен(ов) через правила)" : '';
            $this->info("  ✓ {$parsers->count()} парсеров и " . count($sites) . " паттернов сходятся{$note}");
        }

        return $failed;
    }

    /** "https://host.tld/some/path*" → ['host' => …, 'path' => '/some/path', 'raw' => …] */
    private function parsePattern(string $pattern): array
    {
        $clean = rtrim($pattern, '*');

        return [
            'raw'  => $pattern,
            'host' => parse_url($clean, PHP_URL_HOST) ?: '',
            'path' => parse_url($clean, PHP_URL_PATH) ?: '',
        ];
    }

    /**
     * Пустой path_match означает «весь домен» и покрывается любым паттерном.
     * Иначе достаточно, чтобы один путь был префиксом другого: паттерн может быть
     * и шире парсера (/orders* при path_match=/orders/list), и уже.
     */
    private function pathsOverlap(string $patternPath, string $parserPath): bool
    {
        if ($parserPath === '' || $patternPath === '') {
            return true;
        }

        return str_starts_with($patternPath, $parserPath)
            || str_starts_with($parserPath, $patternPath);
    }
}
