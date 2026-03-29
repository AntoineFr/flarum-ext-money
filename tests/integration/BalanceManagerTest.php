<?php

namespace AntoineFr\Money\Tests\integration;

use AntoineFr\Money\Event\MoneyUpdated;
use AntoineFr\Money\Service\BalanceManager;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManagerTest extends TestCase
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
                    'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'is_email_confirmed' => 1,
                ],
                [
                    'id' => 2,
                    'username' => 'bob',
                    'email' => 'bob@example.com',
                    'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'is_email_confirmed' => 1,
                ],
                [
                    'id' => 3,
                    'username' => 'carol',
                    'email' => 'carol@example.com',
                    'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim',
                    'is_email_confirmed' => 1,
                ],
            ],
        ]);
    }

    /** @test */
    public function it_adjusts_balance_and_dispatches_money_updated_with_full_context(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $actor = User::query()->findOrFail(2);
        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);

        $capturedEvent = null;
        $dispatcher->listen(MoneyUpdated::class, function (MoneyUpdated $event) use (&$capturedEvent): void {
            $capturedEvent = $event;
        });

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $result = $balanceManager->adjustBalance(
            $user,
            12.5,
            'TEST_SOURCE',
            'test.source-key',
            [],
            $actor
        );

        $user->refresh();

        $this->assertTrue($result);
        $this->assertEquals(12.5, (float) $user->money);
        $this->assertInstanceOf(MoneyUpdated::class, $capturedEvent);
        $this->assertSame('TEST_SOURCE', $capturedEvent->source);
        $this->assertSame('test.source-key', $capturedEvent->sourceKey);
        $this->assertSame([], $capturedEvent->sourceParams);
        $this->assertSame($actor->id, $capturedEvent->actor->id);
        $this->assertEquals(0.0, $capturedEvent->balanceBefore);
        $this->assertEquals(12.5, $capturedEvent->balanceAfter);
    }

    /** @test */
    public function it_adjusts_balance_down_and_dispatches_consistent_snapshots(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $actor = User::query()->findOrFail(2);
        $user->money = 40;
        $user->save();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);

        $capturedEvent = null;
        $dispatcher->listen(MoneyUpdated::class, function (MoneyUpdated $event) use (&$capturedEvent): void {
            $capturedEvent = $event;
        });

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $result = $balanceManager->adjustBalance(
            $user,
            -12.5,
            'TEST_DEBIT',
            'test.debit',
            [],
            $actor
        );

        $user->refresh();

        $this->assertTrue($result);
        $this->assertEquals(27.5, (float) $user->money);
        $this->assertInstanceOf(MoneyUpdated::class, $capturedEvent);
        $this->assertEquals(-12.5, $capturedEvent->balanceDelta);
        $this->assertSame([], $capturedEvent->sourceParams);
        $this->assertEquals(40.0, $capturedEvent->balanceBefore);
        $this->assertEquals(27.5, $capturedEvent->balanceAfter);
    }

    /** @test */
    public function it_reloads_the_user_balance_inside_the_transaction_before_applying_the_delta(): void
    {
        $this->app();

        $user = User::query()->findOrFail(1);
        $actor = User::query()->findOrFail(2);
        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);

        $capturedEvent = null;
        $dispatcher->listen(MoneyUpdated::class, function (MoneyUpdated $event) use (&$capturedEvent): void {
            $capturedEvent = $event;
        });

        User::query()->whereKey($user->id)->update(['money' => 40]);

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $result = $balanceManager->adjustBalance(
            $user,
            5.0,
            'TEST_STALE_MODEL',
            'test.stale-model',
            [],
            $actor
        );

        $user->refresh();

        $this->assertTrue($result);
        $this->assertEquals(45.0, (float) $user->money);
        $this->assertInstanceOf(MoneyUpdated::class, $capturedEvent);
        $this->assertEquals(40.0, $capturedEvent->balanceBefore);
        $this->assertEquals(45.0, $capturedEvent->balanceAfter);
        $this->assertEquals(45.0, (float) $capturedEvent->user->money);
    }

    /** @test */
    public function it_adjusts_balances_in_chunks_and_dispatches_one_event_per_updated_user(): void
    {
        $this->app();

        $users = User::query()->whereIn('id', [1, 3])->orderBy('id')->get()->all();
        $actor = User::query()->findOrFail(2);
        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);

        $capturedEvents = [];
        $dispatcher->listen(MoneyUpdated::class, function (MoneyUpdated $event) use (&$capturedEvents): void {
            $capturedEvents[] = $event;
        });

        User::query()->whereKey(1)->update(['money' => 10]);
        User::query()->whereKey(3)->update(['money' => 20]);

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $updatedCount = $balanceManager->adjustBalances(
            $users,
            5.0,
            'TEST_BATCH',
            'test.batch',
            [],
            $actor
        );

        $firstUser = User::query()->findOrFail(1);
        $thirdUser = User::query()->findOrFail(3);

        $this->assertSame(2, $updatedCount);
        $this->assertEquals(15.0, (float) $firstUser->money);
        $this->assertEquals(25.0, (float) $thirdUser->money);
        $this->assertCount(2, $capturedEvents);
        $this->assertSame([1, 3], array_map(fn (MoneyUpdated $event) => $event->user->id, $capturedEvents));
        $this->assertEquals([10.0, 20.0], array_map(fn (MoneyUpdated $event) => $event->balanceBefore, $capturedEvents));
        $this->assertEquals([15.0, 25.0], array_map(fn (MoneyUpdated $event) => $event->balanceAfter, $capturedEvents));
    }

    /** @test */
    public function it_transfers_balance_between_two_users_and_dispatches_two_contextual_events(): void
    {
        $this->app();

        $sender = User::query()->findOrFail(1);
        $receiver = User::query()->findOrFail(3);
        $actor = User::query()->findOrFail(2);
        $sender->money = 30;
        $sender->save();
        $receiver->money = 5;
        $receiver->save();

        $dispatcher = $this->app()->getContainer()->make(Dispatcher::class);
        $capturedEvents = [];

        $dispatcher->listen(MoneyUpdated::class, function (MoneyUpdated $event) use (&$capturedEvents): void {
            $capturedEvents[] = $event;
        });

        $balanceManager = new BalanceManager(
            $this->app()->getContainer()->make(ConnectionInterface::class),
            $dispatcher
        );

        $transferred = $balanceManager->transferBalance(
            $sender,
            $receiver,
            12.5,
            'TEST_TRANSFER',
            'test.transfer.sent',
            'test.transfer.received',
            ['postNumber' => 9],
            $actor
        );

        $sender->refresh();
        $receiver->refresh();

        $this->assertTrue($transferred);
        $this->assertEquals(17.5, (float) $sender->money);
        $this->assertEquals(17.5, (float) $receiver->money);
        $this->assertCount(2, $capturedEvents);
        $this->assertSame([1, 3], array_map(fn (MoneyUpdated $event) => $event->user->id, $capturedEvents));
        $this->assertEquals([-12.5, 12.5], array_map(fn (MoneyUpdated $event) => $event->balanceDelta, $capturedEvents));
        $this->assertSame(['test.transfer.sent', 'test.transfer.received'], array_map(fn (MoneyUpdated $event) => $event->sourceKey, $capturedEvents));
        $this->assertSame([['postNumber' => 9], ['postNumber' => 9]], array_map(fn (MoneyUpdated $event) => $event->sourceParams, $capturedEvents));
        $this->assertEquals([30.0, 5.0], array_map(fn (MoneyUpdated $event) => $event->balanceBefore, $capturedEvents));
        $this->assertEquals([17.5, 17.5], array_map(fn (MoneyUpdated $event) => $event->balanceAfter, $capturedEvents));
    }
}
