<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EnforceRetention extends Command
{
    private const DOCUMENT_CHUNK_SIZE = 500;

    protected $signature = 'elyo:enforce-retention
                            {--execute : Delete rows beyond the proposed retention periods}';

    protected $description = 'Inventory data beyond the ELYO retention matrix (dry-run by default)';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $rows = [];

        $this->components->info($execute
            ? 'EXECUTE: deleting data beyond retention periods.'
            : 'DRY RUN: counting data only; nothing will be deleted.');

        foreach ($this->policies() as $policy) {
            $queryFactory = $policy['query'];
            $overRetention = $queryFactory === null ? 0 : $queryFactory()->count();
            $action = 'retained';

            if ($queryFactory !== null) {
                if (! $execute) {
                    $action = 'would delete';
                } elseif ($policy['deletes_files']) {
                    $action = 'deleted '.$this->deleteDocuments($queryFactory);
                } else {
                    $action = 'deleted '.$queryFactory()->delete();
                }
            }

            $rows[] = [
                $policy['category'],
                $policy['period'],
                $policy['status'],
                $overRetention,
                $action,
            ];
        }

        $this->table(
            ['Category', 'Period', 'Status', 'Over-retention', 'Action'],
            $rows,
        );

        if (! $execute) {
            $this->components->warn('Use --execute for explicit deletion. Scheduler remains disabled while periods are PROPOSED.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array{
     *     category: string,
     *     period: string,
     *     status: string,
     *     query: null|\Closure(): Builder,
     *     deletes_files: bool
     * }>
     */
    private function policies(): array
    {
        $healthCutoff = now()->subMonths(24);
        $wearableConnectionCutoff = now()->subDays(90);
        $auditCutoff = now()->subYears(2);

        return [
            [
                'category' => 'Wellbeing entries',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('wellbeing_entries')
                    ->where('created_at', '<', $healthCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Lab readings',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('lab_marker_readings')
                    ->where('measured_at', '<', $healthCutoff->toDateString()),
                'deletes_files' => false,
            ],
            [
                'category' => 'Lab catalog',
                'period' => 'No expiry (non-personal)',
                'status' => 'DECIDED',
                'query' => null,
                'deletes_files' => false,
            ],
            [
                'category' => 'Anamnesis profiles',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('anamnesis_profiles')
                    ->where('updated_at', '<', $healthCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Health document metadata',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('health_documents')
                    ->where('uploaded_at', '<', $healthCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Health document files',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('user_documents')
                    ->where('uploaded_at', '<', $healthCutoff),
                'deletes_files' => true,
            ],
            [
                'category' => 'Inactive wearable connections',
                'period' => '90 days',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('wearable_connections')
                    ->where('is_active', false)
                    ->where('updated_at', '<', $wearableConnectionCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Wearable syncs',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('health')
                    ->table('wearable_syncs')
                    ->where('date', '<', $healthCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Audit events',
                'period' => '2 years',
                'status' => 'DECIDED',
                'query' => fn (): Builder => DB::connection('audit_migrator')
                    ->table('audit_events')
                    ->where('occurred_at', '<', $auditCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Subject mapping tombstones',
                'period' => 'No expiry',
                'status' => 'DECIDED',
                'query' => null,
                'deletes_files' => false,
            ],
            [
                'category' => 'Point transactions',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('identity')
                    ->table('point_transactions')
                    ->where('created_at', '<', $healthCutoff),
                'deletes_files' => false,
            ],
            [
                'category' => 'Points and streaks',
                'period' => '24 months',
                'status' => 'PROPOSED',
                'query' => fn (): Builder => DB::connection('identity')
                    ->table('user_points')
                    ->where('updated_at', '<', $healthCutoff),
                'deletes_files' => false,
            ],
        ];
    }

    /**
     * Deletes each expired file immediately before its own metadata row, so an
     * interrupted run never leaves metadata pointing at a missing blob and the
     * remaining rows stay eligible for the next run. Chunked because the first
     * enforcement run can cover the entire upload history.
     *
     * @param  \Closure(): Builder  $queryFactory
     */
    private function deleteDocuments(\Closure $queryFactory): int
    {
        $deleted = 0;

        while (true) {
            $documents = $queryFactory()
                ->orderBy('id')
                ->limit(self::DOCUMENT_CHUNK_SIZE)
                ->get(['id', 'blob_key']);

            if ($documents->isEmpty()) {
                return $deleted;
            }

            $deletedInChunk = 0;

            foreach ($documents as $document) {
                if (
                    Storage::disk('public')->exists($document->blob_key)
                    && ! Storage::disk('public')->delete($document->blob_key)
                ) {
                    throw new RuntimeException('Health document file deletion failed.');
                }

                $deletedInChunk += DB::connection('health')
                    ->table('user_documents')
                    ->where('id', $document->id)
                    ->delete();
            }

            if ($deletedInChunk === 0) {
                throw new RuntimeException('Expired health documents could not be deleted; aborting to avoid a retry loop.');
            }

            $deleted += $deletedInChunk;
        }
    }
}
