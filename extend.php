<?php

namespace AntoineFr\Money;

use AntoineFr\Money\Listeners\GiveMoney;
use Flarum\Extend;
use Flarum\Api\Context;
use Flarum\Api\Resource;
use Flarum\Api\Schema;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ApiResource(Resource\UserResource::class))
        ->fields(fn () => [
            Schema\Number::make('money')
                ->writable(),
            Schema\Boolean::make('canEditMoney')
                ->get(fn ($model, Context $context) => $context->getActor()->can('edit_money', $model)),
        ]),

    (new Extend\Settings())
        ->serializeToForum('antoinefr-money.moneyname', 'antoinefr-money.moneyname')
        ->serializeToForum('antoinefr-money.noshowzero', 'antoinefr-money.noshowzero'),

    (new Extend\Event())
        ->subscribe(GiveMoney::class),
];

/*
if (class_exists('Askvortsov\AutoModerator\Extend\AutoModerator')) {
    $extend[] =
        (new \Askvortsov\AutoModerator\Extend\AutoModerator())
            ->metricDriver('money', AutoModerator\Metric\Money::class)
            ->actionDriver('money', AutoModerator\Action\Money::class)
        ;
}
*/
