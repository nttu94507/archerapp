<?php

namespace App\Http\Controllers;

use App\Models\SecondHandItem;
use Illuminate\Http\Request;

class SecondHandItemController extends Controller
{
    public function index(Request $request)
    {
        $keyword = trim((string) $request->query('q', ''));

        $items = SecondHandItem::query()
            ->with(['seller', 'photos'])
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        if ($request->ajax()) {
            return response()->json([
                'html' => view('second-hand.partials.item-cards', ['items' => $items])->render(),
                'next_page_url' => $items->nextPageUrl(),
            ]);
        }

        return view('second-hand.index', ['items' => $items, 'keyword' => $keyword]);
    }

    public function show(SecondHandItem $secondHandItem)
    {
        $secondHandItem->load(['seller', 'photos']);

        return view('second-hand.show', ['item' => $secondHandItem]);
    }

    public function create()
    {
        return view('second-hand.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'contact_type' => ['required', 'in:phone,social'],
            'contact_value' => ['required', 'string', 'max:100'],
            'photos' => ['required', 'array', 'min:1', 'max:8'],
            'photos.*' => ['required', 'image', 'max:4096'],
        ]);

        $item = SecondHandItem::create([
            'title' => $validated['title'],
            'price' => $validated['price'],
            'seller_id' => auth()->id(),
            'description' => $validated['description'] ?? null,
            'contact_type' => $validated['contact_type'],
            'contact_value' => $validated['contact_value'],
            'photo_path' => '',
        ]);

        $firstPath = null;
        foreach ($request->file('photos') as $idx => $photo) {
            $photoPath = $photo->store('second-hand', 'public');
            $item->photos()->create(['photo_path' => $photoPath, 'sort_order' => $idx]);
            if ($firstPath === null) {
                $firstPath = $photoPath;
            }
        }

        $item->update(['photo_path' => $firstPath ?? '']);

        return redirect()->route('second-hand.index')->with('status', '二手商品已上架。');
    }

    public function destroy(SecondHandItem $secondHandItem)
    {
        $user = auth()->user();
        if (! $user || (! $user->isAdmin() && $secondHandItem->seller_id !== $user->id)) {
            abort(403);
        }

        $secondHandItem->delete();

        return redirect()->route('second-hand.index')->with('status', '商品已刪除。');
    }
}
