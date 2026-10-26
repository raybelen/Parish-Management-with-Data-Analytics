<?php

namespace App\Models;

use App\Support\OfficeSettings;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use HasFactory;

    protected $fillable = ['source', 'title', 'description', 'notes', 'location', 'department', 'category', 'personnel_id', 'starts_at', 'ends_at', 'status', 'series_id', 'recurrence', 'recurrence_until', 'group_capacity', 'group_fee', 'version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'recurrence_until' => 'immutable_date', 'group_capacity' => 'integer', 'group_fee' => 'decimal:2', 'version' => 'integer'];
    }

    /** @return Collection<int, self> */
    public static function publicGroupSessions(): Collection
    {
        return self::query()->where('source', OfficeSettings::source())
            ->where('category', 'Group Baptism')->where('status', 'scheduled')->where('starts_at', '>', now())
            ->whereIn('location', OfficeSettings::activeVenues())->orderBy('starts_at')->limit(100)
            ->get(['id', 'starts_at', 'ends_at', 'location', 'group_capacity', 'group_fee']);
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }
}
