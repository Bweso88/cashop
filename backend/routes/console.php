<?php

use App\Domain\Payments\Enums\TransferStatus;
use App\Domain\Payments\Models\Transfer;
use App\Domain\Payments\Services\TransferOrchestrator;
use App\Domain\Providers\Connectors\DTO\CollectionRequest;
use App\Domain\Providers\Connectors\DTO\DisbursementRequest;
use App\Domain\Providers\Connectors\Exceptions\ConnectorException;
use App\Domain\Providers\Connectors\Exceptions\ConnectorUnavailableException;
use App\Domain\Providers\Connectors\MtnMoMo\MtnMoMoConnector;
use App\Domain\Providers\Enums\ProviderMode;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

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

// Vérifie le connecteur MTN MoMo sur la SANDBOX officielle avec les identifiants de .env.
// Montant de test en EUR (seule devise de la sandbox) : aucun fonds réel n'est déplacé.
Artisan::command('cashop:mtn:sandbox-check {msisdn=+46733000001} {--amount=100} {--disburse}', function (string $msisdn) {
    $config = (array) config('cashop.mtn_momo');
    if (($config['target_environment'] ?? null) !== 'sandbox') {
        $this->error('Réservé à la sandbox : MTN_MOMO_TARGET_ENVIRONMENT doit valoir « sandbox ».');

        return 1;
    }
    try {
        $connector = new MtnMoMoConnector(ProviderMode::Sandbox);
    } catch (ConnectorUnavailableException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $run = function (string $label, callable $create, callable $status) {
        $reference = (string) Str::uuid();
        $this->info("{$label} — X-Reference-Id {$reference}");
        try {
            $result = $create($reference);
            $this->line('  création : '.$result->status->value);
            for ($i = 0; $i < 5 && $result->status->value === 'PENDING'; $i++) {
                sleep(3);
                $result = $status($reference);
                $this->line('  statut   : '.$result->status->value.($result->failureCode ? " ({$result->failureCode})" : ''));
            }
            $this->line('  réponse  : '.json_encode($result->raw));
        } catch (ConnectorException $e) {
            $this->warn('  erreur   : '.$e->getMessage().($e->definitive ? ' [définitive]' : ' [résultat inconnu]'));
        }
    };

    $amount = (int) $this->option('amount');
    $run('Collection / Request to Pay', fn ($ref) => $connector->collect(new CollectionRequest($ref, $msisdn, $amount, 'EUR', 'Test sandbox Cashop')), fn ($ref) => $connector->collectionStatus($ref));
    if ($this->option('disburse')) {
        $run('Disbursement / Transfer', fn ($ref) => $connector->disburse(new DisbursementRequest($ref, $msisdn, $amount, 'EUR', 'Test sandbox Cashop')), fn ($ref) => $connector->disbursementStatus($ref));
    }

    return 0;
})->purpose('Teste le connecteur MTN MoMo sur la sandbox officielle (aucun fonds réel)');
