@extends('layouts.app')

@section('title', $event->name.' 快速報到')

@section('content')
<div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
    <header>
        <a href="{{ route('organizer.events.show', $event) }}" class="inline-flex min-h-11 items-center text-sm font-medium text-indigo-600">← 返回賽事工作台</a>
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-600">Check-in</p>
        <h1 class="mt-1 break-words text-2xl font-bold">{{ $event->name }}・快速報到</h1>
        <p class="mt-1 text-sm text-gray-500">掃描選手會員 QR Code，成功後可直接繼續掃描下一位。</p>
    </header>

    <section class="grid grid-cols-2 gap-3">
        <div class="rounded-2xl border bg-white p-4 text-center shadow-sm"><p id="checked-in-count" class="text-3xl font-bold text-emerald-700">{{ $totals['checked_in'] }}</p><p class="mt-1 text-xs text-gray-500">已報到</p></div>
        <div class="rounded-2xl border bg-white p-4 text-center shadow-sm"><p class="text-3xl font-bold">{{ $totals['active'] }}</p><p class="mt-1 text-xs text-gray-500">有效報名</p></div>
    </section>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,.7fr)]">
        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">掃描會員 QR Code</h2><p class="mt-1 text-xs text-gray-500">請允許相機權限並將 QR Code 放入畫面。</p></div><button id="start-camera" type="button" class="min-h-11 shrink-0 rounded-xl bg-emerald-600 px-4 text-sm font-medium text-white">開啟相機</button></div>
            <video id="checkin-video" playsinline muted class="mt-4 hidden aspect-square w-full rounded-2xl bg-gray-950 object-cover sm:aspect-video"></video>
            <div id="scan-result" class="mt-4 rounded-2xl bg-gray-50 p-5 text-center" aria-live="polite">
                <p id="result-title" class="text-lg font-semibold">等待掃描</p>
                <p id="result-detail" class="mt-1 text-sm text-gray-500">也可以使用下方搜尋完成報到。</p>
            </div>
        </section>

        <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
            <h2 class="font-semibold">最近報到</h2>
            <div id="recent-checkins" class="mt-3 divide-y">
                @forelse($recentCheckIns as $registration)
                    <div class="py-3 text-sm"><div class="flex justify-between gap-3"><p class="font-medium">{{ $registration->name }}</p><time class="shrink-0 text-xs text-gray-500">{{ $registration->checked_in_at?->format('H:i') }}</time></div><p class="mt-1 text-xs text-gray-500">{{ $registration->event_group?->name }}</p></div>
                @empty
                    <p id="recent-empty" class="py-5 text-sm text-gray-500">尚無報到紀錄。</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="rounded-2xl border bg-white p-4 shadow-sm sm:p-5">
        <h2 class="font-semibold">姓名搜尋報到</h2>
        <form method="GET" class="mt-3 flex flex-col gap-2 sm:flex-row">
            <input name="q" value="{{ request('q') }}" class="min-h-12 min-w-0 flex-1 rounded-xl border-gray-300 text-base sm:text-sm" placeholder="輸入姓名、暱稱、Email 或會員編號">
            <button class="min-h-12 rounded-xl bg-gray-900 px-5 text-sm font-medium text-white">搜尋</button>
        </form>
        @if(request()->filled('q'))
            <div class="mt-4 grid gap-3 md:grid-cols-2">
                @forelse($searchResults as $registration)
                    <article class="flex items-center justify-between gap-3 rounded-2xl border p-4"><div class="min-w-0"><p class="font-semibold">{{ $registration->name }}</p><p class="mt-1 break-words text-xs text-gray-500">{{ $registration->event_group?->name }}・{{ $registration->status === 'checked_in' ? '已報到' : '尚未報到' }}</p></div><button type="button" data-checkin-uuid="{{ $registration->user?->uuid }}" class="min-h-11 shrink-0 rounded-xl px-4 text-sm font-medium {{ $registration->status === 'checked_in' ? 'border text-gray-600' : 'bg-emerald-600 text-white' }}">{{ $registration->status === 'checked_in' ? '查看' : '確認報到' }}</button></article>
                @empty
                    <p class="text-sm text-gray-500 md:col-span-2">找不到有效報名選手。</p>
                @endforelse
            </div>
        @endif
    </section>
