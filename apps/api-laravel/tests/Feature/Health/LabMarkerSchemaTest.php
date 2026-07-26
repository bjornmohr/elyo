<?php

namespace Tests\Feature\Health;

use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class LabMarkerSchemaTest extends TestCase
{
    public function test_lab_readings_schema_is_health_subject_only_and_history_capable(): void
    {
        $schema = Schema::connection('health');

        $this->assertTrue($schema->hasColumn('lab_marker_readings', 'health_subject_id'));
        $this->assertFalse($schema->hasColumn('lab_marker_readings', 'user_id'));
        $this->assertFalse($schema->hasColumn('lab_marker_readings', 'status'));
        $this->assertTrue($schema->hasColumn('lab_marker_readings', 'measured_at'));

        $historyIndex = collect($schema->getIndexes('lab_marker_readings'))
            ->firstWhere('name', 'lab_readings_subject_marker_measured_index');

        $this->assertNotNull($historyIndex);
        $this->assertSame(
            ['health_subject_id', 'marker_key', 'measured_at'],
            $historyIndex['columns'],
        );
        $this->assertFalse($historyIndex['unique']);

        $subjectForeignKey = collect($schema->getForeignKeys('lab_marker_readings'))
            ->firstWhere('foreign_table', 'health_subjects');

        $this->assertNotNull($subjectForeignKey);
        $this->assertSame(
            'cascade',
            $subjectForeignKey['on_delete'],
            'Readings follow their health subject, like every other health table.',
        );
    }

    public function test_lab_marker_catalog_avoids_the_reserved_group_column_name(): void
    {
        $schema = Schema::connection('health');

        $this->assertTrue($schema->hasColumn('lab_markers', 'marker_group'));
        $this->assertFalse($schema->hasColumn('lab_markers', 'group'));
    }

    public function test_production_code_does_not_reference_the_demo_lab_marker_model(): void
    {
        $violations = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname());
            if ($contents !== false && preg_match('/App\\\\Models\\\\LabMarker(?!\\\\)/', $contents) === 1) {
                $violations[] = $file->getPathname();
            }
        }

        $this->assertSame([], $violations, 'Demo LabMarker model references found.');
    }
}
