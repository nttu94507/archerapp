<?php

use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\BadgeOversightController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\EventTeamController;
use App\Http\Controllers\MyEventController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LeaderBoardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\TeamPostController;
use App\Http\Controllers\SecondHandItemController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\EventBadgeClaimController;
use App\Http\Controllers\Organizer\EventBadgeController as OrganizerEventBadgeController;
use App\Http\Controllers\Organizer\BadgeController as OrganizerBadgeController;
use App\Http\Controllers\BadgeCertificateController;
use App\Http\Controllers\BadgeDropController;
use App\Http\Controllers\Organizer\EventController as OrganizerEventController;
use App\Http\Controllers\Organizer\EventRegistrationController as OrganizerRegistrationController;
use App\Http\Controllers\Organizer\EventResultController as OrganizerResultController;
use App\Http\Controllers\Organizer\EventScoringController as OrganizerScoringController;
use App\Http\Controllers\Organizer\EventJudgingController as OrganizerJudgingController;
use App\Http\Controllers\Organizer\EventEliminationController as OrganizerEliminationController;
use App\Http\Controllers\Organizer\QualificationController;
use App\Http\Controllers\Admin\OrganizerQualificationController as AdminOrganizerQualificationController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\ScoringStationController;
use App\Http\Controllers\EliminationScoringStationController;

// open page
Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/leaderboard', [LeaderBoardController::class, 'index'])->name('leaderboards.index');
Route::get('/login/options', [\App\Http\Controllers\LoginController::class, 'options'])->name('login.options');
Route::get('/arrow-rank', function () {
    return view('arrow-rank.create');
})->name('arrow-rank.create');


Route::get('/storage/{path}', function (string $path) {
    if (! Storage::disk('public')->exists($path)) {
        abort(404);
    }

    return response()->file(Storage::disk('public')->path($path));
})->where('path', '.*')->name('storage.local');

//google 登入相關
Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])
    ->name('login.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('login.google.callback');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::get('/tool', function () {
    return view('tool.index');
})->name('tool.index');
Route::get('/payment', function () {
    return view('tool.paymentfinish');
})->name('tool.paymentfinish');

Route::middleware('auth')->group(function () {
    Route::get('/store', [StoreController::class, 'index'])->name('store.index');
});

Route::get('/second-hand', [SecondHandItemController::class, 'index'])->name('second-hand.index');
Route::middleware('auth')->group(function () {
    Route::get('/second-hand/create', [SecondHandItemController::class, 'create'])->name('second-hand.create');
    Route::post('/second-hand', [SecondHandItemController::class, 'store'])->name('second-hand.store');
    Route::patch('/second-hand/{secondHandItem}/sold', [SecondHandItemController::class, 'markSold'])->name('second-hand.sold');
    Route::delete('/second-hand/{secondHandItem}', [SecondHandItemController::class, 'destroy'])->name('second-hand.destroy');
});
Route::get('/second-hand/{secondHandItem}', [SecondHandItemController::class, 'show'])->name('second-hand.show');
Route::get('/badge-certificates/{publicId}', [BadgeCertificateController::class,'show'])->name('badge-certificates.show');
Route::middleware('auth')->group(function () {
    Route::get('/badge-drops/{token}', [BadgeDropController::class,'show'])->name('badge-drops.show');
    Route::post('/badge-drops/{token}', [BadgeDropController::class,'claim'])->name('badge-drops.claim');
});
Route::get('/badge-drops/{token}/qrcode', [BadgeDropController::class,'qrCode'])->name('badge-drops.qrcode');

Route::prefix('admin')->middleware(['auth', 'admin'])->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.events.index');
    })->name('home');

    Route::resource('events', AdminEventController::class)
        ->only(['index', 'create', 'store', 'show']);

    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/subscription', [AdminUserController::class, 'updateSubscription'])->name('users.subscription.update');
    Route::get('badges', [BadgeOversightController::class, 'index'])->name('badges.index');
    Route::get('badges/create', [BadgeOversightController::class, 'create'])->name('badges.create');
    Route::post('badges', [BadgeOversightController::class, 'store'])->name('badges.store');
    Route::post('badges/{badge}/award', [BadgeOversightController::class, 'award'])->name('badges.award');
    Route::post('badges/{badge}/award-all', [BadgeOversightController::class, 'awardAll'])->name('badges.award-all');
    Route::patch('badges/{badge}/toggle', [BadgeOversightController::class, 'toggle'])->name('badges.toggle');
    Route::patch('badge-awards/{award}/revoke', [BadgeOversightController::class, 'revoke'])->name('badge-awards.revoke');

    Route::patch('events/{event}/registrations/{registration}/payment', [AdminEventController::class, 'updatePayment'])
        ->name('events.registrations.payment');
    Route::post('events/{event}/review', [AdminEventController::class, 'review'])->name('events.review');
    Route::get('organizers', [AdminOrganizerQualificationController::class, 'index'])->name('organizers.index');
    Route::get('organizers/{profile}', [AdminOrganizerQualificationController::class, 'show'])->name('organizers.show');
    Route::post('organizers/{profile}/review', [AdminOrganizerQualificationController::class, 'review'])->name('organizers.review');
    Route::post('organizers/{profile}/suspend', [AdminOrganizerQualificationController::class, 'suspend'])->name('organizers.suspend');
    Route::post('organizers/{profile}/restore', [AdminOrganizerQualificationController::class, 'restore'])->name('organizers.restore');
    Route::get('organizers/{profile}/document', [AdminOrganizerQualificationController::class, 'document'])->name('organizers.document');
});

