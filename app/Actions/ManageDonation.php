<?php

namespace App\Actions;

use App\Models\Donation;
use App\Models\DonationAdjustment;
use App\Models\User;
use App\Support\OfficeAudit;
use App\Support\OfficeSettings;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ManageDonation
{
    /** @param array<string, mixed> $data */
    public function record(User $actor, array $data): Donation
    {
        return $this->transaction(function () use ($actor, $data): Donation {
            User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $details = $this->details($data);
            $hash = hash('sha256', json_encode($details, JSON_THROW_ON_ERROR));
            $existing = Donation::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->recorded_by === $actor->id && $existing->source === OfficeSettings::source()
                    && hash_equals($existing->submission_hash, $hash), 409, 'This submission was already used. Open a new donation form.');

                return $existing;
            }
            $collectionKey = $this->validateCollection($details);
            $donation = Donation::query()->firstOrCreate(['idempotency_key' => $data['idempotency_key']], [
                ...$details, 'submission_hash' => $hash, 'source' => OfficeSettings::source(),
                'receipt_number' => ($details['kind'] === 'mass_collection' ? 'MASS-' : 'DON-').Str::upper((string) Str::ulid()),
                'recorded_by' => $actor->id, 'collection_key' => $collectionKey,
            ]);
            abort_unless($donation->recorded_by === $actor->id && $donation->source === OfficeSettings::source()
                && hash_equals($donation->submission_hash, $hash), 409, 'This submission was already used. Open a new donation form.');
            if ($donation->wasRecentlyCreated) {
                OfficeAudit::record('donation.recorded', $donation, [], $donation->details());
            }

            return $donation;
        });
    }

    /** @param array<string, mixed> $data */
    public function requestAdjustment(Donation $donation, User $actor, string $kind, array $data): void
    {
        $this->transaction(function () use ($donation, $actor, $kind, $data): void {
            $locked = $this->lockDonation($donation);
            $payload = $kind === 'void' ? [] : $this->details($data);
            $existing = DonationAdjustment::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                abort_unless($existing->donation_id === $locked->id && $existing->requested_by === $actor->id
                    && $existing->kind === $kind && $existing->donation_version === (int) $data['version']
                    && $existing->payload === $payload && $existing->reason === $data['reason'], 409);

                return;
            }
            abort_unless($locked->status === 'recorded' && $locked->version === (int) $data['version'], 409, 'This donation changed. Reload before submitting.');
            abort_if($locked->adjustments()->where('status', 'pending')->exists(), 409, 'A request is already awaiting review.');
            if ($kind === 'correction') {
                $this->validateCollection($payload, $locked);
            } else {
                $this->validateVoid($locked);
            }
            $adjustment = $locked->adjustments()->create([
                'idempotency_key' => $data['idempotency_key'], 'kind' => $kind,
                'donation_version' => $locked->version, 'before' => $locked->details(),
                'payload' => $payload, 'reason' => $data['reason'], 'requested_by' => $actor->id,
            ]);
            OfficeAudit::record('donation_adjustment.requested', $adjustment, [], [
                'donation_id' => $locked->id, 'kind' => $kind, 'payload' => $payload,
            ], $data['reason']);
        });
    }

    public function review(DonationAdjustment $adjustment, User $actor, string $decision, string $reason): void
    {
        $this->transaction(function () use ($adjustment, $actor, $decision, $reason): void {
            $donation = $this->lockDonation($adjustment->donation);
            $locked = DonationAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            abort_if($locked->requested_by === $actor->id, 403, 'A different super administrator must review this request.');
            abort_unless($locked->status === 'pending', 409, 'This request was already reviewed.');
            if ($decision === 'approved') {
                abort_unless($donation->status === 'recorded' && $donation->version === $locked->donation_version, 409, 'This donation changed. Reject this request and submit a new one.');
                $before = [...$donation->details(), 'status' => $donation->status, 'version' => $donation->version];
                $payload = [...$donation->details(), ...$locked->payload];
                if ($locked->kind === 'void') {
                    $this->validateVoid($donation);
                    $collectionKey = null;
                } else {
                    if (($payload['is_anonymous'] ?? false) && ! $donation->is_anonymous) {
                        throw ValidationException::withMessages(['decision' => 'Anonymous donations are no longer supported. Reject this request and submit a named donation correction.']);
                    }
                    $collectionKey = $this->validateCollection($payload, $donation);
                }
                $donation->update([
                    ...($locked->kind === 'void' ? ['status' => 'voided'] : $payload),
                    'collection_key' => $collectionKey,
                    'version' => $donation->version + 1,
                ]);
                OfficeAudit::record('donation.'.($locked->kind === 'void' ? 'voided' : 'corrected'), $donation,
                    $before, [...$donation->details(), 'status' => $donation->status, 'version' => $donation->version], $reason);
            }
            $locked->update(['status' => $decision, 'reviewed_by' => $actor->id, 'review_reason' => $reason, 'reviewed_at' => now()]);
            OfficeAudit::record('donation_adjustment.'.$decision, $locked, ['status' => 'pending'], ['status' => $decision], $reason);
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function details(array $data): array
    {
        $details = [];
        foreach (Donation::DETAIL_FIELDS as $field) {
            $details[$field] = $field === 'amount_cents' ? RecordPayment::cents((string) $data['amount']) : ($data[$field] ?? null);
        }
        $details['kind'] = $data['kind'] ?? 'individual';
        $details['is_anonymous'] = false;
        $details['mass_collection_id'] = filled($data['mass_collection_id'] ?? null) ? (int) $data['mass_collection_id'] : null;
        if ($details['kind'] === 'mass_collection') {
            $details['donor_name'] = 'Mass congregation';
            foreach (['donor_email', 'donor_phone', 'donor_address'] as $field) {
                $details[$field] = null;
            }
        }
        if ($details['kind'] !== 'mass_collection') {
            foreach (Donation::MASS_FIELDS as $field) {
                $details[$field] = null;
            }
        } elseif ($details['collection_type'] === 'regular') {
            $details['collection_purpose'] = null;
        }

        return $details;
    }

    private function lockDonation(Donation $donation): Donation
    {
        if ($donation->mass_collection_id) {
            Donation::query()->whereKey($donation->mass_collection_id)->lockForUpdate()->firstOrFail();
        }

        return Donation::query()->lockForUpdate()->findOrFail($donation->id);
    }

    /** @param array<string, mixed> $details */
    private function validateCollection(array $details, ?Donation $donation = null): ?string
    {
        if ($details['mass_collection_id']) {
            $collection = Donation::query()->where('source', OfficeSettings::source())->where('kind', 'mass_collection')
                ->lockForUpdate()->findOrFail($details['mass_collection_id']);
            if ($collection->status !== 'recorded') {
                throw ValidationException::withMessages(['mass_collection_id' => 'Choose a recorded Mass collection.']);
            }
            $allocated = $collection->itemizedDonations()->where('status', 'recorded')
                ->when($donation, fn (Builder $query): Builder => $query->whereKeyNot($donation->id))->sum('amount_cents');
            if ($details['amount_cents'] > $collection->amount_cents - $allocated) {
                throw ValidationException::withMessages(['amount' => 'Individual receipts cannot exceed the Mass collection total.']);
            }
            if ($details['donated_on'] !== $collection->donated_on->toDateString() || $details['method'] !== $collection->method || $details['category'] !== $collection->category) {
                throw ValidationException::withMessages(['mass_collection_id' => 'Use the date, category, and payment method of the Mass collection.']);
            }

            return null;
        }
        if ($details['kind'] !== 'mass_collection') {
            return null;
        }
        if ($donation && $donation->itemizedDonations()->where('status', 'recorded')->exists()) {
            if ($details['amount_cents'] < $donation->itemizedDonations()->where('status', 'recorded')->sum('amount_cents')) {
                throw ValidationException::withMessages(['amount' => 'The collection total cannot be lower than its individual receipts.']);
            }
            foreach (['donated_on', 'method', 'category', 'church', 'mass_time', 'collection_type', 'collection_purpose'] as $field) {
                if ($details[$field] !== $donation->details()[$field]) {
                    throw ValidationException::withMessages([$field => 'Void the individual receipts before changing the Mass or payment details.']);
                }
            }
        }
        $key = hash('sha256', json_encode([
            OfficeSettings::source(), $details['donated_on'], $details['mass_time'], $details['church'],
            $details['collection_type'], mb_strtolower(trim($details['collection_purpose'] ?? '')), $details['method'],
        ], JSON_THROW_ON_ERROR));
        if (Donation::query()->where('collection_key', $key)->when($donation, fn (Builder $query): Builder => $query->whereKeyNot($donation->id))->exists()) {
            throw ValidationException::withMessages(['mass_time' => 'This Mass collection and payment method are already recorded. Open the existing collection to request a correction.']);
        }

        return $key;
    }

    private function validateVoid(Donation $donation): void
    {
        if ($donation->isMassCollection() && $donation->itemizedDonations()->where('status', 'recorded')->exists()) {
            throw ValidationException::withMessages(['reason' => 'Void the individual receipts before voiding their Mass collection.']);
        }
    }

    private function transaction(Closure $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['idempotency_key' => 'A matching collection or submission was already recorded. Reload the donation list before trying again.']);
        }
    }
}
