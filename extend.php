<?php namespace AntoineFr\Money;

use Flarum\Extend;
use Illuminate\Contracts\Events\Dispatcher;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),
    
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),
    
    new Extend\Locales(__DIR__ . '/locale'),
    
    function (Dispatcher $events) {
        $events->subscribe(Listeners\LoadSettingsFromDatabase::class);
        $events->subscribe(Listeners\GiveMoney::class);
    }
];
