<?php

namespace AntoineFr\Money\Event;

use Flarum\User\User;

class MoneyUpdated
{
    public ?User $user;

    public function __construct(?User $user = null)
    {
        $this->user = $user;
    }
}
