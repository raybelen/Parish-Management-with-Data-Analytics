<div class="overflow-x-auto">
    <table class="admin-table"><thead><tr><th>Month</th><th>Completed services</th>@can('finance.view')<th>Net completed-service collections</th>@endcan</tr></thead><tbody>
        @foreach($history as $row)<tr><td>{{ $row['month'] }}</td><td><div class="flex items-center gap-3"><meter min="0" max="{{ max(1, collect($history)->max('count') ?? 0) }}" value="{{ $row['count'] ?? 0 }}" class="h-4 w-32" aria-label="Completed services in {{ $row['month'] }}"></meter>{{ $row['count'] ?? 'Unknown' }}</div></td>@can('finance.view')<td>{{ $row['revenue'] === null ? 'Unknown' : '₱'.number_format($row['revenue'], 2) }}</td>@endcan</tr>@endforeach
    </tbody></table>
</div>
