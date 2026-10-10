<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWebsiteContentRequest;
use App\Models\ParishSetting;
use App\Support\OfficeAudit;
use App\Support\WebsiteContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminWebsiteContentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('content.manage');

        return view('admin.website-content', [
            'content' => WebsiteContent::values(),
            'groups' => WebsiteContent::groups(),
        ]);
    }

    public function update(UpdateWebsiteContentRequest $request): RedirectResponse
    {
        /** @var array<string, string> $content */
        $content = $request->validated('content');

        DB::transaction(function () use ($content): void {
            $setting = ParishSetting::query()->firstOrCreate(
                ['key' => WebsiteContent::SETTING_KEY],
                ['value' => []],
            );
            $setting = ParishSetting::query()->whereKey($setting->getKey())->lockForUpdate()->firstOrFail();
            $before = WebsiteContent::merge($setting->value);
            $after = WebsiteContent::merge($content);
            $setting->update(['value' => $after]);

            $changedKeys = array_keys(array_filter(
                $after,
                fn (string $value, string $key): bool => $before[$key] !== $value,
                ARRAY_FILTER_USE_BOTH,
            ));

            OfficeAudit::record(
                'website_content.updated',
                $setting,
                array_intersect_key($before, array_flip($changedKeys)),
                array_intersect_key($after, array_flip($changedKeys)),
            );
        });
        WebsiteContent::forgetCachedValues();

        return redirect()->route('admin.website-content.index')->with('status', 'Website content saved and published.');
    }
}
