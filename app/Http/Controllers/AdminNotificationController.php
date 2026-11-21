<?php

namespace App\Http\Controllers;

use App\Models\OfficeNotification;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AdminNotificationController extends Controller
{
    public function index(): View
    {
        Gate::authorize('notifications.manage');

        return view('admin.notifications', ['notifications' => OfficeNotification::query()->with(['appointment', 'facilityBooking.facility'])
            ->forSource(OfficeSettings::source())->latest('id')->paginate(30)]);
    }

    public function update(OfficeNotification $notification): RedirectResponse
    {
        Gate::authorize('notifications.manage');
        abort_unless(OfficeNotification::query()->forSource(OfficeSettings::source())->whereKey($notification->id)->exists(), 404);
        $notification->update(['read_at' => now(), 'read_by' => auth()->id()]);
        OfficeAudit::record('notification.acknowledged', $notification);

        return back()->with('status', 'Notification acknowledged.');
    }
}
