<?php

namespace App\Models;

use Database\Factories\ServiceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecord extends Model
{
    /** @use HasFactory<ServiceRecordFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'record_number', 'source', 'service_type', 'service_date', 'participant_names', 'participants', 'details', 'church', 'officiant', 'register_book', 'register_page', 'register_entry', 'notes', 'recorded_by', 'archived_at', 'version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['participants' => 'array', 'details' => 'array', 'service_date' => 'date', 'archived_at' => 'datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Historical records must be archived, never deleted.'));
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
