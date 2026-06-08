<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per signed-in device. Only the sha256 hash of the bearer token is
 * stored — the plaintext is returned once at login and never persisted.
 *
 * SECURITY: this model intentionally knows nothing about the upstream HelloOtel
 * token. That secret lives only on users.hellootel_access_token (encrypted +
 * $hidden) and must never be stored here or exposed through this model.
 */
class ExtensionToken extends Model
{
    /** Sliding idle timeout: a token dies after this many days without a request. */
    public const IDLE_TTL_DAYS = 30;

    protected $fillable = [
        'user_id',
        'token',
        'device_label',
        'last_used_at',
        'expires_at',
    ];

    // The sha256 hash is a credential — never expose it in array/JSON output.
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Expiry for a freshly issued/refreshed token, counting idle time from now. */
    public static function freshExpiry(): Carbon
    {
        return now()->addDays(self::IDLE_TTL_DAYS);
    }

    /**
     * Resolve a live token row from the plaintext bearer value. Only the hash is
     * stored, so we hash the incoming token and look that up. An expired row is
     * deleted and treated as missing.
     */
    public static function findValidByPlain(?string $plain): ?self
    {
        if (!$plain) {
            return null;
        }

        $record = static::where('token', hash('sha256', $plain))->first();

        if (!$record) {
            return null;
        }

        if ($record->expires_at && $record->expires_at->isPast()) {
            $record->delete();
            return null;
        }

        return $record;
    }
}
