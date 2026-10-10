<?php

namespace App\Models;

use Database\Factories\OfficeNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeNotification extends Model
{
    /** @use HasFactory<OfficeNotificationFactory> */
    use HasFactory;

    protected $fillable = ['appointment_id', 'event', 'message', 'deduplication_key', 'read_at', 'read_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
