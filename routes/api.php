<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\ManagerController;
use App\Http\Controllers\Api\MeterController;
use App\Http\Controllers\Api\MeterReadingController;
use App\Http\Controllers\Api\NoticeController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\PropertyImageController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketImageController;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Health check ─────────────────────────────────
Route::get('/health', HealthController::class);

// Public routes
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes (all roles)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => new UserResource($request->user()));

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'changePassword']);

    // Properties (read for all, write for landlord handled in controller)
    Route::apiResource('properties', PropertyController::class);
    Route::put('properties/{property}/restore', [PropertyController::class, 'restore']);

    // Property images (read for all)
    Route::get('properties/{property}/images', [PropertyImageController::class, 'index']);
    Route::post('properties/{property}/images', [PropertyImageController::class, 'store']);
    Route::put('property-images/{image}', [PropertyImageController::class, 'update']);
    Route::delete('property-images/{image}', [PropertyImageController::class, 'destroy']);

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

    // Ticket images
    Route::get('tickets/{ticket}/images', [TicketImageController::class, 'index']);
    Route::post('tickets/{ticket}/images', [TicketImageController::class, 'store']);
    Route::delete('tickets/{ticket}/images/{image}', [TicketImageController::class, 'destroy']);

    // Meters (read for all, write checked in controller)
    Route::get('properties/{property}/meters', [MeterController::class, 'index']);
    Route::post('properties/{property}/meters', [MeterController::class, 'store']);
    Route::get('meters/{meter}', [MeterController::class, 'show']);
    Route::put('meters/{meter}', [MeterController::class, 'update']);
    Route::delete('meters/{meter}', [MeterController::class, 'destroy']);
    Route::get('meters/{meter}/readings', [MeterReadingController::class, 'index']);
    Route::post('meters/{meter}/readings', [MeterReadingController::class, 'store']);
    Route::delete('meters/{meter}/readings/{reading}', [MeterReadingController::class, 'destroy']);

    // Notices
    Route::get('properties/{property}/notices', [NoticeController::class, 'index']);
    Route::post('properties/{property}/notices', [NoticeController::class, 'store']);
    Route::put('notices/{notice}', [NoticeController::class, 'update']);
    Route::delete('notices/{notice}', [NoticeController::class, 'destroy']);

    // Notifications
    Route::get('notifications', [DashboardController::class, 'notifications']);
    Route::put('notifications/{notification}/read', [DashboardController::class, 'markNotificationRead']);

    // Users list (for People page + tenant self-lookup)
    Route::get('/users', function (Request $request) {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return response()->json($query->get(['id', 'name', 'email', 'role', 'phone', 'trust_score', 'created_at']));
    });

    // ─── READ routes accessible to all authenticated users ───
    // Expenses (read)
    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::get('expenses/{expense}', [ExpenseController::class, 'show']);

    // Inventory (read)
    Route::get('properties/{property}/inventory', [InventoryItemController::class, 'index']);
    Route::get('inventory/{item}', [InventoryItemController::class, 'show']);

    // Documents (read + download)
    Route::get('properties/{property}/documents', [DocumentController::class, 'index']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download']);

    // Ratings (read)
    Route::get('leases/{lease}/ratings', [RatingController::class, 'index']);

    // Trust score (read)
    Route::get('tenants/{tenant}/trust-score', [DashboardController::class, 'trustScore']);

    // Manager management (landlord only in controller)
    Route::post('users/{user}/promote-manager', [ManagerController::class, 'promote']);
    Route::post('users/{user}/demote-manager', [ManagerController::class, 'demote']);
    Route::get('properties/{property}/managers', [ManagerController::class, 'list']);
    Route::post('properties/{property}/assign-manager', [ManagerController::class, 'assign']);
    Route::delete('properties/{property}/remove-manager/{user}', [ManagerController::class, 'remove']);

    // Dashboard (landlord stats)
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('dashboard/finance-chart', [DashboardController::class, 'financeChart']);
    Route::get('dashboard/occupancy-chart', [DashboardController::class, 'occupancyChart']);

    // ─── Landlord-only WRITE routes ───
    Route::middleware('role:landlord,manager')->group(function () {
        // Expenses (write)
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
        Route::patch('expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);

        // Inventory (write)
        Route::post('properties/{property}/inventory', [InventoryItemController::class, 'store']);
        Route::put('inventory/{item}', [InventoryItemController::class, 'update']);
        Route::delete('inventory/{item}', [InventoryItemController::class, 'destroy']);

        // Documents (write)
        Route::post('properties/{property}/documents', [DocumentController::class, 'store']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

        // Ratings (write)
        Route::post('leases/{lease}/ratings', [RatingController::class, 'store']);
        Route::delete('ratings/{rating}', [RatingController::class, 'destroy']);
    });
});
