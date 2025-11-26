@extends('layouts.app')

@section('title', 'Admin / '.$event->name)

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs uppercase tracking-widest text-indigo-600 font-semibold">Admin</p>
                <h1 class="text-2xl font-bold text-gray-900">{{ $event->name }}</h1>
                <p class="text-sm text-gray-500">{{ $event->organizer }} · {{ $event->mode === 'indoor' ? '室內賽' : '室外賽' }}</p>
                <p class="text-sm text-gray-500">
                    {{ $event->start_date ? \Illuminate\Support\Carbon::parse($event->start_date)->format('Y/m/d') : '—' }}
                    -
                    {{ $event->end_date ? \Illuminate\Support\Carbon::parse($event->end_date)->format('Y/m/d') : '—' }}
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.events.index') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">返回列表</a>
                <a href="{{ route('events.groups.create', $event) }}" class="inline-flex items-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">新增組別</a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">總報名數</p>
                <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $summary['registrations'] }}</p>
                <p class="text-xs text-gray-500">含所有狀態</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">已報到</p>
                <p class="text-2xl font-semibold text-emerald-600 mt-1">{{ $summary['checked_in'] }}</p>
                <p class="text-xs text-gray-500">{{ $statusCounts['checked_in'] ?? 0 }} 位</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">組別</p>
                <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $summary['groups'] }}</p>
                <p class="text-xs text-gray-500">目前建立</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-gray-500">成績筆數</p>
                <p class="text-2xl font-semibold text-gray-900 mt-1">{{ $summary['score_entries'] }}</p>
                <p class="text-xs text-gray-500">Score records</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">報名名單</h2>
                    <span class="text-xs text-gray-500">共 {{ $participants->total() }} 筆</span>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <form method="GET" class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                        <input type="hidden" name="stat_sort" value="{{ $statSort }}">
                        <input type="hidden" name="stat_dir" value="{{ $statDir }}">
                        <div class="sm:col-span-3">
                            <label for="participant_q" class="text-xs font-medium text-gray-600">搜尋選手</label>
                            <input type="text" id="participant_q" name="participant_q" value="{{ request('participant_q') }}"
                                   class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   placeholder="姓名 / 信箱 / 電話 / 隊伍">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="participant_status" class="text-xs font-medium text-gray-600">狀態</label>
                            <select id="participant_status" name="participant_status"
                                    class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">全部</option>
                                @foreach($participantStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected(request('participant_status')===$value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-1 flex items-end justify-end gap-2">
                            <a href="{{ route('admin.events.show', $event) }}" class="text-xs text-gray-500 hover:text-gray-700">清除</a>
                            <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-500">套用</button>
                        </div>
                    </form>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-widest text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">選手</th>
                                <th class="px-4 py-3 text-left">組別</th>
                                <th class="px-4 py-3 text-left">聯絡方式</th>
                                <th class="px-4 py-3 text-left">狀態</th>
                                <th class="px-4 py-3 text-right">建立時間</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @forelse($participants as $registration)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-gray-900">{{ $registration->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $registration->team_name ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">{{ optional($registration->event_group)->name ?: '未指定' }}</td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <div>{{ $registration->email }}</div>
                                        <div class="text-xs text-gray-500">{{ $registration->phone }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                                            @class([
                                                'bg-yellow-100 text-yellow-700' => $registration->status === 'pending',
                                                'bg-blue-100 text-blue-700' => $registration->status === 'registered',
                                                'bg-emerald-100 text-emerald-700' => $registration->status === 'checked_in',
                                                'bg-gray-200 text-gray-700' => $registration->status === 'withdrawn',
                                            ])">
                                            {{ $participantStatuses[$registration->status] ?? $registration->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ optional($registration->created_at)->format('Y-m-d H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">尚無報名資料</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100">{{ $participants->links() }}</div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">成績統計</h2>
                        <form method="GET" class="flex items-center gap-2 text-xs text-gray-500">
                            <input type="hidden" name="participant_q" value="{{ request('participant_q') }}">
                            <input type="hidden" name="participant_status" value="{{ request('participant_status') }}">
                            <select name="stat_sort" class="rounded-lg border-gray-200 bg-gray-50 px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="total_score" @selected($statSort==='total_score')>總分</option>
                                <option value="x_count" @selected($statSort==='x_count')>X 數</option>
                                <option value="ten_count" @selected($statSort==='ten_count')>10 數</option>
                                <option value="arrow_count" @selected($statSort==='arrow_count')>箭數</option>
                                <option value="stdev" @selected($statSort==='stdev')>穩定度</option>
                                <option value="scored_at" @selected($statSort==='scored_at')>計分時間</option>
                            </select>
                            <select name="stat_dir" class="rounded-lg border-gray-200 bg-gray-50 px-2 py-1 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="desc" @selected($statDir==='desc')>高→低</option>
                                <option value="asc" @selected($statDir==='asc')>低→高</option>
                            </select>
                            <button type="submit" class="rounded-lg border border-gray-200 px-2 py-1 text-gray-600 hover:bg-gray-50">排序</button>
                        </form>
                    </div>
                    <div class="mt-4 space-y-3 max-h-[420px] overflow-y-auto pr-1">
                        @forelse($leaderboard as $score)
                            <div class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50/70 px-3 py-2">
                                <div>
                                    <p class="text-xs uppercase text-gray-500">#{{ $score->rank_position }}</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ optional($score->archer)->name ?? '未命名選手' }}</p>
                                    <p class="text-xs text-gray-500">{{ optional($score->round)->name }} · {{ optional($score->scored_at)->format('Y/m/d') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-gray-900">{{ $score->total_score }}</p>
                                    <p class="text-xs text-gray-500">X{{ $score->x_count }} / 10{{ $score->ten_count }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">尚無任何成績紀錄。</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">對抗表（自動配對）</h2>
                    @if($bracket->isEmpty())
                        <p class="text-sm text-gray-500">尚無足夠成績產生對抗表。</p>
                    @else
                        <div class="space-y-4">
                            @foreach($bracket as $match)
                                <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                    <p class="text-xs uppercase text-gray-500 mb-2">Match {{ $match['match'] }}</p>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex-1">
                                            <p class="text-sm font-semibold text-gray-900">{{ optional($match['a']->archer)->name ?? 'TBD' }}</p>
                                            <p class="text-xs text-gray-500">{{ $match['a']->total_score }} pts</p>
                                        </div>
                                        <span class="text-xs font-semibold text-gray-500">VS</span>
                                        <div class="flex-1 text-right">
                                            <p class="text-sm font-semibold text-gray-900">{{ optional($match['b']->archer)->name ?? 'TBD' }}</p>
                                            <p class="text-xs text-gray-500">{{ $match['b']->total_score }} pts</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
