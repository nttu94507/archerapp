<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LeaderBoardController;
use App\Http\Controllers\ScoreController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\EventRegistrationController;

// open page
Route::view('/', 'dashboard.index')->name('dashboard.index');
Route::get('/leaderboard', [LeaderBoardController::class, 'index'])->name('leaderboards.index');
Route::get('/login/options', [\App\Http\Controllers\LoginController::class, 'options'])->name('login.options');

//google 登入相關
Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])
    ->name('login.google.redirect');
Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('login.google.callback');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

//選手資料
Route::middleware('auth')->group(function () {
    Route::get('/user/profile',[ProfileCompletionController::class,'edit'])->name('user.profile.completion');
    Route::post('/user/profile',[ProfileCompletionController::class,'update'])->name('user.profile.completion.update');
});

//選手專區
Route::middleware(['auth','profile.completed'])->group(function () {
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
