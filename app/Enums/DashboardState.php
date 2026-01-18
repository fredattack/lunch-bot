<?php

namespace App\Enums;

enum DashboardState: string
{
    case NoProposal = 'S1';
    case OpenProposalsNoOrder = 'S2';
    case HasOrder = 'S3';
    case InCharge = 'S4';
    case AllClosed = 'S5';
    case History = 'S6';

    public function label(): string
    {
        return match ($this) {
            self::NoProposal => 'Aucune commande',
            self::OpenProposalsNoOrder => 'Commandes ouvertes',
            self::HasOrder => 'Ma commande',
            self::InCharge => 'En charge',
            self::AllClosed => 'Tout cloture',
            self::History => 'Historique',
        };
    }

    public function isToday(): bool
    {
        return $this !== self::History;
    }

    public function allowsActions(): bool
    {
        return $this !== self::History;
    }
}
