<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StaffController;
use App\Http\Middleware\LimitClients;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(User::count() ? '/dashboard' : '/setup'));
Route::get('/setup', [StaffController::class, 'setup'])->name('setup');
Route::post('/setup', [StaffController::class, 'finishSetup'])->name('setup.store');

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified', LimitClients::class])->name('dashboard');

// After a git pull on the server: run new migrations and rebuild the caches, instead of SSH.
Route::post('/refresh', function () {
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('optimize');

    return redirect()->route('dashboard')->with('status', 'app-refreshed');
})->middleware('auth')->name('refresh');

Route::middleware(['auth', LimitClients::class])->group(function () {
    Route::resource('clients', ClientController::class)->except('show');
    Route::post('/clients/{client}/plan', [ClientController::class, 'plan'])->name('clients.plan');
    Route::get('/clients/{client}/assets', [AssetController::class, 'index'])->name('clients.assets');
    Route::get('/clients/{client}/grid', [ClientController::class, 'grid'])->name('clients.grid');
    Route::get('/assets/{asset}/thumb', [AssetController::class, 'thumb'])->name('assets.thumb');
    Route::get('/assets/{asset}/download', [AssetController::class, 'download'])->name('assets.download');
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
    Route::get('/clients/{client}/reviews', [ReviewController::class, 'index'])->name('clients.reviews');
    Route::post('/clients/{client}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::get('/clients/{client}/accounts', [AccountController::class, 'index'])->name('clients.accounts');
    Route::get('/clients/{client}/reports', [ReportController::class, 'index'])->name('clients.reports');
    Route::post('/clients/{client}/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::post('/clients/{client}/reports/listening', [ReportController::class, 'listening'])->name('reports.listening');
    Route::get('/clients/{client}/comments', [CommentController::class, 'index'])->name('clients.comments');
    Route::post('/comments/{comment}/draft', [CommentController::class, 'draft'])->name('comments.draft');
    Route::post('/comments/{comment}/reply', [CommentController::class, 'reply'])->name('comments.reply');
    Route::post('/comments/{comment}/hide', [CommentController::class, 'hide'])->name('comments.hide');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');
    Route::post('/reports/{report}/send', [ReportController::class, 'send'])->name('reports.send');
    Route::get('/clients/{client}/accounts/connect/{provider}', [AccountController::class, 'connect'])->name('accounts.connect');
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    Route::post('/accounts/{account}/import', [AccountController::class, 'import'])->name('accounts.import');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
    Route::post('/jobs/{uuid}/retry', [JobController::class, 'retry'])->name('jobs.retry');
    Route::delete('/jobs/{uuid}', [JobController::class, 'forget'])->name('jobs.forget');
    Route::get('/clients/{client}/posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('/clients/{client}/posts', [PostController::class, 'store'])->name('posts.store');
    Route::get('/posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::post('/posts/{post}/draft', [PostController::class, 'draft'])->name('posts.draft');
    Route::post('/posts/{post}/shorten', [PostController::class, 'shorten'])->name('posts.shorten');
    Route::post('/posts/{post}/transition', [PostController::class, 'transition'])->name('posts.transition');
    Route::patch('/posts/{post}/reschedule', [PostController::class, 'reschedule'])->name('posts.reschedule');
    Route::post('/posts/{post}/notes', [PostController::class, 'note'])->name('posts.note');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::get('/posts/{post}/export', [PostController::class, 'export'])->name('posts.export');
    Route::get('/posts/{post}/image/{lang}', [PostController::class, 'image'])->name('posts.image')->where('lang', '[a-z]{2}');
    Route::get('/clients/{client}/posts/export.csv', [CalendarController::class, 'csv'])->name('posts.csv');
    Route::get('/clients/{client}/posts/import.csv', [PostController::class, 'importTemplate'])->name('posts.import.template');
    Route::post('/clients/{client}/posts/import', [PostController::class, 'import'])->name('posts.import');
    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('staff.destroy');
    Route::patch('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// OAuth return and the pick step: the state and candidates in the session are the credential, staff or client alike
Route::get('/oauth/{provider}/callback', [AccountController::class, 'callback'])->name('oauth.callback');
Route::post('/clients/{client}/accounts', [AccountController::class, 'attach'])->name('accounts.attach');

// Client-facing pages: no login, the signed link is the credential (GET and POST share the URL)
Route::middleware('signed')->group(function () {
    Route::get('/connect/{client:slug}', [AccountController::class, 'clientConnect'])->name('connect.show');
    Route::get('/connect/{client:slug}/{provider}', [AccountController::class, 'connect'])->name('connect.start');
    Route::get('/calendar/{client:slug}', [CalendarController::class, 'client'])->name('calendar.client');
    Route::get('/reports/{report}/client', [ReportController::class, 'client'])->name('reports.client');
    Route::get('/inbox/{client:slug}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{client:slug}', [InboxController::class, 'store'])->name('inbox.store');
    Route::get('/review/{review}', [PortalController::class, 'show'])->name('portal.show');
    Route::post('/review/{review}', [PortalController::class, 'decide'])->name('portal.decide');
    Route::get('/review/{review}/asset/{asset}', [PortalController::class, 'asset'])->name('portal.asset');
});

require __DIR__.'/auth.php';
