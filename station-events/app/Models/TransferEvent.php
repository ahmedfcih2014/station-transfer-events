<?php

namespace App\Models;

use App\Enum\TransferEventStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferEvent extends Model
{
    /** @use HasFactory<\Database\Factories\TransferEventFactory> */
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'event_id';

    protected $fillable = [
        'event_id',
        'station_id',
        'amount',
        'status',
        'batch_id',
        'created_at',
    ];

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', TransferEventStatus::APPROVED->value);
    }

    #[Scope]
    protected function byStation(Builder $query, string $stationId): void
    {
        $query->where('station_id', $stationId);
    }
}
