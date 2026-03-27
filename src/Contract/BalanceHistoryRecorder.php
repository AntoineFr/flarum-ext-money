<?php

namespace AntoineFr\Money\Contract;

use Flarum\User\User;

interface BalanceHistoryRecorder
{
    public function record(
        ?User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ): void;
}
