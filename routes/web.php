<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LeaderBoardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileCompletionController;
use App\Http\Controllers\LoginController;

// open page
Route::get('/', [LeaderBoardController::class, 'index'])->name('leaderboards.index');
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
    //Route::get('/scores/create',[ScoreController::class,'create'])->name('scores.create');
    //Route::post('/scores/upsert', [ScoreController::class, 'upsert']);
    //Route::get('/rounds/{round}', fn(\App\Models\Round $round) => $round);
    Route::get('/event/register/{id}', [EventController::class, 'register'])->name('events.register');
    Route::resource('events', EventController::class);

});
