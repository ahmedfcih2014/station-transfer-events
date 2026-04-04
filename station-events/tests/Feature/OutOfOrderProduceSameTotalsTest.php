<?php

use App\Enum\TransferEventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

/**
 * Same approved events ingested in different request order must yield the same reconciliation
 * summary (totals depend only on the stored set, not arrival order).
 */
test('out-of-order POST arrival yields the same station summary totals', function (array $orderIndices) {
    $stationId = 'S-OUT-OF-ORDER';

    // Distinct created_at values (simulating events generated at different times); order in the
    // request array is independent of these timestamps (out-of-order arrival).
    $templates = [
        [
            'event_id' => 'oo-evt-a',
            'station_id' => $stationId,
            'amount' => 100.5,
            'status' => TransferEventStatus::APPROVED->value,
            'created_at' => '2026-01-03T12:00:00Z',
        ],
        [
            'event_id' => 'oo-evt-b',
            'station_id' => $stationId,
            'amount' => 50.0,
            'status' => TransferEventStatus::APPROVED->value,
            'created_at' => '2026-01-01T08:00:00Z',
        ],
        [
            'event_id' => 'oo-evt-c',
            'station_id' => $stationId,
            'amount' => 25.25,
            'status' => TransferEventStatus::APPROVED->value,
            'created_at' => '2026-01-02T15:30:00Z',
        ],
    ];

    $expectedTotal = 175.75;
    $expectedCount = 3;

    $events = array_map(static fn(int $i) => $templates[$i], $orderIndices);

    postJson('/transfers', ['events' => $events])
        ->assertStatus(200);

    getJson("/stations/{$stationId}/summary")
        ->assertStatus(200)
        ->assertJson([
            'station_id' => $stationId,
            'total_approved_amount' => $expectedTotal,
            'events_count' => $expectedCount,
        ]);
})->with([
    'chronological by template order' => [[0, 1, 2]],
    'reverse' => [[2, 1, 0]],
    'middle event first' => [[1, 2, 0]],
]);

test('out-of-order arrival with mixed statuses still yields same approved totals', function (array $orderIndices) {
    $stationId = 'S-MIXED-ORDER';

    $templates = [
        [
            'event_id' => 'mix-1',
            'station_id' => $stationId,
            'amount' => 10.0,
            'status' => TransferEventStatus::APPROVED->value,
            'created_at' => '2026-02-01T10:00:00Z',
        ],
        [
            'event_id' => 'mix-2',
            'station_id' => $stationId,
            'amount' => 20.0,
            'status' => 'pending',
            'created_at' => '2026-02-01T11:00:00Z',
        ],
        [
            'event_id' => 'mix-3',
            'station_id' => $stationId,
            'amount' => 30.0,
            'status' => TransferEventStatus::APPROVED->value,
            'created_at' => '2026-02-01T12:00:00Z',
        ],
    ];

    $events = array_map(static fn(int $i) => $templates[$i], $orderIndices);

    postJson('/transfers', ['events' => $events])
        ->assertStatus(200);

    getJson("/stations/{$stationId}/summary")
        ->assertStatus(200)
        ->assertJson([
            'station_id' => $stationId,
            'total_approved_amount' => 40.0,
            'events_count' => 2,
        ]);
})->with([
    'approved first' => [[0, 1, 2]],
    'pending between approved' => [[0, 2, 1]],
    'pending first' => [[1, 0, 2]],
]);
