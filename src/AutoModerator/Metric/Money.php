<?php

namespace AntoineFr\Money\AutoModerator\Metric;

use Askvortsov\AutoModerator\Metric\MetricDriverInterface;
use AntoineFr\Money\Event\MoneyUpdated;
use Flarum\User\User;

class Money implements MetricDriverInterface
{
    public function translationKey(): string
    {
        return 'antoinefr-money.admin.automoderator.metric_name';
    }

    public function extensionDependencies(): array
    {
        return ['antoinefr-money'];
    }

    public function eventTriggers(): array
    {
        return [
            MoneyUpdated::class => function (MoneyUpdated $event) {
                return $event->user;
            },
        ];
    }

    public function getValue(User $user): int
    {
        return floor($user->money);
    }
}
