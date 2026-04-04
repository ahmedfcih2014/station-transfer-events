<?php

namespace Tests\Feature;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Concurrent HTTP POSTs via Laravel Concurrency must not bind to Pest's generated test class
 * (serialization fails in workers). Use PHPUnit + Kernel::handle in workers.
 *
 * Database: we intentionally do not rely on RefreshDatabase + phpunit’s usual SQLite :memory: alone.
 * Concurrency’s default process driver runs work in separate PHP processes (invoke-serialized-closure).
 * Each process gets its own :memory: database, so workers would not share the parent test’s data.
 * A temporary file-backed SQLite path is shared on disk, so all processes see one store; we migrate
 * with migrate:fresh, then restore env/config in finally so other tests keep using :memory:.
 */
class ConcurrentStoreEventsTest extends TestCase
{
    #[Test]
    public function test_same_event_id_in_concurrent_requests_should_not_double_insert(): void
    {
        $path = sys_get_temp_dir() . '/station-transfer-events-concurrent-http-' . uniqid('', true) . '.sqlite';
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

            $json = '{"events":[{"event_id":"evt-001","station_id":"station-001","amount":100,"status":"approved","created_at":"2026-01-01T00:00:00Z"}]}';

            Concurrency::run([
                fn() => app(Kernel::class)->handle(
                    Request::create('/transfers', 'POST', [], [], [], [
                        'CONTENT_TYPE' => 'application/json',
                        'HTTP_ACCEPT' => 'application/json',
                    ], $json)
                )->getStatusCode(),
                fn() => app(Kernel::class)->handle(
                    Request::create('/transfers', 'POST', [], [], [], [
                        'CONTENT_TYPE' => 'application/json',
                        'HTTP_ACCEPT' => 'application/json',
                    ], $json)
                )->getStatusCode(),
                fn() => app(Kernel::class)->handle(
                    Request::create('/transfers', 'POST', [], [], [], [
                        'CONTENT_TYPE' => 'application/json',
                        'HTTP_ACCEPT' => 'application/json',
                    ], $json)
                )->getStatusCode(),
                fn() => app(Kernel::class)->handle(
                    Request::create('/transfers', 'POST', [], [], [], [
                        'CONTENT_TYPE' => 'application/json',
                        'HTTP_ACCEPT' => 'application/json',
                    ], $json)
                )->getStatusCode(),
            ]);

            $this->getJson('/stations/station-001/summary')
                ->assertStatus(200)
                ->assertJson([
                    'station_id' => 'station-001',
                    'total_approved_amount' => 100,
                    'events_count' => 1,
                ]);
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
