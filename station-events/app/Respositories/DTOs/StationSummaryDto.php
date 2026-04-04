<?php

namespace App\Repositories\DTOs;

class StationSummaryDto
{
    private string $stationId;
    private float $totalApprovedAmount;
    private int $eventsCount;

    private function __construct(array $data)
    {
        if (isset($data['station_id'])) {
            $this->stationId = $data['station_id'];
        }
        if (isset($data['total'])) {
            $this->totalApprovedAmount = $data['total'];
        }
        if (isset($data['count'])) {
            $this->eventsCount = $data['count'];
        }
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
