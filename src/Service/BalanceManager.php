<?php

namespace AntoineFr\Money\Service;

use AntoineFr\Money\Event\MoneyUpdated;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManager
{
    public function __construct(
        private ConnectionInterface $connection,
        private Dispatcher $events
    ) {
    }

    public function adjustBalance(
        ?User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        $subject = null
    ): bool {
        if ($user === null || $balanceDelta === 0.0) {
            return false;
        }

        $this->connection->transaction(function () use ($user, $balanceDelta, $source, $sourceKey, $actor, $subject, $sourceParams) {
            $balanceBefore = (float) $user->money;
            $user->money += $balanceDelta;
            $user->save();

            $this->dispatchBalanceUpdated(
                $user,
                $balanceDelta,
                $source,
                $sourceKey,
                $sourceParams,
                $actor,
                $subject,
                $balanceBefore,
                (float) $user->money
            );
        });

        return true;
    }

    public function dispatchBalanceUpdated(
        User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        $subject = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ): void {
        $this->events->dispatch(new MoneyUpdated(
            $user,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            $subject,
            $balanceBefore,
            $balanceAfter
        ));
    }
}
