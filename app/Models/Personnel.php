<?php

namespace App\Models;

use Database\Factories\PersonnelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personnel extends Model
{
    protected $table = 'personnel';

    /** @use HasFactory<PersonnelFactory> */
    use HasFactory;

    protected $fillable = ['name', 'user_id', 'active', 'rotation_position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['active' => 'boolean', 'rotation_position' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unavailabilities(): HasMany
    {
        return $this->hasMany(PriestUnavailability::class);
    }
}
