<?php

namespace Tests\Feature\Privacy;

use App\Models\Health\HealthSubject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EnforceRetentionDeletionTest extends TestCase
{
    protected $connectionsToTransact = ['identity', 'mapping', 'health'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::connection('audit_migrator')->table('audit_events')->delete();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        DB::connection('audit_migrator')->table('audit_events')->delete();

        parent::tearDown();
    }

    public function test_retention_command_defaults_to_count_only_dry_run_without_identifiers(): void
    {
        $subjectId = $this->createSubject();
        $this->insertWellbeingEntry($subjectId, now()->subMonths(25), 'old');
        $this->insertWellbeingEntry($subjectId, now(), 'current');

        $exitCode = Artisan::call('elyo:enforce-retention');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Wellbeing entries', $output);
        $this->assertStringContainsString('24 months', $output);
        $this->assertStringContainsString('PROPOSED', $output);
        $this->assertStringNotContainsString($subjectId, $output);
        $this->assertSame(2, DB::connection('health')->table('wellbeing_entries')->count());
    }

    public function test_retention_command_deletes_expired_rows_and_files_only_with_execute(): void
    {
        $subjectId = $this->createSubject();
        $oldBlobKey = "employee-documents/{$subjectId}/old.pdf";
        $currentBlobKey = "employee-documents/{$subjectId}/current.pdf";
        $this->insertWellbeingEntry($subjectId, now()->subMonths(25), 'old');
        $this->insertWellbeingEntry($subjectId, now(), 'current');
        $this->insertUserDocument($subjectId, $oldBlobKey, now()->subMonths(25));
        $this->insertUserDocument($subjectId, $currentBlobKey, now());
        Storage::disk('public')->put($oldBlobKey, 'old');
        Storage::disk('public')->put($currentBlobKey, 'current');

        $exitCode = Artisan::call('elyo:enforce-retention', ['--execute' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('EXECUTE', $output);
        $this->assertStringNotContainsString($subjectId, $output);
        $this->assertSame(1, DB::connection('health')->table('wellbeing_entries')->count());
        $this->assertSame(1, DB::connection('health')->table('user_documents')->count());
        Storage::disk('public')->assertMissing($oldBlobKey);
        Storage::disk('public')->assertExists($currentBlobKey);
    }

    public function test_retention_command_deletes_expired_documents_across_chunk_boundaries(): void
    {
        $subjectId = $this->createSubject();
        $expiredKeys = [];

        for ($index = 0; $index < 501; $index++) {
            $blobKey = "employee-documents/{$subjectId}/expired-{$index}.pdf";
            $expiredKeys[] = $blobKey;
            $this->insertUserDocument($subjectId, $blobKey, now()->subMonths(25));
            Storage::disk('public')->put($blobKey, 'expired');
        }

        $this->assertSame(0, Artisan::call('elyo:enforce-retention', ['--execute' => true]));
        $this->assertStringContainsString('deleted 501', Artisan::output());
        $this->assertSame(0, DB::connection('health')->table('user_documents')->count());

        foreach ($expiredKeys as $blobKey) {
            Storage::disk('public')->assertMissing($blobKey);
        }
    }

    public function test_retention_command_leaves_no_metadata_for_a_deleted_file_when_a_later_file_fails(): void
    {
        $subjectId = $this->createSubject();
        $firstKey = "employee-documents/{$subjectId}/first.pdf";
        $failingKey = "employee-documents/{$subjectId}/failing.pdf";
        $this->insertUserDocument($subjectId, $firstKey, now()->subMonths(25));
        $this->insertUserDocument($subjectId, $failingKey, now()->subMonths(25));

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturnTrue();
        $disk->shouldReceive('delete')->with($firstKey)->andReturnTrue();
        $disk->shouldReceive('delete')->with($failingKey)->andReturnFalse();
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);

        try {
            Artisan::call('elyo:enforce-retention', ['--execute' => true]);
            $this->fail('A failing file deletion must abort the run.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Health document file deletion failed.', $exception->getMessage());
        }

        $documents = DB::connection('health')->table('user_documents')->pluck('blob_key');

        $this->assertSame([$failingKey], $documents->all());
    }

    private function createSubject(): string
    {
        $subjectId = (string) Str::ulid();

        DB::connection('health')->table('health_subjects')->insert([
            'id' => $subjectId,
            'status' => HealthSubject::STATUS_ACTIVE,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $subjectId;
    }

    private function insertWellbeingEntry(string $subjectId, mixed $createdAt, string $suffix): void
    {
        DB::connection('health')->table('wellbeing_entries')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'mood' => 3,
            'stress' => 3,
            'energy' => 3,
            'score' => 3,
            'period_key' => "{$createdAt->toDateString()}-{$suffix}",
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function insertUserDocument(string $subjectId, string $blobKey, mixed $uploadedAt): void
    {
        DB::connection('health')->table('user_documents')->insert([
            'id' => (string) Str::ulid(),
            'health_subject_id' => $subjectId,
            'file_name' => 'document.pdf',
            'blob_url' => "/storage/{$blobKey}",
            'blob_key' => $blobKey,
            'mime_type' => 'application/pdf',
            'size' => 4,
            'uploaded_at' => $uploadedAt,
            'created_at' => $uploadedAt,
            'updated_at' => $uploadedAt,
        ]);
    }
}
