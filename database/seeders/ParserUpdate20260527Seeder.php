<?php

namespace Database\Seeders;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Database\Seeder;

/**
 * Parser sync for 2026-05-27 deploy.
 *
 * Goal: bring the prod parser set to exactly three rows, matching what the
 * local DB has. The sync proceeds in three steps:
 *
 *   1. Delete every ExtensionParser whose (domain, path_match) is not in the
 *      whitelist below. Removes legacy / experimental parsers (toptravel,
 *      fstravel, transfer, etc.) — confirmed unused on 2026-05-27.
 *   2. Upsert the three current parsers by (domain, path_match). Existing
 *      rows: only `config` and `is_active` are overwritten; `name`,
 *      `operator_name`, `operator_id`, `notes` are preserved (admins may have
 *      customised them — for example we already saw "pegast" → "Пегас тур").
 *   3. Delete orphan ExtensionParserRule rows whose `parser` (string column,
 *      no FK) no longer points to an existing parser by name.
 *
 * Idempotent — safe to run multiple times.
 * Run with:  php artisan db:seed --class=ParserUpdate20260527Seeder --force
 */
class ParserUpdate20260527Seeder extends Seeder
{
    /** @var list<array{domain:string,path:string,defaults:array<string,mixed>,config:array<string,mixed>}> */
    private array $whitelist;

    public function __construct()
    {
        $this->whitelist = [
            [
                'domain'   => 'agency.pegast.ru',
                'path'     => '/MyAccount/Bookings',
                'defaults' => [
                    'name'          => 'pegast',
                    'operator_name' => 'Pegas Touristik',
                    'notes'         => 'Pegas Touristik bookings list. Button anchored to .booking-head__left-block-top so it stays visible on collapsed cards.',
                ],
                'config'   => $this->pegastConfig(),
            ],
            [
                'domain'   => 'coralagency.ru',
                'path'     => '/reservation/search',
                'defaults' => ['name' => 'CoralAgency — Заявки'],
                'config'   => null, // existing row only — mutate via closure
                'mutator'  => $this->coralCleanup(...),
            ],
            [
                'domain'   => 'demo.velikolepniy-vek.com',
                'path'     => '/hotel/book/history',
                'defaults' => ['name' => 'HellOotel — История бронирований'],
                'config'   => null,
                'mutator'  => $this->hellootelCleanup(...),
            ],
        ];
    }

    public function run(): void
    {
        $this->purgeForeign();
        $this->upsertWhitelisted();
        $this->purgeOrphanRules();
    }

    private function purgeForeign(): void
    {
        $keep = collect($this->whitelist)->map(fn($r) => $r['domain'] . '|' . $r['path']);

        $deleted = ExtensionParser::all()
            ->reject(fn($p) => $keep->contains($p->domain . '|' . ($p->path_match ?? '')))
            ->each(function ($p) {
                $this->command?->warn("  delete  #{$p->id} \"{$p->name}\"  ({$p->domain}{$p->path_match})");
                $p->delete();
            })
            ->count();
        $this->command?->info("  → purged $deleted foreign parser(s)");
    }

    private function upsertWhitelisted(): void
    {
        foreach ($this->whitelist as $entry) {
            $row = ExtensionParser::where('domain', $entry['domain'])
                ->where('path_match', $entry['path'])
                ->first();

            $config = $entry['config'];
            if ($row && isset($entry['mutator'])) {
                $config = ($entry['mutator'])($row->config ?? []);
            }

            if ($row) {
                $update = ['is_active' => true];
                if ($config !== null) $update['config'] = $config;
                $row->update($update);
                $this->command?->line("  update  #{$row->id} \"{$row->name}\"");
                continue;
            }

            // Create — only here we apply the full defaults (incl. notes/operator_name)
            $row = ExtensionParser::create(array_merge(
                $entry['defaults'],
                [
                    'domain'     => $entry['domain'],
                    'path_match' => $entry['path'],
                    'is_active'  => true,
                    'config'     => $config ?? [],
                ]
            ));
            $this->command?->info("  create  #{$row->id} \"{$row->name}\"");
        }
    }

    private function purgeOrphanRules(): void
    {
        $names = ExtensionParser::pluck('name')->all();
        $deleted = ExtensionParserRule::whereNotIn('parser', $names)
            ->get()
            ->each(function ($r) {
                $this->command?->warn("  delete rule  #{$r->id}  \"{$r->parser}\" → {$r->domain}{$r->path_match}");
                $r->delete();
            })
            ->count();
        $this->command?->info("  → purged $deleted orphan rule(s)");
    }

    // ── configs ──────────────────────────────────────────────────────────────

    private function pegastConfig(): array
    {
        return [
            'card'             => 'li.bookings-list__item',
            'button'           => '.booking-head__left-block-top',
            'button_placement' => 'after',
            'fields' => [
                'booking_code'   => ['sel' => '.booking-number__number'],
                'hotel_name'     => ['sel' => '.booking-hotel-service__hotel-name a'],
                'subtitle'       => ['sel' => '.main-column__accommodation .inplace-tooltip'],
                'stay_dates'     => ['sel' => '.main-cell--tour-duration .main-cell__primary'],
                'total_price'    => ['sel' => '.main-cell__price .text-nowrap'],
                'reservation_at' => ['sel' => '.booking-head__left-block-dates-value', 'strip_prefix' => 'от'],
            ],
            'tourist_blocks' => [
                'item'   => '.booking-persons-list__item',
                'fields' => [
                    // .person-name is "LASTNAME FIRSTNAME" as one string. strip_pattern
                    // is applied without /g flag → must match the whole tail in one go.
                    'last_name'  => ['sel' => '.person-name', 'strip_pattern' => '[ ].*$'],
                    'first_name' => ['sel' => '.person-name', 'strip_pattern' => '^[^ ]+[ ]+'],
                    'dob'        => ['sel' => '.row__birth'],
                ],
            ],
        ];
    }

    private function coralCleanup(array $cfg): array
    {
        unset(
            $cfg['fields']['guests'],
            $cfg['fields']['statuses'],
            $cfg['fields']['details_link'],
            $cfg['meta_fields'],
        );
        if (!empty($cfg['dl_maps'][0]['fields'])) {
            unset(
                $cfg['dl_maps'][0]['fields']['guests'],
                $cfg['dl_maps'][0]['fields']['meal_plan'],
            );
        }
        return $cfg;
    }

    private function hellootelCleanup(array $cfg): array
    {
        unset(
            $cfg['fields']['nights'],
            $cfg['fields']['statuses'],
            $cfg['meta_maps'],
        );
        return $cfg;
    }
}
