<?php

namespace App\Repositories;

use App\Repositories\DTOs\StationSummaryDto;
use Illuminate\Support\Collection;

interface ITransferEventRepo
{
    public function getSummaryPerStation(string $stationId): StationSummaryDto;
    public function storeEventsAndGetDuplicatesCount(Collection $events): int;
}
