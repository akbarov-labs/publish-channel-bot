<?php

use Illuminate\Support\Facades\Route;

// This host serves the admin and the API; readers are on the Next.js site.
Route::redirect('/', '/admin');
