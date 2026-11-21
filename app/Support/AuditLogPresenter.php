<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

class AuditLogPresenter
{
    /** @var array<string, string> */
    private const CATEGORIES = [
        'appointments' => 'Appointments & documents',
        'payments' => 'Payments & billing',
        'donations' => 'Donations & revenue',
        'facilities' => 'Facilities',
        'schedules' => 'Schedules & clergy',
        'records' => 'Records & reports',
        'administration' => 'Administration & security',
        'other' => 'Other activity',
    ];

    /** @var array<string, list<string>> */
    private const CATEGORY_PREFIXES = [
        'appointments' => ['appointment.', 'document.', 'notification.'],
        'payments' => ['payment.', 'payment_adjustment.', 'invoice.'],
        'donations' => ['donation.', 'donations.', 'revenue.'],
        'facilities' => ['facility.'],
        'schedules' => ['schedule.', 'priest.'],
        'records' => ['record.', 'report.', 'statistics.', 'forecast.', 'demo.'],
        'administration' => ['admin.', 'auth.', 'user.', 'permissions.', 'settings.', 'service.', 'personnel.', 'backup.', 'website_content.'],
    ];

    /** @var array<string, string> */
    private const ACTION_LABELS = [
        'admin.login' => 'Administrator signed in',
        'admin.logout' => 'Administrator signed out',
        'admin.password_authenticated' => 'Administrator confirmed their password',
        'appointment.cancelled' => 'Appointment cancelled',
        'appointment.completed' => 'Appointment completed',
        'appointment.confirmed' => 'Appointment confirmed',
        'appointment.created' => 'Appointment created',
        'appointment.declined' => 'Appointment declined',
        'appointment.expired' => 'Appointment hold expired',
        'appointment.fee_changed' => 'Appointment fee changed',
        'appointment.no_show' => 'Appointment marked as no-show',
        'appointment.pending' => 'Appointment returned to pending',
        'appointment.personnel_assigned' => 'Personnel assigned to appointment',
        'appointment.preference_changed' => 'Preferred schedule changed',
        'auth.login_failed' => 'Sign-in attempt failed',
        'backup.created' => 'Office backup created',
        'document.accessed' => 'Private document viewed',
        'document.uploaded' => 'Document uploaded',
        'donation.corrected' => 'Donation corrected',
        'donation.receipt_viewed' => 'Donation receipt viewed',
        'donation.recorded' => 'Donation recorded',
        'donation.voided' => 'Donation voided',
        'donations.exported' => 'Donations exported',
        'facility.closure.cancelled' => 'Facility closure cancelled',
        'facility.closure.created' => 'Facility closure added',
        'facility.created' => 'Facility added',
        'facility.reservation.approved' => 'Facility request approved',
        'facility.reservation.cancelled' => 'Facility reservation cancelled',
        'facility.reservation.created' => 'Facility reservation created',
        'facility.reservation.expired' => 'Facility request expired',
        'facility.reservation.requested' => 'Facility reservation requested',
        'facility.updated' => 'Facility details updated',
        'forecast.generated' => 'Service forecast generated',
        'invoice.issued' => 'Invoice issued',
        'notification.acknowledged' => 'Office notification acknowledged',
        'payment.payment' => 'Payment recorded',
        'payment.refund' => 'Refund recorded',
        'payment.xendit_completed' => 'Online payment completed',
        'payment.xendit_started' => 'Online payment started',
        'payment_adjustment.approved' => 'Financial adjustment approved',
        'payment_adjustment.rejected' => 'Financial adjustment rejected',
        'payment_adjustment.requested' => 'Financial adjustment requested',
        'permissions.updated' => 'Role permissions updated',
        'personnel.removed' => 'Personnel removed',
        'personnel.updated' => 'Personnel details updated',
        'priest.available' => 'Priest marked as available',
        'priest.rotation_updated' => 'Priest rotation updated',
        'priest.unavailable' => 'Priest marked as unavailable',
        'record.archive_changed' => 'Service record archive status changed',
        'record.corrected' => 'Service record corrected',
        'record.created' => 'Service record created',
        'record.imported' => 'Service record imported',
        'report.exported' => 'Report exported',
        'revenue.exported' => 'Revenue report exported',
        'schedule.blocked' => 'Schedule time blocked',
        'schedule.cancelled' => 'Schedule cancelled',
        'schedule.created' => 'Schedule created',
        'schedule.deleted' => 'Schedule removed',
        'schedule.unblocked' => 'Schedule block removed',
        'schedule.updated' => 'Schedule updated',
        'service.updated' => 'Service details updated',
        'settings.updated' => 'Parish settings updated',
        'statistics.exported' => 'Parish statistics exported',
        'statistics.imported' => 'Parish statistics imported',
        'statistics.saved' => 'Parish statistics updated',
        'user.created' => 'User account created',
        'user.deleted' => 'User account removed',
        'user.password_changed' => 'User password changed',
        'user.role_changed' => 'User role changed',
        'website_content.updated' => 'Website content published',
    ];

