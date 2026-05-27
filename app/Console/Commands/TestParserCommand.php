<?php

namespace App\Console\Commands;

use App\Models\ExtensionPageReport;
use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use App\Services\ParserEngineSimulator;
use Illuminate\Console\Command;

class TestParserCommand extends Command
{
    protected $signature = 'parser:test
                            {report? : Page report id (omit with --all to scan everything)}
                            {--all : Run against every saved page report}
                            {--parser= : Force a specific parser by name (skips domain matching)}
                            {--json : Print full result as JSON instead of a table}
                            {--limit=10 : Max bookings to print per report}';

    protected $description = 'Dry-run a parser config against saved Page Reports (HTML snapshots)';

    public function handle(ParserEngineSimulator $sim): int
    {
        $reports = $this->loadReports();
        if ($reports->isEmpty()) {
            $this->error('No reports to process.');
            return self::FAILURE;
        }

        $forcedParser = $this->option('parser')
            ? ExtensionParser::where('name', $this->option('parser'))->first()
            : null;
        if ($this->option('parser') && !$forcedParser) {
            $this->error("Parser '{$this->option('parser')}' not found.");
            return self::FAILURE;
        }

        $totalReports  = $reports->count();
        $totalBookings = 0;
        $totalParsers  = 0;

        foreach ($reports as $report) {
            $domain = parse_url($report->url, PHP_URL_HOST) ?: '?';
            $this->line('');
            $this->line(str_repeat('━', 76));
            $this->info(sprintf('Report #%d  %s  (%d bytes)', $report->id, $report->url, strlen($report->html ?? '')));

            $parser = $forcedParser ?: $this->pickParser($domain, parse_url($report->url, PHP_URL_PATH) ?: '/');
            if (!$parser) {
                $this->warn("  no matching parser for domain={$domain}");
                continue;
            }
            $this->line("  parser: <fg=cyan>{$parser->name}</> (domain={$parser->domain}, path={$parser->path_match})");
            $totalParsers++;

            $bookings = $sim->run($parser->config ?? [], $report->html ?? '');
            $this->line('  bookings extracted: ' . count($bookings));
            $totalBookings += count($bookings);

            if ($this->option('json')) {
                $this->line(json_encode($bookings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                continue;
            }

            $limit = (int) $this->option('limit');
            foreach (array_slice($bookings, 0, $limit) as $i => $b) {
                $this->renderBooking($i + 1, $b);
            }
            if (count($bookings) > $limit) {
                $this->comment("  ... " . (count($bookings) - $limit) . " more (use --limit to show more)");
            }
        }

        $this->line('');
        $this->info("Summary: {$totalReports} report(s), {$totalParsers} matched parser(s), {$totalBookings} booking(s)");
        return self::SUCCESS;
    }

    private function loadReports()
    {
        if ($id = $this->argument('report')) {
            $r = ExtensionPageReport::find($id);
            return $r ? collect([$r]) : collect();
        }
        if ($this->option('all')) {
            return ExtensionPageReport::orderBy('id')->get();
        }
        $latest = ExtensionPageReport::latest('id')->first();
        return $latest ? collect([$latest]) : collect();
    }

    private function pickParser(string $domain, string $path): ?ExtensionParser
    {
        // Direct parser domain match — pick the most specific path_match prefix.
        $parsers = ExtensionParser::where('is_active', true)
            ->where('domain', $domain)
            ->get();
        $best = null;
        foreach ($parsers as $p) {
            if ($p->path_match && !str_starts_with($path, $p->path_match)) continue;
            if (!$best || strlen($p->path_match ?? '') > strlen($best->path_match ?? '')) {
                $best = $p;
            }
        }
        if ($best) return $best;

        // Fallback: parser_rules can re-route a domain to an existing parser
        $rule = ExtensionParserRule::where('domain', $domain)->first();
        if ($rule && $rule->parser_id) {
            return ExtensionParser::find($rule->parser_id);
        }
        return null;
    }

    private function renderBooking(int $n, array $b): void
    {
        $this->line('');
        $this->line("  ── Booking #{$n} ──");
        foreach ($b as $field => $value) {
            if ($field === 'meta' && is_array($value)) {
                $this->line("    <fg=yellow>meta:</>");
                foreach ($value as $k => $v) {
                    $this->line("      {$k}: " . $this->fmt($v));
                }
                continue;
            }
            if ($field === 'tourists' && is_array($value)) {
                $this->line("    <fg=yellow>tourists ({" . count($value) . "} ):</>");
                foreach ($value as $i => $t) {
                    $parts = [];
                    foreach ($t as $k => $v) $parts[] = "{$k}=" . $this->fmt($v);
                    $this->line('      ' . ($i + 1) . ') ' . implode(' | ', $parts));
                }
                continue;
            }
            $this->line(sprintf('    %-18s %s', $field . ':', $this->fmt($value)));
        }
    }

    private function fmt($v): string
    {
        if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
        if ($v === null)  return '<fg=gray>null</>';
        if ($v === '')    return '<fg=gray>""</>';
        return (string) $v;
    }
}
