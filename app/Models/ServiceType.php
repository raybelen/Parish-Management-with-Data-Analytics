<?php

namespace App\Models;

use Database\Factories\ServiceTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    /** @use HasFactory<ServiceTypeFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name', 'duration_minutes', 'buffer_minutes', 'fee_cents', 'requirements', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['requirements' => 'array', 'active' => 'boolean', 'fee_cents' => 'integer', 'duration_minutes' => 'integer', 'buffer_minutes' => 'integer'];
    }
}
