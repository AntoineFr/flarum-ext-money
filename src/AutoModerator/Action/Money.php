<?php

namespace AntoineFr\Money\AutoModerator\Action;

use Askvortsov\AutoModerator\Action\ActionDriverInterface;
use AntoineFr\Money\Event\MoneyUpdated;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Support\MessageBag;
use Flarum\User\User;

class Money implements ActionDriverInterface
{
    public function translationKey(): string
    {
        return 'antoinefr-money.admin.automoderator.action_name';
    }

    public function availableSettings(): array
    {
        return [
            'money' => 'antoinefr-money.admin.automoderator.metric_name',
        ];
    }

    public function validateSettings(array $settings, Factory $validator): MessageBag
    {
        return $validator->make($settings, [
            'money' => 'required|numeric',
        ])->errors();
    }

    public function extensionDependencies(): array
    {
        return ['antoinefr-money'];
    }

    public function execute(User $user, array $settings = [], User $lastEditedBy = null)
    {
        $money = $settings['money'] ?? 0;
        $money = (float)$money;

        $user->money += $money;
        $user->save();

        resolve('events')->dispatch(new MoneyUpdated($user));
    }
}
