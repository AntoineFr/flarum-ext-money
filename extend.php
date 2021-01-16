<?php namespace AntoineFr\Money;

use Flarum\Extend;
use Flarum\Api\Serializer\UserSerializer;
use Illuminate\Contracts\Events\Dispatcher;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),
    
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),
    
    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->mutate(AddUserMoneyAttributes::class),

    (new Extend\Settings)
        ->serializeToForum('antoinefr-money.moneyname', 'antoinefr-money.moneyname')
        ->serializeToForum('antoinefr-money.moneyforpost', 'antoinefr-money.moneyforpost')
        ->serializeToForum('antoinefr-money.moneyfordiscussion', 'antoinefr-money.moneyfordiscussion')
        ->serializeToForum('antoinefr-money.postminimumlength', 'antoinefr-money.postminimumlength')
        ->serializeToForum('antoinefr-money.noshowzero', 'antoinefr-money.noshowzero'),
    
    function (Dispatcher $events) {
        $events->subscribe(Listeners\GiveMoney::class);
    }
];
