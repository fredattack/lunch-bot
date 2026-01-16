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

            if ($this->hasChanged($current, $value)) {
                $changes[$key] = ['from' => $current, 'to' => $value];
            }
        }

        return $changes;
    }

    private function hasChanged(mixed $current, mixed $value): bool
    {
        if ($current === $value) {
            return false;
        }

        if (is_numeric($current) && is_numeric($value)) {
            return bccomp((string) $current, (string) $value, 4) !== 0;
        }

        if ($current === null || $value === null) {
            return true;
        }

        return $current !== $value;
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
