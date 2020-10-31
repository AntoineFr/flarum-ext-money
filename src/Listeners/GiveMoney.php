<?php namespace AntoineFr\Money\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\User\Event\Saving;

class GiveMoney
{
    protected $settings;
    
    public function __construct(SettingsRepositoryInterface $settings) {
        $this->settings = $settings;
    }
    
    public function subscribe(Dispatcher $events) {
        $events->listen(Posted::class, [$this, 'postWasPosted']);
        $events->listen(PostRestored::class, [$this, 'postWasRestored']);
        $events->listen(PostHidden::class, [$this, 'postWasHidden']);
        $events->listen(Started::class, [$this, 'discussionWasStarted']);
        $events->listen(DiscussionRestored::class, [$this, 'discussionWasRestored']);
        $events->listen(DiscussionHidden::class, [$this, 'discussionWasHidden']);
        $events->listen(Saving::class, [$this, 'userWillBeSaved']);
    }
    
    public function giveMoney(?User $user, $money) {
        if (!is_null($user)) {
            $money = (float)$money;
            $user->money += $money;
            $user->save();
        }
    }
    
    public function postWasPosted(Posted $event) {
        // If it's not the first post of a discussion
        if ($event->post['number'] > 1) {
            $minimumLength = (int)$this->settings->get('antoinefr-money.postminimumlength', 0);
            if (strlen($event->post->content) >= $minimumLength) {
                $money = (float)$this->settings->get('antoinefr-money.moneyforpost', 0);
                $this->giveMoney($event->actor, $money);
            }
        }
    }
    
    public function postWasRestored(PostRestored $event) {
        $minimumLength = (int)$this->settings->get('antoinefr-money.postminimumlength', 0);
        if (strlen($event->post->content) >= $minimumLength) {
            $money = (float)$this->settings->get('antoinefr-money.moneyforpost', 0);
            $this->giveMoney($event->post->user, $money);
        }
    }
    
    public function postWasHidden(PostHidden $event) {
        $minimumLength = (int)$this->settings->get('antoinefr-money.postminimumlength', 0);
        if (strlen($event->post->content) >= $minimumLength) {
            $money = (float)$this->settings->get('antoinefr-money.moneyforpost', 0);
            $this->giveMoney($event->post->user, -$money);
        }
    }
    
    public function discussionWasStarted(Started $event) {
        $money = (float)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->giveMoney($event->actor, $money);
    }
    
    public function discussionWasRestored(DiscussionRestored $event) {
        $money = (float)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->giveMoney($event->discussion->user, $money);
    }
    
    public function discussionWasHidden(DiscussionHidden $event) {
        $money = (float)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->giveMoney($event->discussion->user, -$money);
    }
    
    public function userWillBeSaved(Saving $event) {
        $attributes = Arr::get($event->data, 'attributes', []);
        if (array_key_exists('money', $attributes)) {
            $user = $event->user;
            $actor = $event->actor;
            $actor->assertCan('edit_money', $user);
            $user->money = (float)$attributes['money'];
        }
    }
}
