<?php

namespace AntoineFr\Money;

use Flarum\Extend;
use Flarum\Api\Serializer\UserSerializer;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\User\Event\Saving;
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
        ->listen(Posted::class, [Listeners\GiveMoney::class, 'postWasPosted'])
        ->listen(PostRestored::class, [Listeners\GiveMoney::class, 'postWasRestored'])
        ->listen(PostHidden::class, [Listeners\GiveMoney::class, 'postWasHidden'])
        ->listen(PostDeleted::class, [Listeners\GiveMoney::class, 'postWasDeleted'])
        ->listen(Started::class, [Listeners\GiveMoney::class, 'discussionWasStarted'])
        ->listen(DiscussionRestored::class, [Listeners\GiveMoney::class, 'discussionWasRestored'])
        ->listen(DiscussionHidden::class, [Listeners\GiveMoney::class, 'discussionWasHidden'])
        ->listen(DiscussionDeleted::class, [Listeners\GiveMoney::class, 'discussionWasDeleted'])
        ->listen(Saving::class, [Listeners\GiveMoney::class, 'userWillBeSaved']),
];

if (class_exists('Flarum\Likes\Event\PostWasLiked')) {
    $extend[] =
        (new Extend\Event())
            ->listen(PostWasLiked::class, [Listeners\GiveMoney::class, 'postWasLiked'])
            ->listen(PostWasUnliked::class, [Listeners\GiveMoney::class, 'postWasUnliked'])
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
