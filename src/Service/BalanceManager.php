<?php

namespace AntoineFr\Money\Service;

use AntoineFr\Money\Contract\BalanceHistoryRecorder;
use AntoineFr\Money\Event\MoneyUpdated;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManager
{
    public function __construct(
        private ConnectionInterface $connection,
        private Dispatcher $events,
        private ?BalanceHistoryRecorder $historyRecorder = null
    ) {
    }

    public function adjustBalance(
        ?User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null
    ): bool {
        if ($user === null || $balanceDelta === 0.0) {
            return false;
        }

        $balanceUpdatedEvent = null;
        $updated = (bool) $this->connection->transaction(function () use ($user, $balanceDelta, $source, $sourceKey, $actor, $sourceParams, &$balanceUpdatedEvent) {
            $lockedUser = $user->newQuery()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->first();

            if ($lockedUser === null) {
                return false;
            }

            $balanceBefore = (float) $lockedUser->money;
            $lockedUser->money = $balanceBefore + $balanceDelta;
            $lockedUser->save();

            $balanceAfter = (float) $lockedUser->money;
            $user->money = $balanceAfter;

            $this->recordBalanceUpdate(
                $lockedUser,
                $balanceDelta,
                $source,
                $sourceKey,
                $sourceParams,
                $actor,
                $balanceBefore,
                $balanceAfter
            );

            $balanceUpdatedEvent = $this->newBalanceUpdatedEvent(
                $lockedUser,
                $balanceDelta,
                $source,
                $sourceKey,
                $sourceParams,
                $actor,
                $balanceBefore,
                $balanceAfter
            );

            return true;
        });

        if ($updated && $balanceUpdatedEvent instanceof MoneyUpdated) {
            $this->events->dispatch($balanceUpdatedEvent);
        }

        return $updated;
    }

    public function recordAndDispatchBalanceUpdated(
        User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ): void {
        $this->recordBalanceUpdate(
            $user,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            $balanceBefore,
            $balanceAfter
        );

        $this->dispatchBalanceUpdated(
            $user,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            $balanceBefore,
            $balanceAfter
        );
    }

    public function dispatchBalanceUpdated(
        User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ): void {
        $this->events->dispatch($this->newBalanceUpdatedEvent(
            $user,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            $balanceBefore,
            $balanceAfter
        ));
    }

    private function recordBalanceUpdate(
        ?User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ): void {
        $this->historyRecorder?->record(
            $user,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            $balanceBefore,
            $balanceAfter
        );
    }

    private function newBalanceUpdatedEvent(
        ?User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ): MoneyUpdated {
        return new MoneyUpdated(
            $user,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            $balanceBefore,
            $balanceAfter
        );
    }
}
