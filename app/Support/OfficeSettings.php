<?php

namespace App\Support;

use App\Models\ParishSetting;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfficeSettings
{
    private const REQUEST_CACHE_KEY = 'office.settings';

    /** @return array<string, array<string, mixed>> */
    public static function services(): array
    {
        return ServiceType::query()->orderBy('id')->get()->mapWithKeys(fn ($service) => [$service->slug => [
            'label' => $service->name, 'documents' => $service->requirements, 'active' => $service->active,
        ]])->all();
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $request = app()->bound('request') ? request() : null;
        if ($request instanceof Request && $request->attributes->has(self::REQUEST_CACHE_KEY)) {
            return $request->attributes->get(self::REQUEST_CACHE_KEY);
        }

        $settings = array_replace(
            config('office.defaults'),
            ParishSetting::query()->where('key', 'office')->value('value') ?? [],
        );
        $request?->attributes->set(self::REQUEST_CACHE_KEY, $settings);

        return $settings;
    }

    public static function forgetMemoizedValues(): void
    {
        if (app()->bound('request')) {
            request()->attributes->remove(self::REQUEST_CACHE_KEY);
        }
    }

    public static function demo(): bool
    {
        if (! config('office.demo')) {
            return false;
        }
        $connection = DB::connection();
        $database = str_replace('\\', '/', $connection->getDatabaseName());
        if (! app()->environment(['local', 'testing', 'demo']) || $connection->getDriverName() !== 'sqlite'
            || ! (str_ends_with($database, '/analytics-demo.sqlite') || (app()->environment('testing') && $database === ':memory:'))) {
            throw new \RuntimeException('Demo mode requires a separate analytics-demo.sqlite database in a local or demo environment.');
        }

        return true;
    }

    public static function source(): string
    {
        return self::demo() ? 'synthetic' : 'operational';
    }
}
