# 17 — DEVOPS / DEPLOYMENT (summary)

Dev (current host): Laragon — Apache vhost `http://jasapedia.test`, MySQL 8.4 root, Redis 5 (`D:\laragon\bin\redis\...\redis-server.exe --port 6379`), queue via `php artisan queue:work`, scheduler via `php artisan schedule:work`.

Prod target: Linux + Docker (nginx/php-fpm 8.3-fpm, MySQL 8 managed, Redis managed, S3-compatible storage, supervisor queue workers ×3, cron `schedule:run`, Reverb for realtime). `.env.production.example` with all secrets placeholder. Zero-downtime: `php artisan migrate --force` in release hook; health `/up` + `/health/ready`.

Backups: nightly `mysqldump` + media sync; restore drill documented (doc 23). Secrets via platform env, never in repo.
