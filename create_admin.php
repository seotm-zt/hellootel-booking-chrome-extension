<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

// ─── настройки нового администратора ───────────────────────────────────────
$name     = 'Admin';
$email    = 'admin@booking-configurator.hellootel.com';
$password = 'change-me-123!';          // ← поменяй перед запуском
// ───────────────────────────────────────────────────────────────────────────

Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

$user = User::updateOrCreate(
    ['email' => $email],
    [
        'name'         => $name,
        'password'     => bcrypt($password),
        'access_token' => Str::random(60),
    ]
);

$user->syncRoles(['admin']);

echo "✓ Пользователь создан / обновлён:" . PHP_EOL;
echo "  Email:        {$user->email}" . PHP_EOL;
echo "  Роль:         admin" . PHP_EOL;
echo "  access_token: {$user->access_token}" . PHP_EOL;
echo PHP_EOL;
echo "Удали этот файл после использования!" . PHP_EOL;
