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

### `syncPersistedBalanceChange()`

Use this only if your extension already changed and saved the user balance itself inside its own transaction and you need `money` to:

- record history through the installed recorder
- dispatch `MoneyUpdated`

This is useful when balance persistence is tightly coupled with your own domain writes and cannot be delegated cleanly to `adjustBalance()`.

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

- do not dispatch `MoneyHistoryEvent` directly
- do not store ready-to-display reason text in the backend
- do route balance changes through `BalanceManager`

## Concurrency And Locking

`BalanceManager` locks affected user rows during the write transaction to keep balance snapshots consistent.

Useful notes:

- this is expected and intentional
- reads are not blocked in the same way normal writes are serialized
- prefer `adjustBalances()` and `transferBalance()` over hand-written loops for multi-user operations
- keep transaction work small and avoid slow side effects inside it

## Screenshots

![User profile](https://i.imgur.com/CfdejnI.png)

![Edit user money](https://i.imgur.com/6CiOxal.png)

![Extension settings](https://i.imgur.com/i4gxddo.png)
