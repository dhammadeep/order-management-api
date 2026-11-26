<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## API Authentication

Authentication uses Laravel Sanctum personal access tokens. Start the Docker services and apply the migrations:

```bash
docker compose up -d
docker compose exec app php artisan migrate
```

### Register a customer

`POST /api/register`

```json
{
  "name": "Taylor Customer",
  "email": "taylor@example.test",
  "password": "correct-horse-battery",
  "password_confirmation": "correct-horse-battery"
}
```

Public registration always assigns the `customer` role. A submitted `role` field is ignored.

### Log in

`POST /api/login`

```json
{
  "email": "taylor@example.test",
  "password": "correct-horse-battery"
}
```

The response contains a one-time display of the Sanctum token:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": { "id": 1, "name": "Taylor Customer", "email": "taylor@example.test", "role": "customer" },
    "access_token": "1|your-plain-text-token",
    "token_type": "Bearer"
  }
}
```

Send it with protected requests as `Authorization: Bearer <access_token>`. `GET /api/me` returns the current user and `POST /api/logout` revokes only the presented token.

| Method | Endpoint | Authentication |
| --- | --- | --- |
| POST | `/api/register` | Public |
| POST | `/api/login` | Public |
| POST | `/api/logout` | Sanctum Bearer token |
| GET | `/api/me` | Sanctum Bearer token |

### Create the admin account

Set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in the untracked local `.env` file. `.env.example` contains placeholders only. Then run:

```bash
docker compose exec app php artisan db:seed --class=AdminUserSeeder
```

The seeder updates the configured account if it already exists, so it can be run repeatedly. Do not use the example password outside a disposable local environment.

### Authentication tests

Run the feature suite inside the app container against the configured MySQL database:

```bash
docker compose exec -e DB_CONNECTION=mysql app php artisan test --filter=AuthenticationTest
```

## Products

Every product endpoint requires a Sanctum Bearer token. Admins can create, update, delete, view, list, and filter products. Customers can list and view active products, search, and paginate. Product deletion is a soft delete; deleted and inactive products are hidden from customer queries.

| Method | Endpoint | Permission |
| --- | --- | --- |
| POST | `/api/products` | Admin |
| PUT | `/api/products/{id}` | Admin |
| GET | `/api/products` | Admin or customer |
| GET | `/api/products/{id}` | Admin or customer, subject to visibility |
| DELETE | `/api/products/{id}` | Admin |

### Create a product

`POST /api/products` with an admin Bearer token:

```json
{
  "name": "Stainless Steel Water Bottle",
  "sku": "HOME-BTL-101",
  "price": "18.50",
  "stock_quantity": 24,
  "status": "active"
}
```

For example, send an admin request with the token returned by admin login:

```bash
curl -X POST http://localhost:8080/api/products \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <ADMIN_TOKEN>' \
  -H 'Content-Type: application/json' \
  -d '{"name":"Stainless Steel Water Bottle","sku":"HOME-BTL-101","price":"18.50","stock_quantity":24,"status":"active"}'
```

`PUT /api/products/1` accepts the same fields as a partial update. For example:

```json
{
  "price": "19.95",
  "stock_quantity": 30
}
```

### Search and paginate

Customers can search active products by name or SKU. Results use database pagination; `per_page` is limited to 100.

```text
GET /api/products?search=bottle&page=1&per_page=15
GET /api/products?search=HOME-BTL&per_page=5
```

For example, a customer lists matching active products with their own token:

```bash
curl 'http://localhost:8080/api/products?search=bottle&page=1&per_page=15' \
  -H 'Accept: application/json' \
  -H 'Authorization: Bearer <CUSTOMER_TOKEN>'
