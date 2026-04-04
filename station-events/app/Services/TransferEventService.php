<?php

namespace App\Services;

use App\Http\Requests\TransferEvents\StoreEventsRequest;
use App\Repositories\DTOs\TransferEventDto;
use App\Repositories\ITransferEventRepo;
use App\Services\DTOs\StoreEventsDto;
use Illuminate\Support\Facades\Log;

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

    public function storeEvents(StoreEventsRequest $request): StoreEventsDto
    {
        $batchId = now()->timestamp . rand(1000, 9999);
        Log::info('TransferEventService::storeEvents, Going to store batch id: ' . $batchId);

        $eventIds = [];
        $uniqueEventsData = collect([]);
        $duplicatesInRequest = 0;

        foreach ($request->validated('events') ?? [] as $event) {
            if (!isset($eventIds[$event['event_id']])) {
                $eventIds[$event['event_id']] = true;
                $uniqueEventsData->push(TransferEventDto::fromArray($event, $batchId));
            } else {
                $duplicatesInRequest++;
            }
        }

        $duplicatesInDatabase = $this->transferEventRepo
            ->storeEventsAndGetDuplicatesCount($uniqueEventsData);

        return StoreEventsDto::fromArray([
            'inserted' => $uniqueEventsData->count() - $duplicatesInDatabase,
            'duplicates' => $duplicatesInRequest + $duplicatesInDatabase,
        ]);
    }
}
