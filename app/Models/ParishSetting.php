<?php

namespace App\Models;

use Database\Factories\ParishSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParishSetting extends Model
{
    /** @use HasFactory<ParishSettingFactory> */
    use HasFactory;

    protected $fillable = ['key', 'value'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
