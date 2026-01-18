<?php

namespace App\Services\Slack;

use App\Enums\FulfillmentType;
use App\Enums\SlackAction;
use App\Models\LunchSession;
use App\Models\Order;
use App\Models\Vendor;
use App\Models\VendorProposal;
use Carbon\CarbonInterface;

class SlackBlockBuilder
{
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
        $menu = $vendor->url_menu ? "<{$vendor->url_menu}|Menu>" : 'Menu indisponible';
        $runner = $proposal->runner_user_id ? "<@{$proposal->runner_user_id}>" : '_non assigne_';
        $orderer = $proposal->orderer_user_id ? "<@{$proposal->orderer_user_id}>" : '_non assigne_';
        $type = $proposal->fulfillment_type === FulfillmentType::Delivery ? 'Delivery' : 'Pickup';
        $platform = $proposal->platform ? "Plateforme: {$proposal->platform}" : 'Plateforme: -';

        return [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*{$vendor->name}* ({$menu})\nType: {$type}\n{$platform}\nRunner: {$runner}\nOrderer: {$orderer}",
                ],
            ],
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
        ];
    }

    public function summaryBlocks(VendorProposal $proposal, array $orders, array $totals): array
    {
        $lines = [];
        foreach ($orders as $order) {
            $final = $order->price_final !== null ? number_format((float) $order->price_final, 2) : '-';
            $estimated = number_format((float) $order->price_estimated, 2);
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
            'blocks' => array_merge($this->vendorBlocks($vendor), [
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
        $isPickup = $proposal->fulfillment_type === FulfillmentType::Pickup;
        $orderCount = $proposal->orders_count ?? $proposal->orders()->count();

        $currentResponsible = $isPickup ? $proposal->runner_user_id : $proposal->orderer_user_id;
        $roleLabel = $isPickup ? 'Runner' : 'Orderer';
        $responsibleText = $currentResponsible ? "<@{$currentResponsible}>" : '_Non assigne_';

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*{$vendorName}*\n{$roleLabel} : {$responsibleText}\nCommandes : {$orderCount}",
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
                [
                    'type' => 'input',
                    'block_id' => 'cuisine_type',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Type de cuisine (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'cuisine_type',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'Ex: Japonais, Italien, ...',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'url_website',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Site web (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'url_website',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'https://...',
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'url_menu',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Menu PDF (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'url_menu',
                        'placeholder' => [
                            'type' => 'plain_text',
                            'text' => 'https://...menu.pdf',
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

    /**
     * @param  array<int, VendorProposal>  $proposals
     */
    public function lunchDashboardModal(LunchSession $session, array $proposals, ?Order $userOrder, bool $canClose): array
    {
        $date = $session->date->format('d/m');
        $deadline = $this->formatTime($session->deadline_at);
        $statusLabel = $session->isOpen() ? 'Ouverte' : ($session->isLocked() ? 'Verrouillee' : 'Fermee');

        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => "Lunch - session du {$date}",
                ],
            ],
            [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "Statut: *{$statusLabel}* | Deadline: {$deadline}",
                    ],
                ],
            ],
            [
                'type' => 'divider',
            ],
        ];

        if (empty($proposals)) {
            $blocks[] = [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => '_Aucune proposition pour le moment._',
                ],
            ];
        } else {
            foreach ($proposals as $proposal) {
                $blocks = array_merge($blocks, $this->dashboardProposalBlocks($proposal, $session));
            }
        }

        $blocks[] = [
            'type' => 'divider',
        ];

        $actionElements = [];

        if ($session->isOpen()) {
            $actionElements[] = $this->button('Proposer un restaurant', SlackAction::DashboardProposeVendor->value, (string) $session->id, 'primary');
            $actionElements[] = $this->button('Choisir un favori', SlackAction::DashboardChooseFavorite->value, (string) $session->id);
        }

        if ($userOrder) {
            $actionElements[] = $this->button('Ma commande', SlackAction::DashboardMyOrder->value, (string) $userOrder->vendor_proposal_id);
        }

        if ($canClose && ! $session->isClosed()) {
            $actionElements[] = $this->button('Cloturer la session', SlackAction::DashboardCloseSession->value, (string) $session->id, 'danger');
        }

        if (! empty($actionElements)) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => $actionElements,
            ];
        }

        return [
            'type' => 'modal',
            'callback_id' => SlackAction::CallbackLunchDashboard->value,
            'title' => [
                'type' => 'plain_text',
                'text' => 'Lunch Dashboard',
            ],
            'close' => [
                'type' => 'plain_text',
                'text' => 'Fermer',
            ],
            'blocks' => $blocks,
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

    private function dashboardProposalBlocks(VendorProposal $proposal, LunchSession $session): array
    {
        $vendor = $proposal->vendor;
        $vendorName = $vendor?->name ?? 'Enseigne inconnue';
        $responsible = $proposal->runner_user_id
            ? "<@{$proposal->runner_user_id}>"
            : ($proposal->orderer_user_id ? "<@{$proposal->orderer_user_id}>" : '_non assigne_');
        $orderCount = $proposal->orders_count ?? $proposal->orders->count();

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*{$vendorName}*\nResponsable: {$responsible} | Commandes: {$orderCount}",
                ],
            ],
        ];

        $actionElements = [];

        if ($session->isOpen()) {
            $actionElements[] = $this->button('Commander ici', SlackAction::DashboardOrderHere->value, (string) $proposal->id, 'primary');

            if (! $proposal->runner_user_id && ! $proposal->orderer_user_id) {
                $actionElements[] = $this->button('Je prends en charge', SlackAction::DashboardClaimResponsible->value, (string) $proposal->id);
            }
        }

        if ($orderCount > 0) {
            $actionElements[] = $this->button('Voir commandes', SlackAction::DashboardViewOrders->value, (string) $proposal->id);
        }

        if (! empty($actionElements)) {
            $blocks[] = [
                'type' => 'actions',
                'elements' => $actionElements,
            ];
        }

        return $blocks;
    }

    private function vendorBlocks(?Vendor $vendor = null): array
    {
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
        ];
    }

    private function button(string $text, string $actionId, string $value, ?string $style = null): array
    {
        $button = [
            'type' => 'button',
            'text' => [
                'type' => 'plain_text',
                'text' => $text,
            ],
            'action_id' => $actionId,
            'value' => $value,
        ];

        if ($style) {
            $button['style'] = $style;
        }

        return $button;
    }

    private function formatTime(?CarbonInterface $dateTime): string
    {
        return $dateTime ? $dateTime->format('H:i') : '-';
    }
}
