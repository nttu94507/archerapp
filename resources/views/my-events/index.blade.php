@extends('layouts.app')

@section('title', '我的賽事')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">我的賽事</h1>
                <p class="text-sm text-gray-600">查看已報名、目前可計分的賽事。</p>
            </div>
        </div>

        @if($events->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-6 text-center text-sm text-gray-500">
                尚未有報名紀錄。
            </div>
        @else
            <div class="grid gap-4">
                @foreach($events as $row)
                    @php($event = $row['event'])
                    <div class="rounded-2xl border bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-lg font-semibold text-gray-900">{{ $event->name }}</div>
                                <div class="text-sm text-gray-500">{{ $event->start_date }} ~ {{ $event->end_date }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($row['scoreable'])
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">可計分</span>
                                    <a href="{{ route('my-events.score', $event) }}"
                                       class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">前往計分</a>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">目前不可計分</span>
                                    <a href="{{ route('my-events.score', $event) }}"
                                       class="inline-flex items-center rounded-xl border px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">查看計分表</a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
