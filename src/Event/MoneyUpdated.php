<?php

namespace AntoineFr\Money\Event;

use Flarum\User\User;

class MoneyUpdated
{
    public $user;
    public $balanceDelta;
    public $source;
    public $sourceKey;
    public $sourceParams;
    public $actor;
    public $balanceBefore;
    public $balanceAfter;

    public function __construct(
        ?User $user = null,
        float $balanceDelta = 0,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null,
        ?float $balanceBefore = null,
        ?float $balanceAfter = null
    ) {
        $this->user = $user;
        $this->balanceDelta = $balanceDelta;
        $this->source = $source;
        $this->sourceKey = $sourceKey;
        $this->sourceParams = $sourceParams;
        $this->actor = $actor;
        $this->balanceBefore = $balanceBefore;
        $this->balanceAfter = $balanceAfter;
    }
}
