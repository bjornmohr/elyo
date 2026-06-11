<?php

namespace Database\Seeders;

use App\Models\SystemExercise;
use App\Models\SystemExerciseTag;
use App\Models\SystemMeasureTemplate;
use App\Models\SystemMeasureTemplateExercise;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemExerciseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedTags();
        $this->seedExercises();
        $this->seedTemplates();
    }

    private function seedTags(): void
    {
        $tags = [
            // BODY_REGION
            ['category' => 'BODY_REGION', 'key' => 'NECK', 'label' => 'Nacken', 'sort_order' => 1],
            ['category' => 'BODY_REGION', 'key' => 'SHOULDERS', 'label' => 'Schultern', 'sort_order' => 2],
            ['category' => 'BODY_REGION', 'key' => 'BACK', 'label' => 'Rücken', 'sort_order' => 3],
            ['category' => 'BODY_REGION', 'key' => 'HIPS', 'label' => 'Hüfte', 'sort_order' => 4],
            ['category' => 'BODY_REGION', 'key' => 'LEGS', 'label' => 'Beine', 'sort_order' => 5],
            ['category' => 'BODY_REGION', 'key' => 'FULL_BODY', 'label' => 'Ganzkörper', 'sort_order' => 6],

            // GOAL
            ['category' => 'GOAL', 'key' => 'MOBILITY', 'label' => 'Beweglichkeit', 'sort_order' => 1],
            ['category' => 'GOAL', 'key' => 'STRENGTH', 'label' => 'Kraft', 'sort_order' => 2],
            ['category' => 'GOAL', 'key' => 'RELAXATION', 'label' => 'Entspannung', 'sort_order' => 3],
            ['category' => 'GOAL', 'key' => 'STRESS_REDUCTION', 'label' => 'Stressabbau', 'sort_order' => 4],
            ['category' => 'GOAL', 'key' => 'POSTURE', 'label' => 'Haltung', 'sort_order' => 5],
            ['category' => 'GOAL', 'key' => 'PAIN_PREVENTION', 'label' => 'Schmerzprävention', 'sort_order' => 6],
            ['category' => 'GOAL', 'key' => 'ACTIVATION', 'label' => 'Aktivierung', 'sort_order' => 7],

            // SETTING
            ['category' => 'SETTING', 'key' => 'DESK', 'label' => 'Am Schreibtisch', 'sort_order' => 1],
            ['category' => 'SETTING', 'key' => 'HOME', 'label' => 'Zuhause', 'sort_order' => 2],
            ['category' => 'SETTING', 'key' => 'OFFICE', 'label' => 'Büro', 'sort_order' => 3],
            ['category' => 'SETTING', 'key' => 'ONSITE', 'label' => 'Vor Ort', 'sort_order' => 4],
            ['category' => 'SETTING', 'key' => 'GYM', 'label' => 'Fitnessstudio', 'sort_order' => 5],

            // EQUIPMENT
            ['category' => 'EQUIPMENT', 'key' => 'NONE', 'label' => 'Kein Equipment', 'sort_order' => 1],
            ['category' => 'EQUIPMENT', 'key' => 'CHAIR', 'label' => 'Stuhl', 'sort_order' => 2],
            ['category' => 'EQUIPMENT', 'key' => 'MAT', 'label' => 'Matte', 'sort_order' => 3],
            ['category' => 'EQUIPMENT', 'key' => 'RESISTANCE_BAND', 'label' => 'Widerstandsband', 'sort_order' => 4],

            // CONTRAINDICATION
            ['category' => 'CONTRAINDICATION', 'key' => 'ACUTE_BACK_PAIN', 'label' => 'Akute Rückenschmerzen', 'sort_order' => 1],
            ['category' => 'CONTRAINDICATION', 'key' => 'PREGNANCY', 'label' => 'Schwangerschaft', 'sort_order' => 2],
            ['category' => 'CONTRAINDICATION', 'key' => 'HIGH_BLOOD_PRESSURE', 'label' => 'Bluthochdruck', 'sort_order' => 3],

            // PERSONA_HINT
            ['category' => 'PERSONA_HINT', 'key' => 'DESK_WORKER', 'label' => 'Büroarbeit', 'sort_order' => 1],
            ['category' => 'PERSONA_HINT', 'key' => 'ACTIVE_LIFESTYLE', 'label' => 'Aktiver Lebensstil', 'sort_order' => 2],
            ['category' => 'PERSONA_HINT', 'key' => 'SEDENTARY', 'label' => 'Überwiegend sitzend', 'sort_order' => 3],

            // HEALTH_FOCUS
            ['category' => 'HEALTH_FOCUS', 'key' => 'MENTAL_HEALTH', 'label' => 'Mentale Gesundheit', 'sort_order' => 1],
            ['category' => 'HEALTH_FOCUS', 'key' => 'MUSCULOSKELETAL', 'label' => 'Muskuloskelettale Gesundheit', 'sort_order' => 2],
            ['category' => 'HEALTH_FOCUS', 'key' => 'CARDIOVASCULAR', 'label' => 'Herz-Kreislauf', 'sort_order' => 3],
        ];

        foreach ($tags as $tag) {
            SystemExerciseTag::updateOrCreate(
                ['category' => $tag['category'], 'key' => $tag['key']],
                ['label' => $tag['label'], 'sort_order' => $tag['sort_order']],
            );
        }
    }

    private function seedExercises(): void
    {
        $exercises = [
            [
                'slug' => 'seitliche-nackendehnung',
                'title' => 'Seitliche Nackendehnung',
                'short_description' => 'Sanfte Dehnung der seitlichen Nackenmuskulatur.',
                'description' => 'Diese Übung hilft, Verspannungen im Nacken zu lösen, die durch langes Sitzen am Bildschirm entstehen.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 5,
                'default_hold_seconds' => 30,
                'instructions' => 'Setzen Sie sich aufrecht hin. Neigen Sie den Kopf langsam zur rechten Seite, bis Sie eine angenehme Dehnung auf der linken Seite spüren. Halten Sie 30 Sekunden. Wiederholen Sie auf der anderen Seite.',
                'safety_notes' => 'Bei akuten Nackenschmerzen oder Schwindel die Übung abbrechen.',
                'tags' => ['BODY_REGION:NECK', 'GOAL:MOBILITY', 'GOAL:PAIN_PREVENTION', 'SETTING:DESK', 'SETTING:OFFICE', 'EQUIPMENT:NONE', 'PERSONA_HINT:DESK_WORKER'],
            ],
            [
                'slug' => 'brustwirbelsaeulen-mobilisation',
                'title' => 'Brustwirbelsäulen-Mobilisation',
                'short_description' => 'Rotation und Mobilisation der Brustwirbelsäule.',
                'description' => 'Verbessert die Beweglichkeit der Brustwirbelsäule und wirkt der typischen Büro-Rundrückenhaltung entgegen.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 8,
                'default_repetitions' => 10,
                'instructions' => 'Setzen Sie sich seitlich auf einen Stuhl. Legen Sie die Hände hinter den Kopf und drehen Sie den Oberkörper langsam nach rechts und links. 10 Wiederholungen pro Seite.',
                'safety_notes' => 'Bei Bandscheibenvorfällen im Brustbereich vorher ärztlich abklären.',
                'tags' => ['BODY_REGION:BACK', 'BODY_REGION:SHOULDERS', 'GOAL:MOBILITY', 'GOAL:POSTURE', 'SETTING:OFFICE', 'EQUIPMENT:CHAIR'],
            ],
            [
                'slug' => 'hueftbeuger-stretch',
                'title' => 'Hüftbeuger-Stretch',
                'short_description' => 'Dehnung des verkürzten Hüftbeugers.',
                'description' => 'Langes Sitzen verkürzt den Hüftbeuger. Diese Übung dehnt und entspannt ihn gezielt.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 5,
                'default_hold_seconds' => 45,
                'instructions' => 'Knien Sie sich auf ein Knie, das andere Bein steht im 90-Grad-Winkel vor Ihnen. Schieben Sie die Hüfte sanft nach vorne, bis Sie eine Dehnung im Hüftbeuger spüren. 45 Sekunden halten, dann Seite wechseln.',
                'tags' => ['BODY_REGION:HIPS', 'GOAL:MOBILITY', 'SETTING:HOME', 'EQUIPMENT:MAT'],
            ],
            [
                'slug' => 'glute-bridge',
                'title' => 'Glute Bridge',
                'short_description' => 'Kräftigung der Gesäßmuskulatur und des unteren Rückens.',
                'description' => 'Die Glute Bridge aktiviert die Gesäßmuskulatur, die durch langes Sitzen oft abgeschwächt ist, und stabilisiert den unteren Rücken.',
                'exercise_type' => 'STRENGTH',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 10,
                'default_sets' => 3,
                'default_repetitions' => 12,
                'instructions' => 'Legen Sie sich auf den Rücken, Füße hüftbreit aufgestellt. Heben Sie das Becken an, bis Oberschenkel und Oberkörper eine Linie bilden. Kurz halten, dann kontrolliert absenken. 3 Sätze à 12 Wiederholungen.',
                'tags' => ['BODY_REGION:HIPS', 'BODY_REGION:BACK', 'GOAL:STRENGTH', 'SETTING:HOME', 'EQUIPMENT:MAT'],
            ],
            [
                'slug' => 'wandliegestuetz',
                'title' => 'Wandliegestütz',
                'short_description' => 'Leichte Liegestütz-Variante an der Wand.',
                'description' => 'Eine einsteigerfreundliche Liegestütz-Variante, die Brust, Schultern und Arme kräftigt, ohne den Boden zu benötigen.',
                'exercise_type' => 'STRENGTH',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 5,
                'default_sets' => 3,
                'default_repetitions' => 10,
                'instructions' => 'Stellen Sie sich ca. eine Armlänge von der Wand entfernt auf. Hände schulterbreit an die Wand. Beugen Sie die Arme und bringen Sie die Brust zur Wand, dann drücken Sie sich zurück. 3 Sätze à 10 Wiederholungen.',
                'tags' => ['BODY_REGION:SHOULDERS', 'BODY_REGION:FULL_BODY', 'GOAL:STRENGTH', 'SETTING:OFFICE', 'SETTING:HOME', 'EQUIPMENT:NONE'],
            ],
            [
                'slug' => '4-7-8-atemuebung',
                'title' => '4-7-8 Atemübung',
                'short_description' => 'Beruhigende Atemtechnik zur Stressreduktion.',
                'description' => 'Die 4-7-8 Atemtechnik senkt nachweislich den Stresslevel und kann bei Unruhe, Einschlafproblemen und akutem Stress helfen.',
                'exercise_type' => 'BREATHING',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 5,
                'instructions' => 'Atmen Sie 4 Sekunden lang durch die Nase ein. Halten Sie den Atem 7 Sekunden lang an. Atmen Sie 8 Sekunden lang durch den Mund aus. Wiederholen Sie den Zyklus 4 Mal.',
                'default_feedback_prompt' => 'Wie fühlen Sie sich nach der Atemübung?',
                'tags' => ['GOAL:STRESS_REDUCTION', 'GOAL:RELAXATION', 'SETTING:DESK', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
            ],
            [
                'slug' => '2-minuten-body-scan',
                'title' => '2-Minuten Body Scan',
                'short_description' => 'Kurze Achtsamkeitsübung zum Körperspüren.',
                'description' => 'Ein verkürzter Body Scan, der hilft, Anspannung im Körper wahrzunehmen und loszulassen.',
                'exercise_type' => 'MINDFULNESS',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'instructions' => 'Schließen Sie die Augen. Richten Sie Ihre Aufmerksamkeit nacheinander auf Füße, Beine, Becken, Bauch, Brust, Arme, Schultern, Nacken und Kopf. Nehmen Sie Anspannung wahr und lassen Sie sie mit jedem Ausatmen los.',
                'default_feedback_prompt' => 'Welche Körperregion war am meisten angespannt?',
                'tags' => ['GOAL:RELAXATION', 'GOAL:STRESS_REDUCTION', 'SETTING:DESK', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
            ],
            [
                'slug' => 'stress-ausloeser-notieren',
                'title' => 'Stress-Auslöser kurz notieren',
                'short_description' => 'Reflexionsübung zur Identifikation von Stressauslösern.',
                'description' => 'Notieren Sie kurz, was heute Stress ausgelöst hat. Das schriftliche Festhalten hilft, Muster zu erkennen und bewusster mit Belastungen umzugehen.',
                'exercise_type' => 'REFLECTION',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 3,
                'instructions' => 'Nehmen Sie sich 3 Minuten Zeit. Notieren Sie stichpunktartig: Was hat heute Stress ausgelöst? Wie haben Sie reagiert? Was würden Sie beim nächsten Mal anders machen?',
                'default_feedback_prompt' => 'Konnten Sie einen wiederkehrenden Stressauslöser identifizieren?',
                'tags' => ['GOAL:STRESS_REDUCTION', 'SETTING:DESK', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
            ],
            [
                'slug' => 'ergonomie-bildschirmhoehe',
                'title' => 'Ergonomie-Microlearning: Bildschirmhöhe',
                'short_description' => 'Kurzinfo zur korrekten Einstellung der Bildschirmhöhe.',
                'description' => 'Lernen Sie in wenigen Minuten, wie Sie Ihren Bildschirm optimal einstellen, um Nacken- und Augenbeschwerden vorzubeugen.',
                'exercise_type' => 'EDUCATION',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => null,
                'instructions' => 'Die Oberkante des Bildschirms sollte auf Augenhöhe sein. Der Abstand zum Bildschirm sollte ca. 50-70 cm betragen. Der Blickwinkel sollte leicht nach unten geneigt sein (ca. 15-20 Grad). Stellen Sie Ihren Bildschirm jetzt entsprechend ein.',
                'tags' => ['GOAL:POSTURE', 'GOAL:PAIN_PREVENTION', 'SETTING:DESK', 'SETTING:OFFICE', 'EQUIPMENT:NONE', 'PERSONA_HINT:DESK_WORKER', 'HEALTH_FOCUS:MUSCULOSKELETAL'],
            ],
        ];

        foreach ($exercises as $data) {
            $tagKeys = $data['tags'] ?? [];
            unset($data['tags']);

            $exercise = SystemExercise::updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );

            if (! empty($tagKeys)) {
                $tagIds = [];
                foreach ($tagKeys as $tagKey) {
                    [$category, $key] = explode(':', $tagKey);
                    $tag = SystemExerciseTag::where('category', $category)->where('key', $key)->first();
                    if ($tag) {
                        $tagIds[] = $tag->id;
                    }
                }
                $exercise->tags()->syncWithoutDetaching($tagIds);
            }
        }
    }

    private function seedTemplates(): void
    {
        $templates = [
            [
                'slug' => 'ruecken-mobilitaet-bueroarbeit',
                'title' => 'Rücken-Mobilität für Büroarbeit',
                'short_description' => 'Gezieltes Programm für einen beweglichen Rücken am Arbeitsplatz.',
                'description' => 'Dieses Programm kombiniert Mobilisations- und Kräftigungsübungen, um typischen Büro-Rückenbeschwerden vorzubeugen und die Wirbelsäulenbeweglichkeit zu verbessern.',
                'goal_summary' => 'Rückenmobilität verbessern und Schmerzen vorbeugen',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 20,
                'recommended_frequency' => 'DAILY',
                'default_points' => 10,
                'exercises' => [
                    ['slug' => 'ergonomie-bildschirmhoehe', 'position' => 1, 'is_required' => true],
                    ['slug' => 'brustwirbelsaeulen-mobilisation', 'position' => 2, 'is_required' => true],
                    ['slug' => 'hueftbeuger-stretch', 'position' => 3, 'is_required' => true],
                    ['slug' => 'glute-bridge', 'position' => 4, 'is_required' => false],
                ],
            ],
            [
                'slug' => 'nacken-schulter-entlastung',
                'title' => 'Nacken & Schulter Entlastung',
                'short_description' => 'Schnelle Entlastung für Nacken und Schultern.',
                'description' => 'Gezielte Übungen zur Lösung von Verspannungen im Nacken- und Schulterbereich, ideal für zwischendurch am Arbeitsplatz.',
                'goal_summary' => 'Nacken- und Schulterverspannungen lösen',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 15,
                'recommended_frequency' => 'DAILY',
                'default_points' => 10,
                'exercises' => [
                    ['slug' => 'seitliche-nackendehnung', 'position' => 1, 'is_required' => true],
                    ['slug' => 'brustwirbelsaeulen-mobilisation', 'position' => 2, 'is_required' => true],
                    ['slug' => 'wandliegestuetz', 'position' => 3, 'is_required' => false],
                ],
            ],
            [
                'slug' => '5-minuten-stress-reset',
                'title' => '5-Minuten Stress Reset',
                'short_description' => 'Schnelle Stressreduktion in 5 Minuten.',
                'description' => 'Ein kompaktes Programm aus Atemübung und Achtsamkeit, das in jede Pause passt und nachweislich den Stresslevel senkt.',
                'goal_summary' => 'Akuten Stress schnell reduzieren',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 7,
                'recommended_frequency' => 'DAILY',
                'default_points' => 5,
                'exercises' => [
                    ['slug' => '4-7-8-atemuebung', 'position' => 1, 'is_required' => true],
                    ['slug' => '2-minuten-body-scan', 'position' => 2, 'is_required' => true],
                ],
            ],
            [
                'slug' => 'einsteiger-kraft-haltung',
                'title' => 'Einsteiger Kraft & Haltung',
                'short_description' => 'Grundlegendes Kraft- und Haltungsprogramm.',
                'description' => 'Einfache Kräftigungsübungen für Einsteiger, die die Haltung verbessern und den Bewegungsapparat stärken.',
                'goal_summary' => 'Muskuläre Basis aufbauen und Haltung verbessern',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 20,
                'recommended_frequency' => 'WEEKLY',
                'default_points' => 15,
                'exercises' => [
                    ['slug' => 'wandliegestuetz', 'position' => 1, 'is_required' => true],
                    ['slug' => 'glute-bridge', 'position' => 2, 'is_required' => true],
                    ['slug' => 'hueftbeuger-stretch', 'position' => 3, 'is_required' => true],
                ],
            ],
            [
                'slug' => 'kurze-aktive-pause',
                'title' => 'Kurze aktive Pause im Büro',
                'short_description' => 'Bewegte Kurzpause für mehr Energie.',
                'description' => 'Eine Kombination aus Mobilisation, leichter Kräftigung und Atemübung, die sich perfekt in den Büroalltag integrieren lässt.',
                'goal_summary' => 'Energie tanken und Verspannungen lösen',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 10,
                'recommended_frequency' => 'DAILY',
                'default_points' => 5,
                'exercises' => [
                    ['slug' => 'seitliche-nackendehnung', 'position' => 1, 'is_required' => true],
                    ['slug' => 'wandliegestuetz', 'position' => 2, 'is_required' => false],
                    ['slug' => '4-7-8-atemuebung', 'position' => 3, 'is_required' => true],
                ],
            ],
        ];

        foreach ($templates as $data) {
            $exerciseLinks = $data['exercises'] ?? [];
            unset($data['exercises']);

            $template = SystemMeasureTemplate::updateOrCreate(
                ['slug' => $data['slug']],
                $data,
            );

            foreach ($exerciseLinks as $link) {
                $exercise = SystemExercise::where('slug', $link['slug'])->first();
                if (! $exercise) {
                    continue;
                }

                SystemMeasureTemplateExercise::updateOrCreate(
                    ['system_measure_template_id' => $template->id, 'position' => $link['position']],
                    [
                        'system_exercise_id' => $exercise->id,
                        'is_required' => $link['is_required'] ?? true,
                    ],
                );
            }
        }
    }
}
