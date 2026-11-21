<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditLogPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdminAuditController extends Controller
{
    public function index(Request $request, AuditLogPresenter $presenter): View
    {
        Gate::authorize('audit.view');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'integer'],
            'category' => ['nullable', 'string', Rule::in(array_keys($presenter->categories()))],
        ]);

        $logs = AuditLog::query()
            ->with('actor')
            ->when($filters['q'] ?? null, function (Builder $query, string $term) use ($presenter): void {
                $matchingActions = $presenter->actionsMatching($term);

                $query->where(function (Builder $query) use ($matchingActions, $term): void {
                    $query->where('action', 'like', '%'.$term.'%')
                        ->orWhere('reason', 'like', '%'.$term.'%')
                        ->orWhereHas('actor', fn (Builder $actorQuery) => $actorQuery->where('name', 'like', '%'.$term.'%'));

                    if ($matchingActions !== []) {
                        $query->orWhereIn('action', $matchingActions);
                    }
                });
            })
            ->when($filters['actor'] ?? null, fn (Builder $query, int $actor) => $query->where('actor_id', $actor))
            ->when($filters['category'] ?? null, function (Builder $query, string $category) use ($presenter): void {
                $prefixes = $presenter->prefixesForCategory($category);

                if ($category === 'other') {
                    $knownPrefixes = collect($presenter->categories())
                        ->keys()
                        ->reject(fn (string $key): bool => $key === 'other')
                        ->flatMap(fn (string $key): array => $presenter->prefixesForCategory($key));

                    $query->where(function (Builder $query) use ($knownPrefixes): void {
                        foreach ($knownPrefixes as $prefix) {
                            $query->where('action', 'not like', $prefix.'%');
                        }
                    });

                    return;
                }

                $query->where(function (Builder $query) use ($prefixes): void {
                    foreach ($prefixes as $index => $prefix) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}('action', 'like', $prefix.'%');
                    }
                });
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AuditLog $entry): array => $presenter->present($entry));

        $actors = User::query()
            ->whereIn('id', AuditLog::query()->select('actor_id')->whereNotNull('actor_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.audit', [
            'actors' => $actors,
            'categories' => $presenter->categories(),
            'logs' => $logs,
        ]);
    }
}
