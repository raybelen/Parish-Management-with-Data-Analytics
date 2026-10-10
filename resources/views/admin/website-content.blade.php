@extends('admin.layout')

@section('title', 'Website content')

@section('actions')
    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="admin-secondary">Preview website</a>
@endsection

@section('content')
    <div class="admin-card grid gap-2">
        <h2 class="text-xl">Edit public website text</h2>
        <p class="max-w-4xl text-sm leading-6 text-muted">Changes are published as soon as you save. Only the text fields below can be changed; the website design, page structure, links, and code remain protected.</p>
    </div>

    <form method="POST" action="{{ route('admin.website-content.update') }}" class="grid gap-5">
        @csrf
        @method('PUT')

        @foreach ($groups as $group)
            <details class="admin-card" @if ($loop->first) open @endif>
                <summary class="cursor-pointer text-lg font-semibold">{{ $group['title'] }}</summary>
                <p class="mt-3 text-sm leading-6 text-muted">{{ $group['description'] }}</p>

                <div class="mt-6 grid gap-5 lg:grid-cols-2">
                    @foreach ($group['fields'] as $key => $field)
                        @php($fieldName = 'content.'.$key)
                        <label class="grid gap-2 text-sm @if ($field['type'] === 'textarea') lg:col-span-2 @endif">
                            <span class="flex flex-wrap items-center justify-between gap-2 font-medium">
                                <span>{{ $field['label'] }}</span>
                                <span class="text-xs font-normal text-muted">{{ $field['max'] }} characters max</span>
                            </span>

                            @if ($field['type'] === 'textarea')
                                <textarea name="content[{{ $key }}]" rows="4" maxlength="{{ $field['max'] }}" required class="admin-input min-h-28 resize-y">{{ old($fieldName, $content[$key]) }}</textarea>
                            @else
                                <input name="content[{{ $key }}]" type="text" value="{{ old($fieldName, $content[$key]) }}" maxlength="{{ $field['max'] }}" required class="admin-input">
                            @endif

                            @error($fieldName)
                                <span class="text-xs font-medium text-red-700" role="alert">{{ $message }}</span>
                            @enderror
                        </label>
                    @endforeach
                </div>
            </details>
        @endforeach

        <div class="admin-card flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
            <p class="text-sm leading-6 text-muted">Saved text is displayed using the website's existing components and styling.</p>
            <button class="admin-button w-full sm:w-auto">Save and publish changes</button>
        </div>
    </form>
@endsection
