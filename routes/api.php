<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyImageController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn(Request $request) => new UserResource($request->user()));

    // Properties
    Route::apiResource('properties', PropertyController::class);
    Route::put('properties/{property}/restore', [PropertyController::class, 'restore']);

    // Leases
    Route::apiResource('leases', LeaseController::class);
    Route::get('leases/{lease}/generate-pdf', [LeaseController::class, 'generatePdf']);

    // Payments
    Route::post('payments/import-csv', [PaymentController::class, 'importCsv']);
    Route::put('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid']);
    Route::apiResource('payments', PaymentController::class);

    // Tickets
    Route::apiResource('tickets', TicketController::class);
    Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
    Route::delete('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);

    // Meters
    Route::get('properties/{property}/meters', [MeterController::class, 'index']);
    Route::post('properties/{property}/meters', [MeterController::class, 'store']);
    Route::get('meters/{meter}', [MeterController::class, 'show']);
    Route::put('meters/{meter}', [MeterController::class, 'update']);
    Route::delete('meters/{meter}', [MeterController::class, 'destroy']);
    Route::get('meters/{meter}/readings', [MeterReadingController::class, 'index']);
    Route::post('meters/{meter}/readings', [MeterReadingController::class, 'store']);
    Route::delete('meters/{meter}/readings/{reading}', [MeterReadingController::class, 'destroy']);

    // Property images
    Route::get('properties/{property}/images', [PropertyImageController::class, 'index']);
    Route::post('properties/{property}/images', [PropertyImageController::class, 'store']);
    Route::put('property-images/{image}', [PropertyImageController::class, 'update']);
    Route::delete('property-images/{image}', [PropertyImageController::class, 'destroy']);

    // Notices
    Route::get('properties/{property}/notices', [NoticeController::class, 'index']);
    Route::post('properties/{property}/notices', [NoticeController::class, 'store']);
    Route::put('notices/{notice}', [NoticeController::class, 'update']);
    Route::delete('notices/{notice}', [NoticeController::class, 'destroy']);

    // Notifications
    Route::get('notifications', [DashboardController::class, 'notifications']);
    Route::put('notifications/{notification}/read', [DashboardController::class, 'markNotificationRead']);

    // Landlord-only routes
    Route::middleware('role:landlord')->group(function () {

        // Expenses
        Route::apiResource('expenses', ExpenseController::class);

        // Inventory
        Route::get('properties/{property}/inventory', [InventoryItemController::class, 'index']);
        Route::post('properties/{property}/inventory', [InventoryItemController::class, 'store']);
        Route::get('inventory/{item}', [InventoryItemController::class, 'show']);
        Route::put('inventory/{item}', [InventoryItemController::class, 'update']);
        Route::delete('inventory/{item}', [InventoryItemController::class, 'destroy']);

        // Documents
        Route::get('properties/{property}/documents', [DocumentController::class, 'index']);
        Route::post('properties/{property}/documents', [DocumentController::class, 'store']);
        Route::get('documents/{document}/download', [DocumentController::class, 'download']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

        // Ratings
        Route::get('leases/{lease}/ratings', [RatingController::class, 'index']);
        Route::post('leases/{lease}/ratings', [RatingController::class, 'store']);
        Route::delete('ratings/{rating}', [RatingController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('dashboard/finance-chart', [DashboardController::class, 'financeChart']);
        Route::get('dashboard/occupancy-chart', [DashboardController::class, 'occupancyChart']);
        Route::get('tenants/{tenant}/trust-score', [DashboardController::class, 'trustScore']);
    });
});
