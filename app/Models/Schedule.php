<?php

namespace App\Models;

use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    protected $fillable = ['source', 'title', 'description', 'notes', 'location', 'department', 'category', 'personnel_id', 'starts_at', 'ends_at', 'status', 'series_id', 'recurrence', 'recurrence_until', 'version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'recurrence_until' => 'immutable_date', 'version' => 'integer'];
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
