<?php

namespace App\Console\Commands;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Console\Command;

class GenerateParserSeeder extends Command
{
    protected $signature   = 'parsers:generate-seeder';
    protected $description = 'Генерирует сидер из текущих парсеров и правил (для деплоя на прод)';

    public function handle(): void
    {
        $parsers = ExtensionParser::all([
            'name', 'domain', 'path_match', 'config', 'is_active',
            'operator_id', 'operator_name', 'notes',
        ])->toArray();
        $rules   = ExtensionParserRule::all(['domain', 'path_match', 'parser', 'notes'])->toArray();

        $parsersExport = var_export($parsers, true);
        $rulesExport   = var_export($rules, true);

        $stub = <<<PHP
<?php

namespace Database\Seeders;

use App\Models\ExtensionParser;
use App\Models\ExtensionParserRule;
use Illuminate\Database\Seeder;

// Сгенерировано командой: php artisan parsers:generate-seeder
// Дата: {$this->now()}
// Полная замена: парсеры/правила, которых нет в этом сидере, удаляются.
class ParserDataSeeder extends Seeder
{
    public function run(): void
    {
        \$parsers = {$parsersExport};

        \$keepNames = array_column(\$parsers, 'name');
        ExtensionParser::whereNotIn('name', \$keepNames)->delete();

        foreach (\$parsers as \$row) {
            ExtensionParser::updateOrCreate(
                ['name' => \$row['name']],
                [
                    'domain'        => \$row['domain'],
                    'path_match'    => \$row['path_match'],
                    'config'        => \$row['config'],
                    'is_active'     => \$row['is_active'],
                    'operator_id'   => \$row['operator_id']   ?? null,
                    'operator_name' => \$row['operator_name'] ?? null,
                    'notes'         => \$row['notes'],
                ]
            );
        }

        \$rules = {$rulesExport};

        \$keepRules = array_map(fn(\$r) => \$r['domain'] . '|' . (\$r['path_match'] ?? ''), \$rules);
        ExtensionParserRule::all()->each(function (\$rule) use (\$keepRules) {
            \$key = \$rule->domain . '|' . (\$rule->path_match ?? '');
            if (!in_array(\$key, \$keepRules, true)) \$rule->delete();
        });

        foreach (\$rules as \$row) {
            ExtensionParserRule::updateOrCreate(
                ['domain' => \$row['domain'], 'path_match' => \$row['path_match']],
                [
                    'parser' => \$row['parser'],
                    'notes'  => \$row['notes'],
                ]
            );
        }
    }
}
PHP;

        $path = database_path('seeders/ParserDataSeeder.php');
        file_put_contents($path, $stub);

        $this->info('Сидер создан: database/seeders/ParserDataSeeder.php');
        $this->info('Парсеров: ' . count($parsers) . ', правил: ' . count($rules));
        $this->line('Запусти на проде: php artisan db:seed --class=ParserDataSeeder');
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }
}
