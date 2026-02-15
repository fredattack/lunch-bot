<?php

namespace App\Services\Slack;

use App\Enums\DashboardState;
use App\Enums\FulfillmentType;
use App\Enums\SlackAction;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorProposal;
use App\Services\Slack\Data\DashboardContext;
use Carbon\CarbonInterface;

class DashboardBlockBuilder
{
    use SlackBlockHelpers;

    public function buildModal(DashboardContext $context): array
    {
        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackLunchDashboard->value,
            'title' => [
                'type' => 'plain_text',
                'text' => $this->buildModalTitle($context->workspaceName),
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'private_metadata' => $context->toPrivateMetadataJson(),
            'blocks' => $this->buildBlocks($context),
        ];
    }

    private function buildModalTitle(string $workspaceName): string
    {
        $prefix = 'Lunch-bot — ';
        $maxLength = 24;
        $availableLength = $maxLength - mb_strlen($prefix);

        if (mb_strlen($workspaceName) > $availableLength) {
            $workspaceName = mb_substr($workspaceName, 0, $availableLength - 1).'…';
        }

        return $prefix.$workspaceName;
    }

    private function buildBlocks(DashboardContext $context): array
    {
        $blocks = $this->headerBlocks($context);
        $blocks = array_merge($blocks, $this->quickActionsBlocks($context));

        $blocks = array_merge($blocks, match ($context->state) {
            DashboardState::NoProposal => $this->blocksForS1($context),
            DashboardState::OpenProposalsNoOrder => $this->blocksForS2($context),
            DashboardState::HasOrder => $this->blocksForS3($context),
            DashboardState::InCharge => $this->blocksForS4($context),
            DashboardState::AllClosed => $this->blocksForS5($context),
            DashboardState::History => $this->blocksForS6($context),
        });

        $devUserId = config('slack.dev_user_id');
        if ($devUserId && $context->userId === $devUserId) {
            $blocks = array_merge($blocks, $this->devToolsBlocks());
        }

        return $blocks;
    }

    private function quickActionsBlocks(DashboardContext $context): array
    {
        return [
            [
                'type' => 'actions',
                'block_id' => 'quick_actions',
                'elements' => [
                    $this->button(
                        'Voir les restaurants',
                        SlackAction::DashboardVendorsList->value,
                        (string) $context->session->id
                    ),
                ],
            ],
            ['type' => 'divider'],
        ];
    }

