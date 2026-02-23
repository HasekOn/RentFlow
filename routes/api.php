<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyImageController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Properties
    Route::apiResource('properties', PropertyController::class);

    // Leases
    Route::apiResource('leases', LeaseController::class);

    // Payments
    Route::apiResource('payments', PaymentController::class);
    Route::put('payments/{id}/mark-paid', [PaymentController::class, 'markPaid']);

    // Expenses
    Route::apiResource('expenses', ExpenseController::class);

    // Tickets
    Route::apiResource('tickets', TicketController::class);

    // Ticket comments
    Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
    Route::delete('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);

    // Meters
    Route::get('properties/{property}/meters', [MeterController::class, 'index']);
    Route::post('properties/{property}/meters', [MeterController::class, 'store']);
    Route::get('meters/{meter}', [MeterController::class, 'show']);
    Route::put('meters/{meter}', [MeterController::class, 'update']);
    Route::delete('meters/{meter}', [MeterController::class, 'destroy']);

    // Meter readings
    Route::get('meters/{meter}/readings', [MeterReadingController::class, 'index']);
    Route::post('meters/{meter}/readings', [MeterReadingController::class, 'store']);
    Route::delete('meters/{meter}/readings/{reading}', [MeterReadingController::class, 'destroy']);

    // Property images
    Route::get('properties/{property}/images', [PropertyImageController::class, 'index']);
    Route::post('properties/{property}/images', [PropertyImageController::class, 'store']);
    Route::put('property-images/{image}', [PropertyImageController::class, 'update']);
    Route::delete('property-images/{image}', [PropertyImageController::class, 'destroy']);

    // Inventory
    Route::get('properties/{property}/inventory', [InventoryItemController::class, 'index']);
    Route::post('properties/{property}/inventory', [InventoryItemController::class, 'store']);
    Route::get('inventory/{item}', [InventoryItemController::class, 'show']);
    Route::put('inventory/{item}', [InventoryItemController::class, 'update']);
    Route::delete('inventory/{item}', [InventoryItemController::class, 'destroy']);
});
