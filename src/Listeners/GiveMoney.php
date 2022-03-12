<?php

namespace AntoineFr\Money\Listeners;

use Illuminate\Support\Arr;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Flarum\User\User;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\User\Event\Saving;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;
use AntoineFr\Money\Event\MoneyUpdated;

class GiveMoney
{
    protected $settings;
    protected $events;

    public function __construct(SettingsRepositoryInterface $settings, Dispatcher $events)
    {
        $this->settings = $settings;
        $this->events = $events;
    }

    public function giveMoney(?User $user, $money)
    {
        if (!is_null($user)) {
            $money = (float)$money;

            $user->money += $money;
            $user->save();

            $this->events->dispatch(new MoneyUpdated($user));
        }
    }

    public function postWasPosted(Posted $event)
    {
        // If it's not the first post of a discussion
        if ($event->post['number'] > 1) {
            $minimumLength = (int)$this->settings->get('antoinefr-money.postminimumlength', 0);

            if (strlen($event->post->content) >= $minimumLength) {
                $money = (float)$this->settings->get('antoinefr-money.moneyforpost', 0);
                $this->giveMoney($event->actor, $money);
            }
        }
    }

    public function postWasRestored(PostRestored $event)
    {
        $minimumLength = (int)$this->settings->get('antoinefr-money.postminimumlength', 0);

        if (strlen($event->post->content) >= $minimumLength) {
            $money = (float)$this->settings->get('antoinefr-money.moneyforpost', 0);
            $this->giveMoney($event->post->user, $money);
        }
    }

    public function postWasHidden(PostHidden $event)
    {
        $minimumLength = (int)$this->settings->get('antoinefr-money.postminimumlength', 0);

        if (strlen($event->post->content) >= $minimumLength) {
            $money = (float)$this->settings->get('antoinefr-money.moneyforpost', 0);
            $this->giveMoney($event->post->user, -$money);
        }
    }

    public function discussionWasStarted(Started $event)
    {
        $money = (float)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->giveMoney($event->actor, $money);
    }

    public function discussionWasRestored(DiscussionRestored $event)
    {
        $money = (float)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->giveMoney($event->discussion->user, $money);
    }

    public function discussionWasHidden(DiscussionHidden $event)
    {
        $money = (float)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->giveMoney($event->discussion->user, -$money);
    }

    public function userWillBeSaved(Saving $event)
    {
        $attributes = Arr::get($event->data, 'attributes', []);

        if (array_key_exists('money', $attributes)) {
            $user = $event->user;
            $actor = $event->actor;
            $actor->assertCan('edit_money', $user);
            $user->money = (float)$attributes['money'];

            $this->events->dispatch(new MoneyUpdated($user));
        }
    }

    public function postWasLiked(PostWasLiked $event)
    {
        $money = (float)$this->settings->get('antoinefr-money.moneyforlike', 0);
        $this->giveMoney($event->post->user, $money);
    }

    public function postWasUnliked(PostWasUnliked $event)
    {
        $money = (float)$this->settings->get('antoinefr-money.moneyforlike', 0);
        $this->giveMoney($event->post->user, -$money);
    }
}
