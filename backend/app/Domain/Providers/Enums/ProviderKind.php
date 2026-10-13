<?php

namespace App\Domain\Providers\Enums;

use App\Support\Enums\HasValues;

/**
 * Rôle d'un prestataire dans le parcours.
 *  - WALLET : portefeuille Mobile Money (financement par collecte, versement, remboursement).
 *  - REMITTANCE : réseau de transfert international (Western Union, MoneyGram, Ria).
 *  - CARD : réseau de cartes (Visa Direct, phase ultérieure).
 */
enum ProviderKind: string
{
    use HasValues;

    case Wallet = 'WALLET';
    case Remittance = 'REMITTANCE';
    case Card = 'CARD';
}
