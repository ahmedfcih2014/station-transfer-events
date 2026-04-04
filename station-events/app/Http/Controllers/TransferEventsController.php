<?php

namespace App\Http\Controllers;

use App\Services\TransferEventService;
use Illuminate\Http\JsonResponse;

class TransferEventsController extends Controller
{
    public function __construct(private TransferEventService $transferEventService) {}

    public function stationSummary(string $stationId): JsonResponse
    {
        $summary = $this->transferEventService->getSummaryPerStation($stationId);
        return $this->successResponse($summary);
    }
}
