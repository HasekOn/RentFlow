<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
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
});
