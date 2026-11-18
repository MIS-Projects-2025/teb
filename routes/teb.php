<?php
// routes/teb.php — require this INSIDE the Route::prefix($app_name)->middleware(AuthMiddleware::class)
// group in routes/general.php. That group already applies Authify, so it is not repeated here.

use App\Http\Controllers\Teb\ApprovalController;
use App\Http\Controllers\Teb\BmpController;
use App\Http\Controllers\Teb\DashboardController;
use App\Http\Controllers\Teb\FourMcnController;
use App\Http\Controllers\Teb\ProjectController;
use App\Http\Controllers\Teb\ReviewController;
use App\Http\Controllers\Teb\SettingsController;
use App\Http\Controllers\Teb\StageController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\EnsureTebAdmin;
use Illuminate\Support\Facades\Route;

// Only register inside the Authify group. If this file is also required somewhere without
// AuthMiddleware (e.g. from web.php), skip it there: a second, unprotected '/' named 'dashboard'
// would override general.php's redirect to Authify and break login.
$tebGroup = app('router')->getGroupStack();
if (! in_array(AuthMiddleware::class, (array) (end($tebGroup)['middleware'] ?? []), true)) {
    return;
}

// TRB dashboard is the app home page: /{APP_NAME}
Route::get('/', DashboardController::class)->name('dashboard');

Route::name('teb.')->group(function () {
    // Originator
    Route::get('projects/export', [ProjectController::class, 'export'])->name('projects.export');
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::get('projects/{project}/complete', [ProjectController::class, 'completeForm'])->name('projects.complete');
    Route::post('projects/{project}/complete', [ProjectController::class, 'complete'])->name('projects.complete.store');
    Route::post('projects/{project}/plan', [ProjectController::class, 'submitPlan'])->name('projects.plan');
    Route::post('projects/{project}/stage', StageController::class)->name('projects.stage');
    Route::get('projects/{project}/form', [ProjectController::class, 'form'])->name('projects.form');
    Route::post('projects/{project}/attachments', [ProjectController::class, 'upload'])->name('projects.upload');
    Route::get('projects/{project}/attachments/{attachment}', [ProjectController::class, 'download'])->name('projects.download');
    Route::get('projects/{project}/reviews/{review}.ics', [ReviewController::class, 'ics'])->name('reviews.ics');

    // 4MCN
    Route::get('projects/{project}/4mcn/create', [FourMcnController::class, 'create'])->name('fourmcn.create');
    Route::post('projects/{project}/4mcn', [FourMcnController::class, 'store'])->name('fourmcn.store');
    Route::get('projects/{project}/4mcn', [FourMcnController::class, 'show'])->name('fourmcn.show');

    // Approvers
    Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{approval}', [ApprovalController::class, 'act'])->name('approvals.act');

    // TRB Admin = Process Engineering (config teb.admin_departments)
    Route::middleware(EnsureTebAdmin::class)->group(function () {
        Route::get('review', [ReviewController::class, 'index'])->name('review.index');
        Route::get('projects/{project}/schedule', [ReviewController::class, 'scheduleForm'])->name('review.schedule');
        Route::post('projects/{project}/schedule', [ReviewController::class, 'schedule'])->name('review.schedule.store');
        Route::post('projects/{project}/reviews/{review}/resend', [ReviewController::class, 'resend'])->name('review.resend');
        Route::post('projects/{project}/status', [ReviewController::class, 'updateStatus'])->name('review.status');
        Route::post('projects/{project}/teb-no', [ReviewController::class, 'assignNumber'])->name('review.assign');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings');
        // BMP maintenance: categories per TQRDC driver and their classification.
        Route::post('settings/bmp', [BmpController::class, 'store'])->name('settings.bmp.store');
        Route::put('settings/bmp/{category}', [BmpController::class, 'update'])->name('settings.bmp.update');
        Route::delete('settings/bmp/{category}', [BmpController::class, 'destroy'])->name('settings.bmp.destroy');
    });
});
