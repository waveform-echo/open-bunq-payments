# Migrating from Dutchbase Bunq Betaal Gateway v1.9

The legacy Dutchbase-style gateway redirects to a `bunq.me` URL and places WooCommerce orders on hold for manual reconciliation. It has no automatic provider-verification contract.

Safe migration:

1. Create a full WordPress/database backup.
2. Keep the legacy gateway installed while Open bunq Payments is tested in sandbox.
3. Configure Open bunq Payments OAuth and select the monetary account.
4. Enable its WooCommerce gateway and test it with a non-production/sandbox checkout.
5. Prove accepted/rejected/mismatch states.
6. In production, enable the new gateway and disable the legacy gateway as a separate reversible action.
7. Do not bulk-complete old `on-hold` orders. Reconcile legacy orders using their original evidence.
8. Keep historical plugin/order notes for audit unless retention policy requires otherwise.

Legacy `bunq.me` can remain available as Open bunq Payments' manual backend during a controlled transition, but it still will not auto-mark orders paid.
