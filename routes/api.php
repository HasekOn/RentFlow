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
use App\Http\Controllers\Api\UserController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ─── Health check ─────────────────────────────────
Route::get('/health', HealthController::class);

// Public routes (prefix: vlastní počítadlo, ne sdílené s globálním throttle:200,1)
Route::middleware('throttle:60,1,auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Authenticated routes (all roles)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', fn (Request $request) => new UserResource($request->user()));

    // People (UserPolicy + User::visibleTo)
    Route::get('users', [UserController::class, 'index']);
    // hledání účtu podle e-mailu a pozvánka ke smlouvě sdílí jedno počítadlo (B-3)
    Route::get('users/lookup', [UserController::class, 'lookup'])->middleware('throttle:30,1,tenant-lookup');
    Route::get('users/{user}/ratings', [UserController::class, 'ratings']);

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
    Route::get('property-images/{image}/file', [PropertyImageController::class, 'file'])->name('api.property-images.file');

    // Leases
    Route::apiResource('leases', LeaseController::class)->middlewareFor('store', 'throttle:30,1,tenant-lookup');
    Route::get('leases/{lease}/generate-pdf', [LeaseController::class, 'generatePdf']);
    Route::put('leases/{lease}/accept', [LeaseController::class, 'accept']);
    Route::put('leases/{lease}/decline', [LeaseController::class, 'decline']);

    // Payments
    // import je drahý (parsování a párování v transakci), proto vlastní limit vedle globálního (S11); prefix
    // u limitu na routě, jinak sdílí počítadlo s globálním throttle:200,1 a každý požadavek se počítá dvakrát
    Route::post('payments/import-csv', [PaymentController::class, 'importCsv'])->middleware('throttle:10,1,import-csv');
    Route::put('payments/{payment}/mark-paid', [PaymentController::class, 'markPaid']);
    Route::post('payments/generate-monthly', [PaymentController::class, 'generateMonthly']);
    Route::apiResource('payments', PaymentController::class);

    // Tickets
    Route::apiResource('tickets', TicketController::class);
    Route::get('tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
    Route::post('tickets/{ticket}/comments', [TicketCommentController::class, 'store']);
    Route::delete('tickets/{ticket}/comments/{comment}', [TicketCommentController::class, 'destroy']);
    Route::get('tickets/{ticket}/comments/{comment}/attachment', [TicketCommentController::class, 'attachment'])
        ->name('api.ticket-comments.attachment');

    // Ticket images
    Route::get('tickets/{ticket}/images', [TicketImageController::class, 'index']);
    Route::post('tickets/{ticket}/images', [TicketImageController::class, 'store']);
    Route::delete('tickets/{ticket}/images/{image}', [TicketImageController::class, 'destroy']);
    Route::get('tickets/{ticket}/images/{image}/file', [TicketImageController::class, 'file'])->name('api.ticket-images.file');

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

    // Expenses (ExpensePolicy, jen vlastník)
    Route::get('expenses', [ExpenseController::class, 'index']);
    Route::post('expenses', [ExpenseController::class, 'store']);
    Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
    Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
    Route::patch('expenses/{expense}', [ExpenseController::class, 'update']);
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);

    // Inventory (InventoryItemPolicy)
    Route::get('properties/{property}/inventory', [InventoryItemController::class, 'index']);
    Route::post('properties/{property}/inventory', [InventoryItemController::class, 'store']);
    Route::get('inventory/{item}', [InventoryItemController::class, 'show']);
    Route::put('inventory/{item}', [InventoryItemController::class, 'update']);
    Route::delete('inventory/{item}', [InventoryItemController::class, 'destroy']);

    // Documents (DocumentPolicy)
    Route::get('properties/{property}/documents', [DocumentController::class, 'index']);
    Route::post('properties/{property}/documents', [DocumentController::class, 'store']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('api.documents.download');

    // Ratings (RatingPolicy: čte kdo vidí smlouvu, zapisuje vlastník nemovitosti)
    Route::get('leases/{lease}/ratings', [RatingController::class, 'index']);
    Route::post('leases/{lease}/ratings', [RatingController::class, 'store']);
    Route::delete('ratings/{rating}', [RatingController::class, 'destroy']);

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
});
