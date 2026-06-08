<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Move the extension's auth token from a single per-user column to a per-device
 * table, so signing in on one device no longer revokes the others.
 *
 *   extension_tokens.token : sha256 HASH of the device's opaque bearer token
 *                            (plaintext is returned once at login, never stored).
 *   expires_at             : sliding idle TTL (see ExtensionToken::IDLE_TTL_DAYS).
 *
 * SECURITY: this table holds ONLY the hashed extension token. The upstream
 * HelloOtel token is a separate secret and stays on users.hellootel_access_token
 * (encrypted cast + $hidden) — it is never copied here, returned to the
 * extension, or logged.
 *
 * Clean cutover: the old users.api_token columns are dropped, so every device
 * re-logins once (the extension already surfaces a 401 as "session expired").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extension_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();        // sha256 hex digest
            $table->string('device_label')->nullable();   // best-effort UA label
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        // Drop the legacy single-token columns (clean cutover → forced re-login).
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_api_token_unique');
            $table->dropColumn(['api_token', 'api_token_expires_at', 'api_token_last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('api_token', 64)->nullable()->unique()->after('remember_token');
            $table->timestamp('api_token_expires_at')->nullable()->after('api_token');
            $table->timestamp('api_token_last_used_at')->nullable()->after('api_token_expires_at');
        });

        Schema::dropIfExists('extension_tokens');
    }
};
