<?php

namespace App\Http\Controllers;

use App\Models\SecondHandItem;
use Illuminate\Http\Request;

class SecondHandItemController extends Controller
{
    public function index()
    {
        return view('second-hand.index', [
            'items' => SecondHandItem::query()->latest()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'price' => ['required', 'integer', 'min:0'],
            'seller_nickname' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo' => ['required', 'image', 'max:4096'],
        ]);

        $photoPath = $request->file('photo')->store('second-hand', 'public');

        SecondHandItem::create([
            'title' => $validated['title'],
            'price' => $validated['price'],
            'seller_nickname' => $validated['seller_nickname']
                ?? auth()->user()?->display_name
                ?? '匿名賣家',
            'description' => $validated['description'] ?? null,
            'photo_path' => $photoPath,
        ]);

        return redirect()->route('second-hand.index')->with('status', '二手商品已上架。');
    }
}
