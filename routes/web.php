<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\LeaderBoardController;

//Route::get('/', function () {
//    return view('welcome');
//});

// routes/web.php
Route::get('/', [LeaderBoardController::class, 'index'])->name('leaderboards.index');

Route::get('/auth/google/redirect', [GoogleController::class, 'redirect'])
    ->name('login.google.redirect');

Route::get('/auth/google/callback', [GoogleController::class, 'callback'])
    ->name('login.google.callback');
