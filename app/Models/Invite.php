<?php

namespace App\Models;

use App\Enums\InviteStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An email invitation to register. The high-entropy `token` is the sole credential in the
 * register URL. There is deliberately NO global scope hiding accepted invites — history
 * stays visible and is filtered in the table instead.
 */
class Invite extends Model
{
    use HasFactory;

    /** Invites are valid for this many days from issue/resend. */
    public const EXPIRY_DAYS = 7;

    protected $casts = [
        'role' => UserRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invite $invite): void {
            if (blank($invite->token)) {
                $invite->token = static::generateToken();
            }

            if (blank($invite->expires_at)) {
                $invite->expires_at = now()->addDays(self::EXPIRY_DAYS);
            }
        });
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** Not yet accepted and not yet expired. */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function getStatusAttribute(): InviteStatus
    {
        if ($this->accepted_at !== null) {
            return InviteStatus::Accepted;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return InviteStatus::Expired;
        }

        return InviteStatus::Pending;
    }

    /** True when the invite can still be used to register (pending, unexpired). */
    public function isUsable(): bool
    {
        return $this->status === InviteStatus::Pending;
    }

    /**
     * Issue a fresh token + expiry window (used by the "resend" action, and valid even on
     * an already-expired invite — it revives it).
     */
    public function refreshToken(): static
    {
        $this->forceFill([
            'token' => static::generateToken(),
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            'accepted_at' => null,
        ])->save();

        return $this;
    }

    public function markAccepted(?Carbon $at = null): static
    {
        $this->forceFill(['accepted_at' => $at ?? now()])->save();

        return $this;
    }
}
