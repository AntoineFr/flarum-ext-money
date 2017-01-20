<?php namespace AntoineFr\Money\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Event\PostWillBeSaved;
use Flarum\Event\DiscussionWillBeSaved;

class GiveMoney
{
    protected $settings;
    public function __construct(SettingsRepositoryInterface $settings) {
        $this->settings = $settings;
    }
    
    public function subscribe(Dispatcher $events) {
        $events->listen(PostWillBeSaved::class, [$this, 'postWillBeSaved']);
        $events->listen(DiscussionWillBeSaved::class, [$this, 'discussionWillBeSaved']);
    }
    
    public function postWillBeSaved(PostWillBeSaved $event) {
        if (!isset($event->data['id']) && $event->data['type'] == 'posts') {
            $money = (int)$this->settings->get('antoinefr-money.moneyforpost', 0);
            $event->actor->money += $money;
            $event->actor->save();
        }
    }
    
    public function discussionWillBeSaved(DiscussionWillBeSaved $event) {
        if (!isset($event->data['id'])) {
            $money = (int)$this->settings->get('antoinefr-money.moneyfordiscussion', 0);
            $event->actor->money += $money;
            $event->actor->save();
        }
    }
}