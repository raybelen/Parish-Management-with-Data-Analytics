@can('appointments.manage')
    @if(in_array($appointment->status, ['pending', 'confirmed']))
        <section id="appointment-status" class="admin-card scroll-mt-28" data-appointment-status-section>
            <h2 class="mb-4 text-2xl">Update appointment status</h2>
            <form class="grid items-end gap-4 sm:grid-cols-2" method="POST" action="{{ route('admin.appointments.status', $appointment) }}" data-confirm="Save this status change? Completed records and final outcomes will be preserved.">
                @csrf
                <input type="hidden" name="version" value="{{ $appointment->version }}">
                <x-admin.field label="Change status to" name="status">
                    <select class="admin-input" name="status">
                        @foreach(($appointment->status === 'pending' ? ['declined', 'cancelled'] : ['completed', 'cancelled', 'no_show']) as $status)
                            @if(auth()->user()->can('appointments.manage') || $status === 'declined')
                                <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endif
                        @endforeach
                    </select>
                </x-admin.field>
                @if($appointment->status === 'confirmed')
                    <x-admin.field label="Service completed at (if marking completed)" name="completed_at" type="datetime-local" :value="now()->format('Y-m-d\TH:i')" />
                @endif
                <x-admin.field label="Reason for this change" name="reason" required />
                <button class="admin-button">Save status</button>
            </form>
        </section>
    @endif
@endcan
