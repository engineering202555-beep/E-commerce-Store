<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class InventoryLockService
{
    private string $stateFile;
    private string $lockFile;

    public function __construct()
    {
        $baseDir = storage_path('app/parallel-demo');

        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        $this->stateFile = $baseDir . '/inventory_state.json';
        $this->lockFile = $baseDir . '/inventory.lock';

        if (! file_exists($this->stateFile)) {
            $this->writeState($this->initialState());
        }
    }

    public function reset(int $stock = 10, int $balance = 1000): array
    {
        $state = [
            'stock' => max(0, $stock),
            'balance' => max(0, $balance),
            'orders' => [],
            'last_reset_at' => now()->toDateTimeString(),
        ];

        $this->writeState($state);

        return [
            'message' => 'Demo inventory state was reset.',
            'state' => $state,
        ];
    }

    public function state(): array
    {
        return $this->readState();
    }

    public function unsafePurchase(int $quantity = 1): array
    {
        $quantity = max(1, min($quantity, 20));
        $startedAt = microtime(true);
        $before = $this->readState();

        // Intentional delay to make race conditions visible under concurrent requests.
        usleep(200000);

        $after = $before;

        if ($after['stock'] >= $quantity) {
            $after['stock'] -= $quantity;
            $after['orders'][] = [
                'id' => uniqid('unsafe_', true),
                'quantity' => $quantity,
                'status' => 'created_without_lock',
                'process_id' => getmypid(),
                'created_at' => now()->toDateTimeString(),
            ];

            $this->writeState($after);
        }

        return [
            'requirement' => '7 - Control Concurrency without lock demo',
            'lock_used' => false,
            'process_id' => getmypid(),
            'before_stock' => $before['stock'],
            'after_stock' => $after['stock'],
            'risk' => 'Concurrent requests can overwrite each other because this method reads and writes without a shared lock.',
            'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }

    public function lockedPurchase(int $quantity = 1): array
    {
        $quantity = max(1, min($quantity, 20));
        $startedAt = microtime(true);

        return $this->withSharedLock(function () use ($quantity, $startedAt) {
            $before = $this->readState();
            $after = $before;

            // Intentional delay while the lock is held, proving other instances must wait.
            usleep(200000);

            if ($after['stock'] < $quantity) {
                return [
                    'requirement' => '7 - Pessimistic / Distributed Locking',
                    'lock_used' => true,
                    'shared_lock_file' => $this->lockFile,
                    'process_id' => getmypid(),
                    'status' => 'rejected_not_enough_stock',
                    'before_stock' => $before['stock'],
                    'after_stock' => $after['stock'],
                    'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                ];
            }

            $after['stock'] -= $quantity;
            $after['orders'][] = [
                'id' => uniqid('locked_', true),
                'quantity' => $quantity,
                'status' => 'created_with_lock',
                'process_id' => getmypid(),
                'created_at' => now()->toDateTimeString(),
            ];

            $this->writeState($after);

            Log::channel('monitoring')->info('REQ7_LOCKED_PURCHASE', [
                'quantity' => $quantity,
                'before_stock' => $before['stock'],
                'after_stock' => $after['stock'],
                'process_id' => getmypid(),
            ]);

            return [
                'requirement' => '7 - Pessimistic / Distributed Locking',
                'lock_used' => true,
                'shared_lock_file' => $this->lockFile,
                'process_id' => getmypid(),
                'status' => 'success',
                'before_stock' => $before['stock'],
                'after_stock' => $after['stock'],
                'explanation' => 'A shared file lock is used, so all Laravel instances on different ports serialize access to the same inventory state.',
                'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        });
    }

    public function transactionPurchase(int $quantity = 1, bool $failAfterStock = false): array
    {
        $quantity = max(1, min($quantity, 20));
        $startedAt = microtime(true);

        return $this->withSharedLock(function () use ($quantity, $failAfterStock, $startedAt) {
            $before = $this->readState();
            $workingCopy = $before;
            $committed = false;
            $rollbackReason = null;

            try {
                if ($workingCopy['stock'] < $quantity) {
                    throw new \RuntimeException('Not enough stock');
                }

                // Step 1: update inventory in a working copy, not directly in persistent state.
                $workingCopy['stock'] -= $quantity;

                if ($failAfterStock) {
                    throw new \RuntimeException('Simulated payment failure after stock update');
                }

                // Step 2: payment/balance operation.
                $paymentAmount = 100 * $quantity;
                if ($workingCopy['balance'] < $paymentAmount) {
                    throw new \RuntimeException('Not enough customer balance');
                }
                $workingCopy['balance'] -= $paymentAmount;

                // Step 3: create order.
                $workingCopy['orders'][] = [
                    'id' => uniqid('tx_', true),
                    'quantity' => $quantity,
                    'amount' => $paymentAmount,
                    'status' => 'committed',
                    'process_id' => getmypid(),
                    'created_at' => now()->toDateTimeString(),
                ];

                // Commit all changes together.
                $this->writeState($workingCopy);
                $committed = true;
            } catch (\Throwable $e) {
                // Rollback: do not write the working copy to the shared state.
                $rollbackReason = $e->getMessage();
                $workingCopy = $before;
            }

            $after = $this->readState();

            Log::channel('monitoring')->info('REQ8_TRANSACTION_RESULT', [
                'committed' => $committed,
                'rollback_reason' => $rollbackReason,
                'before' => $before,
                'after' => $after,
                'process_id' => getmypid(),
            ]);

            return [
                'requirement' => '8 - ACID / Transaction Integrity',
                'lock_used' => true,
                'transaction_committed' => $committed,
                'rollback_reason' => $rollbackReason,
                'before_state' => $before,
                'after_state' => $after,
                'explanation' => $committed
                    ? 'All steps succeeded, so stock update, payment and order creation were committed together.'
                    : 'One step failed, so no partial changes were written. This simulates atomic rollback.',
                'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        });
    }

    private function withSharedLock(callable $callback): array
    {
        $handle = fopen($this->lockFile, 'c+');

        if (! $handle) {
            throw new \RuntimeException('Could not open shared lock file.');
        }

        try {
            flock($handle, LOCK_EX);
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function initialState(): array
    {
        return [
            'stock' => 10,
            'balance' => 1000,
            'orders' => [],
            'last_reset_at' => now()->toDateTimeString(),
        ];
    }

    private function readState(): array
    {
        if (! file_exists($this->stateFile)) {
            return $this->initialState();
        }

        $json = file_get_contents($this->stateFile);
        $state = json_decode($json ?: '', true);

        return is_array($state) ? $state : $this->initialState();
    }

    private function writeState(array $state): void
    {
        file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
