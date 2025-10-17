{{-- include 版本，使用 @include('components.kpi', ['title' => '...', 'value' => '...', 'hint' => '...']) --}}
@php
    $title = $title ?? '';
    $value = $value ?? '';
    $hint  = $hint  ?? null;
@endphp

<div class="rounded-2xl border p-4">
    <div class="text-xs text-gray-500">{{ $title }}</div>
    <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
    @if(!is_null($hint) && $hint !== '')
        <div class="mt-1 text-xs text-gray-500">{{ $hint }}</div>
    @endif
</div>
