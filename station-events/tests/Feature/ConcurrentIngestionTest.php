<?php

namespace Tests\Feature;

use App\Models\TransferEvent;
use App\Repositories\DTOs\TransferEventDto;
use App\Repositories\ITransferEventRepo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Repository-level concurrent inserts via Laravel Concurrency (process driver).
 *
 * PHPUnit (not Pest): serialized closures must not be bound to Pest’s generated test class,
 * or invoke-serialized-closure workers fail to load that class.
 *
 * Database: same rationale as ConcurrentStoreEventsTest — we skip RefreshDatabase + :memory: for
 * this test body. Worker processes do not share the parent’s in-memory SQLite; a temp file path
 * gives one shared database for the parent and all workers. migrate:fresh + finally restore keeps
 * the rest of the suite on phpunit’s default :memory: configuration.
 */
class ConcurrentIngestionTest extends TestCase
{
    #[Test]
    public function test_concurrent_ingestion_of_same_event_id_does_not_double_insert(): void
    {
        $path = sys_get_temp_dir() . '/station-transfer-events-concurrent-' . uniqid('', true) . '.sqlite';
        if (File::exists($path)) {
            File::delete($path);
        }
        File::put($path, '');

        $previousPutenv = getenv('DB_DATABASE');
        $previousEnv = $_ENV['DB_DATABASE'] ?? null;
        $previousServer = $_SERVER['DB_DATABASE'] ?? null;

        try {
            putenv('DB_DATABASE=' . $path);
            $_ENV['DB_DATABASE'] = $path;
            $_SERVER['DB_DATABASE'] = $path;

            config(['database.connections.sqlite.database' => $path]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');

            Artisan::call('migrate:fresh', ['--force' => true, '--no-interaction' => true]);

            $duplicateCounts = Concurrency::run([
                static fn() => app(ITransferEventRepo::class)->storeEventsAndGetDuplicatesCount(
                    collect([
                        TransferEventDto::fromArray([
                            'event_id' => 'concurrent-same-id',
                            'station_id' => 'S-CONC',
                            'amount' => 50.25,
                            'status' => 'approved',
                            'created_at' => '2026-04-05T12:00:00Z',
                        ], 'batch-a'),
                    ])
                ),
                static fn() => app(ITransferEventRepo::class)->storeEventsAndGetDuplicatesCount(
                    collect([
                        TransferEventDto::fromArray([
                            'event_id' => 'concurrent-same-id',
                            'station_id' => 'S-CONC',
                            'amount' => 50.25,
                            'status' => 'approved',
                            'created_at' => '2026-04-05T12:00:00Z',
                        ], 'batch-b'),
                    ])
                ),
            ]);

            $this->assertSame(1, TransferEvent::query()->where('event_id', 'concurrent-same-id')->count());
            $this->assertSame(1, array_sum($duplicateCounts));
        } finally {
            if ($previousPutenv !== false) {
                putenv('DB_DATABASE=' . $previousPutenv);
            } else {
                putenv('DB_DATABASE');
            }

            if ($previousEnv !== null) {
                $_ENV['DB_DATABASE'] = $previousEnv;
            } else {
                unset($_ENV['DB_DATABASE']);
            }

            if ($previousServer !== null) {
                $_SERVER['DB_DATABASE'] = $previousServer;
            } else {
                unset($_SERVER['DB_DATABASE']);
            }

            config(['database.connections.sqlite.database' => ':memory:']);
            DB::purge('sqlite');

            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
