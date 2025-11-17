<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LeaderBoardController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\TeamPostController;
use Illuminate\Support\Facades\Route;

// open page
Route::get('/', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('/leaderboard', [LeaderBoardController::class, 'index'])->name('leaderboards.index');
Route::get('/login/options', [\App\Http\Controllers\LoginController::class, 'options'])->name('login.options');
Route::get('/arrow-rank', function () {
    return view('arrow-rank.create');
})->name('arrow-rank.create');

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

//組隊報名相關
Route::middleware('auth')->group(function () {


    Route::get('/team-posts', [TeamPostController::class, 'index'])->name('team-posts.index');
    Route::get('/team-posts/create', [TeamPostController::class, 'create'])->name('team-posts.create');
    Route::post('/team-posts', [TeamPostController::class, 'store'])->name('team-posts.store');
    Route::get('/team-posts/{teamPost}', [TeamPostController::class, 'show'])->name('team-posts.show');
});

//選手資料
Route::middleware('auth')->group(function () {
    Route::get('/user/profile', [ProfileCompletionController::class, 'edit'])->name('user.profile.completion');
    Route::post('/user/profile', [ProfileCompletionController::class, 'update'])->name('user.profile.completion.update');
});

//選手專區
Route::middleware(['auth', 'profile.completed'])->group(function () {
    Route::resource('events', EventController::class);
    Route::resource('events.groups', \App\Http\Controllers\EventGroupController::class)->except(['show']);

});

Route::middleware(['auth'])->group(function () {
    Route::get('scores/setup', [ScoreController::class, 'setup'])->name('scores.setup');
    Route::resource('scores', \App\Http\Controllers\ScoreController::class);
});

//快速報名
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
Route::post('events/{event}/groups/{group}/quick-register', [EventRegistrationController::class, 'quickRegister'])
    ->middleware('auth')
    ->name('events.quick_register');
