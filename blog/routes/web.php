<?php

use App\Http\Controllers\StatsExportController;
use Illuminate\Support\Facades\Route;

// This host serves the admin and the API; readers are on the Next.js site.
Route::redirect('/', '/admin');

// The signed-in author's views as CSV (Premium), linked from the dashboard chart.
// Signed in through the admin, so the same session cookie.
Route::get('/admin/stats.csv', StatsExportController::class);
