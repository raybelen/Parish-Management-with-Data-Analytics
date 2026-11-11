<?php

namespace App\Models;

use Database\Factories\DonationAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationAdjustment extends Model
{
    /** @use HasFactory<DonationAdjustmentFactory> */
    use HasFactory;

    protected $fillable = ['donation_id', 'idempotency_key', 'kind', 'donation_version', 'before', 'payload', 'reason', 'status', 'requested_by', 'reviewed_by', 'review_reason', 'reviewed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['before' => 'array', 'payload' => 'array', 'donation_version' => 'integer', 'reviewed_at' => 'immutable_datetime'];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
