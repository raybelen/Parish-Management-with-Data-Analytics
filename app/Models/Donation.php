<?php

namespace App\Models;

use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    public const CATEGORIES = ['general' => 'General offering', 'mass_offering' => 'Mass offering', 'tithe' => 'Tithe', 'building' => 'Building and maintenance', 'charity' => 'Charity and outreach', 'mission' => 'Mission', 'other' => 'Other'];

    public const COLLECTION_TYPES = ['regular' => 'Regular offertory', 'special' => 'Special collection'];

    public const MASS_FIELDS = ['church', 'mass_time', 'collection_type', 'collection_purpose', 'counted_by', 'verified_by'];

    public const METHODS = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'gcash' => 'GCash', 'check' => 'Check', 'other' => 'Other'];

    public const DETAIL_FIELDS = ['donor_name', 'donor_email', 'donor_phone', 'donor_address', 'amount_cents', 'donated_on', 'category', 'method', 'reference', 'notes', 'kind', 'is_anonymous', 'mass_collection_id', ...self::MASS_FIELDS];

    protected $fillable = ['receipt_number', 'idempotency_key', 'submission_hash', 'source', 'status', 'version', 'recorded_by', 'collection_key', ...self::DETAIL_FIELDS];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['donated_on' => 'immutable_date', 'amount_cents' => 'integer', 'version' => 'integer', 'is_anonymous' => 'boolean', 'mass_collection_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Donation records must be voided, not deleted.'));
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(DonationAdjustment::class);
    }

    public function massCollection(): BelongsTo
    {
        return $this->belongsTo(self::class, 'mass_collection_id');
    }

    public function itemizedDonations(): HasMany
    {
        return $this->hasMany(self::class, 'mass_collection_id');
    }

    public function isMassCollection(): bool
    {
        return $this->kind === 'mass_collection';
    }

    public function donorLabel(): string
    {
        return $this->isMassCollection() ? 'Mass congregation' : ($this->is_anonymous ? 'Anonymous' : $this->donor_name);
    }

    /** @return array<string, mixed> */
    public function details(): array
    {
        return [...$this->only(self::DETAIL_FIELDS), 'donated_on' => $this->donated_on->toDateString()];
    }
}
