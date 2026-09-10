# Generic Payments

## Shortcode

```text
[open_bunq_payment amount="5.00" currency="EUR" description="Contribution" reference="project-42" label="Pay €5"]
```

The shortcode configuration is server-signed and expires after one hour. Return URLs stay on the same WordPress host by default.

## PHP API

```php
$record = open_bunq_create_payment([
    'integration' => 'donation-plugin',
    'object_id' => 'donation-123',
    'amount' => '25.00',
    'currency' => 'EUR',
    'description' => 'Donation 123',
    'merchant_reference' => 'donation-123',
    'return_url' => home_url('/thank-you/'),
]);
```

## REST API

`POST /wp-json/open-bunq/v1/payments`

By default only users with `manage_options` can create payments. Developers can override this with `open_bunq/rest_can_create` and must implement appropriate authentication/authorization.

Public status:

`GET /wp-json/open-bunq/v1/payment/{public_token}`

The public endpoint exposes payment state/reference data, not secrets or full provider payloads.
