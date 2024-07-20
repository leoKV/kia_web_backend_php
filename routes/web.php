<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CancionController;
use App\Http\Controllers\CancionTagController;
use App\Http\Controllers\TagController;
use App\Http\Middleware\CorsMiddleware;

Route::get('/', function () {
    return view('welcome');
});