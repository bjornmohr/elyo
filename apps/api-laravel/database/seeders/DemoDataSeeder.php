<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $password = Hash::make('demo1234');

        $companyId = DB::table('companies')->updateOrInsert(
            ['slug' => 'demo-gmbh'],
            [
                'name' => 'Demo GmbH',
                'status' => 'active',
                'anonymity_threshold' => 3,
                'team_layer_enabled' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $companyId = DB::table('companies')->where('slug', 'demo-gmbh')->value('id');

        DB::table('companies')->updateOrInsert(
            ['slug' => 'elyo-platform'],
            [
                'name' => 'ELYO Platform',
                'status' => 'active',
                'anonymity_threshold' => 5,
                'team_layer_enabled' => false,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $platformCompanyId = DB::table('companies')->where('slug', 'elyo-platform')->value('id');

        $adminId = $this->upsertUser('admin@demo.de', 'Anna Admin', $password, $companyId, null, 'COMPANY_ADMIN', $now);
        $this->upsertUser('support@elyo.de', 'Elyo Support', $password, $platformCompanyId, null, 'ELYO_SUPPORT', $now);

        $teamId = DB::table('teams')->where('company_id', $companyId)->where('name', 'Product & Engineering')->value('id');
        if (! $teamId) {
            $teamId = DB::table('teams')->insertGetId([
                'name' => 'Product & Engineering',
                'description' => 'Demo team with enough employees for anonymized metrics.',
                'color' => '#14b8a6',
                'company_id' => $companyId,
                'manager_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $managerId = $this->upsertUser('manager@demo.de', 'Mia Manager', $password, $companyId, $teamId, 'COMPANY_MANAGER', $now);
        DB::table('teams')->where('id', $teamId)->update(['manager_id' => $managerId, 'updated_at' => $now]);

        $employeeIds = [];
        for ($i = 1; $i <= 6; $i++) {
            $employeeIds[] = $this->upsertUser(
                "employee{$i}@demo.de",
                "Employee {$i}",
                $password,
                $companyId,
                $teamId,
                'EMPLOYEE',
                $now->copy()->subDays($i)
            );
        }

        DB::table('user_points')->updateOrInsert(
            ['user_id' => $employeeIds[0]],
            [
                'total' => 120,
                'level' => 'SILVER',
                'streak' => 12,
                'last_checkin' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $periods = collect(range(0, 11))->map(fn ($week) => $now->copy()->subWeeks($week)->format('Y-\WW'))->values();
        DB::table('wellbeing_entries')->where('company_id', $companyId)->delete();

        foreach ($employeeIds as $idx => $userId) {
            foreach ($periods as $weekIdx => $periodKey) {
                $mood = max(1, min(5, 4 + (($idx + $weekIdx) % 3) - 1));
                $stress = max(1, min(5, 2 + (($idx + $weekIdx) % 4) - 1));
                $energy = max(1, min(5, 4 + (($idx + $weekIdx + 1) % 3) - 1));
                $score = round(($mood + (6 - $stress) + $energy) / 3, 1);

                DB::table('wellbeing_entries')->insert([
                    'mood' => $mood,
                    'stress' => $stress,
                    'energy' => $energy,
                    'score' => $score,
                    'note' => null,
                    'period_key' => $periodKey,
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'created_at' => $now->copy()->subWeeks($weekIdx)->subHours($idx),
                    'updated_at' => $now,
                ]);
            }
        }

        $this->seedDailyWellbeingEntries($companyId, $employeeIds, $now);
        $this->seedAssignedSystemMeasures($employeeIds, $adminId, $now);

        $surveyId = DB::table('surveys')->where('company_id', $companyId)->where('title', 'Quarterly Pulse Check')->value('id');
        if (! $surveyId) {
            $surveyId = DB::table('surveys')->insertGetId([
                'title' => 'Quarterly Pulse Check',
                'description' => 'Kurzumfrage zu Teamklima, Belastung und Fokus.',
                'status' => 'ACTIVE',
                'starts_at' => $now->copy()->subWeek(),
                'ends_at' => $now->copy()->addWeeks(3),
                'is_anonymous' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $questions = [
                ['text' => 'Wie zufrieden bist du mit dem Teamklima?', 'type' => 'SCALE', 'order' => 0, 'scale_min_label' => 'Niedrig', 'scale_max_label' => 'Hoch'],
                ['text' => 'Ist deine Arbeitslast aktuell gut steuerbar?', 'type' => 'YES_NO', 'order' => 1],
                ['text' => 'Welches Thema sollte priorisiert werden?', 'type' => 'MULTIPLE_CHOICE', 'order' => 2, 'options' => json_encode(['Meetings', 'Fokuszeit', 'Kommunikation'])],
            ];

            foreach ($questions as $question) {
                DB::table('survey_questions')->insert($question + [
                    'is_required' => true,
                    'survey_id' => $surveyId,
                    'created_at' => $now,
                ]);
            }
        }

        DB::table('survey_responses')->where('survey_id', $surveyId)->delete();
        $questions = DB::table('survey_questions')->where('survey_id', $surveyId)->orderBy('order')->get();
        foreach ($employeeIds as $idx => $userId) {
            $responseId = DB::table('survey_responses')->insertGetId([
                'submitted_at' => $now->copy()->subDays($idx),
                'company_id' => $companyId,
                'survey_id' => $surveyId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($questions as $question) {
                DB::table('survey_answers')->insert([
                    'response_id' => $responseId,
                    'question_id' => $question->id,
                    'scale_value' => $question->type === 'SCALE' ? 7 + ($idx % 3) : null,
                    'text_value' => null,
                    'choice_value' => $question->type === 'MULTIPLE_CHOICE' ? ['Meetings', 'Fokuszeit', 'Kommunikation'][$idx % 3] : null,
                    'bool_value' => $question->type === 'YES_NO' ? $idx % 2 === 0 : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('measures')->updateOrInsert(
            ['company_id' => $companyId, 'title' => 'Fokuszeit am Vormittag'],
            [
                'team_id' => $teamId,
                'category' => 'mental',
                'description' => 'Zwei meetingfreie Fokusblöcke pro Woche zur Entlastung des Teams.',
                'status' => 'ACTIVE',
                'suggested_at' => $now->copy()->subDays(5),
                'started_at' => $now->copy()->subDays(3),
                'completed_at' => null,
                'created_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->seedMockupMeasures($companyId, $adminId, $employeeIds, $now);

        DB::table('invite_tokens')->updateOrInsert(
            ['email' => 'new.employee@demo.de', 'company_id' => $companyId],
            [
                'team_id' => $teamId,
                'role' => 'EMPLOYEE',
                'token_hash' => hash('sha256', 'demo-invite-token'),
                'status' => 'pending',
                'invited_by_user_id' => $managerId,
                'expires_at' => $now->copy()->addDays(7),
                'accepted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $smallCompanyId = $this->upsertCompany('small-demo-gmbh', 'Small Demo GmbH', false, 3, $now);
        $smallAdminId = $this->upsertUser('small.admin@demo.de', 'Small Admin', $password, $smallCompanyId, null, 'COMPANY_ADMIN', $now);
        $this->upsertUser('small.manager@demo.de', 'Small Manager', $password, $smallCompanyId, null, 'COMPANY_MANAGER', $now);

        $smallEmployeeIds = [];
        for ($i = 1; $i <= 3; $i++) {
            $smallEmployeeIds[] = $this->upsertUser(
                "small.employee{$i}@demo.de",
                "Small Employee {$i}",
                $password,
                $smallCompanyId,
                null,
                'EMPLOYEE',
                $now->copy()->subDays($i)
            );
        }
        DB::table('teams')->where('company_id', $smallCompanyId)->delete();
        $this->seedWellbeingEntries($smallCompanyId, $smallEmployeeIds, $now, 6);
        $this->seedSurvey(
            $smallCompanyId,
            'Small Company Pulse',
            'Company-wide smoke survey for disabled team-layer flows.',
            $smallEmployeeIds,
            $now
        );
        $this->upsertMeasure(
            $smallCompanyId,
            null,
            'Company-wide Recovery Block',
            'mental',
            'Company-wide focus block for local smoke testing.',
            $smallAdminId,
            $now
        );

        $legacyCompanyId = $this->upsertCompany('legacy-teams-disabled-gmbh', 'Legacy Teams Disabled GmbH', false, 3, $now);
        $legacyTeamId = $this->upsertTeam(
            $legacyCompanyId,
            'Legacy Team',
            'Historical team retained after disabling the team layer.',
            '#6366f1',
            null,
            $now
        );
        $legacyAdminId = $this->upsertUser('legacy.admin@demo.de', 'Legacy Admin', $password, $legacyCompanyId, null, 'COMPANY_ADMIN', $now);
        $legacyManagerId = $this->upsertUser('legacy.manager@demo.de', 'Legacy Manager', $password, $legacyCompanyId, $legacyTeamId, 'COMPANY_MANAGER', $now);
        DB::table('teams')->where('id', $legacyTeamId)->update(['manager_id' => $legacyManagerId, 'updated_at' => $now]);

        $legacyEmployeeIds = [];
        for ($i = 1; $i <= 4; $i++) {
            $legacyEmployeeIds[] = $this->upsertUser(
                "legacy.employee{$i}@demo.de",
                "Legacy Employee {$i}",
                $password,
                $legacyCompanyId,
                $legacyTeamId,
                'EMPLOYEE',
                $now->copy()->subDays($i)
            );
        }
        $this->seedWellbeingEntries($legacyCompanyId, $legacyEmployeeIds, $now, 8);
        $this->seedSurvey(
            $legacyCompanyId,
            'Legacy Team Pulse',
            'Historical team-targeted survey hidden while the team layer is disabled.',
            $legacyEmployeeIds,
            $now,
            [$legacyTeamId]
        );
        $this->seedSurvey(
            $legacyCompanyId,
            'Legacy Company Pulse',
            'Company-wide survey for disabled team-layer smoke testing.',
            $legacyEmployeeIds,
            $now
        );
        $this->upsertMeasure(
            $legacyCompanyId,
            $legacyTeamId,
            'Legacy Team Focus Time',
            'mental',
            'Historical team-scoped measure for disabled team-layer smoke testing.',
            $legacyAdminId,
            $now
        );
        $this->upsertMeasure(
            $legacyCompanyId,
            null,
            'Legacy Company Check-in',
            'mental',
            'Company-wide measure that remains usable when the team layer is disabled.',
            $legacyAdminId,
            $now
        );

        foreach ([
            ['partner@demo.de', 'Verified Fitness Berlin', 'Gym', ['fitness'], 'VERIFIED'],
            ['review@demo.de', 'Mindful Coaching', 'Digital', ['mental'], 'PENDING_REVIEW'],
        ] as [$email, $name, $type, $categories, $status]) {
            DB::table('partners')->updateOrInsert(
                ['email' => $email],
                [
                    'password_hash' => $password,
                    'name' => $name,
                    'type' => $type,
                    'categories' => json_encode($categories),
                    'description' => 'Demo partner profile for dynamic partner/admin pages.',
                    'city' => 'Berlin',
                    'minimum_level' => 'STARTER',
                    'nachweis_url' => 'https://example.com/demo.pdf',
                    'verification_status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->command?->info('Demo data seeded: admin@demo.de, small.admin@demo.de, legacy.admin@demo.de / demo1234');
    }

    private function upsertCompany(string $slug, string $name, bool $teamLayerEnabled, int $anonymityThreshold, mixed $now): int
    {
        DB::table('companies')->updateOrInsert(
            ['slug' => $slug],
            [
                'name' => $name,
                'status' => 'active',
                'anonymity_threshold' => $anonymityThreshold,
                'team_layer_enabled' => $teamLayerEnabled,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        return DB::table('companies')->where('slug', $slug)->value('id');
    }

    private function upsertTeam(int $companyId, string $name, string $description, string $color, ?int $managerId, mixed $now): int
    {
        $teamId = DB::table('teams')->where('company_id', $companyId)->where('name', $name)->value('id');

        if (! $teamId) {
            return DB::table('teams')->insertGetId([
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'company_id' => $companyId,
                'manager_id' => $managerId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('teams')->where('id', $teamId)->update([
            'description' => $description,
            'color' => $color,
            'manager_id' => $managerId,
            'updated_at' => $now,
        ]);

        return $teamId;
    }

    /**
     * @param  array<int>  $userIds
     */
    private function seedWellbeingEntries(int $companyId, array $userIds, mixed $now, int $weeks): void
    {
        DB::table('wellbeing_entries')->where('company_id', $companyId)->delete();

        $periods = collect(range(0, $weeks - 1))->map(fn ($week) => $now->copy()->subWeeks($week)->format('Y-\WW'))->values();
        foreach ($userIds as $idx => $userId) {
            foreach ($periods as $weekIdx => $periodKey) {
                $mood = max(1, min(5, 4 + (($idx + $weekIdx) % 3) - 1));
                $stress = max(1, min(5, 2 + (($idx + $weekIdx) % 4) - 1));
                $energy = max(1, min(5, 4 + (($idx + $weekIdx + 1) % 3) - 1));
                $score = round(($mood + (6 - $stress) + $energy) / 3, 1);

                DB::table('wellbeing_entries')->insert([
                    'mood' => $mood,
                    'stress' => $stress,
                    'energy' => $energy,
                    'score' => $score,
                    'note' => null,
                    'period_key' => $periodKey,
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'created_at' => $now->copy()->subWeeks($weekIdx)->subHours($idx),
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @param  array<int>  $employeeIds
     * @param  array<int>  $teamIds
     */
    private function seedSurvey(int $companyId, string $title, string $description, array $employeeIds, mixed $now, array $teamIds = []): int
    {
        $surveyId = DB::table('surveys')->where('company_id', $companyId)->where('title', $title)->value('id');
        if (! $surveyId) {
            $surveyId = DB::table('surveys')->insertGetId([
                'title' => $title,
                'description' => $description,
                'status' => 'ACTIVE',
                'starts_at' => $now->copy()->subWeek(),
                'ends_at' => $now->copy()->addWeeks(3),
                'is_anonymous' => true,
                'company_id' => $companyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('surveys')->where('id', $surveyId)->update([
                'description' => $description,
                'status' => 'ACTIVE',
                'starts_at' => $now->copy()->subWeek(),
                'ends_at' => $now->copy()->addWeeks(3),
                'is_anonymous' => true,
                'updated_at' => $now,
            ]);
        }

        $responseIds = DB::table('survey_responses')->where('survey_id', $surveyId)->pluck('id');
        if ($responseIds->isNotEmpty()) {
            DB::table('survey_answers')->whereIn('response_id', $responseIds)->delete();
        }
        DB::table('survey_responses')->where('survey_id', $surveyId)->delete();
        DB::table('survey_questions')->where('survey_id', $surveyId)->delete();
        DB::table('survey_team')->where('survey_id', $surveyId)->delete();

        $questionIds = [];
        foreach ([
            ['text' => 'Wie zufrieden bist du aktuell?', 'type' => 'SCALE', 'order' => 0, 'scale_min_label' => 'Niedrig', 'scale_max_label' => 'Hoch'],
            ['text' => 'Ist deine Arbeitslast aktuell gut steuerbar?', 'type' => 'YES_NO', 'order' => 1],
        ] as $question) {
            $questionIds[] = DB::table('survey_questions')->insertGetId($question + [
                'is_required' => true,
                'survey_id' => $surveyId,
                'created_at' => $now,
            ]);
        }

        foreach ($teamIds as $teamId) {
            DB::table('survey_team')->insertOrIgnore(['survey_id' => $surveyId, 'team_id' => $teamId]);
        }

        foreach ($employeeIds as $idx => $userId) {
            $responseId = DB::table('survey_responses')->insertGetId([
                'submitted_at' => $now->copy()->subDays($idx),
                'company_id' => $companyId,
                'survey_id' => $surveyId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($questionIds as $questionIdx => $questionId) {
                DB::table('survey_answers')->insert([
                    'response_id' => $responseId,
                    'question_id' => $questionId,
                    'scale_value' => $questionIdx === 0 ? 7 + ($idx % 3) : null,
                    'text_value' => null,
                    'choice_value' => null,
                    'bool_value' => $questionIdx === 1 ? $idx % 2 === 0 : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return $surveyId;
    }

    private function upsertMeasure(int $companyId, ?int $teamId, string $title, string $category, string $description, int $createdBy, mixed $now, array $attributes = []): int
    {
        DB::table('measures')->updateOrInsert(
            ['company_id' => $companyId, 'title' => $title],
            array_merge([
                'team_id' => $teamId,
                'category' => $category,
                'description' => $description,
                'status' => 'ACTIVE',
                'suggested_at' => $now->copy()->subDays(5),
                'started_at' => $now->copy()->subDays(3),
                'completed_at' => null,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ], $attributes)
        );

        return DB::table('measures')
            ->where('company_id', $companyId)
            ->where('title', $title)
            ->value('id');
    }

    /**
     * Measures for demo-gmbh matching the HR-dashboard mockups, so the live
     * measures page (cards, execution details, check-in) lines up with the
     * demo statistics served from database/demo/*.json.
     *
     * @param  array<int, int>  $employeeIds
     */
    private function seedMockupMeasures(int $companyId, int $adminId, array $employeeIds, mixed $now): void
    {
        $workshopId = $this->upsertMeasure(
            $companyId,
            null,
            'Rückenfit Bürostuhl-Workshop',
            'flexibility',
            'Praktischer Workshop für rückenschonendes Arbeiten am Schreibtisch.',
            $adminId,
            $now,
            [
                'delivery_type' => 'ONSITE',
                'execution_type' => 'EVENT_PARTICIPATION',
                'verification_requirement' => 'QR_CODE',
                'starts_at' => $now->copy()->addDays(4)->setTime(14, 0),
                'ends_at' => $now->copy()->addDays(4)->setTime(15, 30),
                'location_name' => 'Kantine EG',
                'capacity' => 80,
            ]
        );
        DB::table('measure_checkin_tokens')->updateOrInsert(
            ['measure_id' => $workshopId],
            [
                'company_id' => $companyId,
                'token_hash' => hash('sha256', 'demo-rueckenfit-checkin'),
                'created_by_user_id' => $adminId,
                'valid_from' => $now->copy()->subDays(6),
                'valid_until' => $now->copy()->addDays(5),
                'revoked_at' => null,
                'created_at' => $now->copy()->subDays(6),
                'updated_at' => $now,
            ]
        );

        $this->upsertMeasure(
            $companyId,
            null,
            'Achtsamkeits-Session',
            'mental',
            'Angeleitete Achtsamkeitsübung für einen ruhigen Start in die Woche.',
            $adminId,
            $now,
            [
                'delivery_type' => 'REMOTE',
                'execution_type' => 'GUIDED_SESSION',
                'starts_at' => $now->copy()->addDays(6)->setTime(9, 0),
                'ends_at' => $now->copy()->addDays(6)->setTime(9, 45),
            ]
        );

        $runningId = $this->upsertMeasure(
            $companyId,
            null,
            'Lauftreff Mittwochs',
            'sport',
            'Gemeinsamer Lauftreff jeden Mittwoch nach Feierabend.',
            $adminId,
            $now,
            [
                'delivery_type' => 'ONSITE',
                'execution_type' => 'CHALLENGE',
                'starts_at' => $now->copy()->subWeeks(3),
            ]
        );

        $yogaId = $this->upsertMeasure(
            $companyId,
            null,
            'Yoga am Morgen',
            'sport',
            'Sanfte Yoga-Einheit vor Arbeitsbeginn, offen für alle Level.',
            $adminId,
            $now,
            [
                'delivery_type' => 'HYBRID',
                'execution_type' => 'GUIDED_SESSION',
                'starts_at' => $now->copy()->subWeeks(2),
            ]
        );

        $vitaminId = $this->upsertMeasure(
            $companyId,
            null,
            'Vitamin-Info-Stand',
            'nutrition',
            'Info-Stand mit Verkostung und Tipps für ausgewogene Ernährung.',
            $adminId,
            $now,
            [
                'status' => 'COMPLETED',
                'delivery_type' => 'ONSITE',
                'execution_type' => 'EVENT_PARTICIPATION',
                'starts_at' => $now->copy()->subDays(14)->setTime(11, 0),
                'ends_at' => $now->copy()->subDays(14)->setTime(15, 0),
                'completed_at' => $now->copy()->subDays(14),
                'location_name' => 'Foyer Haupteingang',
            ]
        );

        $this->upsertMeasure(
            $companyId,
            null,
            'Ernährungs-Webinar',
            'nutrition',
            'Interaktives Webinar zu gesunder Ernährung im Arbeitsalltag.',
            $adminId,
            $now,
            [
                'status' => 'SUGGESTED',
                'started_at' => null,
                'delivery_type' => 'REMOTE',
                'execution_type' => 'INFORMATION_ONLY',
            ]
        );

        // Participation approximating the mockup quotas (6 employees, threshold 3).
        foreach ([
            $workshopId => 5,
            $runningId => 4,
            $yogaId => 3,
            $vitaminId => 4,
        ] as $measureId => $participantCount) {
            foreach (array_slice($employeeIds, 0, $participantCount) as $offset => $userId) {
                DB::table('measure_participations')->updateOrInsert(
                    ['measure_id' => $measureId, 'user_id' => $userId],
                    [
                        'company_id' => $companyId,
                        'team_id' => null,
                        'participated_at' => $now->copy()->subDays(2 + $offset),
                        'verification_type' => 'SELF_REPORTED',
                        'verified_at' => $now->copy()->subDays(2 + $offset),
                        'verified_by_user_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * Daily entries for the employee dashboard (1a): 14 days ending yesterday
     * (today stays open so the check-in CTA shows as pending), ascending from
     * a weaker previous week (~3.2) to a better current week (~3.8). Daily
     * period keys (Y-m-d) never collide with the weekly keys (Y-Www).
     */
    private function seedDailyWellbeingEntries(int $companyId, array $userIds, mixed $now): void
    {
        foreach ($userIds as $idx => $userId) {
            for ($daysAgo = 14; $daysAgo >= 1; $daysAgo--) {
                $date = $now->copy()->subDays($daysAgo);
                $isCurrentWeek = $daysAgo <= 7;

                $wave = ($idx + $daysAgo) % 2;
                $mood = $isCurrentWeek ? 4 + $wave : 3 + $wave;      // 3-4 -> 4-5
                $stress = $isCurrentWeek ? 2 : 3;                     // 3   -> 2
                $energy = $isCurrentWeek ? 3 + $wave : 3;             // 3   -> 3-4
                $mood = max(1, min(5, $mood));
                $energy = max(1, min(5, $energy));
                $score = round(($mood + (6 - $stress) + $energy) / 3, 1);

                DB::table('wellbeing_entries')->insert([
                    'mood' => $mood,
                    'stress' => $stress,
                    'energy' => $energy,
                    'score' => $score,
                    'note' => null,
                    'period_key' => $date->toDateString(),
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'created_at' => $date->copy()->setTime(8, 30)->addMinutes($idx * 7),
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Assign the three demo system-measure templates to every demo employee,
     * snapshotting the template exercises. Demo display values (streak, weekly
     * progress, last-session effect) live in recommendation_context->demo.
     */
    private function seedAssignedSystemMeasures(array $employeeIds, int $adminId, mixed $now): void
    {
        $demoContexts = [
            'nacken-mobilitaet' => [
                'streakDays' => 5, 'weeklyTarget' => 4, 'weeklyDone' => 3,
                'lastEffect' => ['before' => 6, 'after' => 3],
                'lastEffort' => 2, 'lastPoints' => 5,
            ],
            'abend-routine-schlaf' => [
                'streakDays' => 3, 'weeklyTarget' => 3, 'weeklyDone' => 2,
                'lastEffect' => ['before' => 6.5, 'after' => 7.4],
                'lastEffort' => 1, 'lastPoints' => 5,
            ],
            'atem-balance' => [
                'streakDays' => 0, 'weeklyTarget' => 3, 'weeklyDone' => 1,
                'lastEffect' => ['before' => 4, 'after' => 2],
                'lastEffort' => 1, 'lastPoints' => 5,
            ],
            'ruecken-fit-alltag' => [
                'streakDays' => 2, 'weeklyTarget' => 5, 'weeklyDone' => 2,
                'lastEffect' => ['before' => 5, 'after' => 2],
                'lastEffort' => 2, 'lastPoints' => 5,
            ],
        ];

        $templates = DB::table('system_measure_templates')
            ->whereIn('slug', array_keys($demoContexts))
            ->get()
            ->keyBy('slug');

        if ($templates->isEmpty()) {
            return;
        }

        DB::table('user_system_measures')
            ->whereIn('user_id', $employeeIds)
            ->whereIn('source_system_measure_template_id', $templates->pluck('id'))
            ->delete();

        foreach ($employeeIds as $userId) {
            foreach ($demoContexts as $slug => $demo) {
                $template = $templates->get($slug);
                if (! $template) {
                    continue;
                }

                $measureId = DB::table('user_system_measures')->insertGetId([
                    'user_id' => $userId,
                    'source_system_measure_template_id' => $template->id,
                    'assigned_by_user_id' => $adminId,
                    'title' => $template->title,
                    'description' => $template->description,
                    'assignment_reason' => $template->assignment_reason_template,
                    'recommendation_context' => json_encode(['demo' => $demo]),
                    'status' => 'ACTIVE',
                    'starts_at' => $now->copy()->subWeeks(2),
                    'assigned_at' => $now->copy()->subWeeks(2),
                    'streak_enabled' => true,
                    'points_enabled' => true,
                    'points_per_completion' => $template->default_points,
                    'requires_feedback' => $template->requires_feedback,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $templateExercises = DB::table('system_measure_template_exercises')
                    ->join('system_exercises', 'system_exercises.id', '=', 'system_measure_template_exercises.system_exercise_id')
                    ->where('system_measure_template_exercises.system_measure_template_id', $template->id)
                    ->orderBy('system_measure_template_exercises.position')
                    ->select([
                        'system_measure_template_exercises.position',
                        'system_measure_template_exercises.is_required',
                        'system_measure_template_exercises.custom_title',
                        'system_measure_template_exercises.custom_instructions',
                        'system_measure_template_exercises.custom_duration_minutes',
                        'system_measure_template_exercises.custom_sets',
                        'system_measure_template_exercises.custom_repetitions',
                        'system_measure_template_exercises.custom_hold_seconds',
                        'system_measure_template_exercises.custom_feedback_prompt',
                        'system_exercises.id as exercise_id',
                        'system_exercises.title',
                        'system_exercises.short_description',
                        'system_exercises.description',
                        'system_exercises.exercise_type',
                        'system_exercises.difficulty',
                        'system_exercises.default_duration_minutes',
                        'system_exercises.default_sets',
                        'system_exercises.default_repetitions',
                        'system_exercises.default_hold_seconds',
                        'system_exercises.instructions',
                        'system_exercises.safety_notes',
                        'system_exercises.contraindications',
                        'system_exercises.default_feedback_prompt',
                        'system_exercises.requires_feedback',
                    ])
                    ->get();

                foreach ($templateExercises as $exercise) {
                    DB::table('user_system_measure_exercises')->insert([
                        'user_system_measure_id' => $measureId,
                        'source_system_exercise_id' => $exercise->exercise_id,
                        'position' => $exercise->position,
                        'title' => $exercise->custom_title ?? $exercise->title,
                        'short_description' => $exercise->short_description,
                        'description' => $exercise->description,
                        'exercise_type' => $exercise->exercise_type,
                        'difficulty' => $exercise->difficulty,
                        'duration_minutes' => $exercise->custom_duration_minutes ?? $exercise->default_duration_minutes,
                        'sets' => $exercise->custom_sets ?? $exercise->default_sets,
                        'repetitions' => $exercise->custom_repetitions ?? $exercise->default_repetitions,
                        'hold_seconds' => $exercise->custom_hold_seconds ?? $exercise->default_hold_seconds,
                        'instructions' => $exercise->custom_instructions ?? $exercise->instructions,
                        'safety_notes' => $exercise->safety_notes,
                        'contraindications' => $exercise->contraindications,
                        'feedback_prompt' => $exercise->custom_feedback_prompt ?? $exercise->default_feedback_prompt,
                        'requires_feedback' => $exercise->requires_feedback,
                        'status' => 'PENDING',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function upsertUser(string $email, string $name, string $password, int $companyId, ?int $teamId, string $role, mixed $lastLoginAt): int
    {
        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'company_id' => $companyId,
                'team_id' => $teamId,
                'status' => 'active',
                'last_login_at' => $lastLoginAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $userId = DB::table('users')->where('email', $email)->value('id');

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $userId, 'role' => $role],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return $userId;
    }
}
