<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Include Website API routes
require base_path('routes/api_website.php');

// Include Vendor API routes
require base_path('routes/api_vendor.php');