    /** @var array<string, string> */
    private const ENTITY_LABELS = [
        'Appointment' => 'Appointment',
        'AppointmentInvoice' => 'Invoice',
        'Donation' => 'Donation',
        'DonationAdjustment' => 'Donation adjustment',
        'Facility' => 'Facility',
        'FacilityBooking' => 'Facility booking',
        'ForecastRun' => 'Forecast',
        'MonthlyStatistic' => 'Monthly statistic',
        'OfficeNotification' => 'Office notification',
        'ParishSetting' => 'Parish setting',
        'Payment' => 'Payment',
        'PaymentAdjustment' => 'Financial adjustment',
        'Personnel' => 'Personnel record',
        'PriestUnavailability' => 'Priest availability',
        'Schedule' => 'Schedule',
        'ScheduleBlock' => 'Schedule block',
        'ServiceRecord' => 'Service record',
        'ServiceType' => 'Service',
        'User' => 'User account',
        'XenditTransaction' => 'Online payment',
    ];

    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'amount_cents' => 'Amount',
        'archived_at' => 'Archive status',
        'attendees' => 'Number of attendees',
        'decision' => 'Decision',
        'documents_due_at' => 'Documents due',
        'ends_at' => 'End time',
        'fee_cents' => 'Fee',
        'file_count' => 'Files added',
        'kind' => 'Type',
        'method' => 'Payment method',
        'name' => 'Name',
        'number' => 'Reference number',
        'paid_at' => 'Payment date',
        'permissions' => 'Access permissions',
        'reference_number' => 'Reference number',
        'role' => 'Account role',
        'rows' => 'Records processed',
        'service_type' => 'Service',
        'starts_at' => 'Start time',
        'status' => 'Status',
        'type' => 'Document type',
    ];

    /** @return array<string, string> */
    public function categories(): array
    {
        return self::CATEGORIES;
    }

    /** @return list<string> */
    public function prefixesForCategory(string $category): array
    {
        return self::CATEGORY_PREFIXES[$category] ?? [];
    }

    /** @return list<string> */
    public function actionsMatching(string $term): array
    {
        $term = Str::lower($term);

        return array_keys(array_filter(
            self::ACTION_LABELS,
            fn (string $label, string $action): bool => Str::contains(Str::lower($label.' '.$action), $term),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /** @return array{title: string, category: string, category_label: string, actor: string, occurred_at: string, subject: string, reason: ?string, changes: list<array{label: string, type: string, before: ?string, after: ?string}>} */
    public function present(AuditLog $entry): array
    {
        $category = $this->category($entry->action);

        return [
            'title' => self::ACTION_LABELS[$entry->action] ?? Str::headline(str_replace('.', ' ', $entry->action)),
            'category' => $category,
            'category_label' => self::CATEGORIES[$category],
            'actor' => $entry->actor?->name ?? 'Public user or automated process',
            'occurred_at' => $entry->created_at->format('M j, Y \a\t g:i A'),
            'subject' => $this->subject($entry),
            'reason' => filled($entry->reason) ? $entry->reason : null,
            'changes' => $this->changes($entry->before ?? [], $entry->after ?? []),
        ];
    }

    private function category(string $action): string
    {
        foreach (self::CATEGORY_PREFIXES as $category => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($action, $prefix)) {
                    return $category;
                }
            }
        }

        return 'other';
    }

    private function subject(AuditLog $entry): string
    {
        if (! $entry->entity_type) {
            return 'General office activity';
        }

        $entity = class_basename($entry->entity_type);
        $label = self::ENTITY_LABELS[$entity] ?? Str::headline($entity);
        $details = [...($entry->before ?? []), ...($entry->after ?? [])];

        foreach (['reference_number', 'booking_reference', 'number', 'receipt_number'] as $key) {
            if (filled($details[$key] ?? null)) {
                return $label.' '.$details[$key];
            }
        }

        return $entry->entity_id ? $label.' record #'.$entry->entity_id : $label;
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @return list<array{label: string, type: string, before: ?string, after: ?string}>
     */
    private function changes(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if ($this->isTechnicalField($field) || (($before[$field] ?? null) === ($after[$field] ?? null))) {
                continue;
            }

            $hasBefore = array_key_exists($field, $before);
            $hasAfter = array_key_exists($field, $after);
            $complex = is_array($before[$field] ?? null) || is_array($after[$field] ?? null);
            $type = $complex ? 'updated' : ($hasBefore && $hasAfter ? 'changed' : ($hasAfter ? 'added' : 'removed'));

            $changes[] = [
                'label' => self::FIELD_LABELS[$field] ?? Str::headline($field),
                'type' => $type,
                'before' => $complex || ! $hasBefore ? null : $this->formatValue($field, $before[$field]),
                'after' => $complex || ! $hasAfter ? null : $this->formatValue($field, $after[$field]),
            ];
        }

        return array_slice($changes, 0, 12);
    }

    private function isTechnicalField(string $field): bool
    {
        if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at', 'version'], true) || str_ends_with($field, '_id')) {
            return true;
        }

        return Str::contains($field, ['password', 'token', 'secret', 'path', 'sha256', 'hash', 'recovery_code', 'idempotency']);
    }

    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not set';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (str_ends_with($field, '_cents') && is_numeric($value)) {
            return '₱'.number_format(((int) $value) / 100, 2);
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}(?:[T\s].*)?$/', $value) === 1) {
            try {
                return Carbon::parse($value)->format(strlen($value) === 10 ? 'M j, Y' : 'M j, Y g:i A');
            } catch (Throwable) {
            }
        }

        if (is_scalar($value)) {
            return Str::headline((string) $value);
        }

        return 'Updated';
    }
}
