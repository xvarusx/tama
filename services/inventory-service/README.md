# Inventory Service

Symfony 8.1 service that owns stock levels for products: balances per product,
movement history, low/out-of-stock queries, and a Messenger consumer that seeds
zero balances from `ProductUpdated` events.

## Local development

```console
composer install
php bin/console doctrine:migrations:migrate
symfony serve -d
```

`DATABASE_URL`, `REDIS_URL` and `MESSENGER_TRANSPORT_DSN` come from `.env`
(committed defaults) or `.env.local` (local overrides, git-ignored).

## Tests

```console
rm -f var/data_test.db
php bin/console doctrine:schema:create --env=test
php bin/phpunit
```

Unit tests live in `tests/Unit`, kernel/service and HTTP tests in
`tests/Integration`, and the end-to-end HTTP smoke tests in `tests/Application`.

## Deployment / release dependency

This service's Docker deployment is assembled by the parent `erp` repository.
Before releasing this feature, the parent `erp` compose commits

- `68e7e20` — run the product updated worker,
- `67f9be4` — attach rabbitmq to the microservice network,
- `d5e5d98` — run inventory migrations before starting the service,

currently on `agents/auth-service-adjustments-fixtures-tests`, must be merged or
cherry-picked onto the parent `main` branch. Without them, `docker compose up`
does not start the inventory worker, cannot reach RabbitMQ, and never applies
the Doctrine migrations, so a fresh database returns 500s until the schema is
created by hand.
