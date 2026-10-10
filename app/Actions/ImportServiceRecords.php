<?php

namespace App\Actions;

use App\Models\ServiceRecord;
use App\Support\OfficeAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportServiceRecords
{
    public const COLUMNS = ['record_number', 'service_type', 'service_date', 'participant_names', 'church', 'officiant', 'register_book', 'register_page', 'register_entry', 'notes'];

    public function handle(UploadedFile $file, string $reason): int
    {
        $handle = fopen($file->getRealPath(), 'r');
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if ($header) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            if ($header !== self::COLUMNS) {
                throw ValidationException::withMessages(['file' => 'Use the exact columns from the downloadable CSV template.']);
            }
            $rows = [];
            $numbers = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                $line = count($rows) + 2;
                if (count($values) !== count($header) || count($rows) >= 1000) {
                    throw ValidationException::withMessages(['file' => "Invalid column count or more than 1,000 rows at line {$line}."]);
                }
                $row = array_combine($header, array_map(fn ($value) => trim($value ?? ''), $values));
                $validator = Validator::make($row, [
                    'record_number' => ['required', 'string', 'max:100', 'unique:service_records'],
                    'service_type' => ['required', 'exists:service_types,slug'],
                    'service_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'participant_names' => ['required', 'string', 'max:255'], 'church' => ['required', 'string', 'max:255'],
                    'officiant' => ['nullable', 'string', 'max:255'], 'register_book' => ['nullable', 'string', 'max:100'],
                    'register_page' => ['nullable', 'string', 'max:100'], 'register_entry' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:4000'],
                ]);
                if ($validator->fails() || isset($numbers[$row['record_number']])) {
                    throw ValidationException::withMessages(['file' => "Line {$line}: ".($validator->errors()->first() ?: 'Duplicate record number in this file.')]);
                }
                $numbers[$row['record_number']] = true;
                $rows[] = $row;
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['file' => 'The CSV contains no records.']);
            }

            return DB::transaction(function () use ($rows, $reason): int {
                foreach ($rows as $row) {
                    $record = ServiceRecord::create([...$row, 'source' => 'archival', 'recorded_by' => auth()->id()]);
                    OfficeAudit::record('record.imported', $record, [], $row, $reason);
                }

                return count($rows);
            });
        } finally {
            fclose($handle);
        }
    }
}
