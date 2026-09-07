<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'Ummah Connect API',
        'time' => now()->toIso8601String(),
    ]);
});
