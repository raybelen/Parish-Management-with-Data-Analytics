<?php

namespace App\Models;

use Database\Factories\ForecastRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ForecastRun extends Model
{
    /** @use HasFactory<ForecastRunFactory> */
    use HasFactory;

    protected $fillable = ['source', 'service_type', 'status', 'message', 'configuration', 'history', 'results', 'evaluation', 'generated_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['configuration' => 'array', 'history' => 'array', 'results' => 'array', 'evaluation' => 'array'];
    }
}
