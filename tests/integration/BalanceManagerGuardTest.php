<?php

namespace AntoineFr\Money\Tests\integration;

use AntoineFr\Money\Event\MoneyUpdated;
use AntoineFr\Money\Service\BalanceManager;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class BalanceManagerGuardTest extends TestCase
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
}
