.PHONY: install up down test

# Full first-time setup: env file, PHP dependencies (via a disposable Composer
# container — no local PHP/Composer needed), containers, app key, migrate+seed.
install:
	[ -f .env ] || cp .env.example .env
	docker run --rm \
		-u "$$(id -u):$$(id -g)" \
		-v "$$(pwd):/opt" \
		-w /opt \
		laravelsail/php83-composer:latest \
		composer install --ignore-platform-reqs
	./vendor/bin/sail up -d
	./vendor/bin/sail artisan key:generate
	./vendor/bin/sail artisan migrate --seed

# Start an already-installed app (containers already built, vendor/ already there).
up:
	./vendor/bin/sail up -d

down:
	./vendor/bin/sail down

test:
	./vendor/bin/sail test
