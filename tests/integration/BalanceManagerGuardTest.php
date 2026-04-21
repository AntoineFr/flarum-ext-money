<?php

namespace AntoineFr\Money\Tests\integration;

use AntoineFr\Money\Event\MoneyUpdated;
use AntoineFr\Money\Service\BalanceManager;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;

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

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

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

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $result = $balanceManager->adjustBalance(null, 10.0, 'NOUSER', 'test.no-user');

        $this->assertFalse($result);
        $this->assertFalse($dispatched);
    }

    /** @test */
    public function it_can_be_resolved_without_money_history_enabled(): void
    {
        $this->app();

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $this->assertInstanceOf(BalanceManager::class, $balanceManager);
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

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

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
    /** @test */
    public function it_prevents_overdraft_on_adjust_balance_when_flag_is_set(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $user->money = 5;
        $user->save();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $dispatched = false;

        $dispatcher->listen(MoneyUpdated::class, function () use (&$dispatched): void {
            $dispatched = true;
        });

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $result = $balanceManager->adjustBalance(
            $user,
            -10.0,
            'TEST_OVERDRAFT',
            'test.overdraft',
            [],
            null,
            preventOverdraft: true
        );

        $user->refresh();

        $this->assertFalse($result);
        $this->assertEquals(5.0, (float) $user->money);
        $this->assertFalse($dispatched);
    }

    /** @test */
    public function it_allows_overdraft_on_adjust_balance_when_flag_is_not_set(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $user->money = 5;
        $user->save();

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $result = $balanceManager->adjustBalance(
            $user,
            -10.0,
            'TEST_ALLOW_OVERDRAFT',
            'test.allow-overdraft'
        );

        $user->refresh();

        $this->assertTrue($result);
        $this->assertEquals(-5.0, (float) $user->money);
    }

    /** @test */
    public function it_allows_debit_within_balance_when_prevent_overdraft_is_set(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $user->money = 15;
        $user->save();

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $result = $balanceManager->adjustBalance(
            $user,
            -10.0,
            'TEST_WITHIN',
            'test.within',
            [],
            null,
            preventOverdraft: true
        );

        $user->refresh();

        $this->assertTrue($result);
        $this->assertEquals(5.0, (float) $user->money);
    }

    /** @test */
    public function it_skips_users_who_cannot_afford_debit_in_adjust_balances(): void
    {
        $this->app();

        User::query()->whereKey(1)->update(['money' => 3]);
        User::query()->whereKey(2)->update(['money' => 20]);

        $users = User::query()->whereIn('id', [1, 2])->orderBy('id')->get()->all();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $capturedEvents = [];
        $dispatcher->listen(MoneyUpdated::class, function (MoneyUpdated $event) use (&$capturedEvents): void {
            $capturedEvents[] = $event;
        });

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $updatedCount = $balanceManager->adjustBalances(
            $users,
            -10.0,
            'TEST_BATCH_OVERDRAFT',
            'test.batch-overdraft',
            [],
            null,
            preventOverdraft: true
        );

        $firstUser = User::query()->findOrFail(1);
        $secondUser = User::query()->findOrFail(2);

        $this->assertSame(1, $updatedCount);
        $this->assertEquals(3.0, (float) $firstUser->money);
        $this->assertEquals(10.0, (float) $secondUser->money);
        $this->assertCount(1, $capturedEvents);
        $this->assertSame(2, $capturedEvents[0]->user->id);
    }

    /** @test */
    public function it_prevents_overdraft_on_apply_balance_change_when_flag_is_set(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $user->money = 5;
        $user->save();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $dispatched = false;

        $dispatcher->listen(MoneyUpdated::class, function () use (&$dispatched): void {
            $dispatched = true;
        });

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);
        $connection = $this->app()->getContainer()->make(\Illuminate\Database\ConnectionInterface::class);

        $result = null;

        $connection->transaction(function () use ($user, $balanceManager, &$result) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

            $result = $balanceManager->applyBalanceChange(
                $lockedUser,
                -10.0,
                'TEST_APPLY_OVERDRAFT',
                'test.apply-overdraft',
                [],
                null,
                preventOverdraft: true
            );

            $this->assertEquals(5.0, (float) $lockedUser->money);
        });

        $user->refresh();

        $this->assertFalse($result);
        $this->assertEquals(5.0, (float) $user->money);
        $this->assertFalse($dispatched);
    }

    /** @test */
    public function it_returns_false_for_zero_delta_on_apply_balance_change(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $user->money = 10;
        $user->save();

        $balanceManager = $this->app()->getContainer()->make(BalanceManager::class);

        $result = $balanceManager->applyBalanceChange(
            $user,
            0.0,
            'TEST_ZERO',
            'test.zero'
        );

        $this->assertFalse($result);
        $this->assertEquals(10.0, (float) $user->money);
    }
}
