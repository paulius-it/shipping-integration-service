# Shipping integration service

## How to launch
- PHP8.1 and Composer should be installed;
- Docker and Docker compose should be available in order to launch it in the container.
In the terminal run:
```bash
cp .env.example .env
composer install
vendor/bin/sail artisan key:generate
vendor/bin/sail up -d
```

Integration service is ready - it should be available at 'localhost:8001', where 'localhost' is the domain configured on Docker setup, it might be different depending on the Docker configs in the system.
