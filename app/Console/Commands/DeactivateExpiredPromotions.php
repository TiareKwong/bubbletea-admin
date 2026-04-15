<?php

namespace App\Console\Commands;

use App\Models\Promotion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('promotions:deactivate-expired')]
#[Description('Set expired Active promotions to Inactive')]
class DeactivateExpiredPromotions extends Command
{
    public function handle(): void
    {
        $count = Promotion::where('status', 'Active')
            ->where('valid_until', '<', now()->toDateString())
            ->update(['status' => 'Inactive']);

        $this->info("Deactivated $count expired promotion(s).");
    }
}
