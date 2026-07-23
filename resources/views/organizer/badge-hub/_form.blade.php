@php($badge=$badge??null)
@php($editing=$badge!==null)
<form method="POST" action="{{ $editing ? route('organizer.badges.update',$badge) : route('organizer.badges.store') }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2" x-data="{locationEnabled:{{old('location_claim_enabled',$editing ? $badge->location_claim_enabled : false)?'true':'false'}},locating:false,lat:'{{old('claim_lat',$editing ? $badge->claim_lat : '')}}',lng:'{{old('claim_lng',$editing ? $badge->claim_lng : '')}}',locate(){if(!navigator.geolocation){alert('此瀏覽器無法取得位置');return}this.locating=true;navigator.geolocation.getCurrentPosition(p=>{this.lat=p.coords.latitude.toFixed(7);this.lng=p.coords.longitude.toFixed(7);this.locating=false},()=>{this.locating=false;alert('無法取得位置，請允許定位後再試一次。')},{enableHighAccuracy:false,timeout:15000,maximumAge:60000})}}">
    @csrf @if($editing) @method('PUT') @endif
    <div><label class="text-sm font-medium">Badge 名稱 *</label><input name="name" required value="{{old('name',$badge?->name??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>
    <div><label class="text-sm font-medium">活動／賽事名稱 *</label><input name="external_activity_name" required value="{{old('external_activity_name',$badge?->external_activity_name??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>
    <div><label class="text-sm font-medium">活動日期</label><input type="date" name="external_activity_date" value="{{old('external_activity_date',$badge?->external_activity_date?->format('Y-m-d')??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>
    <div><label class="text-sm font-medium">活動地點</label><input name="external_activity_location" value="{{old('external_activity_location',$badge?->external_activity_location??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>
    <div><label class="text-sm font-medium">限量數量</label><input type="number" min="1" name="max_supply" value="{{old('max_supply',$badge?->max_supply??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300" placeholder="未填則不限制"></div>
    <div><label class="text-sm font-medium">Badge 圖示</label><input type="file" name="icon" accept="image/jpeg,image/png,image/webp" class="mt-1 min-h-11 w-full rounded-xl border p-2 text-sm"><p class="mt-1 text-xs text-gray-500">JPG、PNG、WebP，最大 10MB</p></div>
    <div class="sm:col-span-2"><label class="text-sm font-medium">說明</label><textarea name="description" rows="3" class="mt-1 w-full rounded-xl border-gray-300">{{old('description',$badge?->description??'')}}</textarea></div>
    <div class="rounded-2xl border bg-gray-50 p-4 sm:col-span-2">
        <label class="flex items-center gap-2 font-medium"><input type="checkbox" name="location_claim_enabled" value="1" x-model="locationEnabled" class="rounded"> 開放掃描 QR Code 定位領取</label>
        <div x-show="locationEnabled" class="mt-4 grid gap-3 sm:grid-cols-3">
            <input name="claim_lat" x-model="lat" :required="locationEnabled" class="min-h-11 rounded-xl border-gray-300" placeholder="緯度">
            <input name="claim_lng" x-model="lng" :required="locationEnabled" class="min-h-11 rounded-xl border-gray-300" placeholder="經度">
            <select name="claim_radius_km" class="min-h-11 rounded-xl border-gray-300">@foreach([5,10,20,30] as $radius)<option value="{{$radius}}" @selected((string)old('claim_radius_km',$badge?->claim_radius_km??10)===(string)$radius)>{{$radius}} 公里</option>@endforeach</select>
            <button type="button" @click="locate()" class="min-h-11 rounded-xl border border-indigo-200 bg-white text-sm text-indigo-700 sm:col-span-3" x-text="locating?'正在取得位置…':'使用目前位置'"></button>
            @unless($editing)<div class="sm:col-span-3"><label class="text-xs text-gray-500">單日領取（選填）</label><input type="date" name="claim_date" value="{{old('claim_date')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>@endunless
            <div><label class="text-xs text-gray-500">開始時間</label><input type="datetime-local" name="claim_starts_at" value="{{old('claim_starts_at',$badge?->claim_starts_at?->format('Y-m-d\TH:i')??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>
            <div><label class="text-xs text-gray-500">結束時間</label><input type="datetime-local" name="claim_ends_at" value="{{old('claim_ends_at',$badge?->claim_ends_at?->format('Y-m-d\TH:i')??'')}}" class="mt-1 min-h-11 w-full rounded-xl border-gray-300"></div>
            <p class="self-end pb-3 text-xs text-gray-500">未填期間則長期開放</p>
        </div>
    </div>
    <div class="flex gap-3 sm:col-span-2"><a href="{{route('organizer.badges.index')}}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border text-sm">取消</a><button class="min-h-11 flex-1 rounded-xl bg-indigo-600 px-5 text-sm font-medium text-white">{{$editing?'儲存變更':'建立 Badge'}}</button></div>
</form>
