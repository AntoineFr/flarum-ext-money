<?php

namespace AntoineFr\Money\Listeners;

use AntoineFr\Money\AutoRemoveEnum;
use AntoineFr\Money\Service\BalanceManager;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\Discussion\Event\Hidden as DiscussionHidden;
use Flarum\Discussion\Event\Restored as DiscussionRestored;
use Flarum\Discussion\Event\Started;
use Flarum\Likes\Event\PostWasLiked;
use Flarum\Likes\Event\PostWasUnliked;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Post\Event\Hidden as PostHidden;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored as PostRestored;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\Saving;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class MoneyBalanceSubscriber
{
    private const SOURCE_POST_WAS_POSTED = 'POST_WAS_POSTED';
    private const SOURCE_POST_WAS_RESTORED = 'POST_WAS_RESTORED';
    private const SOURCE_POST_WAS_HIDDEN = 'POST_WAS_HIDDEN';
    private const SOURCE_POST_WAS_DELETED = 'POST_WAS_DELETED';
    private const SOURCE_DISCUSSION_WAS_STARTED = 'DISCUSSION_WAS_STARTED';
    private const SOURCE_DISCUSSION_WAS_RESTORED = 'DISCUSSION_WAS_RESTORED';
    private const SOURCE_DISCUSSION_WAS_HIDDEN = 'DISCUSSION_WAS_HIDDEN';
    private const SOURCE_DISCUSSION_WAS_DELETED = 'DISCUSSION_WAS_DELETED';
    private const SOURCE_USER_WILL_BE_SAVED = 'USER_WILL_BE_SAVED';
    private const SOURCE_POST_WAS_LIKED = 'POST_WAS_LIKED';
    private const SOURCE_POST_WAS_UNLIKED = 'POST_WAS_UNLIKED';

    protected float $moneyforpost;
    protected int $postminimumlength;
    protected float $moneyfordiscussion;
    protected float $moneyforlike;
    protected int $autoremove;
    protected bool $cascaderemove;
    protected bool $ignoreNotifyingUsersSwitch;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected BalanceManager $balances
    ) {
        $this->moneyforpost = (float) $this->settings->get('antoinefr-money.moneyforpost', 0);
        $this->postminimumlength = (int) $this->settings->get('antoinefr-money.postminimumlength', 0);
        $this->moneyfordiscussion = (float) $this->settings->get('antoinefr-money.moneyfordiscussion', 0);
        $this->moneyforlike = (float) $this->settings->get('antoinefr-money.moneyforlike', 0);
        $this->autoremove = (int) $this->settings->get('antoinefr-money.autoremove', 1);
        $this->cascaderemove = (bool) $this->settings->get('antoinefr-money.cascaderemove', false);
        $this->ignoreNotifyingUsersSwitch = (bool) $this->settings->get('antoinefr-money.ignorenotifyingusers', false);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Posted::class, [$this, 'postWasPosted']);
        $events->listen(PostRestored::class, [$this, 'postWasRestored']);
        $events->listen(PostHidden::class, [$this, 'postWasHidden']);
        $events->listen(PostDeleted::class, [$this, 'postWasDeleted']);
        $events->listen(Started::class, [$this, 'discussionWasStarted']);
        $events->listen(DiscussionRestored::class, [$this, 'discussionWasRestored']);
        $events->listen(DiscussionHidden::class, [$this, 'discussionWasHidden']);
        $events->listen(DiscussionDeleted::class, [$this, 'discussionWasDeleted']);
        $events->listen(Saving::class, [$this, 'userWillBeSaved']);
    }

    public function adjustBalance(
        ?User $user,
        float $balanceDelta,
        string $source = '',
        string $sourceKey = '',
        array $sourceParams = [],
        ?User $actor = null
    ): bool {
        return $this->balances->adjustBalance($user, $balanceDelta, $source, $sourceKey, $sourceParams, $actor);
    }

    public function adjustPostAuthorBalance(
        ?User $user,
        float $balanceDelta,
        Post $post,
        string $source = '',
        string $sourceKey = '',
        ?User $actor = null,
        array $sourceParams = []
    ): void {
        if ($user === null) {
            return;
        }

        $permissions = true;
        foreach ($post->discussion->tags as $tag) {
            if ($user->hasPermission("tag{$tag->id}.discussion.money.disable_money") && ! $user->isAdmin()) {
                $permissions = false;
            }
        }

        if ($permissions) {
            $this->adjustBalance($user, $balanceDelta, $source, $sourceKey, $sourceParams, $actor);
        }
    }

    private function sourceKey(string $name): string
    {
        return "antoinefr-money.forum.history.{$name}";
    }

    public function ignoreNotifyingUsers(string $content): string
    {
        if (! $this->ignoreNotifyingUsersSwitch) {
            return $content;
        }

        $pattern = '/@.*?(#\d+|#p\d+)/';
        return trim(str_replace(["\r", "\n"], '', preg_replace($pattern, '', $content)));
    }

    public function postWasPosted(Posted $event): void
    {
        $content = $this->ignoreNotifyingUsers($event->post->content);
        if (
            $event->post->number > 1
            && mb_strlen($content) >= $this->postminimumlength
        ) {
            $this->adjustPostAuthorBalance(
                $event->post->user,
                $this->moneyforpost,
                $event->post,
                self::SOURCE_POST_WAS_POSTED,
                $this->sourceKey('post-reward'),
                $event->actor
            );
        }
    }

    public function postWasRestored(PostRestored $event): void
    {
        $content = $this->ignoreNotifyingUsers($event->post->content);
        if (
            $this->autoremove == AutoRemoveEnum::HIDDEN
            && $event->post->type == 'comment'
            && mb_strlen($content) >= $this->postminimumlength
        ) {
            $this->adjustPostAuthorBalance(
                $event->post->user,
                $this->moneyforpost,
                $event->post,
                self::SOURCE_POST_WAS_RESTORED,
                $this->sourceKey('post-restored'),
                $event->actor
            );
        }
    }

    public function postWasHidden(PostHidden $event): void
    {
        $content = $this->ignoreNotifyingUsers($event->post->content);
        if (
            $this->autoremove == AutoRemoveEnum::HIDDEN
            && $event->post->type == 'comment'
            && mb_strlen($content) >= $this->postminimumlength
        ) {
            $this->adjustPostAuthorBalance(
                $event->post->user,
                -1 * $this->moneyforpost,
                $event->post,
                self::SOURCE_POST_WAS_HIDDEN,
                $this->sourceKey('post-hidden'),
                $event->actor
            );
        }
    }

    public function postWasDeleted(PostDeleted $event): void
    {
        $content = $this->ignoreNotifyingUsers($event->post->content);
        if (
            $this->autoremove == AutoRemoveEnum::DELETED
            && $event->post->type == 'comment'
            && mb_strlen($content) >= $this->postminimumlength
        ) {
            $this->adjustPostAuthorBalance(
                $event->post->user,
                -1 * $this->moneyforpost,
                $event->post,
                self::SOURCE_POST_WAS_DELETED,
                $this->sourceKey('post-deleted'),
                $event->actor
            );
        }
    }

    public function discussionWasStarted(Started $event): void
    {
        $this->adjustBalance(
            $event->discussion->user,
            $this->moneyfordiscussion,
            self::SOURCE_DISCUSSION_WAS_STARTED,
            $this->sourceKey('discussion-reward'),
            [],
            $event->actor
        );
    }

    public function discussionWasRestored(DiscussionRestored $event): void
    {
        if ($this->autoremove == AutoRemoveEnum::HIDDEN) {
            $this->adjustBalance(
                $event->discussion->user,
                $this->moneyfordiscussion,
                self::SOURCE_DISCUSSION_WAS_RESTORED,
                $this->sourceKey('discussion-restored'),
                [],
                $event->actor
            );

            $this->discussionCascadePosts(
                $event->discussion,
                1,
                self::SOURCE_POST_WAS_RESTORED,
                $this->sourceKey('post-restored'),
                $event->actor
            );
        }
    }

    public function discussionWasHidden(DiscussionHidden $event): void
    {
        if ($this->autoremove == AutoRemoveEnum::HIDDEN) {
            $this->adjustBalance(
                $event->discussion->user,
                -$this->moneyfordiscussion,
                self::SOURCE_DISCUSSION_WAS_HIDDEN,
                $this->sourceKey('discussion-hidden'),
                [],
                $event->actor
            );

            $this->discussionCascadePosts(
                $event->discussion,
                -1,
                self::SOURCE_POST_WAS_HIDDEN,
                $this->sourceKey('post-hidden'),
                $event->actor
            );
        }
    }

    public function discussionWasDeleted(DiscussionDeleted $event): void
    {
        if ($this->autoremove == AutoRemoveEnum::DELETED) {
            $this->adjustBalance(
                $event->discussion->user,
                -$this->moneyfordiscussion,
                self::SOURCE_DISCUSSION_WAS_DELETED,
                $this->sourceKey('discussion-deleted'),
                [],
                $event->actor
            );

            $this->discussionCascadePosts(
                $event->discussion,
                -1,
                self::SOURCE_POST_WAS_DELETED,
                $this->sourceKey('post-deleted'),
                $event->actor
            );
        }
    }

    protected function discussionCascadePosts(
        Discussion $discussion,
        int $multiply,
        string $source,
        string $sourceKey,
        ?User $actor = null
    ): void {
        if (! $this->cascaderemove) {
            return;
        }

        foreach ($discussion->posts as $post) {
            $content = $this->ignoreNotifyingUsers($post->content);
            if (
                $post->type == 'comment'
                && mb_strlen($content) >= $this->postminimumlength
                && $post->number > 1
                && is_null($post->hidden_at)
            ) {
                $this->adjustPostAuthorBalance($post->user, $multiply * $this->moneyforpost, $post, $source, $sourceKey, $actor);
            }
        }
    }

    public function userWillBeSaved(Saving $event): void
    {
        $attributes = Arr::get($event->data, 'attributes', []);

        if (! array_key_exists('money', $attributes)) {
            return;
        }

        $user = $event->user;
        $actor = $event->actor;
        $actor->assertCan('edit_money', $user);

        $balanceBefore = (float) $user->money;
        $balanceAfter = (float) $attributes['money'];
        $balanceDelta = $balanceAfter - $balanceBefore;
        $user->money = $balanceAfter;

        if ($balanceDelta !== 0.0) {
            $user->afterSave(function (User $savedUser) use ($balanceDelta, $actor, $balanceBefore, $balanceAfter): void {
                $this->balances->syncPersistedBalanceChange(
                    $savedUser,
                    $balanceDelta,
                    self::SOURCE_USER_WILL_BE_SAVED,
                    $this->sourceKey('manual-adjustment'),
                    [],
                    $actor,
                    $balanceBefore,
                    $balanceAfter
                );
            });
        }
    }

    public function postWasLiked(PostWasLiked $event): void
    {
        $this->adjustBalance(
            $event->post->user,
            $this->moneyforlike,
            self::SOURCE_POST_WAS_LIKED,
            $this->sourceKey('post-liked'),
            [],
            $event->user
        );
    }

    public function postWasUnliked(PostWasUnliked $event): void
    {
        $this->adjustBalance(
            $event->post->user,
            -1 * $this->moneyforlike,
            self::SOURCE_POST_WAS_UNLIKED,
            $this->sourceKey('post-unliked'),
            [],
            $event->user
        );
    }
}
