<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'hellootel_access_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'hellootel_access_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            // Upstream HelloOtel token: encrypted at rest, never exposed.
            'hellootel_access_token' => 'encrypted',
            'api_token_expires_at'   => 'datetime',
            'api_token_last_used_at' => 'datetime',
        ];
    }

    /**
     * Resolve a user by the plaintext extension token. Only the sha256 hash is
     * stored, so we hash the incoming token and look that up.
     */
    public static function findByApiToken(?string $plain): ?self
    {
        if (!$plain) {
            return null;
        }

        return static::where('api_token', hash('sha256', $plain))->first();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'operator']);
    }
}
