<?php
use App\Http\Controllers\Website\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('website')->group(function (){
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');
});