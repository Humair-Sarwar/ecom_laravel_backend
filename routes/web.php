<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/storage/{path}', function (string $path) {
    // Prevent directory traversal
    if (str_contains($path, '..')) {
        abort(404);
    }

    $fullPath = storage_path('app/public/' . ltrim($path, '/\\'));

    if (!is_file($fullPath)) {
        abort(404);
    }

    return response()->file($fullPath, [
        'Cache-Control' => 'public, max-age=31536000',
    ]);
})->where('path', '.*');
