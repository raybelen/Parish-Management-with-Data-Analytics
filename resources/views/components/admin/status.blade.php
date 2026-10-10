@props(['value'])
<span @class([
    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold',
    'border-emerald-200 bg-emerald-50 text-emerald-800' => in_array($value, ['completed', 'ready']),
    'border-blue-200 bg-blue-50 text-blue-800' => $value === 'confirmed',
    'border-amber-200 bg-amber-50 text-amber-900' => in_array($value, ['pending', 'unreliable', 'insufficient_data']),
    'border-red-200 bg-red-50 text-red-800' => in_array($value, ['declined', 'cancelled', 'failed']),
    'border-slate-200 bg-slate-100 text-slate-700' => ! in_array($value, ['completed', 'ready', 'confirmed', 'pending', 'unreliable', 'insufficient_data', 'declined', 'cancelled', 'failed']),
])><span aria-hidden="true" class="size-1.5 rounded-full bg-current"></span>{{ ucfirst(str_replace('_', ' ', $value)) }}</span>
