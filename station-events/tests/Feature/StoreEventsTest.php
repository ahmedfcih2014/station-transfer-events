<?php

use App\Enum\TransferEventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function PHPUnit\Framework\assertEquals;

uses(RefreshDatabase::class);

test('Store events, bad request validation', function () {
    $response = postJson('/transfers', [
        'events' => [
            [
                'event_id' => 123
            ]
        ],
    ]);
    $response->assertStatus(400);
    $response->assertJsonStructure([
        'message',
        'errors',
    ]);
    assertEquals($response->json('errors'), [
        "events.0.event_id" => "The Event ID field must be a string.",
        "events.0.station_id" => "The Station ID field is required.",
        "events.0.amount" => "The Amount field is required.",
        "events.0.status" => "The Status field is required.",
        "events.0.created_at" => "The Created At field is required.",
    ], 'Errors are not equals as expected');
});

test('duplicate event replay does not change station summary totals', function () {
    $stationId = 'S-DUP-TOTALS';
    $payload = [
        'events' => [
            [
                'event_id' => 'evt-dup-001',
                'station_id' => $stationId,
                'amount' => 123.45,
                'status' => TransferEventStatus::APPROVED->value,
                'created_at' => '2026-04-05T10:00:00Z',
            ],
        ],
    ];

    postJson('/transfers', $payload)
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 1,
            'duplicates' => 0,
        ]);

    getJson("/stations/{$stationId}/summary")
        ->assertStatus(200)
        ->assertJson([
            'station_id' => $stationId,
            'total_approved_amount' => 123.45,
            'events_count' => 1,
        ]);

    postJson('/transfers', $payload)
        ->assertStatus(200)
        ->assertJson([
            'inserted' => 0,
            'duplicates' => 1,
        ]);

    getJson("/stations/{$stationId}/summary")
        ->assertStatus(200)
        ->assertJson([
            'station_id' => $stationId,
            'total_approved_amount' => 123.45,
            'events_count' => 1,
        ]);
});
