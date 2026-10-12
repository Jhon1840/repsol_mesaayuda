<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));
Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', fn (Request $request) => redirect($request->user()->isSupport() ? '/support/tickets' : '/mis-tickets'))->name('dashboard');
    Route::get('/mis-tickets', [TicketController::class, 'index'])->name('tickets.mine');
    Route::get('/support/tickets', [TicketController::class, 'index'])->name('support.tickets');
    Route::get('/tickets/create', [TicketController::class, 'create']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
    Route::patch('/tickets/{ticket}', [TicketController::class, 'update']);
    Route::post('/tickets/{ticket}/comments', [TicketController::class, 'comment'])->middleware('throttle:30,1');
    Route::post('/tickets/{ticket}/attachments', [TicketController::class, 'attach']);
    Route::get('/attachments/{attachment}/download', [TicketController::class, 'download'])->name('attachments.download');
    Route::get('/attachments/{attachment}/preview', [TicketController::class, 'preview'])->name('attachments.preview');
    Route::get('/support/users', [AdministrationController::class, 'users']);
    Route::post('/support/users', [AdministrationController::class, 'storeUser']);
    Route::patch('/support/users/{user}', [AdministrationController::class, 'updateUser']);
    Route::get('/support/categories', [AdministrationController::class, 'categories']);
    Route::post('/support/categories', [AdministrationController::class, 'storeCategory']);
    Route::patch('/support/categories/{category}', [AdministrationController::class, 'updateCategory']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
});
