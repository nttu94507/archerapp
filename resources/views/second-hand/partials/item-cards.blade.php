@foreach ($items as $item)
    <article class="rounded-2xl border bg-white shadow-sm overflow-hidden">
        <img src="{{ asset('storage/' . $item->photo_path) }}" alt="{{ $item->title }}" class="h-48 w-full object-cover">
        <div class="p-4 space-y-2">
            <h3 class="font-semibold text-gray-900">{{ $item->title }}</h3>
            <div class="flex items-center justify-between">
                <p class="text-lg font-bold">NT$ {{ number_format($item->price) }}</p>
                <p class="text-sm text-gray-600">{{ $item->seller_nickname }}</p>
            </div>
            <p class="text-xs text-gray-500">聯絡方式：{{ $item->contact_type === 'phone' ? '手機' : '社群媒體' }} / {{ $item->contact_value }}</p>
        </div>
    </article>
@endforeach
