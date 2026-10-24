<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MonthlyStatisticFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyStatistic extends Model
{
    public function setMonthAttribute(string $value): void
    {
        $this->attributes['month'] = CarbonImmutable::parse($value)->toDateString();
    }

    /** @use HasFactory<MonthlyStatisticFactory> */
    use HasFactory;

    protected $fillable = ['source', 'service_type', 'month', 'count', 'status', 'reference', 'notes', 'confirmed_by', 'confirmed_at', 'version'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'immutable_date', 'count' => 'integer', 'confirmed_at' => 'datetime', 'version' => 'integer'];
    }
}
