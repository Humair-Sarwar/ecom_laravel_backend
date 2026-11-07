<?php

use App\Http\Controllers\SystemUser\AuthController as SystemUserAuthController;
use App\Http\Controllers\SystemUser\RoleController;
use App\Http\Controllers\SystemUser\CountryController;
use App\Http\Controllers\SystemUser\CityController;
use App\Http\Controllers\SystemUser\UserController;
use App\Http\Controllers\SystemUser\BusinessController;
use App\Http\Controllers\SystemUser\DashboardController;
use Illuminate\Support\Facades\Route;

// Public System User Auth Routes
Route::prefix('systemuser/auth')->group(function () {
    Route::post('/login', [SystemUserAuthController::class, 'login']);
});

// Protected System User Routes (user_type = 3)
Route::prefix('systemuser')->middleware(['auth:sanctum', 'user_type:3'])->group(function () {
    Route::post('/auth/logout', [SystemUserAuthController::class, 'logout']);
    
    // Dashboard Stats Route
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard', [DashboardController::class, 'stats']);

    // Roles Management
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    Route::patch('/roles/{id}/toggle-status', [RoleController::class, 'toggleStatus']);
    Route::post('/roles/{id}/sync', [RoleController::class, 'syncRole']);

    // Locations: Countries Management
    Route::get('/countries/all', [CountryController::class, 'all']);
    Route::get('/countries', [CountryController::class, 'index']);
    Route::post('/countries', [CountryController::class, 'store']);
    Route::get('/countries/{id}', [CountryController::class, 'show']);
    Route::put('/countries/{id}', [CountryController::class, 'update']);
    Route::delete('/countries/{id}', [CountryController::class, 'destroy']);
    Route::patch('/countries/{id}/toggle-status', [CountryController::class, 'toggleStatus']);

    // Locations: Cities Management
    Route::get('/cities', [CityController::class, 'index']);
    Route::post('/cities', [CityController::class, 'store']);
    Route::get('/cities/{id}', [CityController::class, 'show']);
    Route::put('/cities/{id}', [CityController::class, 'update']);
    Route::delete('/cities/{id}', [CityController::class, 'destroy']);
    Route::patch('/cities/{id}/toggle-status', [CityController::class, 'toggleStatus']);

    // Users Management (Business Owners & Website Users)
    Route::get('/users/export-csv', [UserController::class, 'exportCsv']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::patch('/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

    // Businesses Management
    Route::get('/businesses/form-options', [BusinessController::class, 'formOptions']);
    Route::get('/businesses', [BusinessController::class, 'index']);
    Route::post('/businesses', [BusinessController::class, 'store']);
    Route::get('/businesses/{id}', [BusinessController::class, 'show']);
    Route::put('/businesses/{id}', [BusinessController::class, 'update']);
    Route::patch('/businesses/{id}/toggle-block', [BusinessController::class, 'toggleBlock']);
    Route::delete('/businesses/{id}', [BusinessController::class, 'destroy']);
});
