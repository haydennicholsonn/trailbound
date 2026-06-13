<?php

use Illuminate\Support\Facades\Route;

Route::get('/healthz', fn () => response()->json(['ok' => true]))
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class]);

Route::get('/{any?}', fn () => view('app'))->where('any', '.*');
