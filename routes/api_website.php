<?php
use App\Http\Controllers\Vendor\CategoryController;
use App\Http\Controllers\Vendor\SiteMenuController;
use App\Http\Controllers\Website\UserController;
use Illuminate\Support\Facades\Route;


Route::prefix('website')->group(function (){
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
    Route::post('/logout', [UserController::class, 'logout'])->middleware('auth:sanctum');

    Route::get('/menu-json', [SiteMenuController::class, 'siteMenuGet']);
    Route::get('/categories', [CategoryController::class, 'websiteCategories']);
});