    private function devToolsBlocks(): array
    {
        return [
            ['type' => 'divider'],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => ':wrench: *Dev Tools*',
                    ],
                ],
            ],
            [
                'type' => 'actions',
                'block_id' => 'dev_tools',
                'elements' => [
                    [
                        'type' => 'button',
                        'action_id' => SlackAction::DevResetDatabase->value,
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Reset DB',
                        ],
                        'style' => 'danger',
                        'confirm' => [
                            'title' => ['type' => 'plain_text', 'text' => 'Reset Database?'],
                            'text' => ['type' => 'plain_text', 'text' => 'Cette action va supprimer toutes les donnees et reinitialiser la base.'],
                            'confirm' => ['type' => 'plain_text', 'text' => 'Reset'],
                            'deny' => ['type' => 'plain_text', 'text' => 'Annuler'],
                        ],
                    ],
                    [
                        'type' => 'button',
                        'action_id' => SlackAction::DevExportVendors->value,
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Export Vendors JSON',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function headerBlocks(DashboardContext $context): array
    {
        $dateLabel = $this->formatDateLabel($context->date, $context->isToday, $context->locale);
        $statusLabel = $this->sessionStatusLabel($context);

        $headerText = $context->state === DashboardState::History
            ? "{$dateLabel} (historique)"
            : $dateLabel;

        return [
            [
                'type' => 'header',
                'block_id' => 'hdr',
                'text' => [
                    'type' => 'plain_text',
                    'text' => $headerText,
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => $statusLabel,
                    ],
                ],
            ],
            ['type' => 'divider'],
        ];
    }

    /**
     * S1: Aucune proposition - CTA unique pour demarrer.
     */
    private function blocksForS1(DashboardContext $context): array
    {
        return [
            [
                'type' => 'section',
                'block_id' => 'empty_state',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "Aucune commande n'a ete lancee aujourd'hui.",
                ],
            ],
            [
                'type' => 'actions',
                'block_id' => 'actions_start',
                'elements' => [
                    $this->button(
                        'Demarrer une commande',
                        SlackAction::DashboardStartFromCatalog->value,
                        (string) $context->session->id,
                        'primary'
                    ),
                    $this->button(
                        'Proposer un nouveau restaurant',
                        SlackAction::DashboardCreateProposal->value,
                        (string) $context->session->id
                    ),
                ],
            ],
        ];
    }

    /**
     * S2: Propositions ouvertes, utilisateur sans commande.
     */
    private function blocksForS2(DashboardContext $context): array
    {
        $blocks = [];

        foreach ($context->openProposals as $proposal) {
            $blocks = array_merge($blocks, $this->proposalCard($proposal, $context));
        }

        $blocks[] = ['type' => 'divider'];
        $blocks[] = [
            'type' => 'actions',
            'block_id' => 'footer_actions',
            'elements' => [
                $this->button(
                    'Lancer une autre commande',
                    SlackAction::DashboardStartFromCatalog->value,
                    (string) $context->session->id
                ),
            ],
        ];

        return $blocks;
    }

    /**
     * S3: Utilisateur a une commande (participant).
     */
    private function blocksForS3(DashboardContext $context): array
    {
        $blocks = $this->myOrderBlock($context);

        if ($context->openProposals->count() > 1) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "_Autres commandes en cours : {$context->openProposals->count()}_",
                    ],
                ],
            ];
        }

        return $blocks;
    }

    /**
     * S4: Utilisateur en charge d'une proposition.
     */
    private function blocksForS4(DashboardContext $context): array
    {
        $blocks = [];

        foreach ($context->myProposalsInCharge as $proposal) {
            $blocks = array_merge($blocks, $this->inChargeProposalBlock($proposal, $context));
        }

        if ($context->hasOrder() && $context->myOrderProposal) {
            $isMyOrderInCharge = $context->myProposalsInCharge->contains('id', $context->myOrderProposal->id);
            if (! $isMyOrderInCharge) {
                $blocks[] = ['type' => 'divider'];
                $blocks = array_merge($blocks, $this->myOrderBlock($context));
            }
        }

        return $blocks;
    }

    /**
     * S5: Tout cloture, possibilite de relancer.
     */
    private function blocksForS5(DashboardContext $context): array
    {
        $blocks = [
            [
                'type' => 'section',
                'block_id' => 'closed_state',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => 'Aucune commande en cours actuellement.',
                ],
            ],
            [
                'type' => 'actions',
                'block_id' => 'actions_relaunch',
                'elements' => [
                    $this->button(
                        'Relancer une commande',
                        SlackAction::DashboardRelaunch->value,
                        (string) $context->session->id,
                        'primary'
                    ),
                ],
            ],
        ];

        if ($context->proposals->isNotEmpty()) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Aujourd'hui*",
                ],
            ];

            foreach ($context->proposals as $proposal) {
                $blocks[] = $this->compactProposalSummary($proposal);
            }
        }

        return $blocks;
    }

    /**
     * S6: Historique (jour passe).
     */
    private function blocksForS6(DashboardContext $context): array
    {
        $blocks = [];

        if ($context->hasOrder()) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Mes commandes*',
                ],
            ];
            $blocks[] = $this->orderSummaryLine($context->myOrder, $context->myOrderProposal);
            $blocks[] = ['type' => 'divider'];
        }

        if ($context->myProposalsInCharge->isNotEmpty()) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Commandes prises en charge par moi*',
                ],
            ];

            foreach ($context->myProposalsInCharge as $proposal) {
                $blocks[] = $this->historyProposalSummary($proposal, $context);
            }
        }

        if (! $context->hasOrder() && $context->myProposalsInCharge->isEmpty()) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "_Vous n'avez pas participe a cette session._",
                ],
            ];
        }

        return $blocks;
    }

    private function proposalCard(VendorProposal $proposal, DashboardContext $context): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant inconnu';
        $fulfillmentLabel = $this->fulfillmentLabel($proposal->fulfillment_type);
        $deadlineTime = $proposal->deadline_time ?? '11:30';

        $responsibleText = $this->responsibleText($proposal);
        $participantsText = $this->participantsText($proposal, 5);

        $sectionText = "*{$vendorName}*\n{$fulfillmentLabel} | Deadline {$deadlineTime} | {$responsibleText}";
        if ($participantsText) {
            $sectionText .= "\n{$participantsText}";
        }

        $blocks = [
            [
                'type' => 'section',
                'block_id' => "proposal_{$proposal->id}",
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $sectionText,
                ],
            ],
        ];

        if ($proposal->help_requested) {
            $blocks[] = [
                'type' => 'context',
                'block_id' => "proposal_help_{$proposal->id}",
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => ':warning: *Aide demandee*',
                    ],
                ],
            ];
        }

        $urlButtons = $this->vendorUrlButtons($vendor);
        if (! empty($urlButtons)) {
            $blocks[] = [
                'type' => 'actions',
                'block_id' => "proposal_urls_{$proposal->id}",
                'elements' => $urlButtons,
            ];
        }

        if ($context->canCreateProposal()) {
            $userOrderInProposal = $proposal->orders->first(
                fn ($order) => $order->provider_user_id === $context->userId
            );

            if ($userOrderInProposal) {
                $elements = [
                    $this->button(
                        'Voir ma commande',
                        SlackAction::OrderOpenEdit->value,
                        (string) $userOrderInProposal->id,
                        'primary'
                    ),
                ];
            } else {
                $elements = [
                    $this->button(
                        'Commander ici',
                        SlackAction::DashboardJoinProposal->value,
                        (string) $proposal->id,
                        'primary'
                    ),
                ];
            }

            $hasResponsible = $proposal->runner_user_id !== null || $proposal->orderer_user_id !== null;
            if (! $hasResponsible) {
                $elements[] = $this->button(
                    'Gerer',
                    SlackAction::ProposalOpenManage->value,
                    (string) $proposal->id
                );
            }

            $blocks[] = [
                'type' => 'actions',
                'block_id' => "proposal_actions_{$proposal->id}",
                'elements' => $elements,
            ];
        }

        return $blocks;
    }

    private function vendorUrlButtons(?Vendor $vendor): array
    {
        if (! $vendor) {
            return [];
        }

        $buttons = [];

        if (! empty($vendor->url_website)) {
            $buttons[] = $this->urlButton('Site web', $vendor->url_website);
        }

        if (! empty($vendor->url_menu)) {
            $buttons[] = $this->urlButton('Menu PDF', $vendor->url_menu);
        }

        return $buttons;
    }

    private function urlButton(string $text, string $url): array
    {
        return [
            'type' => 'button',
            'text' => [
                'type' => 'plain_text',
                'text' => $text,
            ],
            'url' => $url,
        ];
    }

    private function myOrderBlock(DashboardContext $context): array
    {
        $order = $context->myOrder;
        $proposal = $context->myOrderProposal;

        if (! $order || ! $proposal) {
            return [];
        }

        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant';
        $fulfillmentLabel = $this->fulfillmentLabel($proposal->fulfillment_type);
        $priceText = $this->formatPrice($order->price_estimated ? (float) $order->price_estimated : null);

        $description = strlen($order->description) > 60
            ? substr($order->description, 0, 57).'...'
            : $order->description;

        $orderText = "*Ma commande*\n{$vendorName} ({$fulfillmentLabel})\n_{$description}_";
        if ($priceText !== '-') {
            $orderText .= " - {$priceText}";
        }

        $blocks = [
            [
                'type' => 'section',
                'block_id' => 'my_order',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $orderText,
                ],
            ],
        ];

        if ($context->state->allowsActions()) {
            $blocks[] = [
                'type' => 'actions',
                'block_id' => 'my_order_actions',
                'elements' => [
                    $this->button(
                        'Modifier ma commande',
                        SlackAction::OrderOpenEdit->value,
                        (string) $order->id,
                        'primary'
                    ),
                ],
            ];
        }

        return $blocks;
    }

    private function inChargeProposalBlock(VendorProposal $proposal, DashboardContext $context): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant';
        $orderCount = $proposal->orders_count ?? $proposal->orders->count();
        $roleLabel = $proposal->fulfillment_type === FulfillmentType::Delivery ? 'orderer' : 'runner';
        $statusLabel = $this->proposalStatusLabel($proposal);

        $blocks = [
            [
                'type' => 'section',
                'block_id' => "in_charge_{$proposal->id}",
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Commande en cours - prise en charge par vous*\n*{$vendorName}* | {$orderCount} commande(s) | {$statusLabel}",
                ],
            ],
            [
                'type' => 'actions',
                'block_id' => "in_charge_actions_{$proposal->id}",
                'elements' => [
                    $this->button(
                        'Voir le recapitulatif',
                        SlackAction::ProposalOpenRecap->value,
                        (string) $proposal->id
                    ),
                    $this->button(
                        'Cloturer cette commande',
                        SlackAction::ProposalClose->value,
                        (string) $proposal->id,
                        'danger'
                    ),
                ],
            ],
        ];

        return $blocks;
    }

    private function compactProposalSummary(VendorProposal $proposal): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant';
        $orderCount = $proposal->orders_count ?? $proposal->orders->count();
        $statusLabel = $this->proposalStatusLabel($proposal);

        return [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => "{$vendorName} | {$orderCount} commande(s) | {$statusLabel}",
                ],
            ],
        ];
    }

    private function historyProposalSummary(VendorProposal $proposal, DashboardContext $context): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant';
        $orderCount = $proposal->orders_count ?? $proposal->orders->count();

        return [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*{$vendorName}* - {$orderCount} commande(s)",
            ],
            'accessory' => $this->button(
                'Voir recapitulatif',
                SlackAction::ProposalOpenRecap->value,
                (string) $proposal->id
            ),
        ];
    }

    private function orderSummaryLine(?Order $order, ?VendorProposal $proposal): array
    {
        if (! $order || ! $proposal) {
            return ['type' => 'context', 'elements' => [['type' => 'mrkdwn', 'text' => '_Aucune commande_']]];
        }

        $vendorName = $proposal->vendor?->name ?? 'Restaurant';
        $price = $order->price_estimated !== null
            ? $this->formatPrice((float) $order->price_estimated)
            : '';
        $description = strlen($order->description) > 40
            ? substr($order->description, 0, 37).'...'
            : $order->description;

        $text = "{$vendorName} : _{$description}_";
        if ($price !== '') {
            $text .= " ({$price})";
        }

        return [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ],
        ];
    }

    private function responsibleText(VendorProposal $proposal): string
    {
        if ($proposal->fulfillment_type === FulfillmentType::Delivery && $proposal->orderer_user_id) {
            return "Orderer : <@{$proposal->orderer_user_id}>";
        }

        if ($proposal->runner_user_id) {
            return "Runner : <@{$proposal->runner_user_id}>";
        }

        return 'Responsable : _non assigne_';
    }

    private function participantsText(VendorProposal $proposal, int $maxAvatars): string
    {
        $orders = $proposal->orders;
        if ($orders->isEmpty()) {
            return '';
        }

        $userIds = $orders->pluck('provider_user_id')->unique()->take($maxAvatars);
        $avatars = $userIds->map(fn ($id) => "<@{$id}>")->implode(' ');

        $remaining = $orders->count() - $maxAvatars;
        if ($remaining > 0) {
            $avatars .= " +{$remaining}";
        }

        return $avatars;
    }

    private function sessionStatusLabel(DashboardContext $context): string
    {
        if ($context->session->isClosed()) {
            return 'Cloturee';
        }

        if ($context->session->isLocked()) {
            return 'Verrouillee';
        }

        return 'En cours';
    }

    private function proposalStatusLabel(VendorProposal $proposal): string
    {
        return match ($proposal->status->value) {
            'open' => 'Ouverte',
            'ordering' => 'Commande en cours',
            'placed' => 'Commandee',
            'received' => 'Recue',
            'closed' => 'Cloturee',
            default => $proposal->status->value,
        };
    }

    private function formatDateLabel(CarbonInterface $date, bool $isToday, string $locale = 'en'): string
    {
        return $date->locale($locale)->translatedFormat('D. d/m');
    }
}
