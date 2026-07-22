@extends('layouts.app')

@section('title', '掃描會員 QR Code')

@section('content')
<div class="mx-auto max-w-xl px-4 py-8 sm:px-6">
    <a href="{{ route('member-profile.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500">← 返回會員資料</a>
    <h1 class="mt-2 text-2xl font-bold">掃描會員 QR Code</h1>
    <p class="mt-1 text-sm text-gray-500">請允許相機權限，並將 QR Code 放入畫面中央。</p>

    <div class="mt-6 overflow-hidden rounded-2xl bg-gray-900 shadow-sm">
        <video id="qr-video" class="aspect-square w-full object-cover" playsinline muted></video>
    </div>
    <p id="scan-status" class="mt-4 rounded-xl bg-white p-3 text-sm text-gray-600">正在啟動相機…</p>
    <button id="retry-scan" type="button" class="mt-3 hidden rounded-xl border bg-white px-4 py-2 text-sm hover:bg-gray-50">重新啟動相機</button>

    <form id="manual-form" class="mt-6 border-t pt-5">
        <label for="member-code" class="block text-sm font-medium">或輸入會員編號</label>
        <div class="mt-2 flex gap-2">
            <input id="member-code" class="min-w-0 flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm" placeholder="UUID 會員編號">
            <button class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white">查詢</button>
        </div>
    </form>
</div>

<script>
(() => {
    const video = document.getElementById('qr-video');
    const status = document.getElementById('scan-status');
    const retry = document.getElementById('retry-scan');
    const memberBase = @json(url('/members'));
    const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
    let stream;
    let scanning = false;

    function openMember(raw) {
        let code = raw.trim();
        try {
            const parsed = new URL(code);
            if (parsed.origin !== location.origin || !parsed.pathname.startsWith('/members/')) throw new Error();
            code = parsed.pathname.split('/').filter(Boolean).pop();
        } catch (_) {}
        if (!uuidPattern.test(code)) {
            status.textContent = '這不是有效的 ArrowTrack 會員 QR Code。';
            return false;
        }
        stop();
        location.assign(`${memberBase}/${code}`);
        return true;
    }

    function stop() {
        scanning = false;
        stream?.getTracks().forEach(track => track.stop());
    }

    async function start() {
        retry.classList.add('hidden');
        if (!('BarcodeDetector' in window)) {
            status.textContent = '此瀏覽器不支援相機掃描，請使用 Chrome／Edge，或在下方輸入會員編號。';
            retry.classList.remove('hidden');
            return;
        }
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: {ideal: 'environment'}}, audio: false});
            video.srcObject = stream;
            await video.play();
            const detector = new BarcodeDetector({formats: ['qr_code']});
            scanning = true;
            status.textContent = '掃描中…';
            const detect = async () => {
                if (!scanning) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes[0] && openMember(codes[0].rawValue)) return;
                } catch (_) {}
                requestAnimationFrame(detect);
            };
            detect();
        } catch (_) {
            status.textContent = '無法開啟相機，請確認已授權相機權限，或在下方輸入會員編號。';
            retry.classList.remove('hidden');
        }
    }

    retry.addEventListener('click', start);
    document.getElementById('manual-form').addEventListener('submit', event => {
        event.preventDefault();
        openMember(document.getElementById('member-code').value);
    });
    window.addEventListener('pagehide', stop);
    start();
})();
</script>
@endsection
