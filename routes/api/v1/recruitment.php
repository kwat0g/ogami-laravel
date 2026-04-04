<?php

declare(strict_types=1);

use App\Http\Controllers\HR\Recruitment\ApplicationController;
use App\Http\Controllers\HR\Recruitment\CandidateController;
use App\Http\Controllers\HR\Recruitment\HiringController;
use App\Http\Controllers\HR\Recruitment\InterviewController;
use App\Http\Controllers\HR\Recruitment\JobPostingController;
use App\Http\Controllers\HR\Recruitment\OfferController;
use App\Http\Controllers\HR\Recruitment\PreEmploymentController;
use App\Http\Controllers\HR\Recruitment\RecruitmentDashboardController;
use App\Http\Controllers\HR\Recruitment\RecruitmentReportController;
use App\Http\Controllers\HR\Recruitment\RequisitionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Recruitment Routes — /api/v1/recruitment/*
| All routes require Sanctum authentication + HR module access.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'module_access:hr'])->group(function () {

    // ── Dashboard & Reports ──────────────────────────────────────────────
    Route::get('dashboard', [RecruitmentDashboardController::class, 'index'])
        ->name('dashboard');
    Route::get('reports/pipeline', [RecruitmentReportController::class, 'pipeline'])
        ->name('reports.pipeline');
    Route::get('reports/time-to-fill', [RecruitmentReportController::class, 'timeToFill'])
        ->name('reports.time-to-fill');
    Route::get('reports/source-mix', [RecruitmentReportController::class, 'sourceMix'])
        ->name('reports.source-mix');

    // ── Job Requisitions ─────────────────────────────────────────────────
    Route::apiResource('requisitions', RequisitionController::class)
        ->except(['destroy']);
    Route::post('requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])
        ->name('requisitions.submit');
    Route::post('requisitions/{requisition}/approve', [RequisitionController::class, 'approve'])
        ->name('requisitions.approve');
    Route::post('requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])
        ->name('requisitions.reject');
    Route::post('requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel'])
        ->name('requisitions.cancel');
    Route::post('requisitions/{requisition}/hold', [RequisitionController::class, 'hold'])
        ->name('requisitions.hold');
    Route::post('requisitions/{requisition}/resume', [RequisitionController::class, 'resume'])
        ->name('requisitions.resume');
    Route::post('requisitions/{requisition}/open', [RequisitionController::class, 'open'])
        ->name('requisitions.open');

    // ── Job Postings ─────────────────────────────────────────────────────
    Route::apiResource('postings', JobPostingController::class)
        ->except(['destroy']);
    Route::post('postings/{posting}/publish', [JobPostingController::class, 'publish'])
        ->name('postings.publish');
    Route::post('postings/{posting}/close', [JobPostingController::class, 'close'])
        ->name('postings.close');
    Route::post('postings/{posting}/reopen', [JobPostingController::class, 'reopen'])
        ->name('postings.reopen');

    // ── Applications ─────────────────────────────────────────────────────
    Route::apiResource('applications', ApplicationController::class)
        ->except(['update']);
    Route::post('applications/{application}/review', [ApplicationController::class, 'review'])
        ->name('applications.review');
    Route::post('applications/{application}/shortlist', [ApplicationController::class, 'shortlist'])
        ->name('applications.shortlist');
    Route::post('applications/{application}/reject', [ApplicationController::class, 'reject'])
        ->name('applications.reject');
    Route::post('applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])
        ->name('applications.withdraw');

    // ── Interviews ───────────────────────────────────────────────────────
    Route::apiResource('interviews', InterviewController::class)
        ->except(['destroy']);
    Route::post('interviews/{interview}/cancel', [InterviewController::class, 'cancel'])
        ->name('interviews.cancel');
    Route::post('interviews/{interview}/no-show', [InterviewController::class, 'markNoShow'])
        ->name('interviews.no-show');
    Route::post('interviews/{interview}/complete', [InterviewController::class, 'complete'])
        ->name('interviews.complete');
    Route::post('interviews/{interview}/evaluation', [InterviewController::class, 'submitEvaluation'])
        ->name('interviews.evaluation');

    // ── Offers ───────────────────────────────────────────────────────────
    Route::apiResource('offers', OfferController::class)
        ->except(['destroy']);
    Route::post('offers/{offer}/send', [OfferController::class, 'send'])
        ->name('offers.send');
    Route::post('offers/{offer}/accept', [OfferController::class, 'accept'])
        ->name('offers.accept');
    Route::post('offers/{offer}/reject', [OfferController::class, 'reject'])
        ->name('offers.reject');
    Route::post('offers/{offer}/withdraw', [OfferController::class, 'withdraw'])
        ->name('offers.withdraw');

    // ── Pre-Employment ───────────────────────────────────────────────────
    Route::get('pre-employment/{application}', [PreEmploymentController::class, 'show'])
        ->name('pre-employment.show');
    Route::post('pre-employment/{application}/init', [PreEmploymentController::class, 'init'])
        ->name('pre-employment.init');
    Route::post('pre-employment/requirements/{requirement}/submit-document', [PreEmploymentController::class, 'submitDocument'])
        ->name('pre-employment.submit-document');
    Route::post('pre-employment/requirements/{requirement}/verify', [PreEmploymentController::class, 'verify'])
        ->name('pre-employment.verify');
    Route::post('pre-employment/requirements/{requirement}/reject', [PreEmploymentController::class, 'rejectDocument'])
        ->name('pre-employment.reject');
    Route::post('pre-employment/requirements/{requirement}/waive', [PreEmploymentController::class, 'waive'])
        ->name('pre-employment.waive');
    Route::post('pre-employment/{checklist}/complete', [PreEmploymentController::class, 'complete'])
        ->name('pre-employment.complete');

    // ── Hiring ───────────────────────────────────────────────────────────
    Route::post('hire/{application}', [HiringController::class, 'hire'])
        ->name('hire');
    Route::post('hirings/{hiring}/vp-approve', [HiringController::class, 'vpApprove'])
        ->name('hirings.vp-approve');
    Route::post('hirings/{hiring}/vp-reject', [HiringController::class, 'vpReject'])
        ->name('hirings.vp-reject');

    // ── Candidates ───────────────────────────────────────────────────────
    Route::get('candidates', [CandidateController::class, 'index'])
        ->name('candidates.index');
    Route::post('candidates', [CandidateController::class, 'store'])
        ->name('candidates.store');
    Route::get('candidates/{candidate}', [CandidateController::class, 'show'])
        ->name('candidates.show');
    Route::patch('candidates/{candidate}', [CandidateController::class, 'update'])
        ->name('candidates.update');
});
