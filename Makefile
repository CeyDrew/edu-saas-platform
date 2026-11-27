.PHONY: up down setup seed test lint stan pint artisan web-shell

up:
	docker compose up -d

down:
	docker compose down

setup:
	test -f api/.env || cp api/.env.example api/.env
	docker compose up -d --build
	docker compose exec php composer install
	docker compose exec php php artisan key:generate
	docker compose exec php php artisan migrate:fresh --seed
	docker compose exec php php artisan storage:link

seed:
	docker compose exec php php artisan migrate:fresh --seed

test:
	docker compose exec php ./vendor/bin/pest

stan:
	docker compose exec php ./vendor/bin/phpstan analyse --memory-limit=1G

pint:
	docker compose exec php ./vendor/bin/pint

lint: pint stan
	docker compose exec web npm run lint
