<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Support\OfficeAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminPersonnelController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new Personnel);
    }

    public function update(Request $request, Personnel $personnel): RedirectResponse
    {
        return $this->save($request, $personnel);
    }

    private function save(Request $request, Personnel $personnel): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'active' => ['required', 'boolean'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'priest'), Rule::unique('personnel', 'user_id')->ignore($personnel)],
        ]);
        DB::transaction(function () use ($personnel, $data): void {
            $before = $personnel->toArray();
            $personnel->fill($data)->save();
            OfficeAudit::record('personnel.updated', $personnel, $before, $personnel->toArray());
        });

        return back()->with('status', 'Personnel saved.');
    }
}
