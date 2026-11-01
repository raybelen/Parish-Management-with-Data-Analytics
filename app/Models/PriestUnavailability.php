<?php

namespace App\Models;

use Database\Factories\PriestUnavailabilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriestUnavailability extends Model
{
    /** @use HasFactory<PriestUnavailabilityFactory> */
    use HasFactory;

    protected $fillable = ['personnel_id', 'unavailable_on', 'reason'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['unavailable_on' => 'immutable_date'];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
