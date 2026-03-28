<?php

declare(strict_types=1);

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Shared\Contracts\ServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lead Scoring Service — Item 18.
 *
 * Assigns a 0-100 score to leads based on configurable criteria:
 *   - Source quality (referral > trade_show > website > cold_call > social_media)
 *   - Engagement level (activities count)
 *   - Recency (days since last activity)
 *   - Response rate (how quickly they respond)
 *
 * Auto-qualifies leads above a configurable threshold (default 70).
 * Scores are recalculated on demand or via scheduled command.
 */
final class LeadScoringService implements ServiceContract
{
    /** Source quality scores (higher = better lead source). */
    private const SOURCE_SCORES = [
        'referral' => 30,
        'trade_show' => 25,
        'website' => 20,
        'cold_call' => 10,
        'social_media' => 15,
        'other' => 5,
    ];

    /**
     * Score a single lead.
     *
     * @return array{lead_id: int, score: int, breakdown: array, qualified: bool}
     */
    public function scoreLead(Lead $lead): array
    {
        $breakdown = [];

        // 1. Source quality (0-30 points)
        $sourceScore = self::SOURCE_SCORES[$lead->source] ?? 5;
        $breakdown['source'] = ['points' => $sourceScore, 'max' => 30, 'detail' => $lead->source];

        // 2. Engagement: activity count (0-30 points)
        $activityCount = $lead->activities()->count();
        $engagementScore = min(30, $activityCount * 5); // 5 points per activity, max 30
        $breakdown['engagement'] = ['points' => $engagementScore, 'max' => 30, 'detail' => "{$activityCount} activities"];

        // 3. Recency: days since last activity (0-20 points)
        $lastActivity = $lead->activities()->orderByDesc('created_at')->value('created_at');
        $daysSinceActivity = $lastActivity ? now()->diffInDays($lastActivity) : 999;
        $recencyScore = match (true) {
            $daysSinceActivity <= 7 => 20,
            $daysSinceActivity <= 14 => 15,
            $daysSinceActivity <= 30 => 10,
            $daysSinceActivity <= 60 => 5,
            default => 0,
        };
        $breakdown['recency'] = ['points' => $recencyScore, 'max' => 20, 'detail' => "{$daysSinceActivity} days ago"];

        // 4. Profile completeness (0-20 points)
        $profileScore = 0;
        if (filled($lead->email)) {
            $profileScore += 5;
        }
        if (filled($lead->phone)) {
            $profileScore += 5;
        }
        if (filled($lead->company_name)) {
            $profileScore += 5;
        }
        if (filled($lead->contact_name)) {
            $profileScore += 5;
        }
        $breakdown['profile'] = ['points' => $profileScore, 'max' => 20, 'detail' => 'contact completeness'];

        $totalScore = min(100, $sourceScore + $engagementScore + $recencyScore + $profileScore);

        // Auto-qualify threshold
        $threshold = (int) (DB::table('system_settings')
            ->where('key', 'crm.lead_qualify_threshold')
            ->value('value') ?? 70);

        return [
            'lead_id' => $lead->id,
            'score' => $totalScore,
            'breakdown' => $breakdown,
            'qualified' => $totalScore >= $threshold,
            'threshold' => $threshold,
        ];
    }

    /**
     * Score all active leads and return ranked list.
     *
     * @return Collection<int, array>
     */
    public function scoreAll(): Collection
    {
        $leads = Lead::whereIn('status', ['new', 'contacted'])
            ->with('activities')
            ->get();

        return $leads->map(fn (Lead $lead) => [
            ...$this->scoreLead($lead),
            'company_name' => $lead->company_name,
            'contact_name' => $lead->contact_name,
            'status' => $lead->status,
        ])->sortByDesc('score')->values();
    }

    /**
     * Auto-qualify leads above threshold and return count of newly qualified.
     */
    public function autoQualify(): int
    {
        $leads = Lead::where('status', 'contacted')->get();
        $qualified = 0;

        foreach ($leads as $lead) {
            $result = $this->scoreLead($lead);
            if ($result['qualified'] && $lead->status !== 'qualified') {
                $lead->update(['status' => 'qualified']);
                $qualified++;
            }
        }

        return $qualified;
    }
}
