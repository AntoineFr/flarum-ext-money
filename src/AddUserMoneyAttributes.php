<?php

namespace AntoineFr\Money;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserMoneyAttributes
{
    public function __invoke(UserSerializer $serializer, User $user)
    {
        $canEditMoney = $serializer->getActor()->can('edit_money', $user);

        $attributes = [];

        $attributes['money'] = $user->money;
        $attributes['canEditMoney'] = $canEditMoney;

        return $attributes;
    }
}
