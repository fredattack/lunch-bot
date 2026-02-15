<?php

namespace App\Services\Slack;

use App\Enums\DashboardState;
use App\Enums\ProposalStatus;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Organization;
use App\Models\VendorProposal;
use App\Services\Slack\Data\DashboardContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardStateResolver
{
    public function resolve(LunchSession $session, string $userId, bool $isAdmin = false): DashboardContext
    {
        $timezone = config('lunch.timezone', 'Europe/Paris');
        $today = Carbon::now($timezone);
        $isToday = $session->date->isSameDay($today);

        $proposals = $this->loadProposals($session);
        $openProposals = $this->filterOpenProposals($proposals);
        $myProposalsInCharge = $this->filterMyProposalsInCharge($proposals, $userId);
        $myOrder = $this->findMyOrder($proposals, $userId);
        $myOrderProposal = $myOrder ? $this->findProposalForOrder($proposals, $myOrder) : null;

        $state = $this->determineState(
            isToday: $isToday,
            proposals: $proposals,
            openProposals: $openProposals,
            myProposalsInCharge: $myProposalsInCharge,
            myOrder: $myOrder
        );

        $workspaceName = $session->organization?->name ?? 'Workspace';
        $locale = $this->resolveLocale($session->organization);

        return new DashboardContext(
            state: $state,
            session: $session,
            userId: $userId,
            date: $session->date,
            isToday: $isToday,
            isAdmin: $isAdmin,
            workspaceName: $workspaceName,
            locale: $locale,
            proposals: $proposals,
            openProposals: $openProposals,
            myProposalsInCharge: $myProposalsInCharge,
            myOrder: $myOrder,
            myOrderProposal: $myOrderProposal,
        );
    }

    /**
     * @return Collection<int, VendorProposal>
     */
    private function loadProposals(LunchSession $session): Collection
    {
        return VendorProposal::query()
            ->where('lunch_session_id', $session->id)
            ->with(['vendor', 'orders'])
            ->withCount('orders')
            ->get();
    }

    /**
     * @param  Collection<int, VendorProposal>  $proposals
     * @return Collection<int, VendorProposal>
     */
    private function filterOpenProposals(Collection $proposals): Collection
    {
        return $proposals->filter(function (VendorProposal $proposal) {
            return in_array($proposal->status, [
                ProposalStatus::Open,
                ProposalStatus::Ordering,
            ], true);
        })->values();
    }

    /**
     * @param  Collection<int, VendorProposal>  $proposals
     * @return Collection<int, VendorProposal>
     */
    private function filterMyProposalsInCharge(Collection $proposals, string $userId): Collection
    {
        return $proposals->filter(function (VendorProposal $proposal) use ($userId) {
            if ($proposal->status === ProposalStatus::Closed) {
                return false;
            }

            return $proposal->hasRole($userId);
        })->values();
    }

    /**
     * @param  Collection<int, VendorProposal>  $proposals
     */
    private function findMyOrder(Collection $proposals, string $userId): ?Order
    {
        foreach ($proposals as $proposal) {
            foreach ($proposal->orders as $order) {
                if ($order->provider_user_id === $userId) {
                    return $order;
                }
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, VendorProposal>  $proposals
     */
    private function findProposalForOrder(Collection $proposals, Order $order): ?VendorProposal
    {
        return $proposals->first(fn (VendorProposal $p) => $p->id === $order->vendor_proposal_id);
    }

    /**
     * @param  Collection<int, VendorProposal>  $proposals
     * @param  Collection<int, VendorProposal>  $openProposals
     * @param  Collection<int, VendorProposal>  $myProposalsInCharge
     */
    private function determineState(
        bool $isToday,
        Collection $proposals,
        Collection $openProposals,
        Collection $myProposalsInCharge,
        ?Order $myOrder
    ): DashboardState {
        if (! $isToday) {
            return DashboardState::History;
        }

        if ($proposals->isEmpty()) {
            return DashboardState::NoProposal;
        }

        if ($myProposalsInCharge->isNotEmpty()) {
            return DashboardState::InCharge;
        }

        if ($myOrder !== null) {
            return DashboardState::HasOrder;
        }

        if ($openProposals->isNotEmpty()) {
            return DashboardState::OpenProposalsNoOrder;
        }

        return DashboardState::AllClosed;
    }

    private function resolveLocale(?Organization $organization): string
    {
        if (! $organization) {
            return 'fr';
        }

        if ($organization->locale) {
            return $organization->locale;
        }

        return 'fr';
    }
}
