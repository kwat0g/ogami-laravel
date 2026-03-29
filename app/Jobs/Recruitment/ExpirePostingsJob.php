<?php

declare(strict_types=1);

namespace App\Jobs\Recruitment;

use App\Domains\HR\Recruitment\Enums\PostingStatus;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Services\JobPostingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ExpirePostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(JobPostingService $postingService): void
    {
        $expiredPostings = JobPosting::where('status', PostingStatus::Published->value)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<', now())
            ->get();

        foreach ($expiredPostings as $posting) {
            try {
                $postingService->expire($posting);
                Log::info('Expired job posting', ['posting_id' => $posting->id, 'posting_number' => $posting->posting_number]);
            } catch (\Throwable $e) {
                Log::error('Failed to expire posting', [
                    'posting_id' => $posting->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