</div>

<script>
(() => {
    const endpoint = @json(route('organizer.events.registrations.check-in', $event));
    const csrf = @json(csrf_token());
    const video = document.getElementById('checkin-video');
    const startButton = document.getElementById('start-camera');
    const result = document.getElementById('scan-result');
    const title = document.getElementById('result-title');
    const detail = document.getElementById('result-detail');
    const count = document.getElementById('checked-in-count');
    const recent = document.getElementById('recent-checkins');
    let scanning = false;
    let processing = false;
    let lastValue = '';
    let lastScannedAt = 0;

    const uuidFromValue = value => {
        try { value = new URL(value).pathname.split('/').filter(Boolean).pop(); } catch (_) {}
        return value;
    };

    const showResult = (type, heading, message) => {
        result.className = 'mt-4 rounded-2xl p-5 text-center ' + (type === 'success' ? 'bg-emerald-50 text-emerald-800' : type === 'warning' ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700');
        title.textContent = heading;
        detail.textContent = message;
        detail.className = 'mt-1 text-sm';
    };

    const addRecent = data => {
        document.getElementById('recent-empty')?.remove();
        const row = document.createElement('div');
        row.className = 'py-3 text-sm';
        const now = new Date().toLocaleTimeString('zh-TW', {hour: '2-digit', minute: '2-digit', hour12: false});
        row.innerHTML = `<div class="flex justify-between gap-3"><p class="font-medium"></p><time class="shrink-0 text-xs text-gray-500">${now}</time></div><p class="mt-1 text-xs text-gray-500"></p>`;
        row.querySelector('p').textContent = data.name;
        row.querySelectorAll('p')[1].textContent = data.groups.join('、');
        recent.prepend(row);
        while (recent.children.length > 5) recent.lastElementChild.remove();
    };

    const checkIn = async rawValue => {
        const uuid = uuidFromValue(rawValue.trim());
        if (!uuid || processing) return;
        processing = true;
        showResult('warning', '資料確認中', '正在查詢本賽事報名資料…');
        try {
            const response = await fetch(endpoint, {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf}, body: JSON.stringify({uuid})});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || '報到失敗');
            const extra = data.payment_warning ? '；注意：尚有報名未完成繳費' : '';
            showResult(data.already_checked_in ? 'warning' : 'success', data.message, data.groups.join('、') + extra);
            count.textContent = data.checked_in_count;
            if (!data.already_checked_in) addRecent(data);
            if (navigator.vibrate) navigator.vibrate(data.already_checked_in ? [80, 60, 80] : 120);
        } catch (error) {
            showResult('error', '無法完成報到', error.message);
            if (navigator.vibrate) navigator.vibrate([150, 80, 150]);
        } finally {
            window.setTimeout(() => { processing = false; }, 1200);
        }
    };

    document.querySelectorAll('[data-checkin-uuid]').forEach(button => button.addEventListener('click', () => checkIn(button.dataset.checkinUuid || '')));

    startButton?.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window)) {
            showResult('error', '此瀏覽器不支援直接掃碼', '請改用下方姓名搜尋，或使用支援 QR 掃描的瀏覽器。');
            return;
        }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}});
            video.srcObject = stream;
            video.classList.remove('hidden');
            await video.play();
            startButton.classList.add('hidden');
            scanning = true;
            const detector = new BarcodeDetector({formats: ['qr_code']});
            const scan = async () => {
                if (!scanning) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes[0]) {
                        const now = Date.now();
                        if (codes[0].rawValue !== lastValue || now - lastScannedAt > 3000) {
                            lastValue = codes[0].rawValue;
                            lastScannedAt = now;
                            await checkIn(lastValue);
                        }
                    }
                } catch (_) {}
                requestAnimationFrame(scan);
            };
            scan();
        } catch (_) {
            showResult('error', '無法開啟相機', '請確認相機權限，或改用下方姓名搜尋。');
        }
    });
})();
</script>
@endsection
