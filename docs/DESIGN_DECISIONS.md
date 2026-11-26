# Design decisions

## Modules and responsibilities

The API groups code by feature under `app/Http`: Auth, Product, Order, Payment, and Report. Controllers stay thin and translate requests into responses. Form Requests own input validation, Resources define public JSON fields, Services apply business rules and coordinate work, and Repositories contain Eloquent/query-builder access. Role middleware and the Order policy enforce authorization. This Service + Repository structure follows the project's existing PHP/Laravel style and makes it possible to explain each layer independently without adding interfaces or abstractions where there is only one implementation.

## Consistency and concurrency

Order creation calculates prices from locked database product rows and stores purchase-time name, SKU, and price snapshots. Product rows are locked in ascending ID order with `lockForUpdate`; stock checks, order/item inserts, and decrements share one transaction. Therefore a competing checkout waits and rechecks stock after the first transaction commits. BCMath string calculations avoid binary floating-point currency arithmetic.

Payment notifications authenticate the exact raw request body with HMAC-SHA256 and `hash_equals`. A unique provider transaction ID is the database idempotency constraint. Payment processing locks the order, checks that it is still pending, inserts the payment, and marks the order paid in one transaction. Duplicate deliveries for that order return the existing payment; reuse for another order conflicts.

The top-products report aggregates once per product ID in SQL. When historical item snapshots differ for a product, it displays the latest qualifying snapshot while including quantities from every qualifying item row.

The confirmation mailable implements Laravel's queued after-commit contract and permits three attempts with backoff. The local mail transport is intentionally redacted and does not deliver mail. Expired-order cancellation shares the order lock with payment, then locks products in ascending ID order and restores stock together with the cancelled status in a retried transaction. The Laravel 11 scheduler runs that command every minute with overlap prevention; operators must run a scheduler process separately from the queue worker.

## Trade-offs and limitations

MySQL is required to verify the row-locking behavior; SQLite tests would not demonstrate the same guarantees. The queue uses Laravel's database driver for a compact local setup, and the payment callback is a focused provider-neutral example rather than a complete gateway integration. There is no refund, shipping, or customer cancellation workflow. Production deployment still needs provider-specific signature/key rotation, monitoring and alerting, backup strategy, and load testing under the target database isolation and connection settings.

## AI tools

OpenAI Codex was used as a coding assistant to scaffold and edit implementation, tests, and documentation from the staged requirements. Generated changes were reviewed against the module boundaries and security rules, inspected as diffs, formatted with Pint, syntax-checked, and exercised with PHPUnit in Docker/MySQL. The MySQL concurrency tests use separate processes/connections; no successful email delivery or untested behavior is claimed.
