<?php

namespace App\Services\DTOs;

class StoreEventsDto
{
    private int $inserted;
    private int $duplicates;

    private function __construct() {}

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->inserted = $data['inserted'] ?? 0;
        $dto->duplicates = $data['duplicates'] ?? 0;
        return $dto;
    }

    public function getInserted(): int
    {
        return $this->inserted;
    }

    public function getDuplicates(): int
    {
        return $this->duplicates;
    }

    public function toArray(): array
    {
        return [
            'inserted' => $this->inserted,
            'duplicates' => $this->duplicates,
        ];
    }
}
