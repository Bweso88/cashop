<?php

use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Services\TransferOrchestrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Suivi des transferts non terminés : relit l'état réel chez les prestataires
| (complète les webhooks, qui peuvent se perdre) et traite les rapprochements.
*/
Artisan::command('cashop:transfers:advance {--limit=100}', function (TransferOrchestrator $orchestrator) {
    $final = array_map(fn (TransferStatus $s) => $s->value, array_filter(TransferStatus::cases(), fn ($s) => $s->isTerminal()));
    $transfers = Transfer::query()
        ->whereNotIn('status', $final)
        ->where('status', '!=', TransferStatus::TransferAvailableForPickup->value)
        ->where(fn ($q) => $q->whereNull('next_status_poll_at')->orWhere('next_status_poll_at', '<=', now()))
        ->orderBy('updated_at')->limit((int) $this->option('limit'))->get();

    foreach ($transfers as $transfer) {
        $after = $orchestrator->advance($transfer);
        $this->line("{$transfer->external_reference} : {$transfer->status->value} → {$after->status->value}");
    }
    $this->info("{$transfers->count()} transfert(s) examiné(s).");
})->purpose('Fait avancer les transferts en attente d\'une confirmation des prestataires');

Schedule::command('cashop:transfers:advance')->everyMinute()->withoutOverlapping();
