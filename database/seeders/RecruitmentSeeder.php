<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\HR\Models\Department;
use App\Domains\HR\Models\Position;
use App\Domains\HR\Recruitment\Models\Application;
use App\Domains\HR\Recruitment\Models\Candidate;
use App\Domains\HR\Recruitment\Models\Hiring;
use App\Domains\HR\Recruitment\Models\InterviewEvaluation;
use App\Domains\HR\Recruitment\Models\InterviewSchedule;
use App\Domains\HR\Recruitment\Models\JobOffer;
use App\Domains\HR\Recruitment\Models\JobPosting;
use App\Domains\HR\Recruitment\Models\JobRequisition;
use App\Domains\HR\Recruitment\Models\PreEmploymentChecklist;
use App\Domains\HR\Recruitment\Models\PreEmploymentRequirement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Recruitment module with meaningful demo data for thesis defense.
 *
 * Produces:
 * - 10 candidates with varied sources
 * - 5 approved/open requisitions across departments
 * - 10 published job postings
 * - 30 applications in various stages
 * - 15 interviews with evaluations
 * - 5 offers (mixed statuses)
 * - 3 completed hirings
 */
class RecruitmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Recruitment module...');

        // Get existing departments and positions, or skip if none exist
        $departments = Department::where('is_active', true)->limit(5)->get();
        $positions = Position::where('is_active', true)->limit(10)->get();

        if ($departments->isEmpty() || $positions->isEmpty()) {
            $this->command->warn('Skipping RecruitmentSeeder: no departments or positions found. Run DepartmentPositionSeeder first.');

            return;
        }

        // Get an HR user for actions
        $hrUser = User::whereHas('roles', fn ($q) => $q->where('name', 'manager'))
            ->first() ?? User::first();

        $approverUser = User::whereHas('roles', fn ($q) => $q->where('name', 'vice_president'))
            ->first() ?? User::where('id', '!=', $hrUser->id)->first() ?? $hrUser;

        if (! $hrUser) {
            $this->command->warn('Skipping RecruitmentSeeder: no users found.');

            return;
        }

        // ── 1. Candidates ─────────────────────────────────────────────────
        $this->command->info('  Creating 10 candidates...');
        $candidates = collect();
        $sources = ['referral', 'walk_in', 'job_board', 'agency', 'internal'];
        $names = [
            ['Juan', 'Dela Cruz'], ['Maria', 'Santos'], ['Pedro', 'Garcia'],
            ['Ana', 'Reyes'], ['Carlos', 'Mendoza'], ['Sofia', 'Villanueva'],
            ['Miguel', 'Torres'], ['Isabella', 'Ramos'], ['Rafael', 'Cruz'],
            ['Gabriela', 'Flores'],
        ];

        foreach ($names as $i => [$first, $last]) {
            $candidates->push(Candidate::create([
                'first_name' => $first,
                'last_name' => $last,
                'email' => strtolower($first) . '.' . strtolower($last) . '@example.com',
                'phone' => '09' . fake()->numerify('#########'),
                'address' => fake()->address(),
                'source' => $sources[$i % count($sources)],
            ]));
        }

        // ── 2. Job Requisitions ───────────────────────────────────────────
        $this->command->info('  Creating 5 requisitions...');
        $requisitions = collect();
        $statuses = ['open', 'open', 'open', 'approved', 'closed'];

        for ($i = 0; $i < 5; $i++) {
            $dept = $departments[$i % $departments->count()];
            $pos = $positions[$i % $positions->count()];

            $req = JobRequisition::create([
                'department_id' => $dept->id,
                'position_id' => $pos->id,
                'requested_by' => $hrUser->id,
                'approved_by' => $approverUser->id,
                'employment_type' => 'regular',
                'headcount' => fake()->numberBetween(1, 3),
                'reason' => 'Business expansion requires additional ' . $pos->title . ' for ' . $dept->name . ' department.',
                'salary_range_min' => 2000000,
                'salary_range_max' => 4000000,
                'target_start_date' => now()->addMonths(1)->toDateString(),
                'status' => $statuses[$i],
                'approved_at' => now()->subDays(10),
            ]);

            $requisitions->push($req);
        }

        // ── 3. Job Postings ───────────────────────────────────────────────
        $this->command->info('  Creating 10 postings...');
        $postings = collect();

        foreach ($requisitions as $i => $req) {
            // 2 postings per requisition
            for ($j = 0; $j < 2; $j++) {
                $posting = JobPosting::create([
                    'job_requisition_id' => $req->id,
                    'title' => $req->position->title . ($j > 0 ? ' (Internal)' : ''),
                    'description' => "We are looking for a qualified {$req->position->title} to join our {$req->department->name} team. " .
                        'The ideal candidate should have relevant experience and a strong work ethic. ' .
                        'This is a great opportunity to grow within a dynamic manufacturing company.',
                    'requirements' => "- Bachelor's degree in related field\n- Minimum 2 years experience\n- Strong communication skills\n- Team player with leadership potential",
                    'location' => 'Main Plant, Cavite',
                    'employment_type' => 'regular',
                    'is_internal' => $j > 0,
                    'is_external' => $j === 0,
                    'status' => $req->status === 'closed' ? 'closed' : 'published',
                    'published_at' => now()->subDays(rand(5, 20)),
                    'closes_at' => now()->addDays(rand(10, 30)),
                ]);

                $postings->push($posting);
            }
        }

        // ── 4. Applications ───────────────────────────────────────────────
        $this->command->info('  Creating 30 applications...');
        $applications = collect();
        $appStatuses = ['new', 'new', 'under_review', 'under_review', 'shortlisted', 'shortlisted', 'shortlisted', 'rejected', 'withdrawn', 'shortlisted'];

        $publishedPostings = $postings->where('status', 'published')->values();

        foreach ($candidates as $ci => $candidate) {
            // Each candidate applies to 3 different postings
            $targetPostings = $publishedPostings->shuffle()->take(3);

            foreach ($targetPostings as $pi => $posting) {
                $appStatus = $appStatuses[($ci * 3 + $pi) % count($appStatuses)];

                $app = Application::create([
                    'job_posting_id' => $posting->id,
                    'candidate_id' => $candidate->id,
                    'application_date' => now()->subDays(rand(1, 15))->toDateString(),
                    'source' => $candidate->source,
                    'status' => $appStatus,
                    'cover_letter' => fake()->optional(0.6)->paragraphs(2, true),
                    'reviewed_by' => in_array($appStatus, ['under_review', 'shortlisted', 'rejected']) ? $hrUser->id : null,
                    'reviewed_at' => in_array($appStatus, ['under_review', 'shortlisted', 'rejected']) ? now()->subDays(rand(1, 5)) : null,
                    'rejection_reason' => $appStatus === 'rejected' ? 'Does not meet minimum qualifications.' : null,
                    'withdrawn_reason' => $appStatus === 'withdrawn' ? 'Accepted another offer.' : null,
                ]);

                $applications->push($app);
            }
        }

        // ── 5. Interviews ─────────────────────────────────────────────────
        $this->command->info('  Creating 15 interviews with evaluations...');
        $shortlistedApps = $applications->where('status', 'shortlisted')->take(15)->values();

        foreach ($shortlistedApps as $i => $app) {
            $interviewStatus = $i < 10 ? 'completed' : 'scheduled';
            $scheduledAt = $interviewStatus === 'completed'
                ? now()->subDays(rand(1, 7))
                : now()->addDays(rand(1, 7));

            $interview = InterviewSchedule::create([
                'application_id' => $app->id,
                'round' => 1,
                'type' => ['hr_screening', 'technical', 'panel', 'one_on_one', 'final'][$i % 5],
                'scheduled_at' => $scheduledAt,
                'duration_minutes' => [30, 45, 60][$i % 3],
                'location' => 'Conference Room ' . chr(65 + ($i % 4)),
                'interviewer_id' => $hrUser->id,
                'status' => $interviewStatus,
            ]);

            // Add evaluation for completed interviews
            if ($interviewStatus === 'completed') {
                $scorecard = [
                    ['criterion' => 'Communication', 'score' => rand(3, 5), 'comments' => 'Good communicator.'],
                    ['criterion' => 'Technical Skills', 'score' => rand(2, 5), 'comments' => 'Adequate technical background.'],
                    ['criterion' => 'Problem Solving', 'score' => rand(3, 5), 'comments' => 'Shows analytical thinking.'],
                    ['criterion' => 'Culture Fit', 'score' => rand(3, 5), 'comments' => 'Would integrate well with the team.'],
                ];
                $scores = array_column($scorecard, 'score');
                $avg = round(array_sum($scores) / count($scores), 2);

                InterviewEvaluation::create([
                    'interview_schedule_id' => $interview->id,
                    'submitted_by' => $hrUser->id,
                    'scorecard' => $scorecard,
                    'overall_score' => $avg,
                    'recommendation' => $avg >= 4 ? 'endorse' : ($avg >= 3 ? 'hold' : 'reject'),
                    'general_remarks' => $avg >= 4 ? 'Strong candidate, recommend for offer.' : 'Needs further evaluation.',
                    'submitted_at' => $scheduledAt->addHours(2),
                ]);
            }
        }

        // ── 6. Job Offers ─────────────────────────────────────────────────
        $this->command->info('  Creating 5 offers...');
        $endorsedApps = $shortlistedApps->take(5)->values();
        $offerStatuses = ['accepted', 'accepted', 'accepted', 'sent', 'draft'];

        foreach ($endorsedApps as $i => $app) {
            $offerStatus = $offerStatuses[$i];
            $posting = $app->posting;
            $req = $posting->requisition;

            JobOffer::create([
                'application_id' => $app->id,
                'offered_position_id' => $req->position_id,
                'offered_department_id' => $req->department_id,
                'offered_salary' => rand(2500000, 4000000),
                'employment_type' => 'regular',
                'start_date' => now()->addMonths(1)->toDateString(),
                'status' => $offerStatus,
                'sent_at' => $offerStatus !== 'draft' ? now()->subDays(5) : null,
                'expires_at' => $offerStatus === 'sent' ? now()->addDays(5) : null,
                'responded_at' => $offerStatus === 'accepted' ? now()->subDays(2) : null,
                'prepared_by' => $hrUser->id,
                'approved_by' => $offerStatus !== 'draft' ? $approverUser->id : null,
            ]);
        }

        // ── 7. Pre-Employment + Hirings ───────────────────────────────────
        $this->command->info('  Creating 3 hirings...');
        $acceptedApps = $endorsedApps->take(3)->values();

        foreach ($acceptedApps as $i => $app) {
            // Pre-employment checklist
            $checklist = PreEmploymentChecklist::create([
                'application_id' => $app->id,
                'status' => 'completed',
                'completed_at' => now()->subDays(1),
                'verified_by' => $hrUser->id,
            ]);

            foreach (['nbi_clearance', 'medical_certificate', 'tin', 'sss', 'philhealth', 'pagibig', 'birth_certificate', 'id_photo'] as $type) {
                PreEmploymentRequirement::create([
                    'pre_employment_checklist_id' => $checklist->id,
                    'requirement_type' => $type,
                    'label' => str_replace('_', ' ', ucfirst($type)),
                    'is_required' => true,
                    'status' => 'verified',
                    'verified_at' => now()->subDays(1),
                ]);
            }

            // Hiring record
            $req = $app->posting->requisition;
            Hiring::create([
                'application_id' => $app->id,
                'job_requisition_id' => $req->id,
                'employee_id' => null,
                'status' => 'hired',
                'hired_at' => now(),
                'start_date' => now()->addWeeks(2)->toDateString(),
                'hired_by' => $hrUser->id,
                'notes' => 'Welcome to the team!',
            ]);
        }

        $this->command->info('Recruitment seeding complete.');
        $this->command->info("  Candidates: {$candidates->count()}, Requisitions: {$requisitions->count()}, Postings: {$postings->count()}, Applications: {$applications->count()}");
    }
}
