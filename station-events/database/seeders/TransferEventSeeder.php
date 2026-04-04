<?php

namespace Database\Seeders;

use App\Models\TransferEvent;
use Illuminate\Database\Seeder;

class TransferEventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // generate 5 batches (station per batch) of 5 events each
        for ($i = 1; $i <= 5; $i++) {
            $randomBatchId = now()->timestamp . $i;
            TransferEvent::factory(5)->create([
                'batch_id' => $randomBatchId,
            ]);
        }
    }
}
