<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('shipping/create', [App\Http\Controllers\ShippingIntegrationController::class, 'createShipping']);
route::get('/test', [App\Http\Controllers\ShippingIntegrationController::class, 'createShipping']);
Route::get('shipping/update', [App\Http\Controllers\ShippingIntegrationController::class, 'updateShipping']);
Route::get('shipping/get', [App\Http\Controllers\ShippingIntegrationController::class, 'getShipping']);
Route::get('shipping/delete', [App\Http\Controllers\ShippingIntegrationController::class, 'deleteShipping']);
Route::get('shipping/track', [App\Http\Controllers\ShippingIntegrationController::class, 'shippingTracking']);
Route::get('shipping/call-courier', [App\Http\Controllers\ShippingIntegrationController::class, 'callCourier']);
Route::get('shipping/download-labels', [App\Http\Controllers\ShippingIntegrationController::class, 'downloadLabels']);
Route::get('shipping/download-manifest', [App\Http\Controllers\ShippingIntegrationController::class, 'downloadManifest']);