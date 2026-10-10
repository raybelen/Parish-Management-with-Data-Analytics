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

        return view('admin.notifications', ['notifications' => OfficeNotification::query()->with('appointment')
            ->whereHas('appointment', fn ($q) => $q->where('source', OfficeSettings::source()))->latest('id')->paginate(30)]);
    }

    public function update(OfficeNotification $notification): RedirectResponse
    {
        Gate::authorize('notifications.manage');
        abort_unless($notification->appointment?->source === OfficeSettings::source(), 404);
        $notification->update(['read_at' => now(), 'read_by' => auth()->id()]);
        OfficeAudit::record('notification.acknowledged', $notification);

        return back()->with('status', 'Notification acknowledged.');
    }
}
