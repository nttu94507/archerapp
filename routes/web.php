<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LeaderBoardController;
use App\Http\Controllers\ScoreController;
use App\Http\Controllers\EventController;

//Route::get('/', function () {
//    return view('welcome');
//});

// routes/web.php
Route::get('/', [LeaderBoardController::class, 'index'])->name('leaderboards.index');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])
    ->name('login.google.redirect');

Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('login.google.callback');
//Route::get('/scores/create',[ScoreController::class,'create'])->name('scores.create');
//Route::post('/scores/upsert', [ScoreController::class, 'upsert']);
//Route::get('/rounds/{round}', fn(\App\Models\Round $round) => $round);
Route::get('/event/register/{id}', [EventController::class, 'register'])->name('events.register');
Route::resource('events', EventController::class);
