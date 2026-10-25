<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/server-info', function (Request $request) {
    return response()->json([
        'app_name' => 'Flowrive Core API',
        'server_port' => $_SERVER['SERVER_PORT'] ?? $request->getPort(),
        'timestamp' => now()->toTimeString(),
    ]);
});