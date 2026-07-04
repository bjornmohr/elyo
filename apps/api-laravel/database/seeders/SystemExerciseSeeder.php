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
                'default_sets' => 3,
                'main_pictogram_path' => 'pictograms/nacken-mobilitaet/nacken-dehnung-seitlich/main.svg',
                'main_pictogram_alt' => 'Strichfigur neigt den Kopf zur Seite, ein Pfeil zeigt die Dehnrichtung an.',
                'steps' => [
                    ['text' => 'Aufrecht hinsetzen oder hinstellen, Schultern bewusst senken.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Kopf langsam zur Seite neigen, bis eine sanfte Dehnung spürbar ist.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => '30 Sekunden halten, dann die Seite wechseln.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['office', 'plant', 'home'],
                'posture_tags' => ['sitting', 'standing'],
                'requires_floor' => false,
                'default_effort' => 2,
                'tags' => ['BODY_REGION:NECK', 'GOAL:MOBILITY', 'GOAL:PAIN_PREVENTION', 'SETTING:DESK', 'SETTING:OFFICE', 'EQUIPMENT:NONE', 'PERSONA_HINT:DESK_WORKER'],
            ],
            [
                'slug' => 'schulterkreisen',
                'title' => 'Schulterkreisen',
                'short_description' => 'Lockert Nacken- und Schulterbereich durch kontrolliertes Kreisen.',
                'description' => 'Löst Verspannungen im Nacken- und Schulterbereich und fördert die Durchblutung.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'default_sets' => 2,
                'default_repetitions' => 15,
                'instructions' => 'Aufrecht hinstellen, Arme locker hängen lassen. Schultern langsam und groß nach hinten kreisen, dann die Richtung wechseln.',
                'safety_notes' => 'Sanft bleiben — kein Ziehen oder Schmerz.',
                'main_pictogram_path' => 'pictograms/nacken-mobilitaet/schulterkreisen/main.svg',
                'main_pictogram_alt' => 'Strichfigur steht aufrecht, zwei orangefarbene Rotationspfeile zeigen kreisende Schultern an.',
                'steps' => [
                    ['text' => 'Aufrecht hinstellen, Arme locker hängen lassen.', 'pictogram_path' => 'pictograms/nacken-mobilitaet/schulterkreisen/step-1.svg', 'alt' => 'Aufrecht stehende Strichfigur mit locker hängenden Armen.'],
                    ['text' => 'Schultern langsam nach hinten kreisen — groß und kontrolliert.', 'pictogram_path' => 'pictograms/nacken-mobilitaet/schulterkreisen/step-2.svg', 'alt' => 'Strichfigur mit Rotationspfeil an der rechten Schulter.'],
                    ['text' => 'Ruhig weiteratmen, 15 Wiederholungen, dann Richtung wechseln.', 'pictogram_path' => 'pictograms/nacken-mobilitaet/schulterkreisen/step-3.svg', 'alt' => 'Strichfigur mit Rotationspfeil in Gegenrichtung an der linken Schulter.'],
                ],
                'posture_tags' => ['standing'],
                'requires_floor' => false,
                'default_effort' => 2,
                'tags' => ['BODY_REGION:NECK', 'BODY_REGION:SHOULDERS', 'GOAL:MOBILITY', 'SETTING:OFFICE', 'EQUIPMENT:NONE'],
            ],
            [
                'slug' => 'kinn-retraktion',
                'title' => 'Kinn-Retraktion',
                'short_description' => 'Kräftigt die tiefe Nackenmuskulatur und richtet den Kopf auf.',
                'description' => 'Die Kinn-Retraktion wirkt der typischen vorgeschobenen Kopfhaltung am Bildschirm entgegen.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'default_sets' => 3,
                'default_repetitions' => 10,
                'instructions' => 'Aufrecht sitzen oder stehen. Kinn gerade nach hinten schieben (Doppelkinn machen), kurz halten, langsam lösen.',
                'safety_notes' => 'Bewegung klein und schmerzfrei halten.',
                'main_pictogram_path' => 'pictograms/nacken-mobilitaet/kinn-retraktion/main.svg',
                'main_pictogram_alt' => 'Strichfigur mit Pfeil, der das Kinn gerade nach hinten schiebt.',
                'steps' => [
                    ['text' => 'Aufrecht hinsetzen oder hinstellen, Blick geradeaus.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Kinn gerade nach hinten schieben, ohne den Kopf zu neigen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Kurz halten, langsam lösen — 10 Wiederholungen.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['office', 'plant', 'home'],
                'posture_tags' => ['sitting', 'standing'],
                'requires_floor' => false,
                'default_effort' => 1,
                'tags' => ['BODY_REGION:NECK', 'GOAL:POSTURE', 'GOAL:PAIN_PREVENTION', 'SETTING:DESK', 'SETTING:OFFICE', 'EQUIPMENT:NONE'],
            ],
            [
                'slug' => 'oberer-trapez-stretch',
                'title' => 'Oberer Trapez-Stretch',
                'short_description' => 'Dehnt den oberen Trapezmuskel zwischen Nacken und Schulter.',
                'description' => 'Gezielte Dehnung des oberen Trapezmuskels, der bei Bildschirmarbeit häufig verspannt.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'default_sets' => 2,
                'default_hold_seconds' => 30,
                'instructions' => 'Eine Hand über den Kopf an die gegenüberliegende Schläfe legen. Kopf sanft zur Seite ziehen, 30 Sekunden halten, Seite wechseln.',
                'safety_notes' => 'Nur sanft ziehen, kein Schmerz.',
                'main_pictogram_path' => 'pictograms/nacken-mobilitaet/oberer-trapez-stretch/main.svg',
                'main_pictogram_alt' => 'Strichfigur zieht den geneigten Kopf mit dem Arm sanft zur Seite.',
                'steps' => [
                    ['text' => 'Aufrecht hinstellen, eine Hand über den Kopf zur Gegenseite führen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Kopf sanft Richtung Schulter ziehen, bis es dehnt.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => '30 Sekunden halten, dann Seite wechseln.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['office', 'plant', 'home'],
                'posture_tags' => ['standing', 'sitting'],
                'requires_floor' => false,
                'default_effort' => 2,
                'tags' => ['BODY_REGION:NECK', 'BODY_REGION:SHOULDERS', 'GOAL:MOBILITY', 'SETTING:OFFICE', 'EQUIPMENT:NONE'],
            ],
            [
                'slug' => 'digital-sunset',
                'title' => 'Digital Sunset',
                'short_description' => 'Bildschirmfreie Zeit vor dem Einschlafen.',
                'description' => 'Bildschirme 30 Minuten vor dem Schlafengehen bewusst weglegen — das blaue Licht verzögert sonst die Melatonin-Ausschüttung.',
                'exercise_type' => 'MINDFULNESS',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'instructions' => 'Handy und Laptop 30 Minuten vor dem Zubettgehen weglegen. Stattdessen: lesen, Musik hören oder die Abenddehnung machen.',
                'main_pictogram_path' => 'pictograms/abend-routine-schlaf/digital-sunset/main.svg',
                'main_pictogram_alt' => 'Durchgestrichenes Smartphone neben einem Mond-Symbol.',
                'steps' => [
                    ['text' => 'Erinnerung 30 Minuten vor dem Zubettgehen stellen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Alle Bildschirme weglegen oder in den Nicht-stören-Modus schalten.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Eine ruhige Alternative wählen: lesen, Musik, Dehnung.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['home'],
                'requires_floor' => false,
                'default_effort' => 1,
                'tags' => ['GOAL:RELAXATION', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
            ],
            [
                'slug' => 'leichte-abenddehnung',
                'title' => 'Leichte Abenddehnung',
                'short_description' => 'Sanfte Ganzkörper-Dehnung zum Runterfahren.',
                'description' => 'Eine kurze, sanfte Dehnfolge, die den Körper aufs Schlafen vorbereitet und Anspannung des Tages löst.',
                'exercise_type' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 4,
                'instructions' => 'Im Stehen langsam nach vorne beugen und die Arme hängen lassen. Ein paar tiefe Atemzüge, dann Wirbel für Wirbel aufrichten.',
                'safety_notes' => 'Knie leicht gebeugt lassen, keine ruckartigen Bewegungen.',
                'main_pictogram_path' => 'pictograms/abend-routine-schlaf/leichte-abenddehnung/main.svg',
                'main_pictogram_alt' => 'Strichfigur in sanfter Vorbeuge mit Bewegungspfeil nach unten.',
                'steps' => [
                    ['text' => 'Hüftbreit hinstellen, Knie leicht beugen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Langsam nach vorne rollen, Arme hängen lassen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Drei tiefe Atemzüge, dann Wirbel für Wirbel aufrichten.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['home'],
                'posture_tags' => ['standing'],
                'requires_floor' => false,
                'default_effort' => 2,
                'tags' => ['BODY_REGION:FULL_BODY', 'GOAL:RELAXATION', 'GOAL:MOBILITY', 'SETTING:HOME', 'EQUIPMENT:NONE'],
            ],
            [
                'slug' => 'body-scan-einschlafen',
                'title' => 'Body Scan zum Einschlafen',
                'short_description' => 'Achtsamkeitsübung im Liegen zum Einschlafen.',
                'description' => 'Ein ruhiger Body Scan im Bett, der die Aufmerksamkeit durch den Körper wandern lässt und das Einschlafen erleichtert.',
                'exercise_type' => 'MINDFULNESS',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 3,
                'instructions' => 'Bequem hinlegen, Augen schließen. Aufmerksamkeit langsam von den Füßen bis zum Kopf wandern lassen und mit jedem Ausatmen loslassen.',
                'main_pictogram_path' => 'pictograms/abend-routine-schlaf/body-scan-einschlafen/main.svg',
                'main_pictogram_alt' => 'Liegende Strichfigur mit Entspannungswellen und Schlaf-Symbol.',
                'steps' => [
                    ['text' => 'Bequem auf den Rücken legen, Augen schließen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Aufmerksamkeit langsam von den Füßen zum Kopf wandern lassen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Mit jedem Ausatmen bewusst loslassen.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['home'],
                'requires_floor' => false,
                'default_effort' => 1,
                'tags' => ['GOAL:RELAXATION', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
            ],
            [
                'slug' => 'box-breathing',
                'title' => 'Box Breathing',
                'short_description' => 'Gleichmäßige 4-4-4-4-Atmung zur schnellen Beruhigung.',
                'description' => 'Box Breathing strukturiert die Atmung in vier gleich lange Phasen und unterstützt eine bewusste Stressregulation zwischendurch.',
                'exercise_type' => 'BREATHING',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'default_sets' => 4,
                'instructions' => '4 Sekunden einatmen, 4 Sekunden halten, 4 Sekunden ausatmen, 4 Sekunden halten. 4 Runden wiederholen.',
                'default_feedback_prompt' => 'Wie fühlen Sie sich nach der Atemübung?',
                'main_pictogram_path' => 'pictograms/atem-balance/box-breathing/main.svg',
                'main_pictogram_alt' => 'Quadrat mit vier umlaufenden Pfeilen für die vier Atemphasen.',
                'steps' => [
                    ['text' => '4 Sekunden durch die Nase einatmen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => '4 Sekunden den Atem halten, 4 Sekunden ausatmen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => '4 Sekunden halten — dann die nächste Runde, insgesamt 4.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['office', 'home', 'plant', 'onroad'],
                'posture_tags' => ['sitting', 'standing'],
                'requires_floor' => false,
                'default_effort' => 1,
                'tags' => ['GOAL:STRESS_REDUCTION', 'GOAL:RELAXATION', 'SETTING:DESK', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
            ],
            [
                'slug' => 'mini-body-scan',
                'title' => 'Mini Body Scan',
                'short_description' => 'Kurzer Achtsamkeits-Check im Sitzen.',
                'description' => 'Ein zweiminütiger Body Scan für zwischendurch, der Anspannung sichtbar macht und löst.',
                'exercise_type' => 'MINDFULNESS',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 2,
                'instructions' => 'Aufrecht hinsetzen, Augen schließen. Aufmerksamkeit kurz durch Schultern, Nacken und Kiefer wandern lassen und bewusst lockern.',
                'main_pictogram_path' => 'pictograms/atem-balance/mini-body-scan/main.svg',
                'main_pictogram_alt' => 'Sitzende Strichfigur im Schneidersitz mit Scan-Linien darunter.',
                'steps' => [
                    ['text' => 'Aufrecht hinsetzen, Augen schließen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Schultern, Nacken und Kiefer nacheinander wahrnehmen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => 'Mit jedem Ausatmen die Region bewusst lockern.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['office', 'home', 'plant', 'onroad'],
                'posture_tags' => ['sitting'],
                'requires_floor' => false,
                'default_effort' => 1,
                'tags' => ['GOAL:RELAXATION', 'GOAL:STRESS_REDUCTION', 'SETTING:DESK', 'SETTING:HOME', 'EQUIPMENT:NONE', 'HEALTH_FOCUS:MENTAL_HEALTH'],
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
                'description' => 'Die 4-7-8 Atemtechnik ist als kurze Orientierung für bewusste Stressregulation gedacht und kann zur kurzen Entlastung beitragen.',
                'exercise_type' => 'BREATHING',
                'difficulty' => 'BEGINNER',
                'default_duration_minutes' => 5,
                'instructions' => 'Atmen Sie 4 Sekunden lang durch die Nase ein. Halten Sie den Atem 7 Sekunden lang an. Atmen Sie 8 Sekunden lang durch den Mund aus. Wiederholen Sie den Zyklus 4 Mal.',
                'default_feedback_prompt' => 'Wie fühlen Sie sich nach der Atemübung?',
                'main_pictogram_path' => 'pictograms/atem-balance/4-7-8-atmung/main.svg',
                'main_pictogram_alt' => 'Atemkreis mit Pfeilen für Ein- und Ausatmen.',
                'steps' => [
                    ['text' => '4 Sekunden durch die Nase einatmen.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => '7 Sekunden den Atem anhalten.', 'pictogram_path' => null, 'alt' => null],
                    ['text' => '8 Sekunden durch den Mund ausatmen — 4 Zyklen.', 'pictogram_path' => null, 'alt' => null],
                ],
                'location_tags' => ['office', 'home', 'plant', 'onroad'],
                'posture_tags' => ['sitting', 'standing'],
                'requires_floor' => false,
                'default_effort' => 1,
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
                $exercise->tags()->sync($tagIds);
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
                'description' => 'Ein kompaktes Programm aus Atemübung und Achtsamkeit, das in jede Pause passt und eine bewusste Unterbrechung unterstützt.',
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
            [
                'slug' => 'nacken-mobilitaet',
                'title' => 'Nacken-Mobilität',
                'short_description' => 'Gezieltes Mobilitätsprogramm gegen Nackenschmerzen.',
                'description' => 'Vier kurze Übungen, die Verspannungen im Nacken- und Schulterbereich lösen und die Beweglichkeit verbessern — ideal für zwischendurch im Büro oder Werk.',
                'goal_summary' => 'Nackenschmerzen reduzieren und Beweglichkeit verbessern',
                'category' => 'MOBILITY',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 10,
                'recommended_frequency' => 'DAILY',
                'default_points' => 5,
                'target_signal' => 'neck_pain',
                'assignment_reason_template' => 'aus Check-in „Nackenschmerzen“',
                'effect_metric' => 'pain',
                'effect_metric_unit' => 'nrs_0_10',
                'location_tags' => ['office', 'plant'],
                'posture_tags' => ['standing'],
                'requires_floor' => false,
                'exercises' => [
                    ['slug' => 'seitliche-nackendehnung', 'position' => 1, 'is_required' => true],
                    ['slug' => 'schulterkreisen', 'position' => 2, 'is_required' => true],
                    ['slug' => 'kinn-retraktion', 'position' => 3, 'is_required' => true],
                    ['slug' => 'oberer-trapez-stretch', 'position' => 4, 'is_required' => false],
                ],
            ],
            [
                'slug' => 'abend-routine-schlaf',
                'title' => 'Abend-Routine für besseren Schlaf',
                'short_description' => 'Drei Schritte zum Runterfahren am Abend.',
                'description' => 'Eine kurze Abendroutine aus bildschirmfreier Zeit, sanfter Dehnung und Body Scan, die das Einschlafen erleichtert und die Schlafdauer verbessert.',
                'goal_summary' => 'Schlafqualität und Schlafdauer verbessern',
                'category' => 'MINDFULNESS',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 8,
                'recommended_frequency' => 'DAILY',
                'default_points' => 5,
                'target_signal' => 'sleep',
                'assignment_reason_template' => 'aus Check-in „schlechter Schlaf“',
                'effect_metric' => 'sleep_hours',
                'effect_metric_unit' => 'hours',
                'location_tags' => ['home'],
                'requires_floor' => false,
                'exercises' => [
                    ['slug' => 'digital-sunset', 'position' => 1, 'is_required' => true],
                    ['slug' => 'leichte-abenddehnung', 'position' => 2, 'is_required' => true],
                    ['slug' => 'body-scan-einschlafen', 'position' => 3, 'is_required' => true],
                ],
            ],
            [
                'slug' => 'atem-balance',
                'title' => 'Atem-Balance',
                'short_description' => 'Atemübungen zur Stressregulation für zwischendurch.',
                'description' => 'Drei kurze Atem- und Achtsamkeitsübungen, die überall funktionieren und akuten Stress spürbar senken.',
                'goal_summary' => 'Stress im Alltag schnell regulieren',
                'category' => 'BREATHING',
                'difficulty' => 'BEGINNER',
                'estimated_duration_minutes' => 6,
                'recommended_frequency' => 'DAILY',
                'default_points' => 5,
                'target_signal' => 'stress',
                'assignment_reason_template' => 'aus Check-in „hoher Stress“',
                'effect_metric' => 'stress',
                'effect_metric_unit' => 'scale_1_5',
                'location_tags' => ['office', 'home', 'plant', 'onroad'],
                'posture_tags' => ['sitting', 'standing'],
                'requires_floor' => false,
                'exercises' => [
                    ['slug' => '4-7-8-atemuebung', 'position' => 1, 'is_required' => true],
                    ['slug' => 'box-breathing', 'position' => 2, 'is_required' => true],
                    ['slug' => 'mini-body-scan', 'position' => 3, 'is_required' => true],
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
