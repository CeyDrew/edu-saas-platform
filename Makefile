.PHONY: up down reset seed test stan pint lint docs e2e ai renew

# Build and start everything; the first start installs dependencies and seeds demo data.
up:
	docker compose up -d --build --wait

down:
	docker compose down

# Drop all data (database, search index, node_modules volume) and start from scratch.
reset:
	docker compose down -v
	docker compose up -d --build --wait

seed:
	docker compose exec php php artisan migrate:fresh --seed
	docker compose exec php php artisan cache:clear

test:
	docker compose exec php ./vendor/bin/pest

stan:
	docker compose exec php ./vendor/bin/phpstan analyse --memory-limit=1G

pint:
	docker compose exec php ./vendor/bin/pint

lint: pint stan
	docker compose exec web npm run lint
	docker compose exec web npm run typecheck

docs:
	docker compose exec php php artisan scribe:generate
	cp api/public/docs/openapi.yaml docs/openapi.yaml

e2e:
	cd web && npx playwright test

# Local model for AI question generation (about 2 GB download).
ai:
	docker compose --profile ai up -d ollama
	docker compose --profile ai exec ollama ollama pull llama3.2:3b

renew:
	docker compose exec php php artisan subscriptions:renew
