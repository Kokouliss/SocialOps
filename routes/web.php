<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('clients', ClientController::class)->except('show');
    Route::get('/clients/{client}/assets', [AssetController::class, 'index'])->name('clients.assets');
    Route::get('/assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::get('/clients/{client}/reviews', [ReviewController::class, 'index'])->name('clients.reviews');
    Route::post('/clients/{client}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/clients/{client}/accounts', [AccountController::class, 'index'])->name('clients.accounts');
    Route::get('/clients/{client}/accounts/connect/{provider}', [AccountController::class, 'connect'])->name('accounts.connect');
    Route::post('/clients/{client}/accounts', [AccountController::class, 'attach'])->name('accounts.attach');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::get('/oauth/{provider}/callback', [AccountController::class, 'callback'])->name('oauth.callback');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/clients/{client}/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/clients/{client}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/draft', [PostController::class, 'draft'])->name('posts.draft');
    Route::post('/posts/{post}/transition', [PostController::class, 'transition'])->name('posts.transition');
    Route::patch('/posts/{post}/reschedule', [PostController::class, 'reschedule'])->name('posts.reschedule');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Client-facing pages: no login, the signed link is the credential (GET and POST share the URL)
Route::middleware('signed')->group(function () {
    Route::get('/inbox/{client:slug}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{client:slug}', [InboxController::class, 'store'])->name('inbox.store');
    Route::get('/review/{review}', [PortalController::class, 'show'])->name('portal.show');
    Route::post('/review/{review}', [PortalController::class, 'decide'])->name('portal.decide');
    Route::get('/review/{review}/asset/{asset}', [PortalController::class, 'asset'])->name('portal.asset');
});

require __DIR__.'/auth.php';
