<?php

namespace App\Http\Controllers;

use App\Models\SecondHandItem;
use Illuminate\Http\Request;

class SecondHandItemController extends Controller
{
    public function index(Request $request)
    {
        $items = SecondHandItem::query()->latest()->paginate(12);

        if ($request->ajax()) {
            return response()->json([
                'html' => view('second-hand.partials.item-cards', ['items' => $items])->render(),
                'next_page_url' => $items->nextPageUrl(),
            ]);
        }

        return view('second-hand.index', [
            'items' => $items,
        ]);
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
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $photoPath = $request->file('photo')->store('second-hand', 'public');

        SecondHandItem::create([
            'title' => $validated['title'],
            'price' => $validated['price'],
            'seller_nickname' => auth()->user()->display_name,
            'description' => $validated['description'] ?? null,
            'contact_type' => $validated['contact_type'],
            'contact_value' => $validated['contact_value'],
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('second-hand.index')->with('status', '二手商品已上架。');
    }
}
