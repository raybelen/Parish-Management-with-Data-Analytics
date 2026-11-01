@props(['status', 'regular' => null])
@php
    $labels = ['assigned' => 'Assigned', 'regular' => 'Regular rotation', 'substitute' => 'Substitute for '.$regular, 'unavailable' => 'Unavailable priest', 'conflict' => 'Scheduling conflict', 'needs_reassignment' => 'Needs reassignment'];
    $warning = in_array($status, ['unavailable', 'conflict', 'needs_reassignment'], true);
@endphp
<span @class(['mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-red-100 text-red-900' => $warning, 'bg-amber-100 text-amber-900' => $status === 'substitute', 'bg-emerald-100 text-emerald-900' => ! $warning && $status !== 'substitute'])>{{ $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}</span>
