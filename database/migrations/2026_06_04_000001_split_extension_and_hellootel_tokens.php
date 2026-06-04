<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the single users.access_token (which was BOTH the extension auth token
 * and the upstream HelloOtel token, stored in plaintext) into two secrets:
 *
 *   - api_token              : sha256 HASH of the extension's own opaque token.
 *   - hellootel_access_token : the upstream HelloOtel token, stored ENCRYPTED.
 *
 * Clean cutover: api_token is left NULL for existing rows, so every device must
 * re-login once (the extension token is regenerated server-side on login).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. New columns for the hashed extension token + its lifecycle.
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('api_token_expires_at')->nullable()->after('api_token');
            $table->timestamp('api_token_last_used_at')->nullable()->after('api_token_expires_at');
        });

        // 2. Repurpose access_token → hellootel_access_token (encrypted, longer).
        //    Drop the unique index first: a TEXT column can't carry it, and the
        //    upstream token is no longer a lookup key.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_access_token_unique');
            $table->renameColumn('access_token', 'hellootel_access_token');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->text('hellootel_access_token')->nullable()->change();
        });

        // 3. Encrypt existing plaintext upstream tokens so the model's
        //    `encrypted` cast can read them. api_token stays NULL (forced re-login).
        DB::table('users')
            ->whereNotNull('hellootel_access_token')
            ->orderBy('id')
            ->select('id', 'hellootel_access_token')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('users')->where('id', $row->id)->update([
                        'hellootel_access_token' => Crypt::encryptString($row->hellootel_access_token),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Best-effort decrypt back to plaintext so the legacy schema keeps working.
        DB::table('users')
            ->whereNotNull('hellootel_access_token')
            ->orderBy('id')
            ->select('id', 'hellootel_access_token')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $plain = Crypt::decryptString($row->hellootel_access_token);
                    } catch (\Throwable $e) {
                        $plain = null; // already plaintext or undecryptable
                    }
                    if ($plain !== null) {
                        DB::table('users')->where('id', $row->id)->update([
                            'hellootel_access_token' => $plain,
                        ]);
                    }
                }
            });

        Schema::table('users', function (Blueprint $table) {
            $table->string('hellootel_access_token', 64)->nullable()->change();
            $table->renameColumn('hellootel_access_token', 'access_token');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->unique('access_token', 'users_access_token_unique');
            $table->dropColumn(['api_token', 'api_token_expires_at', 'api_token_last_used_at']);
        });
    }
};
