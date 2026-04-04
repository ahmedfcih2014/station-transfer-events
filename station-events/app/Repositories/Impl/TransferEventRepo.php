<?php

namespace App\Repositories\Impl;

use App\Models\TransferEvent;
use App\Repositories\DTOs\StationSummaryDto;
use App\Repositories\DTOs\TransferEventDto;
use App\Repositories\ITransferEventRepo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferEventRepo implements ITransferEventRepo
{
    public function getSummaryPerStation(string $stationId): StationSummaryDto
    {
        $stationEvents = TransferEvent::query()->byStation($stationId)->approved()->select(
            DB::raw('SUM(amount) as total'),
            DB::raw('COUNT(*) as count'),
        )->first();
        return StationSummaryDto::fromArray([
            'station_id' => $stationId,
            'total' => $stationEvents->total,
            'count' => $stationEvents->count,
        ]);
    }

    public function storeEventsAndGetDuplicatesCount(Collection $events): int
    {
        $values = collect([]);
        $events->each(fn(TransferEventDto $e)  => $values->push($e->toInsertValues()));

        $placeholders = Str::of('(?, ?, ?, ?, ?, ?), ')->repeat($values->count())->toString();
        $placeholders = rtrim($placeholders, ', ');

        $bindings = collect($values)->flatten()->toArray();

        $sql = "
            INSERT INTO transfer_events (event_id, station_id, amount, status, batch_id, created_at)
            VALUES $placeholders
            ON CONFLICT (event_id) DO NOTHING
            RETURNING event_id;
        ";

        $insertedEvents = DB::transaction(function () use ($sql, $bindings) {
            return DB::select($sql, $bindings);
        });

        return $events->count() - count($insertedEvents);
    }
}
