<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

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
