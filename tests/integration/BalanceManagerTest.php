<?php

namespace AntoineFr\Money\Tests\integration;

use AntoineFr\Money\Event\MoneyUpdated;
use AntoineFr\Money\Service\BalanceManager;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManagerTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('antoinefr-money');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser([
                    'id' => 1,
                    'username' => 'alice',
                    'email' => 'alice@example.com',
                ]),
                $this->normalUser([
                    'id' => 2,
                    'username' => 'bob',
                    'email' => 'bob@example.com',
                ]),
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
}
