# lara2.doc — только Docker

Сюда клонируй свой Laravel 13 из git в корень этой папки.

## Запуск

```bash
docker compose up -d --build
docker exec lara2_web composer install
docker exec lara2_web php artisan key:generate
docker exec lara2_web php artisan migrate
docker exec lara2_web chown -R www-data:www-data storage bootstrap/cache
```

## Адреса

| Сервис | URL / порт |
|--------|------------|
| Laravel | http://localhost:8485 |
| MySQL | localhost:3386 (user `lara2` / pass `lara2`, db `lara2`) |
| phpMyAdmin | http://localhost:8303 |
| Postgres 16 + pgvector | localhost:5436 (db `lara2_vectors`) |
| pgAdmin | http://localhost:8304 (`admin@example.com` / `admin`) |

Настройки для `.env` — см. `.env.docker.example`.
