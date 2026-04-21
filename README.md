# flarum-ext-money

Give money to your users for different actions.

**This extension is compatible with Flarum >= 1.0**

## Installation

```
composer require antoinefr/flarum-ext-money
```

## Updating

```
composer update antoinefr/flarum-ext-money
php flarum migrate
php flarum cache:clear
```

## Configuration

You can configure this extension in the Admin area, in the Extensions tab.

## For Other Extension Authors

`flarum-ext-money` should be the main balance-changing entry point for other extensions.

If your extension needs to:

- credit a user
- debit a user
- transfer balance between users
- update many users at once

Inject:

```php
use AntoineFr\Money\Service\BalanceManager;
```

## Available Methods

### `adjustBalance()`

Use for one user balance change.

```php
$this->balances->adjustBalance(
    $user,
    -12.5,
    'MYEXTENSION',
    'vendor-my-extension.forum.history.purchase',
    [
        'itemTitle' => 'VIP Badge',
    ],
    $actor
);
```

### `adjustBalances()`

Use for batch updates instead of looping `adjustBalance()` repeatedly.

This is the preferred method for system rewards, bulk grants, and other many-user operations.

### `transferBalance()`

Use when money moves from one user to another and you want both sides recorded consistently.

```php
$this->balances->transferBalance(
    $sender,
    $receiver,
    25.0,
    'MYEXTENSION',
    'vendor-my-extension.forum.history.sent',
    'vendor-my-extension.forum.history.received',
    [
        'giverUsername' => (string) $sender->username,
        'receiverUsername' => (string) $receiver->username,
    ],
    $actor
);
```

### `applyBalanceChange()`

Use when your extension manages its own transaction and needs to save money alongside other domain fields atomically.

This method mutates `$user->money` on the model and schedules history recording + event dispatch via `afterSave`. The caller is responsible for:

- Opening the transaction
- Locking the user row (`SELECT FOR UPDATE`)
- Calling `$user->save()` after this method

```php
$this->connection->transaction(function () use ($user, $actor) {
    $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();

    // Set your domain fields
    $lockedUser->last_checkin_time = now();

    // Apply balance change — mutates $lockedUser->money and schedules history
    $this->balances->applyBalanceChange(
        $lockedUser,
        5.0,
        'DAILY_REWARD',
        'vendor-my-extension.forum.daily-reward',
        ['streakDays' => 7],
        $actor
    );

    // One save persists everything atomically
    $lockedUser->save();
});
```

## `source`, `sourceKey`, `sourceParams`

Recommended usage:

- `source`: stable machine-readable source name such as `MONEY_STORE_PURCHASE`
- `sourceKey`: translation key for the frontend reason text
- `sourceParams`: flat structured data used by the translation

Recommended `sourceParams` conventions:

- plain values: `itemTitle`, `postNumber`, `username`
- translated values: keys ending with `Key`
- link values: keys ending with `LinkHref`

Example:

```php
[
    'itemTitle' => 'VIP Badge',
    'purchaseTypeKey' => 'vendor-my-extension.forum.purchase-type.monthly',
    'itemLinkHref' => '/item/123',
]
```

## Integration With Money History

If `mattoid/flarum-ext-money-history` is installed, it listens to `MoneyUpdated` and records the history entry automatically.

That means for new integrations:

- do not dispatch `MoneyUpdated` or record history manually
- do not store ready-to-display reason text in the backend
- do route balance changes through `BalanceManager`

## Concurrency And Locking

`BalanceManager` locks affected user rows during the write transaction to keep balance snapshots consistent.

Useful notes:

- this is expected and intentional
- reads are not blocked in the same way normal writes are serialized
- prefer `adjustBalances()` and `transferBalance()` over hand-written loops for multi-user operations
- keep transaction work small and avoid slow side effects inside it

## Overdraft Prevention

`adjustBalance()`, `adjustBalances()`, and `applyBalanceChange()` accept an optional `preventOverdraft` parameter.

When enabled, the balance check happens **inside the lock**, making it race-safe. Without it, checking the balance before calling the method is unreliable — another request could change the balance between your check and the actual mutation.

`adjustBalance()` and `applyBalanceChange()` return `false` when the balance is insufficient. `adjustBalances()` silently skips users who can't afford the debit and returns the count of users actually updated.

### With `adjustBalance()`

```php
$debited = $this->balances->adjustBalance(
    $user,
    -50.0,
    'MYEXTENSION_PURCHASE',
    'vendor-my-extension.forum.history.purchase',
    ['itemTitle' => 'VIP Badge'],
    $actor,
    preventOverdraft: true
);

if (! $debited) {
    throw new ValidationException(['message' => $this->translator->trans('...')]);
}
```

### With `applyBalanceChange()`

```php
$applied = $this->balances->applyBalanceChange(
    $lockedUser,
    -50.0,
    'MYEXTENSION_PURCHASE',
    'vendor-my-extension.forum.history.purchase',
    ['itemTitle' => 'VIP Badge'],
    $actor,
    preventOverdraft: true
);

if (! $applied) {
    throw new ValidationException(['message' => $this->translator->trans('...')]);
}

$lockedUser->save();
```

Note: `transferBalance()` always prevents overdraft on the sender side — no flag needed.

## Screenshots

![User profile](https://i.imgur.com/CfdejnI.png)

![Edit user money](https://i.imgur.com/6CiOxal.png)

![Extension settings](https://i.imgur.com/i4gxddo.png)
