<?php

namespace App\Repositories\DTOs;

class StationSummaryDto
{
    private string $stationId;
    private float $totalApprovedAmount;
    private int $eventsCount;

    private function __construct(array $data)
    {
        // Use ?? not isset: SQL SUM(...) is NULL when there are no matching rows; isset(null) is false.
        $this->stationId = $data['station_id'] ?? '';
        $this->totalApprovedAmount = (float) ($data['total'] ?? 0.0);
        $this->eventsCount = (int) ($data['count'] ?? 0);
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function setStationId(string $stationId): self
    {
        $this->stationId = $stationId;
        return $this;
    }

    public function setTotalApprovedAmount(float $totalApprovedAmount): self
    {
        $this->totalApprovedAmount = $totalApprovedAmount;
        return $this;
    }

    public function setEventsCount(int $eventsCount): self
    {
        $this->eventsCount = $eventsCount;
        return $this;
    }

    public function getStationId(): string
    {
        return $this->stationId;
    }

    public function getTotalApprovedAmount(): float
    {
        return $this->totalApprovedAmount;
    }

    public function getEventsCount(): int
    {
        return $this->eventsCount;
    }
}
