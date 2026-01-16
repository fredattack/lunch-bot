<?php

namespace App\Actions\Lunch;

use App\Models\Order;

class UpdateOrder
{
    /**
     * @param  array{description?: string, price_estimated?: float, price_final?: float|null, notes?: string|null}  $data
     */
    public function handle(Order $order, array $data, string $actorId): Order
    {
        $changes = $this->detectChanges($order, $data);

        $order->fill($data);

        if (! empty($changes)) {
            $this->appendAuditLog($order, $actorId, $changes);
        }

        $order->save();

        return $order;
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function detectChanges(Order $order, array $data): array
    {
        $changes = [];

        foreach ($data as $key => $value) {
            $current = $order->{$key};

            if (is_float($value) && $current !== null) {
                $current = (float) $current;
            }

            if ($current !== $value) {
                $changes[$key] = ['from' => $order->{$key}, 'to' => $value];
            }
        }

        return $changes;
    }

    private function appendAuditLog(Order $order, string $actorId, array $changes): void
    {
        $log = $order->audit_log ?? [];
        $log[] = [
            'at' => now()->toIso8601String(),
            'by' => $actorId,
            'changes' => $changes,
        ];
        $order->audit_log = $log;
    }
}