```

Admins can filter either status:

```text
GET /api/products?status=inactive&page=1&per_page=20
```

Customers are always restricted to active products. Requesting the inactive status filter returns 403. An inactive or soft-deleted product requested by ID returns 404 to a customer.

The response includes an explicit product representation and pagination metadata:

```json
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": {
    "products": [
      {
        "id": 1,
        "name": "Stainless Steel Water Bottle",
        "sku": "HOME-BTL-101",
        "price": "18.50",
        "stock_quantity": 24,
        "status": "active",
        "created_at": "2026-11-26T12:00:00.000000Z",
        "updated_at": "2026-11-26T12:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 1,
      "last_page": 1,
      "from": 1,
      "to": 1
    }
  }
}
```

## Orders

All order endpoints require a Sanctum Bearer token. Customers can place orders and see only their own order history. Admins can read all orders, but cannot place orders through the customer checkout endpoint.

| Method | Endpoint | Permission |
| --- | --- | --- |
| POST | `/api/orders` | Customer |
| GET | `/api/orders` | Customer (own orders) or admin (all orders) |
| GET | `/api/orders/{id}` | Owner or admin |

### Place an order

`POST /api/orders` accepts product IDs and positive quantities. Each product ID may appear once; duplicate IDs receive HTTP 422. The API ignores any client-supplied prices or totals and uses the current product price from MySQL.

```json
{
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 2, "quantity": 1 }
  ]
}
```

Successful creation returns HTTP 201:

```json
{
  "success": true,
  "message": "Order placed successfully.",
  "data": {
    "order": {
      "id": 1,
      "order_number": "ORD-01JABCDEF0123456789ABCDEF0",
      "total_amount": "42.25",
      "status": "pending",
      "items": [
        {
          "id": 1,
          "product_id": 1,
          "product_name": "Stainless Steel Water Bottle",
          "product_sku": "HOME-BTL-101",
          "unit_price": "18.50",
          "quantity": 2,
          "line_total": "37.00"
        },
        {
          "id": 2,
          "product_id": 2,
          "product_name": "Insulated Steel Cup",
          "product_sku": "HOME-CUP-204",
          "unit_price": "5.25",
          "quantity": 1,
          "line_total": "5.25"
        }
      ]
    }
  }
}
```

Amounts are stored and calculated as decimal strings using BCMath, so currency calculations do not use floating-point arithmetic. Order items save the product name, SKU, and unit price at checkout; later product edits do not rewrite order history. Order statuses are `pending`, `paid`, `cancelled`, and `shipped`. This phase creates orders as `pending` and exposes read operations; later phases will own payment and cancellation workflows.

Order creation runs in one MySQL transaction. It locks requested product rows with `SELECT ... FOR UPDATE`, in ascending product ID order, checks that each product is active and not deleted, and checks stock while holding the locks. It then writes the order and item snapshots and applies stock reductions before committing. If any product is unavailable or short on stock, the transaction rolls back and returns HTTP 422. Concurrent purchases for the final unit queue behind the same row lock; after the first request commits, the next request sees the reduced stock and fails instead of overselling. The MySQL concurrency integration test exercises two simultaneous HTTP requests contending for one unit.

Customers receive 403 when viewing another customer's order. A missing order returns 404; unauthenticated requests return 401. Listing supports `page` and `per_page` (maximum 100) and returns pagination metadata. Order rows and their item snapshots are loaded with eager loading.

```text
GET /api/orders?page=1&per_page=15
GET /api/orders/1
```

Insufficient stock, inactive/deleted products, invalid item data, or duplicate product IDs return the common 422 validation response:

```json
{
  "success": false,
  "message": "Validation failed.",
  "data": null,
  "errors": { "items": ["Insufficient stock for product 1."] }
}
```

Run the order feature tests and the full suite inside Docker with MySQL configured:

```bash
docker compose exec -e DB_CONNECTION=mysql app php artisan test --filter=Order
docker compose exec -e DB_CONNECTION=mysql app php artisan test
```

## Queued order confirmation email

After an order transaction commits, the API queues one `OrderConfirmationMail` to the customer's saved email address. The message uses the saved order number, total, and item snapshots. A failed order does not queue mail. Queue dispatch errors are logged with the order ID and exception type only; permanent delivery failures use Laravel's failed-job handling and a sanitized application log entry.

The default settings in `.env.example` are:

```dotenv
QUEUE_CONNECTION=database
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

