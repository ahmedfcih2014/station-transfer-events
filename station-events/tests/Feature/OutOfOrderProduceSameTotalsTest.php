<?php

use App\Enum\TransferEventStatus;
use App\Models\TransferEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('get summary per station id, out of order produce same totals', function () {
    // prepare data for seeding
    $stationId = 'Station-1';
    $now = now();
    $events = collect([]);
    $events->push(['amount' => 105, 'status' => TransferEventStatus::APPROVED->value, 'created_at' => $now->addHour()]);
    $events->push(['amount' => 50, 'status' => TransferEventStatus::APPROVED->value, 'created_at' => $now]);
    $events->push(['amount' => 230, 'status' => 'cancelled']);
    $events->push(['amount' => 170, 'status' => TransferEventStatus::APPROVED->value, 'created_at' => $now->subHour()]);
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
        'created_at' => $e['created_at'],
    ]));

    // assert the summary API is correct
    getJson("/stations/{$stationId}/summary")
        ->assertStatus(200)
        ->assertJson([
            'station_id' => $stationId,
            'total_approved_amount' => $expectedTotalApprovedAmount,
            'events_count' => $expectedEventsCount,
        ]);
});
