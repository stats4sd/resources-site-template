<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A single-use, expiring token that lets a newly-created user set their own password
 * without registering. The high-entropy `token` is the sole credential in the set-password
 * URL. Modelled on Invite, but there is no registration and no role — the user already
 * exists; the link only sets a password.
 */
class PasswordSetup extends Model
{
    use HasFactory;

    /** Setup links are valid for this many days from issue/resend. */
    public const EXPIRY_DAYS = 7;

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PasswordSetup $setup): void {
            if (blank($setup->token)) {
                $setup->token = static::generateToken();
            }

            if (blank($setup->expires_at)) {
                $setup->expires_at = now()->addDays(self::EXPIRY_DAYS);
            }
        });
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Not yet used and not yet expired. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** True when the link can still be used to set a password (unused, unexpired). */
    public function isUsable(): bool
    {
        return $this->used_at === null && ! $this->isExpired();
    }

    /**
     * Issue a fresh token + expiry window (used by the "resend" action, and valid even on
     * an already-expired/used setup — it revives it).
     */
    public function refreshToken(): static
    {
        $this->forceFill([
            'token' => static::generateToken(),
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'used_at' => null,
        ])->save();

        return $this;
    }

    public function markUsed(?Carbon $at = null): static
    {
        $this->forceFill(['used_at' => $at ?? now()])->save();

        return $this;
    }
}
