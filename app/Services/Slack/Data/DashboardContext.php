<?php

namespace App\Services\Slack\Data;

use App\Enums\DashboardState;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\VendorProposal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final readonly class DashboardContext
{
    /**
     * @param  Collection<int, VendorProposal>  $proposals
     * @param  Collection<int, VendorProposal>  $openProposals
     * @param  Collection<int, VendorProposal>  $myProposalsInCharge
     */
    public function __construct(
        public DashboardState $state,
        public LunchSession $session,
        public string $userId,
        public CarbonInterface $date,
        public bool $isToday,
        public bool $isAdmin,
        public string $workspaceName,
        public Collection $proposals,
        public Collection $openProposals,
        public Collection $myProposalsInCharge,
        public ?Order $myOrder,
        public ?VendorProposal $myOrderProposal,
    ) {}

    public function hasOpenProposals(): bool
    {
        return $this->openProposals->isNotEmpty();
    }

    public function hasProposals(): bool
    {
        return $this->proposals->isNotEmpty();
    }

    public function hasOrder(): bool
    {
        return $this->myOrder !== null;
    }

    public function isInCharge(): bool
    {
        return $this->myProposalsInCharge->isNotEmpty();
    }

    public function canCreateProposal(): bool
    {
        return $this->isToday && $this->session->isOpen();
    }

    public function canCloseSession(): bool
    {
        if (! $this->isToday || $this->session->isClosed()) {
            return false;
        }

        return $this->isAdmin || $this->isInCharge();
    }

    public function toPrivateMetadata(): array
    {
        return [
            'tenant_id' => $this->session->organization_id,
            'date' => $this->date->toDateString(),
            'lunch_session_id' => $this->session->id,
            'origin' => 'slash_lunch',
            'user_id' => $this->userId,
            'state' => $this->state->value,
        ];
    }

    public function toPrivateMetadataJson(): string
    {
        return json_encode($this->toPrivateMetadata(), JSON_THROW_ON_ERROR);
    }
}
