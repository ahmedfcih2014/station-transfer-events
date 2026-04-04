<?php

namespace App\Repositories;

use App\Repositories\DTOs\StationSummaryDto;

interface ITransferEventRepo
{
    public function getSummaryPerStation(string $stationId): StationSummaryDto;
}
