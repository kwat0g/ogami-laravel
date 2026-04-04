<?php

declare(strict_types=1);

use App\Http\Controllers\PublicSite\RecruitmentPublicController;
use Illuminate\Support\Facades\Route;

Route::get('postings', [RecruitmentPublicController::class, 'index'])
    ->name('postings.index');

Route::post('applications', [RecruitmentPublicController::class, 'store'])
    ->middleware('throttle:api-action')
    ->name('applications.store');
