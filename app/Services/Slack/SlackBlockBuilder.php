<?php

namespace App\Services\Slack;

use App\Enums\FulfillmentType;
use App\Enums\QuickRunStatus;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\QuickRun;
use App\Models\QuickRunRequest;
use App\Models\Vendor;
use App\Models\VendorProposal;

class SlackBlockBuilder
{
    use SlackBlockHelpers;

    public function dailyKickoffBlocks(LunchSession $session): array
    {
        $deadline = $this->formatTime($session->deadline_at);
        $date = $session->date->format('Y-m-d');

        return [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Dejeuner du {$date}*\nDeadline: {$deadline}",
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    $this->button('Proposer une enseigne', SlackAction::OpenProposalModal->value, (string) $session->id),
                    $this->button('Ajouter une enseigne', SlackAction::OpenAddEnseigneModal->value, (string) $session->id),
                    $this->button('Cloturer la journee', SlackAction::CloseDay->value, (string) $session->id, 'danger'),
                ],
            ],
        ];
    }

    public function proposalBlocks(VendorProposal $proposal, int $orderCount): array
    {
        $vendor = $proposal->vendor;
        $logoUrl = $vendor->getLogoThumbUrl();
        $menu = $vendor->url_menu ? "<{$vendor->url_menu}|Menu>" : 'Menu indisponible';
        $runner = $proposal->runner_user_id ? "<@{$proposal->runner_user_id}>" : '_non assigne_';
        $orderer = $proposal->orderer_user_id ? "<@{$proposal->orderer_user_id}>" : '_non assigne_';
        $type = $this->fulfillmentLabel($proposal->fulfillment_type);
        $platform = $proposal->platform ? "Plateforme: {$proposal->platform}" : 'Plateforme: -';

        $blocks = [];

        $headerElements = [];
        if ($logoUrl) {
            $headerElements[] = ['type' => 'image', 'image_url' => $logoUrl, 'alt_text' => $vendor->name];
        }
        $emojiMarkdown = $vendor->getEmojiMarkdown();
        $headerElements[] = ['type' => 'mrkdwn', 'text' => "{$emojiMarkdown}*{$vendor->name}* ({$menu})"];

        $blocks[] = [
            'type' => 'context',
            'elements' => $headerElements,
        ];

        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "Type: {$type}\n{$platform}\nRunner: {$runner}\nOrderer: {$orderer}",
            ],
        ];

        return array_merge($blocks, [
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Commandes: {$orderCount}",
                    ],
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    $this->button('Je suis runner', SlackAction::ClaimRunner->value, (string) $proposal->id),
                    $this->button('Je suis orderer', SlackAction::ClaimOrderer->value, (string) $proposal->id),
                    $this->button('Je commande', SlackAction::OpenOrderModal->value, (string) $proposal->id, 'primary'),
                    $this->button('Modifier ma commande', SlackAction::OpenEditOrderModal->value, (string) $proposal->id),
                    $this->button('Recapitulatif', SlackAction::OpenSummary->value, (string) $proposal->id),
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    $this->button('Deleguer mon role', SlackAction::OpenDelegateModal->value, (string) $proposal->id),
                    $this->button('Ajuster prix final', SlackAction::OpenAdjustPriceModal->value, (string) $proposal->id),
                    $this->button('Gerer enseigne', SlackAction::OpenManageEnseigneModal->value, (string) $proposal->id),
                ],
            ],
        ]);
    }

    public function summaryBlocks(VendorProposal $proposal, array $orders, array $totals): array
    {
        $lines = [];
        foreach ($orders as $order) {
            $final = $this->formatPrice($order->price_final !== null ? (float) $order->price_final : null);
            $estimated = $this->formatPrice($order->price_estimated !== null ? (float) $order->price_estimated : null);
            $lines[] = "- <@{$order->provider_user_id}>: {$order->description} (est: {$estimated}, final: {$final})";
        }

        if (empty($lines)) {
            $lines[] = '- Aucune commande pour le moment.';
        }

        $text = "*Recapitulatif*\n".implode("\n", $lines);
        $text .= "\n\nTotal estime: {$totals['estimated']} | Total final: {$totals['final']}";

        return [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ],
        ];
    }

    public function vendorExportModal(string $jsonData): array
    {
        $chunks = str_split($jsonData, 2900);

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '*Export Vendors JSON*\nCopiez le contenu ci-dessous :',
                ],
            ],
            ['type' => 'divider'],
        ];

        foreach ($chunks as $index => $chunk) {
            $blocks[] = [
                'type' => 'section',
                'block_id' => "json_chunk_{$index}",
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "```{$chunk}```",
                ],
            ];
        }

        return [
            'type' => 'modal',
            'title' => [
                'type' => 'plain_text',
                'text' => 'Export Vendors',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'blocks' => $blocks,
        ];
    }

    /**
     * @param  array<Vendor>  $vendors
     */
    public function vendorsListModal(LunchSession $session, array $vendors, string $searchQuery = ''): array
    {
        $blocks = [
            [
                'type' => 'input',
                'block_id' => 'search',
                'dispatch_action' => true,
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => SlackAction::VendorsListSearch->value,
                    'placeholder' => ['type' => 'plain_text', 'text' => 'Rechercher un restaurant...'],
                    'initial_value' => $searchQuery,
                ],
                'label' => ['type' => 'plain_text', 'text' => 'Recherche'],
                'optional' => true,
            ],
            ['type' => 'divider'],
        ];

        if (empty($vendors)) {
            $blocks[] = [
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => '_Aucun restaurant trouve._'],
            ];
        } else {
            foreach ($vendors as $vendor) {
                $blocks = array_merge($blocks, $this->vendorListItem($vendor));
            }
        }

        return [
            'type' => 'modal',
            'callback_id' => 'vendors_list',
            'title' => ['type' => 'plain_text', 'text' => 'Restaurants'],
            'close' => ['type' => 'plain_text', 'text' => 'Fermer'],
            'private_metadata' => json_encode(['lunch_session_id' => $session->id], JSON_THROW_ON_ERROR),
            'blocks' => $blocks,
        ];
    }

    private function vendorListItem(Vendor $vendor): array
    {
        $emoji = $vendor->getEmojiMarkdown();

        return [
            [
                'type' => 'section',
                'block_id' => "vendor_{$vendor->id}",
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "{$emoji}*{$vendor->name}*",
                ],
                'accessory' => [
                    'type' => 'button',
                    'action_id' => SlackAction::VendorsListEdit->value,
                    'value' => (string) $vendor->id,
                    'text' => ['type' => 'plain_text', 'text' => 'Modifier'],
                ],
            ],
        ];
    }

    public function recapModal(VendorProposal $proposal, array $orders, array $totals): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant';
        $logoUrl = $vendor?->getLogoThumbUrl();

        $headerElements = [];
        if ($logoUrl) {
            $headerElements[] = ['type' => 'image', 'image_url' => $logoUrl, 'alt_text' => $vendorName];
        }
        $headerElements[] = ['type' => 'mrkdwn', 'text' => "*Recapitulatif - {$vendorName}*"];

        $blocks = [
            [
                'type' => 'context',
                'elements' => $headerElements,
            ],
            [
                'type' => 'divider',
            ],
        ];

        if (empty($orders)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '_Aucune commande pour le moment._',
                ],
            ];
        } else {
            foreach ($orders as $order) {
                $priceEstimated = $this->formatPrice($order->price_estimated !== null ? (float) $order->price_estimated : null);
                $priceFinal = $this->formatPrice($order->price_final !== null ? (float) $order->price_final : null);
                $description = strlen($order->description) > 50
                    ? substr($order->description, 0, 47).'...'
                    : $order->description;

                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "<@{$order->provider_user_id}>\n_{$description}_\nEstime: {$priceEstimated} | Final: {$priceFinal}",
                    ],
                ];
            }
        }

        $blocks[] = ['type' => 'divider'];
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Total estime:* {$totals['estimated']} EUR\n*Total final:* {$totals['final']} EUR",
            ],
        ];

        return [
            'type' => 'modal',
            'title' => [
                'type' => 'plain_text',
                'text' => 'Recapitulatif',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'blocks' => $blocks,
        ];
    }

    public function proposalModal(LunchSession $session, array $vendors): array
    {
        $options = array_map(function (Vendor $vendor) {
            return [
                'text' => [
                    'type' => 'plain_text',
                    'text' => $vendor->name,
                ],
                'value' => (string) $vendor->id,
            ];
        }, $vendors);

        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackProposalCreate->value,
            'private_metadata' => json_encode(['lunch_session_id' => $session->id], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Demarrer une commande',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Continuer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => [
                [
                    'type' => 'input',
                    'block_id' => 'enseigne',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Restaurant',
                    ],
                    'element' => [
                        'type' => 'static_select',
                        'action_id' => 'enseigne_id',
                        'options' => $options,
                    ],
                ],
                [
                    'type' => 'actions',
                    'block_id' => 'new_restaurant_action',
                    'elements' => [
                        [
                            'type' => 'button',
                            'action_id' => SlackAction::DashboardCreateProposal->value,
                            'value' => (string) $session->id,
                            'text' => [
                                'type' => 'plain_text',
                                'text' => 'Nouveau restaurant',
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'fulfillment',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Type',
                    ],
                    'element' => [
                        'type' => 'static_select',
                        'action_id' => 'fulfillment_type',
                        'initial_option' => [
                            'text' => ['type' => 'plain_text', 'text' => 'A Emporter'],
                            'value' => FulfillmentType::Pickup->value,
                        ],
                        'options' => [
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'A Emporter'],
                                'value' => FulfillmentType::Pickup->value,
                            ],
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Livraison'],
                                'value' => FulfillmentType::Delivery->value,
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'context',
                    'block_id' => 'mode_info',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '*Mode :* Commande groupee',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'deadline',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Deadline (indicative)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'deadline_time',
                        'initial_value' => '11:30',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'HH:MM',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'note',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Remarque (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'note',
                        'multiline' => true,
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Instructions particulieres...',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'help',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Besoin d\'aide ?',
                    ],
                    'element' => [
                        'type' => 'checkboxes',
                        'action_id' => 'help_requested',
                        'options' => [
                            [
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => 'Je suis tres occupe — quelqu\'un peut s\'en occuper ?',
                                ],
                                'value' => 'help_requested',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function addVendorModal(array $metadata = []): array
    {
        $modal = [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackEnseigneCreate->value,
            'title' => [
                'type' => 'plain_text',
                'text' => 'Ajouter une enseigne',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Ajouter',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => $this->vendorBlocks(),
        ];

        if (! empty($metadata)) {
            $modal['private_metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
        }

        return $modal;
    }

    public function editVendorModal(Vendor $vendor, array $metadata = []): array
    {
        $emoji = $vendor->getEmojiMarkdown();
        $headerBlock = [
            'type' => 'context',
            'elements' => [
                ['type' => 'mrkdwn', 'text' => "{$emoji}*{$vendor->name}*"],
            ],
        ];

        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackEnseigneUpdate->value,
            'private_metadata' => json_encode(array_merge(['vendor_id' => $vendor->id], $metadata), JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Modifier enseigne',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Enregistrer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => array_merge([$headerBlock], $this->vendorBlocks($vendor), [
                [
                    'type' => 'input',
                    'block_id' => 'active',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Statut',
                    ],
                    'element' => [
                        'type' => 'static_select',
                        'action_id' => 'active',
                        'options' => [
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Active'],
                                'value' => '1',
                            ],
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Inactive'],
                                'value' => '0',
                            ],
                        ],
                        'initial_option' => [
                            'text' => [
                                'type' => 'plain_text',
                                'text' => $vendor->active ? 'Active' : 'Inactive',
                            ],
                            'value' => $vendor->active ? '1' : '0',
                        ],
                    ],
                ],
            ]),
        ];
    }

    public function orderModal(VendorProposal $proposal, ?Order $order, bool $allowFinal, bool $isEdit): array
    {
        $blocks = [
            [
                'type' => 'input',
                'block_id' => 'description',
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Description',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'description',
                    'initial_value' => $order?->description ?? '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'price_estimated',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Prix estime',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'price_estimated',
                    'initial_value' => $order?->price_estimated ? (string) $order->price_estimated : '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'notes',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Notes',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'notes',
                    'multiline' => true,
                    'initial_value' => $order?->notes ?? '',
                ],
            ],
        ];

        if ($allowFinal) {
            $blocks[] = [
                'type' => 'input',
                'block_id' => 'price_final',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Prix final',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'price_final',
                    'initial_value' => $order?->price_final ? (string) $order->price_final : '',
                ],
            ];
        }

        if ($isEdit && $order) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'actions',
                'block_id' => 'danger_zone',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Supprimer ma commande',
                        ],
                        'action_id' => SlackAction::OrderDelete->value,
                        'value' => (string) $order->id,
                        'style' => 'danger',
                        'confirm' => [
                            'title' => [
                                'type' => 'plain_text',
                                'text' => 'Confirmer la suppression',
                            ],
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => 'Voulez-vous vraiment supprimer votre commande ? Cette action est irreversible.',
                            ],
                            'confirm' => [
                                'type' => 'plain_text',
                                'text' => 'Supprimer',
                            ],
                            'deny' => [
                                'type' => 'plain_text',
                                'text' => 'Annuler',
                            ],
                            'style' => 'danger',
                        ],
                    ],
                ],
            ];
        }

        $metadata = [
            'proposal_id' => $proposal->id,
            'lunch_session_id' => $proposal->lunch_session_id,
        ];

        if ($order) {
            $metadata['order_id'] = $order->id;
        }

        return [
            'type' => 'modal',
            'callback_id' => $isEdit ? SlackAction::CallbackOrderEdit->value : SlackAction::CallbackOrderCreate->value,
            'private_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => $isEdit ? 'Modifier commande' : 'Nouvelle commande',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => $isEdit ? 'Enregistrer' : 'Commander',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => $blocks,
        ];
    }

    public function delegateModal(VendorProposal $proposal, string $role): array
    {
        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackRoleDelegate->value,
            'private_metadata' => json_encode([
                'proposal_id' => $proposal->id,
                'lunch_session_id' => $proposal->lunch_session_id,
                'role' => $role,
            ], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Deleguer le role',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Deleguer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => [
                [
                    'type' => 'input',
                    'block_id' => 'delegate',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Choisir un membre',
                    ],
                    'element' => [
                        'type' => 'users_select',
                        'action_id' => 'user_id',
                    ],
                ],
            ],
        ];
    }

    public function proposalManageModal(VendorProposal $proposal, string $userId): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Restaurant';
        $logoUrl = $vendor?->getLogoThumbUrl();
        $isPickup = $proposal->fulfillment_type === FulfillmentType::Pickup;
        $orderCount = $proposal->orders_count ?? $proposal->orders()->count();

        $currentResponsible = $isPickup ? $proposal->runner_user_id : $proposal->orderer_user_id;
        $roleLabel = $isPickup ? 'Runner' : 'Orderer';
        $responsibleText = $currentResponsible ? "<@{$currentResponsible}>" : '_Non assigne_';

        $headerElements = [];
        if ($logoUrl) {
            $headerElements[] = ['type' => 'image', 'image_url' => $logoUrl, 'alt_text' => $vendorName];
        }
        $headerElements[] = ['type' => 'mrkdwn', 'text' => "*{$vendorName}*"];

        $blocks = [
            [
                'type' => 'context',
                'elements' => $headerElements,
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "{$roleLabel} : {$responsibleText}\nCommandes : {$orderCount}",
                ],
            ],
        ];

        $canTakeCharge = $currentResponsible === null;
        $isAlreadyInCharge = $proposal->hasRole($userId);

        if ($canTakeCharge && ! $isAlreadyInCharge) {
            $buttonText = $isPickup
                ? "Je m'occupe d'aller chercher"
                : "Je m'occupe de passer la commande";

            $blocks[] = [
                'type' => 'actions',
                'block_id' => 'take_charge_actions',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => $buttonText,
                        ],
                        'action_id' => SlackAction::ProposalTakeCharge->value,
                        'value' => (string) $proposal->id,
                        'style' => 'primary',
                    ],
                ],
            ];
        } elseif ($isAlreadyInCharge) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => '_Vous etes deja en charge de cette commande._',
                    ],
                ],
            ];
        } else {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => '_Un responsable est deja assigne._',
                    ],
                ],
            ];
        }

        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackProposalManage->value,
            'private_metadata' => json_encode([
                'proposal_id' => $proposal->id,
                'lunch_session_id' => $proposal->lunch_session_id,
            ], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Gerer la commande',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'blocks' => $blocks,
        ];
    }

    public function proposeRestaurantModal(LunchSession $session): array
    {
        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackRestaurantPropose->value,
            'private_metadata' => json_encode(['lunch_session_id' => $session->id], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Proposer un restaurant',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Continuer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => [
                // Restaurant name (required)
                [
                    'type' => 'input',
                    'block_id' => 'name',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Nom du restaurant',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'name',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Ex: Sushi Wasabi',
                        ],
                    ],
                ],
                // Website URL (optional)
                [
                    'type' => 'input',
                    'block_id' => 'url_website',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Website',
                    ],
                    'element' => [
                        'type' => 'url_text_input',
                        'action_id' => 'url_website',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'https://www.example.com',
                        ],
                    ],
                ],
                // Fulfillment types (checkboxes multi-select)
                [
                    'type' => 'input',
                    'block_id' => 'fulfillment_types',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Types disponibles',
                    ],
                    'element' => [
                        'type' => 'checkboxes',
                        'action_id' => 'fulfillment_types',
                        'initial_options' => [
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'A emporter'],
                                'value' => FulfillmentType::Pickup->value,
                            ],
                        ],
                        'options' => [
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'A emporter'],
                                'value' => FulfillmentType::Pickup->value,
                            ],
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Livraison'],
                                'value' => FulfillmentType::Delivery->value,
                            ],
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Sur place'],
                                'value' => FulfillmentType::OnSite->value,
                            ],
                        ],
                    ],
                ],
                // Allow individual orders (checkbox)
                [
                    'type' => 'input',
                    'block_id' => 'allow_individual',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Options de commande',
                    ],
                    'element' => [
                        'type' => 'checkboxes',
                        'action_id' => 'allow_individual_order',
                        'options' => [
                            [
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => 'Autoriser les commandes individuelles',
                                ],
                                'value' => 'allow_individual',
                            ],
                        ],
                    ],
                ],
                // Deadline (indicative)
                [
                    'type' => 'input',
                    'block_id' => 'deadline',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Deadline (indicatif)',
                    ],
                    'hint' => [
                        'type' => 'plain_text',
                        'text' => 'Information indicative, n\'impacte pas la commande',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'deadline_time',
                        'initial_value' => '11:30',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'HH:MM',
                        ],
                    ],
                ],
                // Note (proposal)
                [
                    'type' => 'input',
                    'block_id' => 'note',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Remarque (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'note',
                        'multiline' => true,
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Instructions particulieres...',
                        ],
                    ],
                ],
                // Help requested (social signal)
                [
                    'type' => 'input',
                    'block_id' => 'help',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Besoin d\'aide ?',
                    ],
                    'element' => [
                        'type' => 'checkboxes',
                        'action_id' => 'help_requested',
                        'options' => [
                            [
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => 'Je suis tres occupe — quelqu\'un peut s\'en occuper ?',
                                ],
                                'value' => 'help_requested',
                            ],
                        ],
                    ],
                ],
                // File upload (logo)
                [
                    'type' => 'input',
                    'block_id' => 'file',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Logo',
                    ],
                    'element' => [
                        'type' => 'file_input',
                        'action_id' => 'file_upload',
                        'filetypes' => ['png', 'jpg', 'jpeg', 'pdf'],
                        'max_files' => 1,
                    ],
                ],
            ],
        ];
    }

    public function adjustPriceModal(VendorProposal $proposal, array $orders): array
    {
        $options = array_map(function (Order $order) {
            $label = $order->description;
            if (strlen($label) > 50) {
                $label = substr($label, 0, 47).'...';
            }
            $text = "<@{$order->provider_user_id}> - {$label}";

            return [
                'text' => [
                    'type' => 'plain_text',
                    'text' => $text,
                ],
                'value' => (string) $order->id,
            ];
        }, $orders);

        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackOrderAdjustPrice->value,
            'private_metadata' => json_encode([
                'proposal_id' => $proposal->id,
                'lunch_session_id' => $proposal->lunch_session_id,
            ], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Ajuster prix final',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Enregistrer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => [
                [
                    'type' => 'input',
                    'block_id' => 'order',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Commande',
                    ],
                    'element' => [
                        'type' => 'static_select',
                        'action_id' => 'order_id',
                        'options' => $options,
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'price_final',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Prix final',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'price_final',
                    ],
                ],
            ],
        ];
    }

    public function quickRunCreateModal(string $channelId): array
    {
        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackQuickRunCreate->value,
            'private_metadata' => json_encode(['channel_id' => $channelId], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Quick Run',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Lancer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => [
                [
                    'type' => 'input',
                    'block_id' => 'destination',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Ou allez-vous ?',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'destination',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Ex: Boulangerie du coin, Starbucks...',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'delay',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Delai (en minutes)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'delay_minutes',
                        'initial_value' => '10',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => '10',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'note',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Note (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'note',
                        'multiline' => true,
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Infos supplementaires...',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function quickRunRequestModal(QuickRun $quickRun, ?QuickRunRequest $existingRequest = null): array
    {
        $isEdit = $existingRequest !== null;

        $blocks = [
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "*Quick Run :* {$quickRun->destination} par <@{$quickRun->provider_user_id}>",
                    ],
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'description',
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Que voulez-vous ?',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'description',
                    'initial_value' => $existingRequest?->description ?? '',
                    'placeholder' => [
                        'type' => 'plain_text',
                        'text' => 'Ex: Un cafe latte, un croissant...',
                    ],
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'price_estimated',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Prix estime',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'price_estimated',
                    'initial_value' => $existingRequest?->price_estimated ? (string) $existingRequest->price_estimated : '',
                ],
            ],
        ];

        if ($isEdit && $existingRequest) {
            $blocks[] = ['type' => 'divider'];
            $blocks[] = [
                'type' => 'actions',
                'block_id' => 'danger_zone',
                'elements' => [
                    [
                        'type' => 'button',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Supprimer ma demande',
                        ],
                        'action_id' => SlackAction::QuickRunDeleteRequest->value,
                        'value' => (string) $existingRequest->id,
                        'style' => 'danger',
                        'confirm' => [
                            'title' => [
                                'type' => 'plain_text',
                                'text' => 'Confirmer la suppression',
                            ],
                            'text' => [
                                'type' => 'mrkdwn',
                                'text' => 'Voulez-vous vraiment supprimer votre demande ?',
                            ],
                            'confirm' => [
                                'type' => 'plain_text',
                                'text' => 'Supprimer',
                            ],
                            'deny' => [
                                'type' => 'plain_text',
                                'text' => 'Annuler',
                            ],
                            'style' => 'danger',
                        ],
                    ],
                ],
            ];
        }

        $metadata = ['quick_run_id' => $quickRun->id];
        if ($existingRequest) {
            $metadata['request_id'] = $existingRequest->id;
        }

        return [
            'type' => 'modal',
            'callback_id' => $isEdit ? SlackAction::CallbackQuickRunRequestEdit->value : SlackAction::CallbackQuickRunRequestCreate->value,
            'private_metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => $isEdit ? 'Modifier demande' : 'Ajouter une demande',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => $isEdit ? 'Enregistrer' : 'Ajouter',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => $blocks,
        ];
    }

    public function quickRunBlocks(QuickRun $quickRun, int $requestCount, array $requesterNames = []): array
    {
        $deadline = $this->formatTime($quickRun->deadline_at);
        $statusLabel = match ($quickRun->status) {
            QuickRunStatus::Open => 'Ouvert',
            QuickRunStatus::Locked => 'Verrouille',
            QuickRunStatus::Closed => 'Cloture',
        };

        $text = "*Quick Run*\n<@{$quickRun->provider_user_id}> va chez *{$quickRun->destination}*\nDeadline: {$deadline} | Statut: {$statusLabel}";

        if ($quickRun->note) {
            $text .= "\n:memo: _{$quickRun->note}_";
        }

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ],
        ];

        $requestersText = $requestCount > 0
            ? "Demandes: {$requestCount}".(! empty($requesterNames) ? ' ('.implode(', ', $requesterNames).')' : '')
            : 'Aucune demande pour le moment.';

        $blocks[] = [
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'mrkdwn',
                    'text' => $requestersText,
                ],
            ],
        ];

        if ($quickRun->isOpen()) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => [
                    $this->button('Ajouter une demande', SlackAction::QuickRunAddRequest->value, (string) $quickRun->id, 'primary'),
                    $this->button('Je pars', SlackAction::QuickRunLock->value, (string) $quickRun->id, 'danger'),
                ],
            ];
        } elseif ($quickRun->isLocked()) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => [
                    $this->button('Recapitulatif', SlackAction::QuickRunRecap->value, (string) $quickRun->id),
                    $this->button('Ajuster prix', SlackAction::QuickRunAdjustPrices->value, (string) $quickRun->id),
                    $this->button('Cloturer', SlackAction::QuickRunClose->value, (string) $quickRun->id, 'danger'),
                ],
            ];
        }

        return $blocks;
    }

    public function quickRunRecapModal(QuickRun $quickRun, array $requests, array $totals): array
    {
        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*Recapitulatif Quick Run*\nDestination: {$quickRun->destination}",
                ],
            ],
            ['type' => 'divider'],
        ];

        if (empty($requests)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '_Aucune demande._',
                ],
            ];
        } else {
            foreach ($requests as $request) {
                $priceEstimated = $this->formatPrice($request->price_estimated !== null ? (float) $request->price_estimated : null);
                $priceFinal = $this->formatPrice($request->price_final !== null ? (float) $request->price_final : null);

                $blocks[] = [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "<@{$request->provider_user_id}>\n_{$request->description}_\nEstime: {$priceEstimated} | Final: {$priceFinal}",
                    ],
                ];
            }
        }

        $blocks[] = ['type' => 'divider'];
        $blocks[] = [
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => "*Total estime:* {$totals['estimated']} EUR\n*Total final:* {$totals['final']} EUR",
            ],
        ];

        return [
            'type' => 'modal',
            'title' => [
                'type' => 'plain_text',
                'text' => 'Recap Quick Run',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'blocks' => $blocks,
        ];
    }

    public function quickRunAdjustPricesModal(QuickRun $quickRun, array $requests): array
    {
        $options = array_map(function (QuickRunRequest $request) {
            $label = $request->description;
            if (strlen($label) > 50) {
                $label = substr($label, 0, 47).'...';
            }
            $text = "<@{$request->provider_user_id}> - {$label}";

            return [
                'text' => [
                    'type' => 'plain_text',
                    'text' => $text,
                ],
                'value' => (string) $request->id,
            ];
        }, $requests);

        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackQuickRunClose->value,
            'private_metadata' => json_encode(['quick_run_id' => $quickRun->id], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Ajuster et cloturer',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Cloturer',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Annuler',
            ],
            'blocks' => [
                [
                    'type' => 'input',
                    'block_id' => 'request',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Demande',
                    ],
                    'element' => [
                        'type' => 'static_select',
                        'action_id' => 'request_id',
                        'options' => $options,
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'price_final',
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Prix final',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'price_final',
                    ],
                ],
            ],
        ];
    }

    public function errorModal(string $title, string $message): array
    {
        return [
            'type' => 'modal',
            'title' => [
                'type' => 'plain_text',
                'text' => mb_substr($title, 0, 24),
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => ":warning: *{$title}*",
                    ],
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => $message,
                    ],
                ],
            ],
        ];
    }

    private function vendorBlocks(?Vendor $vendor = null): array
    {
        $fulfillmentTypes = $vendor?->fulfillment_types ?? ['pickup'];

        return [
            [
                'type' => 'input',
                'block_id' => 'name',
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Nom',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'name',
                    'initial_value' => $vendor?->name ?? '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'cuisine_type',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Type de cuisine',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'cuisine_type',
                    'initial_value' => $vendor?->cuisine_type ?? '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'url_website',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Site web',
                ],
                'element' => [
                    'type' => 'url_text_input',
                    'action_id' => 'url_website',
                    'initial_value' => $vendor?->url_website ?? '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'url_menu',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'URL du menu',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'url_menu',
                    'initial_value' => $vendor?->url_menu ?? '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'fulfillment_types',
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Types disponibles',
                ],
                'element' => [
                    'type' => 'checkboxes',
                    'action_id' => 'fulfillment_types',
                    'initial_options' => $this->fulfillmentInitialOptions($fulfillmentTypes),
                    'options' => [
                        [
                            'text' => ['type' => 'plain_text', 'text' => 'A emporter'],
                            'value' => FulfillmentType::Pickup->value,
                        ],
                        [
                            'text' => ['type' => 'plain_text', 'text' => 'Livraison'],
                            'value' => FulfillmentType::Delivery->value,
                        ],
                        [
                            'text' => ['type' => 'plain_text', 'text' => 'Sur place'],
                            'value' => FulfillmentType::OnSite->value,
                        ],
                    ],
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'allow_individual',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Options',
                ],
                'element' => [
                    'type' => 'checkboxes',
                    'action_id' => 'allow_individual_order',
                    'initial_options' => $vendor?->allow_individual_order ? [
                        [
                            'text' => ['type' => 'mrkdwn', 'text' => 'Autoriser les commandes individuelles'],
                            'value' => 'allow_individual',
                        ],
                    ] : [],
                    'options' => [
                        [
                            'text' => ['type' => 'mrkdwn', 'text' => 'Autoriser les commandes individuelles'],
                            'value' => 'allow_individual',
                        ],
                    ],
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'notes',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Notes',
                ],
                'element' => [
                    'type' => 'plain_text_input',
                    'action_id' => 'notes',
                    'multiline' => true,
                    'initial_value' => $vendor?->notes ?? '',
                ],
            ],
            [
                'type' => 'input',
                'block_id' => 'file',
                'optional' => true,
                'label' => [
                    'type' => 'plain_text',
                    'text' => 'Logo',
                ],
                'element' => [
                    'type' => 'file_input',
                    'action_id' => 'file_upload',
                    'filetypes' => ['png', 'jpg', 'jpeg'],
                    'max_files' => 1,
                ],
            ],
        ];
    }

    private function fulfillmentInitialOptions(array $types): array
    {
        $options = [];

        foreach ($types as $type) {
            $enum = FulfillmentType::tryFrom($type);
            if ($enum) {
                $options[] = [
                    'text' => ['type' => 'plain_text', 'text' => $this->fulfillmentLabel($enum)],
                    'value' => $type,
                ];
            }
        }

        return $options;
    }
}