With `MAIL_MAILER=log`, the order service routes this mailable through the local `redacted_log` transport. It records only that the local transport handled a message; it does not log recipient addresses or message content, and it does not deliver email. To deliver real messages, set `MAIL_MAILER=smtp` and configure `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` with your SMTP provider's values. The SMTP credentials belong in the untracked `.env`, never in Git.

The Compose `queue` service runs `php artisan queue:work database --sleep=3 --tries=3 --timeout=90`. Start or restart it with:

```bash
docker compose up -d queue
docker compose logs -f queue
```

Each confirmation allows at most three attempts, with 10-second and 30-second delays before retries. After the final failure Laravel stores the job in `failed_jobs`; inspect or retry failed jobs with `php artisan queue:failed` and `php artisan queue:retry <id>`. The mailer's `ShouldQueueAfterCommit` contract also defers dispatch when an outer database transaction is still open.

Run the focused order confirmation tests and full PHPUnit suite inside Docker:

```bash
docker compose exec -e DB_CONNECTION=mysql app php artisan test --filter=OrderConfirmationTest
docker compose exec -e DB_CONNECTION=mysql app php artisan test --filter=RedactedLogTransportTest
docker compose exec -e DB_CONNECTION=mysql app php artisan test
```

## Payment webhook

`POST /api/webhooks/payment` is public and does not use Sanctum. The provider must send an `X-Signature` header containing the 64-character hexadecimal HMAC-SHA256 digest of the exact raw JSON request body. Laravel checks the signature in middleware with `hash_equals` before validating fields or accessing payment data.

Set a private `PAYMENT_WEBHOOK_SECRET` in the untracked `.env`. Generate a local value with:

```bash
openssl rand -hex 32
```

The `.env.example` value is only a placeholder. Never commit or log the real secret.

Expected successful payload:

```json
{
  "transaction_id": "TXN123456",
  "order_id": 1,
  "amount": "1500.00",
  "status": "success"
}
```

`transaction_id`, `order_id`, and `status: "success"` are required. `amount` is optional; when supplied, it must match the saved order total. The server stores the saved total rather than trusting a supplied amount. Only pending orders can transition to paid. Cancelled, shipped, or otherwise non-pending orders are rejected with HTTP 409; invalid payloads or amount mismatches return 422, and a missing or invalid signature returns 401.

For a PHP provider-side signature example, sign the exact bytes sent over HTTP:

```php
$rawBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$signature = hash_hmac('sha256', $rawBody, $secret);
// Send $rawBody unchanged and set X-Signature: $signature.
```

Successful notifications create one payment row under a unique `transaction_id` constraint and mark the matching order paid in one transaction. Repeating the same transaction ID for the same order returns HTTP 200 with `duplicate: true` and makes no additional changes. Reusing a transaction ID for a different order returns HTTP 409. The unique constraint plus order-row locking makes concurrent deliveries safe.

A repeated delivery returns the same payment record with an explicit duplicate flag:

```json
{
  "success": true,
  "message": "Payment notification was already processed.",
  "data": {
    "payment": { "id": 1, "order_id": 1, "transaction_id": "TXN123456", "amount": "1500.00", "status": "success" },
    "duplicate": true
  }
}
```

```json
{
  "success": true,
  "message": "Payment processed successfully.",
  "data": {
    "payment": {
      "id": 1,
      "order_id": 1,
      "transaction_id": "TXN123456",
      "amount": "1500.00",
      "status": "success"
    },
    "duplicate": false
  }
}
```

The webhook does not change stock or the saved order total. Run its focused tests and then the full suite inside Docker:

```bash
docker compose exec -e DB_CONNECTION=mysql app php artisan test --filter=PaymentWebhookTest
docker compose exec -e DB_CONNECTION=mysql app php artisan test
```

Product endpoints return 401 without a valid token, 403 when an authenticated role lacks permission, 404 when a product is missing or not visible to a customer, and 422 for invalid input.

### Product tests and sample catalog

```bash
docker compose exec -e DB_CONNECTION=mysql app php artisan test --filter=ProductManagementTest
docker compose exec app php artisan db:seed --class=ProductSeeder
```

The product seeder uses SKU-based upserts, so it can be run repeatedly without duplicating its sample catalog.
