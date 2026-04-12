<?php

use App\Http\Controllers\Vendor\AttributeController;
use App\Http\Controllers\Vendor\BrandController;
use App\Http\Controllers\Vendor\CategoryController;
use App\Http\Controllers\Vendor\MediaController;
use App\Http\Controllers\Vendor\ProductController;
use App\Http\Controllers\Vendor\TermController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendor')->middleware('auth:sanctum')->group(function (){

    // Brand Routes
    Route::get('/brands', [BrandController::class, 'index']);
    Route::post('/brands', [BrandController::class, 'addNewBrand']);
    Route::get('/brand/{id}', [BrandController::class, 'editBrand']);
    Route::put('/brand/{id}', [BrandController::class, 'updateBrand']);
    Route::delete('/brand/{id}', [BrandController::class, 'deleteBrand']);
    Route::delete('/brands', [BrandController::class, 'DeleteSelectedBrands']);
    Route::get('/brands/panel', [BrandController::class, 'brands']);

    // Category Routes
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/category', [CategoryController::class, 'createCategory']);
    Route::get('/category/{id}', [CategoryController::class, 'editCategory']);
    Route::put('/category', [CategoryController::class, 'updateCategory']);
    Route::delete('/category/{id}', [CategoryController::class, 'deleteCategory']);
    Route::get('/categories/panel', [CategoryController::class, 'categories']);

    // Product Routes
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/product/step1', [ProductController::class, 'createProductStep1']);
    Route::put('/product/type/update', [ProductController::class, 'updateProductType']);
    Route::put('/product/create-step2', [ProductController::class, 'createProductStep2']);
    Route::get('/product/edit/step1/{id}', [ProductController::class, 'editProductStep1']);
    Route::put('/product/update/step1', [ProductController::class, 'updateProductStep1']);
    Route::get('/product/edit/step2/simple/{id}', [ProductController::class, 'editProductSimpleStep2']);
    Route::get('/product/attributes/get', [ProductController::class, 'loadAttributes']);
    Route::get('/product/terms/get/{id}', [ProductController::class, 'loadTerms']);
    Route::post('/product/attribute/terms/save', [ProductController::class, 'saveSelectedAttributeTerms']);

    // Attribute Routes
    Route::get('/attributes', [AttributeController::class, 'index']);
    Route::post('/attribute', [AttributeController::class, 'createAttribute']);
    Route::get('/attribute/edit/{id}', [AttributeController::class, 'editAttribute']);
    Route::put('/attribute/update', [AttributeController::class, 'updateAttribute']);
    Route::delete('/attribute/{id}', [AttributeController::class, 'deleteAttribute']);

    // Term Routes
    Route::get('/terms/{id}', [TermController::class, 'index']);
    Route::post('/term', [TermController::class, 'createTerm']);
    Route::get('/term/edit/{id}', [TermController::class, 'editTerm']);
    Route::put('/term/update', [TermController::class, 'updateTerm']);
    Route::delete('/term/{id}', [TermController::class, 'deleteTerm']);

    // Media Routes
    Route::get('/medias', [MediaController::class, 'index']);
    Route::post('/media', [MediaController::class, 'addNewMedia']);
    Route::post('/medias/uploads', [MediaController::class, 'addMultipleUploads']);
    Route::delete('/media/delete/{id}', [MediaController::class, 'deleteMedia']);
});