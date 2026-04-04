<?php

namespace App\Repositories\Impl;

use App\Models\TransferEvent;
use App\Repositories\DTOs\StationSummaryDto;
use App\Repositories\ITransferEventRepo;
use Illuminate\Support\Facades\DB;

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
}
