<?php

namespace AntoineFr\Money\Tests\integration;

use AntoineFr\Money\Event\MoneyUpdated;
use AntoineFr\Money\Service\BalanceManager;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManagerGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('antoinefr-money');

        $this->prepareDatabase([
            'users' => [
                [
                    'id' => 1,
                    'username' => 'alice',
                    'email' => 'alice@example.com',
                    'is_email_confirmed' => 1,
                ],
                [
                    'id' => 2,
                    'username' => 'bob',
                    'email' => 'bob@example.com',
                    'is_email_confirmed' => 1,
                ],
            ],
        ]);
    }

    /** @test */
    public function it_rejects_zero_delta_without_changing_balance_or_dispatching_history_event(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $user->money = 33;
        $user->save();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $dispatched = false;

        $dispatcher->listen(MoneyUpdated::class, function () use (&$dispatched): void {
            $dispatched = true;
        });

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $result = $balanceManager->adjustBalance($user, 0.0, 'NOOP', 'test.noop');

        $user->refresh();

        $this->assertFalse($result);
        $this->assertEquals(33.0, (float) $user->money);
        $this->assertFalse($dispatched);
    }

    /** @test */
    public function it_rejects_null_user_without_dispatching_event(): void
    {
        $this->app();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $dispatched = false;

        $dispatcher->listen(MoneyUpdated::class, function () use (&$dispatched): void {
            $dispatched = true;
        });

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $result = $balanceManager->adjustBalance(null, 10.0, 'NOUSER', 'test.no-user');

        $this->assertFalse($result);
        $this->assertFalse($dispatched);
    }

    /** @test */
    public function it_rejects_transfers_when_the_sender_does_not_have_enough_balance(): void
    {
        $this->app();

        $sender = User::query()->findOrFail(1);
        $receiver = User::query()->findOrFail(2);
        $sender->money = 5;
        $sender->save();
        $receiver->money = 1;
        $receiver->save();
        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $dispatched = false;

        $dispatcher->listen(MoneyUpdated::class, function () use (&$dispatched): void {
            $dispatched = true;
        });

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $result = $balanceManager->transferBalance(
            $sender,
            $receiver,
            10.0,
            'TRANSFER',
            'test.transfer.sent',
            'test.transfer.received'
        );

        $sender->refresh();
        $receiver->refresh();

        $this->assertFalse($result);
        $this->assertEquals(5.0, (float) $sender->money);
        $this->assertEquals(1.0, (float) $receiver->money);
        $this->assertFalse($dispatched);
    }
}