Route::prefix('organizer')->middleware('auth')->name('organizer.')->group(function () {
    Route::get('badges', [OrganizerBadgeController::class,'index'])->name('badges.index');
    Route::get('badges/create', [OrganizerBadgeController::class,'create'])->name('badges.create');
    Route::post('badges', [OrganizerBadgeController::class,'store'])->name('badges.store');
    Route::get('badges/{badge}/edit', [OrganizerBadgeController::class,'edit'])->name('badges.edit');
    Route::put('badges/{badge}', [OrganizerBadgeController::class,'update'])->name('badges.update');
    Route::post('badges/{badge}/award', [OrganizerBadgeController::class,'award'])->name('badges.award');
    Route::patch('badges/{badge}/claim-toggle', [OrganizerBadgeController::class,'toggleClaim'])->name('badges.claim-toggle');
    Route::get('qualification', [QualificationController::class, 'show'])->name('qualification.show');
    Route::put('qualification', [QualificationController::class, 'update'])->name('qualification.update');
    Route::post('qualification/submit', [QualificationController::class, 'submit'])->name('qualification.submit');
    Route::post('qualification/withdraw', [QualificationController::class, 'withdraw'])->name('qualification.withdraw');
    Route::get('events', [OrganizerEventController::class, 'index'])->name('events.index');
    Route::get('events/create', [OrganizerEventController::class, 'create'])->middleware('organizer.approved')->name('events.create');
    Route::post('events', [OrganizerEventController::class, 'store'])->middleware('organizer.approved')->name('events.store');
    Route::get('events/{event}', [OrganizerEventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [OrganizerEventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [OrganizerEventController::class, 'update'])->name('events.update');
    Route::post('events/{event}/submit', [OrganizerEventController::class, 'submit'])->name('events.submit');
    Route::post('events/{event}/unpublish', [OrganizerEventController::class, 'unpublish'])->name('events.unpublish');
    Route::post('events/{event}/cancel', [OrganizerEventController::class, 'cancel'])->name('events.cancel');
    Route::post('events/{event}/complete', [OrganizerEventController::class, 'complete'])->name('events.complete');
    Route::post('events/{event}/staff', [OrganizerEventController::class, 'addStaff'])->name('events.staff.store');
    Route::patch('events/{event}/staff/{staff}/revoke', [OrganizerEventController::class, 'revokeStaff'])->name('events.staff.revoke');
    Route::get('staff-invitations/{event}/{role}', [OrganizerEventController::class, 'showStaffInvitation'])->middleware('signed')->name('staff-invitations.show');
    Route::post('staff-invitations/{event}/{role}', [OrganizerEventController::class, 'acceptStaffInvitation'])->middleware('signed')->name('staff-invitations.accept');
    Route::get('events/{event}/registrations', [OrganizerRegistrationController::class, 'index'])->name('events.registrations.index');
    Route::get('events/{event}/check-in', [OrganizerRegistrationController::class, 'checkInDesk'])->name('events.check-in.index');
    Route::patch('events/{event}/registrations/bulk', [OrganizerRegistrationController::class, 'bulk'])->name('events.registrations.bulk');
    Route::patch('events/{event}/registrations/payment', [OrganizerRegistrationController::class, 'bulkPayment'])->name('events.registrations.payment');
    Route::post('events/{event}/registrations/check-in', [OrganizerRegistrationController::class, 'checkIn'])->name('events.registrations.check-in');
    Route::patch('events/{event}/registrations/{registration}', [OrganizerRegistrationController::class, 'update'])->name('events.registrations.update');
    Route::get('events/{event}/results', [OrganizerResultController::class, 'index'])->name('events.results.index');
    Route::get('events/{event}/results/registrations/{registration}/edit', [OrganizerResultController::class, 'edit'])->name('events.results.registrations.edit');
    Route::patch('events/{event}/results/registrations/{registration}', [OrganizerResultController::class, 'update'])->name('events.results.registrations.update');
    Route::post('events/{event}/results/verify', [OrganizerResultController::class, 'verify'])->name('events.results.verify');
    Route::post('events/{event}/results/groups/{group}/verify', [OrganizerResultController::class, 'verifyGroup'])->name('events.results.groups.verify');
    Route::post('events/{event}/results/groups/{group}/publish', [OrganizerResultController::class, 'publish'])->name('events.results.publish');
    Route::patch('events/{event}/results/groups/{group}/live-visibility', [OrganizerResultController::class, 'updateLiveVisibility'])->name('events.results.live-visibility');
    Route::post('events/{event}/results/groups/{group}/ranking-snapshot', [OrganizerResultController::class, 'createRankingSnapshot'])->name('events.results.ranking-snapshot');
    Route::get('events/{event}/elimination', [OrganizerEliminationController::class, 'index'])->name('events.elimination.index');
    Route::post('events/{event}/elimination', [OrganizerEliminationController::class, 'store'])->name('events.elimination.store');
    Route::patch('events/{event}/elimination/{bracket}/visibility', [OrganizerEliminationController::class, 'updateVisibility'])->name('events.elimination.visibility');
    Route::post('events/{event}/elimination/{bracket}/bronze-walkover', [OrganizerEliminationController::class, 'reconcileBronzeWalkover'])->name('events.elimination.bronze-walkover');
    Route::get('events/{event}/elimination/matches/{match}/qrcode', [OrganizerEliminationController::class, 'qrCode'])->name('events.elimination.matches.qrcode');
    Route::delete('events/{event}/elimination/matches/{match}/device', [OrganizerEliminationController::class, 'releaseDevice'])->name('events.elimination.matches.device.destroy');
    Route::get('events/{event}/elimination/matches/{match}', [OrganizerEliminationController::class, 'showMatch'])->name('events.elimination.matches.show');
    Route::post('events/{event}/elimination/matches/{match}/shoot-offs/adjudicate', [OrganizerEliminationController::class, 'adjudicateShootOff'])->name('events.elimination.matches.shoot-offs.adjudicate');
    Route::get('events/{event}/scoring', [OrganizerScoringController::class, 'index'])->name('events.scoring.index');
    Route::post('events/{event}/scoring', [OrganizerScoringController::class, 'store'])->name('events.scoring.store');
    Route::delete('events/{event}/scoring/targets/{target}/device', [OrganizerScoringController::class, 'releaseDevice'])->name('events.scoring.targets.device.destroy');
    Route::get('events/{event}/scoring/targets/{target}/qrcode', [OrganizerScoringController::class, 'qrCode'])->name('events.scoring.targets.qrcode');
    Route::get('events/{event}/judging', [OrganizerJudgingController::class, 'index'])->name('events.judging.index');
    Route::patch('events/{event}/judging/targets/{target}', [OrganizerJudgingController::class, 'update'])->name('events.judging.targets.update');

    Route::prefix('events/{event}')->name('events.')->group(function () {
        Route::get('badges', [OrganizerEventBadgeController::class, 'index'])->name('badges.index');
        Route::post('badges', [OrganizerEventBadgeController::class, 'store'])->name('badges.store');
        Route::get('badges/{badge}', [OrganizerEventBadgeController::class, 'show'])->name('badges.show');
        Route::patch('badges/{badge}', [OrganizerEventBadgeController::class, 'update'])->name('badges.update');
        Route::post('badges/{badge}/regenerate-token', [OrganizerEventBadgeController::class, 'regenerateToken'])->name('badges.regenerate-token');
        Route::get('badges/{badge}/qrcode', [OrganizerEventBadgeController::class, 'qrCode'])->name('badges.qrcode');
        Route::post('badges/{badge}/review', [OrganizerEventBadgeController::class, 'bulkReview'])->name('badges.review');
        Route::post('badges/{badge}/award', [OrganizerEventBadgeController::class, 'manualAward'])->name('badges.award');
    });
});

Route::get('/scoring-stations/{token}', [ScoringStationController::class, 'show'])->name('scoring-stations.show');
Route::post('/scoring-stations/{token}/claim', [ScoringStationController::class, 'claim'])->middleware('throttle:10,1')->name('scoring-stations.claim');
Route::post('/scoring-stations/{token}/ends', [ScoringStationController::class, 'storeEnd'])->name('scoring-stations.ends.store');
Route::post('/scoring-stations/{token}/second-round', [ScoringStationController::class, 'startSecondRound'])->name('scoring-stations.second-round.start');
Route::get('/elimination-stations/{token}', [EliminationScoringStationController::class, 'show'])->name('elimination-stations.show');
Route::post('/elimination-stations/{token}/claim', [EliminationScoringStationController::class, 'claim'])->middleware('throttle:10,1')->name('elimination-stations.claim');
Route::post('/elimination-stations/{token}/sets', [EliminationScoringStationController::class, 'storeSet'])->name('elimination-stations.sets.store');
Route::post('/elimination-stations/{token}/ends', [EliminationScoringStationController::class, 'storeEnd'])->name('elimination-stations.ends.store');
Route::post('/elimination-stations/{token}/shoot-offs', [EliminationScoringStationController::class, 'storeShootOff'])->name('elimination-stations.shoot-offs.store');

Route::middleware('auth')->group(function () {
    Route::get('/badge-claims/{token}', [EventBadgeClaimController::class, 'show'])->name('badge-claims.show');
    Route::post('/badge-claims/{token}', [EventBadgeClaimController::class, 'store'])->name('badge-claims.store');
});

//組隊報名相關
Route::middleware('auth')->group(function () {


    Route::get('/team-posts', [TeamPostController::class, 'index'])->name('team-posts.index');
    Route::get('/team-posts/create', [TeamPostController::class, 'create'])->name('team-posts.create');
    Route::post('/team-posts', [TeamPostController::class, 'store'])->name('team-posts.store');
    Route::get('/team-posts/{teamPost}', [TeamPostController::class, 'show'])->name('team-posts.show');
});

//會員資料
Route::middleware('auth')->group(function () {
    Route::get('/member-profile', [ProfileCompletionController::class, 'index'])->name('member-profile.index');
    Route::get('/member-profile/edit', [ProfileCompletionController::class, 'edit'])->name('member-profile.edit');
    Route::get('/member-profile/qrcode', [ProfileCompletionController::class, 'qrCode'])->name('member-profile.qrcode');
    Route::get('/members/scan', [ProfileCompletionController::class, 'scan'])->name('members.scan');
    Route::get('/members/{user:uuid}', [ProfileCompletionController::class, 'show'])->name('members.show');
    Route::get('/user/profile', [ProfileCompletionController::class, 'edit'])->name('user.profile.completion');
    Route::post('/user/profile', [ProfileCompletionController::class, 'update'])->name('user.profile.completion.update');
});

//選手專區
Route::get('events', [EventController::class, 'index'])->name('events.index');

Route::middleware('auth')->group(function () {
    Route::get('events/create', [OrganizerEventController::class, 'create'])->middleware('organizer.approved')->name('events.create');
    Route::post('events', [OrganizerEventController::class, 'store'])->middleware('organizer.approved')->name('events.store');

    Route::resource('events.groups', \App\Http\Controllers\EventGroupController::class)->except(['show']);
});

Route::middleware(['auth'])->group(function () {
    Route::get('scores/setup', [ScoreController::class, 'setup'])->name('scores.setup');
    Route::resource('scores', \App\Http\Controllers\ScoreController::class);
});

Route::middleware(['auth', 'profile.completed'])->group(function () {
    Route::get('/my-events', [MyEventController::class, 'index'])->name('my-events.index');
    Route::get('/my-events/registrations/{registration}/result', [MyEventController::class, 'result'])->name('my-events.results.show');
});

//快速報名
Route::get('events/{event}/live', [EventController::class, 'live'])->name('events.live');
Route::get('events/{event}/elimination', [EventController::class, 'elimination'])->name('events.elimination');
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
Route::post('events/{event}/groups/{group}/quick-register', [EventRegistrationController::class, 'quickRegister'])
    ->middleware('auth')
    ->name('events.quick_register');
Route::get('events/{event}/groups/{group}/confirm-registration', [EventRegistrationController::class, 'confirm'])
    ->middleware('auth')
    ->name('events.registration.confirm');
Route::patch('event-registrations/{registration}/withdraw', [EventRegistrationController::class, 'withdraw'])
    ->middleware('auth')->name('event-registrations.withdraw');
Route::middleware(['auth', 'profile.completed'])->group(function () {
    Route::get('events/{event}/groups/{group}/teams', [EventTeamController::class, 'index'])->name('events.teams.index');
    Route::post('events/{event}/groups/{group}/teams', [EventTeamController::class, 'store'])->name('events.teams.store');
    Route::post('events/{event}/groups/{group}/teams-auto-match', [EventTeamController::class, 'autoMatch'])->name('events.teams.auto-match');
    Route::post('events/{event}/groups/{group}/teams/{team}/apply', [EventTeamController::class, 'apply'])->name('events.teams.apply');
    Route::post('events/{event}/groups/{group}/teams/{team}/invite', [EventTeamController::class, 'invite'])->name('events.teams.invite');
    Route::patch('events/{event}/groups/{group}/team-memberships/{membership}/respond', [EventTeamController::class, 'respond'])->name('events.teams.respond');
    Route::patch('events/{event}/groups/{group}/teams/{team}/memberships/{membership}/review', [EventTeamController::class, 'review'])->name('events.teams.review');
    Route::delete('events/{event}/groups/{group}/team-memberships/{membership}', [EventTeamController::class, 'leave'])->name('events.teams.leave');
});
