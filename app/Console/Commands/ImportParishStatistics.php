<?php

namespace App\Console\Commands;

use App\Models\MonthlyStatistic;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use App\Support\ParishStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportParishStatistics extends Command
{
    protected $signature = 'analytics:import-history';

    protected $description = 'Import the verified 2015–2025 parish totals without overwriting existing statistics';

    public function handle(): int
    {
        if (OfficeSettings::demo()) {
            $this->error('Real parish reports cannot be imported into demo mode.');

            return self::FAILURE;
        }
        $payload = json_decode(file_get_contents(database_path('seeders/parish-statistics-2015-2025.json')), true, 512, JSON_THROW_ON_ERROR);
        try {
            $imported = Cache::lock('statistics:historical-import', 120)->block(3, function () use ($payload): int {
                return DB::transaction(function () use ($payload): int {
                    $existingRows = MonthlyStatistic::query()->where('source', 'operational')
                        ->whereBetween('month', ['2015-01-01', '2025-12-01'])->lockForUpdate()->get()
                        ->keyBy(fn ($record) => $record->month->format('Y-m').':'.$record->service_type);
                    $inserts = [];
                    $timestamp = now();
                    foreach ($payload['months'] as $row) {
                        foreach (ParishStatistics::SERVICES as $service => $label) {
                            $count = $row[$service];
                            if ($count === null) {
                                continue;
                            }
                            if (! is_int($count) || $count < 0) {
                                throw new RuntimeException('Invalid historical count.');
                            }
                            $key = ['source' => 'operational', 'month' => $row['month'].'-01', 'service_type' => $service];
                            $existing = $existingRows->get($row['month'].':'.$service);
                            if ($existing) {
                                if ($existing->count !== $count || $existing->status !== 'confirmed') {
                                    throw new RuntimeException('Import stopped: '.$row['month'].' '.$label.' already has a different value or is unconfirmed. Review the monthly statistics; existing edits were preserved.');
                                }

                                continue;
                            }
                            $inserts[] = [...$key, 'count' => $count, 'status' => 'confirmed', 'reference' => $payload['reference'],
                                'notes' => implode("\n", $payload['decisions']), 'confirmed_by' => auth()->id(), 'confirmed_at' => $timestamp,
                                'version' => 1, 'created_at' => $timestamp, 'updated_at' => $timestamp];
                        }
                    }
                    foreach (array_chunk($inserts, 50) as $chunk) {
                        MonthlyStatistic::query()->insert($chunk);
                    }
                    $imported = count($inserts);
                    if ($imported > 0) {
                        OfficeAudit::record('statistics.imported', null, [], ['rows' => $imported, 'reference' => $payload['reference'], 'sha256' => hash_file('sha256', database_path('seeders/parish-statistics-2015-2025.json'))], 'Import of user-approved monthly sums.');
                    }

                    return $imported;
                });
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info($imported.' verified monthly totals imported. Matching existing totals were left unchanged.');

        return self::SUCCESS;
    }
}
