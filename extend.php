<?php namespace AntoineFr\Money;

use Flarum\Extend;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\User\Event\Saving;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js'),
    
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),
    
    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(AddUserMoneyAttributes::class),

    (new Extend\Settings)
        ->serializeToForum('antoinefr-money.moneyname', 'antoinefr-money.moneyname')
        ->serializeToForum('antoinefr-money.moneyforpost', 'antoinefr-money.moneyforpost')
        ->serializeToForum('antoinefr-money.moneyfordiscussion', 'antoinefr-money.moneyfordiscussion')
        ->serializeToForum('antoinefr-money.postminimumlength', 'antoinefr-money.postminimumlength')
        ->serializeToForum('antoinefr-money.noshowzero', 'antoinefr-money.noshowzero'),
    
    (new Extend\Event())
        ->listen(Posted::class, [Listeners\GiveMoney::class, 'postWasPosted'])
        ->listen(PostRestored::class, [Listeners\GiveMoney::class, 'postWasRestored'])
        ->listen(PostHidden::class, [Listeners\GiveMoney::class, 'postWasHidden'])
        ->listen(Started::class, [Listeners\GiveMoney::class, 'discussionWasStarted'])
        ->listen(DiscussionRestored::class, [Listeners\GiveMoney::class, 'discussionWasRestored'])
        ->listen(DiscussionHidden::class, [Listeners\GiveMoney::class, 'discussionWasHidden'])
        ->listen(Saving::class, [Listeners\GiveMoney::class, 'userWillBeSaved'])
];
