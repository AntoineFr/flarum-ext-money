<?php

namespace AntoineFr\Money\Service;

use AntoineFr\Money\Contract\BalanceHistoryRecorder;
use AntoineFr\Money\Event\MoneyUpdated;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManager
{
    public function __construct(
        private ConnectionInterface $connection,
        private Dispatcher $events,
        private ExtensionManager $extensions,
        private Container $container
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

    public function adjustBalances(
        array $users,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null
    ): int {
        if ($balanceDelta === 0.0) {
            return 0;
        }

        $userIds = [];
        $usersById = [];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            $userIds[(int) $user->id] = (int) $user->id;
            $usersById[(int) $user->id] = $user;
        }

        if ($userIds === []) {
            return 0;
        }

        sort($userIds);

        $balanceUpdatedEvents = [];
        $updatedCount = (int) $this->connection->transaction(function () use (
            $userIds,
            $usersById,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor,
            &$balanceUpdatedEvents
        ) {
            $lockedUsers = User::query()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedUsers->isEmpty()) {
                return 0;
            }

            foreach ($lockedUsers as $lockedUser) {
                $balanceBefore = (float) $lockedUser->money;
                $lockedUser->money = $balanceBefore + $balanceDelta;
                $lockedUser->save();

                $balanceAfter = (float) $lockedUser->money;

                if (isset($usersById[(int) $lockedUser->id])) {
                    $usersById[(int) $lockedUser->id]->money = $balanceAfter;
                }

                $balanceUpdatedEvents[] = $this->newBalanceUpdatedEvent(
                    $lockedUser,
                    $balanceDelta,
                    $source,
                    $sourceKey,
                    $sourceParams,
                    $actor,
                    $balanceBefore,
                    $balanceAfter
                );
            }

            $this->recordBalanceUpdates(
                $lockedUsers->all(),
                $balanceDelta,
                $source,
                $sourceKey,
                $sourceParams,
                $actor
            );

            return count($balanceUpdatedEvents);
        });

        foreach ($balanceUpdatedEvents as $balanceUpdatedEvent) {
            $this->events->dispatch($balanceUpdatedEvent);
        }

        return $updatedCount;
    }

    public function transferBalance(
        ?User $fromUser,
        ?User $toUser,
        float $amount,
        string $source = '',
        string $fromSourceKey = '',
        string $toSourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?callable $withinTransaction = null
    ): bool {
        if ($toUser === null || $amount === 0.0) {
            return false;
        }

        $balanceUpdatedEvents = [];

        $updated = (bool) $this->connection->transaction(function () use (
            $fromUser,
            $toUser,
            $amount,
            $source,
            $fromSourceKey,
            $toSourceKey,
            $sourceParams,
            $actor,
            $withinTransaction,
            &$balanceUpdatedEvents
        ) {
            $userIds = [(int) $toUser->id];

            if ($fromUser !== null) {
                $userIds[] = (int) $fromUser->id;
            }

            $lockedUsers = User::query()
                ->whereIn('id', array_values(array_unique($userIds)))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedFromUser = $fromUser ? $lockedUsers->get((int) $fromUser->id) : null;
            $lockedToUser = $lockedUsers->get((int) $toUser->id);

            if ($lockedToUser === null) {
                return false;
            }

            if ($fromUser !== null) {
                if ($lockedFromUser === null) {
                    return false;
                }

                if ((float) $lockedFromUser->money < $amount) {
                    return false;
                }

                $fromBalanceBefore = (float) $lockedFromUser->money;
                $lockedFromUser->money = $fromBalanceBefore - $amount;
                $lockedFromUser->save();

                $fromBalanceAfter = (float) $lockedFromUser->money;
                $fromUser->money = $fromBalanceAfter;

                $this->recordBalanceUpdate(
                    $lockedFromUser,
                    -$amount,
                    $source,
                    $fromSourceKey,
                    $sourceParams,
                    $actor,
                    $fromBalanceBefore,
                    $fromBalanceAfter
                );

                $balanceUpdatedEvents[] = $this->newBalanceUpdatedEvent(
                    $lockedFromUser,
                    -$amount,
                    $source,
                    $fromSourceKey,
                    $sourceParams,
                    $actor,
                    $fromBalanceBefore,
                    $fromBalanceAfter
                );
            }

            $toBalanceBefore = (float) $lockedToUser->money;
            $lockedToUser->money = $toBalanceBefore + $amount;
            $lockedToUser->save();

            $toBalanceAfter = (float) $lockedToUser->money;
            $toUser->money = $toBalanceAfter;

            $this->recordBalanceUpdate(
                $lockedToUser,
                $amount,
                $source,
                $toSourceKey,
                $sourceParams,
                $actor,
                $toBalanceBefore,
                $toBalanceAfter
            );

            $balanceUpdatedEvents[] = $this->newBalanceUpdatedEvent(
                $lockedToUser,
                $amount,
                $source,
                $toSourceKey,
                $sourceParams,
                $actor,
                $toBalanceBefore,
                $toBalanceAfter
            );

            if ($withinTransaction !== null) {
                $withinTransaction($lockedFromUser, $lockedToUser);
            }

            return true;
        });

        if ($updated) {
            foreach ($balanceUpdatedEvents as $balanceUpdatedEvent) {
                $this->events->dispatch($balanceUpdatedEvent);
            }
        }

        return $updated;
    }

    public function syncPersistedBalanceChange(
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
        if (! $this->extensions->isEnabled('mattoid-money-history')) {
            return;
        }

        $historyRecorder = $this->container->make(BalanceHistoryRecorder::class);

        $historyRecorder->record(
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

    private function recordBalanceUpdates(
        array $users,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null
    ): void {
        if (! $this->extensions->isEnabled('mattoid-money-history')) {
            return;
        }

        $historyRecorder = $this->container->make(BalanceHistoryRecorder::class);

        $historyRecorder->recordMany(
            $users,
            $balanceDelta,
            $source,
            $sourceKey,
            $sourceParams,
            $actor
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
