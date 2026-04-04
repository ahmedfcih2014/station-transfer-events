<?php

use App\Enum\TransferEventStatus;
use App\Models\TransferEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('get summary for unknown station with no events returns zeros', function () {
    getJson('/api/stations/unknown-station/summary')
        ->assertStatus(200)
        ->assertJson([
            'station_id' => 'unknown-station',
            'total_approved_amount' => 0,
            'events_count' => 0,
        ]);
});

test('get summary per station id', function () {
    // prepare data for seeding
    $stationId = 'Station-1';
    $events = collect([]);
    $events->push(['amount' => 105, 'status' => TransferEventStatus::APPROVED->value]);
    $events->push(['amount' => 50, 'status' => TransferEventStatus::APPROVED->value]);
    $events->push(['amount' => 230, 'status' => 'cancelled']);
    $events->push(['amount' => 170, 'status' => TransferEventStatus::APPROVED->value]);
    $events->push(['amount' => 90, 'status' => 'pending']);

    // filter approved events & calculate expected values
    $approvedEvents = $events
        ->filter(fn($e) => $e['status'] === TransferEventStatus::APPROVED->value);
    $expectedEventsCount = $approvedEvents->count();
    $expectedTotalApprovedAmount = $approvedEvents->sum('amount');

    // seeding data in the database
    $approvedEvents->each(fn($e) => TransferEvent::factory()->create([
        'station_id' => $stationId,
        'amount' => $e['amount'],
        'status' => $e['status'],
    ]));

    // assert the summary API is correct
    getJson("/api/stations/{$stationId}/summary")
        ->assertStatus(200)
        ->assertJson([
            'station_id' => $stationId,
            'total_approved_amount' => $expectedTotalApprovedAmount,
            'events_count' => $expectedEventsCount,
        ]);
});
