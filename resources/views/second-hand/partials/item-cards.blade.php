@foreach ($items as $item)
    <a href="{{ route('second-hand.show', $item) }}" class="block rounded-2xl border bg-white shadow-sm overflow-hidden hover:shadow-md transition">
        <img src="{{ asset('storage/' . $item->photo_path) }}" alt="{{ $item->title }}" class="h-48 w-full object-cover">
        <div class="p-4 space-y-2">
            <h3 class="font-semibold text-gray-900">{{ $item->title }}</h3>
            <div class="flex items-center justify-between">
                <p class="text-lg font-bold">NT$ {{ number_format($item->price) }}</p>
                <p class="text-sm text-gray-600">{{ $item->seller_display_name }}</p>
            </div>
        </div>
    </a>
@endforeach
