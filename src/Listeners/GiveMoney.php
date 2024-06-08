<?php

namespace AntoineFr\Money\Listeners;

use Illuminate\Support\Arr;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Started;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\User\Event\Saving;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;
use AntoineFr\Money\Event\MoneyUpdated;
use AntoineFr\Money\AutoRemoveEnum;

class GiveMoney
{
    protected SettingsRepositoryInterface $settings;
    protected Dispatcher $events;
    protected ExtensionManager $extensions;
    protected float $moneyforpost;
    protected int $postminimumlength;
    protected float $moneyfordiscussion;
    protected float $moneyforlike;
    protected int $autoremove;
    protected bool $cascaderemove;
    protected bool $moneyifprivate;

    public function __construct(SettingsRepositoryInterface $settings, Dispatcher $events, ExtensionManager $extensions)
    {
        $this->settings = $settings;
        $this->events = $events;
        $this->extensions = $extensions;

        $this->moneyforpost = (float) $this->settings->get('antoinefr-money.moneyforpost', 0);
        $this->postminimumlength = (int) $this->settings->get('antoinefr-money.postminimumlength', 0);
        $this->moneyfordiscussion = (float) $this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->moneyforlike = (float) $this->settings->get('antoinefr-money.moneyforlike', 0);
        $this->autoremove = (int) $this->settings->get('antoinefr-money.autoremove', 1);
        $this->cascaderemove = (bool) $this->settings->get('antoinefr-money.cascaderemove', false);
        $this->moneyifprivate = (bool) $this->settings->get('antoinefr-money.moneyifprivate', true);
    }

    public function giveMoney(?User $user, float $money): bool
    {
        if (!is_null($user)) {
            $user->money += $money;
            $user->save();

            $this->events->dispatch(new MoneyUpdated($user));

            return true;
        }

        return false;
    }

    private function checkPrivate(bool $isPrivate): bool
    {
        return (
            !$this->extensions->isEnabled('fof-byobu')
            || $this->moneyifprivate
            || !$isPrivate
        );
    }

    public function postWasPosted(Posted $event): void
    {
        if (
            $event->post->number > 1 // If it's not the first post of a discussion
            && strlen($event->post->content) >= $this->postminimumlength
            && $this->checkPrivate($event->post->discussion->is_private || $event->post->is_private)
        ) {
            $this->giveMoney($event->actor, $this->moneyforpost);
        }
    }

    public function postWasRestored(PostRestored $event): void
    {
        if (
            $this->autoremove == AutoRemoveEnum::HIDDEN
            && $event->post->type == 'comment'
            && strlen($event->post->content) >= $this->postminimumlength
            && $this->checkPrivate($event->post->discussion->is_private || $event->post->is_private)
        ) {
            $this->giveMoney($event->post->user, $this->moneyforpost);
        }
    }

    public function postWasHidden(PostHidden $event): void
    {
        if (
            $this->autoremove == AutoRemoveEnum::HIDDEN
            && $event->post->type == 'comment'
            && strlen($event->post->content) >= $this->postminimumlength
            && $this->checkPrivate($event->post->discussion->is_private || $event->post->is_private)
        ) {
            $this->giveMoney($event->post->user, -1 * $this->moneyforpost);
        }
    }

    public function postWasDeleted(PostDeleted $event): void
    {
        if (
            $this->autoremove == AutoRemoveEnum::DELETED
            && $event->post->type == 'comment'
            && strlen($event->post->content) >= $this->postminimumlength
            && $this->checkPrivate($event->post->discussion->is_private || $event->post->is_private)
        ) {
            $this->giveMoney($event->post->user, -1 * $this->moneyforpost);
        }
    }

    public function discussionWasStarted(Started $event): void
    {
        if ($this->checkPrivate($event->discussion->is_private)) {
            $this->giveMoney($event->actor, $this->moneyfordiscussion);
        }
    }

    public function discussionWasRestored(DiscussionRestored $event): void
    {
        if (
            $this->autoremove == AutoRemoveEnum::HIDDEN
            && $this->checkPrivate($event->discussion->is_private)
        ) {
            $this->giveMoney($event->discussion->user, $this->moneyfordiscussion);

            $this->discussionCascadePosts($event->discussion, 1);
        }
    }

    public function discussionWasHidden(DiscussionHidden $event): void
    {
        if (
            $this->autoremove == AutoRemoveEnum::HIDDEN
            && $this->checkPrivate($event->discussion->is_private)
        ) {
            $this->giveMoney($event->discussion->user, -$this->moneyfordiscussion);

            $this->discussionCascadePosts($event->discussion, -1);
        }
    }

    public function discussionWasDeleted(DiscussionDeleted $event): void
    {
        if (
            $this->autoremove == AutoRemoveEnum::DELETED
            && $this->checkPrivate($event->discussion->is_private)
        ) {
            $this->giveMoney($event->discussion->user, -$this->moneyfordiscussion);

            $this->discussionCascadePosts($event->discussion, -1);
        }
    }

    protected function discussionCascadePosts(Discussion $discussion, int $multiply): void
    {
        if ($this->cascaderemove) {
            foreach ($discussion->posts as $post) {
                if (
                    $post->type == 'comment'
                    && strlen($post->content) >= $this->postminimumlength
                    && $post->number > 1
                    && is_null($post->hidden_at)
                    && $this->checkPrivate($post->discussion->is_private || $post->is_private)
                ) {
                    $this->giveMoney($post->user, $multiply * $this->moneyforpost);
                }
            }
        }
    }

    public function userWillBeSaved(Saving $event): void
    {
        $attributes = Arr::get($event->data, 'attributes', []);

        if (array_key_exists('money', $attributes)) {
            $user = $event->user;
            $actor = $event->actor;
            $actor->assertCan('edit_money', $user);
            $user->money = (float) $attributes['money'];

            $this->events->dispatch(new MoneyUpdated($user));
        }
    }

    public function postWasLiked(PostWasLiked $event): void
    {
        if ($this->checkPrivate($event->post->discussion->is_private || $event->post->is_private)) {
            $this->giveMoney($event->post->user, $this->moneyforlike);
        }
    }

    public function postWasUnliked(PostWasUnliked $event): void
    {
        if ($this->checkPrivate($event->post->discussion->is_private || $event->post->is_private)) {
            $this->giveMoney($event->post->user, -1 * $this->moneyforlike);
        }
    }
}
