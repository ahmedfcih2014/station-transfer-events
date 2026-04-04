<?php

namespace App\Services;

use App\Repositories\ITransferEventRepo;

class TransferEventService
{
    public function __construct(private ITransferEventRepo $transferEventRepo) {}

    public function getSummaryPerStation(string $stationId): array
    {
        $stationSummary = $this->transferEventRepo->getSummaryPerStation($stationId);

        return [
            'station_id' => $stationSummary->getStationId(),
            'total_approved_amount' => $stationSummary->getTotalApprovedAmount(),
            'events_count' => $stationSummary->getEventsCount(),
        ];
    }
}
