<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminAuditController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('audit.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'actor' => ['nullable', 'integer']]);

        return view('admin.audit', ['logs' => AuditLog::query()->with('actor')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('action', 'like', '%'.$term.'%'))
            ->when($filters['actor'] ?? null, fn ($q, $actor) => $q->where('actor_id', $actor))
            ->latest('id')->paginate(30)->withQueryString()]);
    }
}
