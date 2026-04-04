<?php

namespace App\Repositories\DTOs;

class TransferEventDto
{
    private string $eventId;
    private string $stationId;
    private float $amount;
    private string $status;
    private string $batchId;
    private string $createdAt;

    private function __construct() {}

    public static function fromArray(array $data, string $batchId): self
    {
        $dto = new self();
        $dto->eventId = $data['event_id'];
        $dto->stationId = $data['station_id'];
        $dto->amount = $data['amount'];
        $dto->status = $data['status'];
        $dto->batchId = $batchId;
        $dto->createdAt = $data['created_at'];
        return $dto;
    }

    public function toArray(): array
    {
        return [
            'event_id' => $this->eventId,
            'station_id' => $this->stationId,
            'amount' => $this->amount,
            'status' => $this->status,
            'batch_id' => $this->batchId,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Should be in this order
     * (event_id, station_id, amount, status, batch_id, created_at)
     *
     * @return array
     */
    public function toInsertValues(): array
    {
        return [
            $this->eventId,
            $this->stationId,
            $this->amount,
            $this->status,
            $this->batchId,
            $this->createdAt,
        ];
    }
}
