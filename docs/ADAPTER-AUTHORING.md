# Writing a New Adapter

An adapter should be thin. It must not create a second bunq client.

## Minimal pattern

```php
add_action('open_bunq/payment_verified', function($record, $request) {
    if ($record['integration'] !== 'my-commerce') {
        return;
    }

    $native = my_commerce_get_order($record['object_id']);
    if (!$native || $native->is_paid()) {
        return;
    }

    $native->mark_paid();
}, 10, 2);
```

Initiation:

```php
$record = open_bunq_create_payment([
    'integration'        => 'my-commerce',
    'object_id'          => (string) $order_id,
    'user_id'            => get_current_user_id(),
    'amount'             => $order_total,
    'currency'           => $currency,
    'description'        => 'Order ' . $order_id,
    'merchant_reference' => 'my-' . $order_id,
    'return_url'         => home_url('/order/' . $order_id . '/'),
    'metadata'           => ['native_order_key' => $order_key],
]);
```

The service reuses an in-flight payment for the same `integration + object_id`, preventing ordinary double-clicks from producing duplicate RequestInquiries.

## Register metadata

```php
OBP_Integration_Registry::register('my-commerce', [
    'name'      => 'My Commerce',
    'detected'  => true,
    'enabled'   => true,
    'recurring' => false,
    'notes'     => 'One-time payments.',
]);
```

## Rules

- Re-fetch/check the native order before mutating it.
- Native mutation must also be idempotent.
- Do not trust query parameters or webhook payloads as payment proof.
- Do not call `payment_verified` yourself.
- Do not claim subscription support unless future renewal is actually chargeable and reconcilable.
