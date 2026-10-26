<?php

namespace App\Http\Controllers;

use App\Actions\RecordPayment;
use App\Models\ServiceType;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminServiceTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new ServiceType);
    }

    public function update(Request $request, ServiceType $serviceType): RedirectResponse
    {
        return $this->save($request, $serviceType);
    }

    private function save(Request $request, ServiceType $serviceType): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => [$serviceType->exists ? 'nullable' : 'required', 'regex:/^[a-z][a-z0-9_]{1,29}$/', Rule::unique('service_types')->ignore($serviceType)],
            'duration_minutes' => ['required', 'integer', Rule::in([60])], 'buffer_minutes' => ['required', 'integer', Rule::in([0])],
            'fee' => ['required', 'numeric', 'min:0', 'max:9999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'active' => ['required', 'boolean'], 'requirements' => ['nullable', 'array', 'max:20'],
            'requirements.*.key' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]{0,60}$/'],
            'requirements.*.label' => ['nullable', 'string', 'max:100'],
            'requirements.*.required' => ['nullable', 'boolean'], 'requirements.*.multiple' => ['nullable', 'boolean'],
        ]);
        $requirements = [];
        foreach ($data['requirements'] ?? [] as $row) {
            if (empty($row['label'])) {
                continue;
            }
            $key = $row['key'] ?? Str::slug($row['label'], '_');
            if (! preg_match('/^[a-z][a-z0-9_]{0,60}$/', $key) || isset($requirements[$key])) {
                throw ValidationException::withMessages(['requirements' => 'Each document needs a distinct label using letters.']);
            }
            $requirements[$key] = ['label' => $row['label'], 'required' => (bool) ($row['required'] ?? false), 'multiple' => (bool) ($row['multiple'] ?? false)];
        }
        DB::transaction(function () use ($serviceType, $data, $requirements): void {
            $before = $serviceType->toArray();
            $serviceType->fill([
                'slug' => $serviceType->exists ? $serviceType->slug : $data['slug'], 'name' => $data['name'],
                'duration_minutes' => 60, 'buffer_minutes' => 0,
                'fee_cents' => RecordPayment::cents($data['fee']), 'requirements' => $requirements, 'active' => $data['active'],
            ])->save();
            OfficeAudit::record('service.updated', $serviceType, $before, $serviceType->toArray());
        });

        return back()->with('status', 'Service saved. Existing appointments retain their original document requirements and fee.');
    }
}
