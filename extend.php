<?php

namespace AntoineFr\Money;

use Flarum\Extend;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;

$extend = [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(AddUserMoneyAttributes::class),

    (new Extend\Settings())
        ->serializeToForum('antoinefr-money.moneyname', 'antoinefr-money.moneyname')
        ->serializeToForum('antoinefr-money.noshowzero', 'antoinefr-money.noshowzero'),

    (new Extend\Event())
        ->subscribe(Listeners\MoneyBalanceSubscriber::class),
];

if (class_exists('Flarum\Likes\Event\PostWasLiked')) {
    $extend[] =
        (new Extend\Event())
            ->listen(PostWasLiked::class, [Listeners\MoneyBalanceSubscriber::class, 'postWasLiked'])
            ->listen(PostWasUnliked::class, [Listeners\MoneyBalanceSubscriber::class, 'postWasUnliked'])
    ;
}

if (class_exists('Askvortsov\AutoModerator\Extend\AutoModerator')) {
    $extend[] =
        (new \Askvortsov\AutoModerator\Extend\AutoModerator())
            ->metricDriver('money', AutoModerator\Metric\Money::class)
            ->actionDriver('money', AutoModerator\Action\Money::class)
        ;
}

return $extend;
