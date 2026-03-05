<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// Serve the React SPA for all web routes — API is handled in routes/api.php
Route::get('/{any?}', SpaController::class)->where('any', '.*');
