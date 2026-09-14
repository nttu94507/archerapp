@unless($deviceMode)
@can('manageScoreCorrections', $event)
<details class="rounded-2xl border border-red-200 bg-white shadow-sm">
    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between px-4 font-semibold text-red-700 sm:px-5"><span>異常處理</span><span>⌄</span></summary>
    <div class="space-y-4 border-t border-red-100 p-4 sm:p-5">
        <p class="text-sm text-gray-600">僅在賽程卡住或結果有誤時使用。若受影響的下一輪已開始，系統會阻止覆蓋。</p>
        <form method="POST" action="{{ route('organizer.events.elimination.matches.recovery', [$event, $match]) }}" class="space-y-3">@csrf @method('PATCH')
            <input type="hidden" name="action" value="force_winner">
            <fieldset><legend class="text-sm font-semibold">指定正確勝方</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><label class="flex min-h-12 items-center gap-2 rounded-xl border px-3"><input type="radio" name="winner" value="participant_one" required> {{ $participantOneName }}</label><label class="flex min-h-12 items-center gap-2 rounded-xl border px-3"><input type="radio" name="winner" value="participant_two" required> {{ $participantTwoName }}</label></div></fieldset>
            <textarea name="reason" required minlength="3" maxlength="1000" class="min-h-20 w-full rounded-xl border-gray-300" placeholder="異常處理原因（必填）"></textarea>
            <button class="min-h-12 w-full rounded-xl bg-red-600 font-semibold text-white" onclick="return confirm('確定強制指定勝方並重新同步晉級？')">指定勝方並繼續賽程</button>
        </form>
        <div class="grid gap-3 border-t pt-4 sm:grid-cols-2">
            <form method="POST" action="{{ route('organizer.events.elimination.matches.recovery', [$event, $match]) }}" class="space-y-2">@csrf @method('PATCH')<input type="hidden" name="action" value="reopen_shoot_off"><input name="reason" required minlength="3" maxlength="1000" class="min-h-12 w-full rounded-xl border-gray-300" placeholder="退回原因（必填）"><button class="min-h-11 w-full rounded-xl border border-amber-300 text-sm font-semibold text-amber-800" onclick="return confirm('確定退回等待重新加射？')">退回重新加射</button></form>
            <form method="POST" action="{{ route('organizer.events.elimination.matches.recovery', [$event, $match]) }}" class="space-y-2">@csrf @method('PATCH')<input type="hidden" name="action" value="resynchronize"><input name="reason" required minlength="3" maxlength="1000" class="min-h-12 w-full rounded-xl border-gray-300" placeholder="同步原因（必填）"><button class="min-h-11 w-full rounded-xl border border-indigo-300 text-sm font-semibold text-indigo-700" onclick="return confirm('確定重新同步整張對抗表？')">重新同步晉級</button></form>
        </div>
    </div>
</details>
@endcan
@endunless
