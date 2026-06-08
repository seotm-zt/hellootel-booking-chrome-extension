<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'hellootel_access_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'password'               => 'hashed',
            // Upstream HelloOtel token: encrypted at rest, never exposed.
            'hellootel_access_token' => 'encrypted',
        ];
    }

    /** Per-device extension auth tokens (one row per signed-in device). */
    public function extensionTokens(): HasMany
    {
        return $this->hasMany(ExtensionToken::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'operator']);
    }
}
