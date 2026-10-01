<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAdminRoleRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AdminRoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-admin-roles');

        return view('admin.roles', ['users' => User::query()->orderBy('name')->paginate(25)]);
    }

    public function update(UpdateAdminRoleRequest $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403, 'You cannot change your own role.');

        DB::transaction(function () use ($user, $request): void {
            $lockedUsers = User::query()
                ->where('role', User::ROLE_SUPER_ADMIN)
                ->orWhereKey([$user->getKey(), $request->user()->getKey()])
                ->orderBy('id')->lockForUpdate()->get();
            $actor = $lockedUsers->find($request->user()->getKey());
            abort_unless($actor?->role === User::ROLE_SUPER_ADMIN, 403);
            $target = $lockedUsers->find($user->getKey());
            $target->role = $request->validated('role');
            $target->save();
            DB::table('sessions')->where('user_id', $target->getKey())->delete();
        });

        return back()->with('status', 'Role updated. The user must sign in again.');
    }
}
