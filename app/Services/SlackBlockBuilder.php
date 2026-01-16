<?php

namespace App\Services;

use App\Enums\FulfillmentType;
use App\Models\Enseigne;
use App\Models\LunchDay;
use App\Models\LunchDayProposal;
use App\Models\Order;
use Carbon\CarbonInterface;

class SlackBlockBuilder
{
    public function dailyKickoffBlocks(LunchDay $day): array
    {
        $deadline = $this->formatTime($day->deadline_at);
        $date = $day->date->format('Y-m-d');

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
                    $this->button('Proposer une enseigne', SlackActions::OPEN_PROPOSAL_MODAL, (string) $day->id),
                    $this->button('Ajouter une enseigne', SlackActions::OPEN_ADD_ENSEIGNE_MODAL, (string) $day->id),
                    $this->button('Cloturer la journee', SlackActions::CLOSE_DAY, (string) $day->id, 'danger'),
                ],
            ],
        ];
    }

    public function proposalBlocks(LunchDayProposal $proposal, int $orderCount): array
    {
        $enseigne = $proposal->enseigne;
        $menu = $enseigne->url_menu ? "<{$enseigne->url_menu}|Menu>" : 'Menu indisponible';
        $runner = $proposal->runner_user_id ? "<@{$proposal->runner_user_id}>" : '_non assigne_';
        $orderer = $proposal->orderer_user_id ? "<@{$proposal->orderer_user_id}>" : '_non assigne_';
        $type = $proposal->fulfillment_type === FulfillmentType::Delivery ? 'Delivery' : 'Pickup';
        $platform = $proposal->platform ? "Plateforme: {$proposal->platform}" : 'Plateforme: -';

        return [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*{$enseigne->name}* ({$menu})\nType: {$type}\n{$platform}\nRunner: {$runner}\nOrderer: {$orderer}",
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
                    $this->button('Je suis runner', SlackActions::CLAIM_RUNNER, (string) $proposal->id),
                    $this->button('Je suis orderer', SlackActions::CLAIM_ORDERER, (string) $proposal->id),
                    $this->button('Je commande', SlackActions::OPEN_ORDER_MODAL, (string) $proposal->id, 'primary'),
                    $this->button('Modifier ma commande', SlackActions::OPEN_EDIT_ORDER_MODAL, (string) $proposal->id),
                    $this->button('Recapitulatif', SlackActions::OPEN_SUMMARY, (string) $proposal->id),
                ],
            ],
            [
                'type' => 'actions',
                'elements' => [
                    $this->button('Deleguer mon role', SlackActions::OPEN_DELEGATE_MODAL, (string) $proposal->id),
                    $this->button('Ajuster prix final', SlackActions::OPEN_ADJUST_PRICE_MODAL, (string) $proposal->id),
                    $this->button('Gerer enseigne', SlackActions::OPEN_MANAGE_ENSEIGNE_MODAL, (string) $proposal->id),
                ],
            ],
        ];
    }

    public function summaryBlocks(LunchDayProposal $proposal, array $orders, array $totals): array
    {
        $lines = [];
        foreach ($orders as $order) {
            $final = $order->price_final !== null ? number_format((float) $order->price_final, 2) : '-';
            $estimated = number_format((float) $order->price_estimated, 2);
            $lines[] = "- <@{$order->slack_user_id}>: {$order->description} (est: {$estimated}, final: {$final})";
        }

        if (empty($lines)) {
            $lines[] = '- Aucune commande pour le moment.';
        }

        $text = "*Recapitulatif*\n" . implode("\n", $lines);
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

    public function proposalModal(LunchDay $day, array $enseignes): array
    {
        $options = array_map(function (Enseigne $enseigne) {
            return [
                'text' => [
                    'type' => 'plain_text',
                    'text' => $enseigne->name,
                ],
                'value' => (string) $enseigne->id,
            ];
        }, $enseignes);

        return [
            'type' => 'modal',
            'callback_id' => SlackActions::CALLBACK_PROPOSAL_CREATE,
            'private_metadata' => json_encode(['lunch_day_id' => $day->id], JSON_THROW_ON_ERROR),
            'title' => [
                'type' => 'plain_text',
                'text' => 'Proposer une enseigne',
            ],
            'submit' => [
                'type' => 'plain_text',
                'text' => 'Proposer',
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
                        'text' => 'Enseigne',
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
                        'text' => 'Type de commande',
                    ],
                    'element' => [
                        'type' => 'static_select',
                        'action_id' => 'fulfillment_type',
                        'options' => [
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Pickup'],
                                'value' => FulfillmentType::Pickup->value,
                            ],
                            [
                                'text' => ['type' => 'plain_text', 'text' => 'Delivery'],
                                'value' => FulfillmentType::Delivery->value,
                            ],
                        ],
                    ],
                ],
                [
                    'type' => 'input',
                    'block_id' => 'platform',
                    'optional' => true,
                    'label' => [
                        'type' => 'plain_text',
                        'text' => 'Plateforme (optionnel)',
                    ],
                    'element' => [
                        'type' => 'plain_text_input',
                        'action_id' => 'platform',
                    ],
                ],
            ],
        ];
    }

    public function addEnseigneModal(array $metadata = []): array
    {
        $modal = [
            'type' => 'modal',
            'callback_id' => SlackActions::CALLBACK_ENSEIGNE_CREATE,
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
            'blocks' => $this->enseigneBlocks(),
        ];

        if (!empty($metadata)) {
            $modal['private_metadata'] = json_encode($metadata, JSON_THROW_ON_ERROR);
        }

        return $modal;
    }

    public function editEnseigneModal(Enseigne $enseigne, array $metadata = []): array
    {
        $modal = [
            'type' => 'modal',
            'callback_id' => SlackActions::CALLBACK_ENSEIGNE_UPDATE,
            'private_metadata' => json_encode(array_merge(['enseigne_id' => $enseigne->id], $metadata), JSON_THROW_ON_ERROR),
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
            'blocks' => array_merge($this->enseigneBlocks($enseigne), [
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
                                'text' => $enseigne->active ? 'Active' : 'Inactive',
                            ],
                            'value' => $enseigne->active ? '1' : '0',
                        ],
                    ],
                ],
            ]),
        ];

        return $modal;
    }

    public function orderModal(LunchDayProposal $proposal, ?Order $order, bool $allowFinal, bool $isEdit): array
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

        return [
            'type' => 'modal',
            'callback_id' => $isEdit ? SlackActions::CALLBACK_ORDER_EDIT : SlackActions::CALLBACK_ORDER_CREATE,
            'private_metadata' => json_encode([
                'proposal_id' => $proposal->id,
                'lunch_day_id' => $proposal->lunch_day_id,
            ], JSON_THROW_ON_ERROR),
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

    public function delegateModal(LunchDayProposal $proposal, string $role): array
    {
        return [
            'type' => 'modal',
            'callback_id' => SlackActions::CALLBACK_ROLE_DELEGATE,
            'private_metadata' => json_encode([
                'proposal_id' => $proposal->id,
                'lunch_day_id' => $proposal->lunch_day_id,
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

    public function adjustPriceModal(LunchDayProposal $proposal, array $orders): array
    {
        $options = array_map(function (Order $order) {
            $label = $order->description;
            if (strlen($label) > 50) {
                $label = substr($label, 0, 47) . '...';
            }
            $text = "<@{$order->slack_user_id}> - {$label}";

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
            'callback_id' => SlackActions::CALLBACK_ORDER_ADJUST_PRICE,
            'private_metadata' => json_encode([
                'proposal_id' => $proposal->id,
                'lunch_day_id' => $proposal->lunch_day_id,
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

    private function enseigneBlocks(?Enseigne $enseigne = null): array
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
                    'initial_value' => $enseigne?->name ?? '',
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
                    'initial_value' => $enseigne?->url_menu ?? '',
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
                    'initial_value' => $enseigne?->notes ?? '',
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
