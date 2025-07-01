# Symfony Docker

A [Docker](https://www.docker.com/)-based installer and runtime for the [Symfony](https://symfony.com) web framework,
with [FrankenPHP](https://frankenphp.dev) and [Caddy](https://caddyserver.com/) inside!

![CI](https://github.com/dunglas/symfony-docker/workflows/CI/badge.svg)


# Project Specific Notes

## Installation
- Build Containers 

```make build-clean```

- Initialize Permissions

```make fix-perms```

- Setup Custom SSL 

```mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key "symfony.localhost"```
- Trust Custom SSL 

```mkcert -install```
- GOTO: https://symfony.localhost

## General Info

### Start / Stop Project
```
make down; make up
```

### Exec in PHP container:
```
make php-bash
```

### Exec in DB container:
```
make shell database
```

### Logs of specific Container ( or no parameter for all containers):
```
make logs php
```

### Worker(s) Logs :
```
make worker-logs
```

### PSQL:
- Show tables:
```
\d
```
- Query like usual ```SELECT * FROM table;```

### Services local url:
```
Mailer:
http://localhost:8026/

RedisInsight:
http://localhost:5540/

RabbitMQ:
http://localhost:15672/#/queues

guest
guest
```


### Load Database with test data. 
```
php bin/console doctrine:fixtures:load
```

### Admin Login
```
https://symfony.localhost/login

admin@symfony.com
1234
```

### Tailwind CSS 
- Build CSS (dev): 
```
php bin/console tailwind:build --watch 
```
- If there is Permission Error do below 
```
docker compose exec php /bin/bash
php bin/console tailwind:build
exit
make fix-perms
```
- Generate Assets (prod):
```
php bin/console asset-map:compile
```


## !!!! Do not use `die;` in code. Please just dont. It will require a restart

---
---
---

# Original Repository README notes(https://github.com/dunglas/symfony-docker)

## Getting Started

1. If not already done, [install Docker Compose](https://docs.docker.com/compose/install/) (v2.10+)
2. Run `docker compose build --pull --no-cache` to build fresh images
3. Run `docker compose up --wait` to set up and start a fresh Symfony project
4. Open `https://localhost` in your favorite web browser(https://stackoverflow.com/a/15076602/1352334)
5. Run `docker compose down --remove-orphans` to stop the Docker containers.

## Custom SSL
- mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key "symfony.localhost"

- mkcert -install

## Features

* Production, development and CI ready
* Just 1 service by default
* Blazing-fast performance thanks to [the worker mode of FrankenPHP](https://github.com/dunglas/frankenphp/blob/main/docs/worker.md) (automatically enabled in prod mode)
* [Installation of extra Docker Compose services](docs/extra-services.md) with Symfony Flex
* Automatic HTTPS (in dev and prod)
* HTTP/3 and [Early Hints](https://symfony.com/blog/new-in-symfony-6-3-early-hints) support
* Real-time messaging thanks to a built-in [Mercure hub](https://symfony.com/doc/current/mercure.html)
* [Vulcain](https://vulcain.rocks) support
* Native [XDebug](docs/xdebug.md) integration
* Super-readable configuration

**Enjoy!**

## Docs

1. [Options available](docs/options.md)
2. [Using Symfony Docker with an existing project](docs/existing-project.md)
3. [Support for extra services](docs/extra-services.md)
4. [Deploying in production](docs/production.md)
5. [Debugging with Xdebug](docs/xdebug.md)
6. [TLS Certificates](docs/tls.md)
7. [Using MySQL instead of PostgreSQL](docs/mysql.md)
8. [Using Alpine Linux instead of Debian](docs/alpine.md)
9. [Using a Makefile](docs/makefile.md)
10. [Updating the template](docs/updating.md)
11. [Troubleshooting](docs/troubleshooting.md)

## License

Symfony Docker is available under the MIT License.

## Credits

Created by [Kévin Dunglas](https://dunglas.dev), co-maintained by [Maxime Helias](https://twitter.com/maxhelias) and sponsored by [Les-Tilleuls.coop](https://les-tilleuls.coop).
