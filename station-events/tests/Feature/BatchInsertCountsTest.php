<?php

use App\Enum\TransferEventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

test('batch of unique approved events returns inserted count and zero duplicates', function () {
    $stationId = 'S-BATCH-UNIQUE';
    $baseTime = '2026-04-05T12:00:00Z';

    postJson('/transfers', [
        'events' => [
            [
                'event_id' => 'batch-u-1',
                'station_id' => $stationId,
                'amount' => 10,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-u-2',
                'station_id' => $stationId,
                'amount' => 20,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-u-3',
                'station_id' => $stationId,
                'amount' => 30,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
        ],
    ])
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 3,
            'duplicates' => 0,
        ]);
});

test('duplicate event_id in same batch counts as duplicates and inserts one row', function () {
    $stationId = 'S-BATCH-DUP-IN';
    $baseTime = '2026-04-05T12:00:00Z';

    postJson('/transfers', [
        'events' => [
            [
                'event_id' => 'batch-dup-a',
                'station_id' => $stationId,
                'amount' => 100,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-dup-b',
                'station_id' => $stationId,
                'amount' => 200,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-dup-a',
                'station_id' => $stationId,
                'amount' => 999,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
        ],
    ])
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 2,
            'duplicates' => 1,
        ]);
});

test('replay of entire batch reports zero inserted and duplicates equal to batch size', function () {
    $stationId = 'S-BATCH-REPLAY';
    $baseTime = '2026-04-05T12:00:00Z';

    $payload = [
        'events' => [
            [
                'event_id' => 'batch-r-1',
                'station_id' => $stationId,
                'amount' => 1,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-r-2',
                'station_id' => $stationId,
                'amount' => 2,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
        ],
    ];

    postJson('/transfers', $payload)
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 2,
            'duplicates' => 0,
        ]);

    postJson('/transfers', $payload)
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 0,
            'duplicates' => 2,
        ]);
});

test('non-approved events in batch do not affect inserted or duplicates', function () {
    $stationId = 'S-BATCH-NON-APP';
    $baseTime = '2026-04-05T12:00:00Z';

    postJson('/transfers', [
        'events' => [
            [
                'event_id' => 'batch-na-1',
                'station_id' => $stationId,
                'amount' => 5,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-na-2',
                'station_id' => $stationId,
                'amount' => 50,
                'status' => TransferEventStatus::PENDING->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-na-3',
                'station_id' => $stationId,
                'amount' => 7,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
        ],
    ])
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 2,
            'duplicates' => 0,
        ]);
});

test('partial batch replay inserts only new event_ids and counts prior keys as duplicates', function () {
    $stationId = 'S-BATCH-PARTIAL';
    $baseTime = '2026-04-05T12:00:00Z';

    postJson('/transfers', [
        'events' => [
            [
                'event_id' => 'batch-p-a',
                'station_id' => $stationId,
                'amount' => 1,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-p-b',
                'station_id' => $stationId,
                'amount' => 2,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
        ],
    ])->assertStatus(200);

    postJson('/transfers', [
        'events' => [
            [
                'event_id' => 'batch-p-a',
                'station_id' => $stationId,
                'amount' => 1,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-p-b',
                'station_id' => $stationId,
                'amount' => 2,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
            [
                'event_id' => 'batch-p-c',
                'station_id' => $stationId,
                'amount' => 3,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => $baseTime,
            ],
        ],
    ])
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 1,
            'duplicates' => 2,
        ]);
});
