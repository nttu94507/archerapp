@extends('layouts.app')

@section('title', '新增二手商品')

@section('content')
<div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-8">
<section class="rounded-2xl border bg-white p-5 sm:p-6">
<h1 class="text-2xl font-semibold tracking-tight">新增二手商品</h1>
@if ($errors->any())<div class="mt-4 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-700"><ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form action="{{ route('second-hand.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">@csrf
<label class="space-y-1 md:col-span-2"><span class="text-sm text-gray-700">商品名稱</span><input name="title" value="{{ old('title') }}" required class="w-full rounded-xl border px-3 py-2 text-sm"></label>
<label class="space-y-1"><span class="text-sm text-gray-700">售價（NT$）</span><input type="number" min="0" name="price" value="{{ old('price') }}" required class="w-full rounded-xl border px-3 py-2 text-sm"></label>
<label class="space-y-1"><span class="text-sm text-gray-700">商品照片（可多張）</span><input type="file" name="photos[]" accept="image/*" multiple required class="w-full rounded-xl border px-3 py-2 text-sm"><p class="text-xs text-gray-500">最多 8 張，每張上限 4MB。</p></label>
<label class="space-y-1"><span class="text-sm text-gray-700">聯繫方式類型</span><select name="contact_type" class="w-full rounded-xl border px-3 py-2 text-sm" required><option value="phone" @selected(old('contact_type') === 'phone')>手機</option><option value="social" @selected(old('contact_type') === 'social')>社群媒體</option></select></label>
<label class="space-y-1"><span class="text-sm text-gray-700">聯繫方式</span><input name="contact_value" value="{{ old('contact_value') }}" required class="w-full rounded-xl border px-3 py-2 text-sm"></label>
<label class="space-y-1 md:col-span-2"><span class="text-sm text-gray-700">補充說明（選填）</span><textarea name="description" rows="3" class="w-full rounded-xl border px-3 py-2 text-sm">{{ old('description') }}</textarea></label>
<div class="md:col-span-2 flex gap-2"><button type="submit" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">上架商品</button></div>
</form>
</section>
</div>
@endsection
