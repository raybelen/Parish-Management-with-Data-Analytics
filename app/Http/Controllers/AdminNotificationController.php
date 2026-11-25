<?php

namespace App\Http\Controllers;

use App\Models\OfficeNotification;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminNotificationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('notifications.manage');

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:unread,acknowledged'],
            'type' => ['nullable', 'string', 'in:appointment,facility'],
        ]);

        $notifications = OfficeNotification::query()->with(['appointment', 'facilityBooking.facility'])
            ->forSource(OfficeSettings::source());

        if (($filters['status'] ?? null) === 'unread') {
            $notifications->whereNull('read_at');
        } elseif (($filters['status'] ?? null) === 'acknowledged') {
            $notifications->whereNotNull('read_at');
        }

        if (($filters['type'] ?? null) === 'appointment') {
            $notifications->whereNotNull('appointment_id');
        } elseif (($filters['type'] ?? null) === 'facility') {
            $notifications->whereNotNull('facility_booking_id');
        }

        return view('admin.notifications', [
            'notifications' => $notifications->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
        ]);
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
