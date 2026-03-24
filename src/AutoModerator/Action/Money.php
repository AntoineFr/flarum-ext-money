<?php

namespace AntoineFr\Money\AutoModerator\Action;

use Askvortsov\AutoModerator\Action\ActionDriverInterface;
use AntoineFr\Money\Service\BalanceManager;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Contracts\Support\MessageBag;
use Flarum\User\User;

class Money implements ActionDriverInterface
{
    public function __construct(private BalanceManager $balances)
    {
    }

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
        $balanceDelta = $settings['money'] ?? 0;
        $balanceDelta = (float) $balanceDelta;
        $this->balances->adjustBalance(
            $user,
            $balanceDelta,
            'AUTOMODERATOR_ACTION',
            'antoinefr-money.forum.history.automoderator-action',
            [],
            $lastEditedBy,
            null
        );
    }
}
