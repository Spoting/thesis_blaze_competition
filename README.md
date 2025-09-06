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
- GOTO: https://symfony.localhost or your server name

## General Info

### Start / Stop Project
```
make up; make down;
```

### Exec in PHP container:
```
make php-bash
```

### Exec Pod :
```
make shell <pod>
```

### Logs of specific Container ( or no parameter for all containers):
```
make logs php
```

### Worker(s) Logs :
```
make worker-logs
```

### Services local url:
```
Mailer:
http://localhost:8026/

RedisInsight:
http://localhost:5540/

RabbitMQ Management:
http://localhost:15672/#/queues

u: guest
p: guest
```


### Load Database with test data (dev mode only). 
```
php bin/console doctrine:fixtures:load
```

### Dev Admin Login
```
https://symfony.localhost/login

u: admin@symfony.com
p: 1234
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
- Generate Assets ( css / js ) :
```
php bin/console asset-map:compile
```

- Require NPM modules :
```
php bin/console importmap:require chartjs-plugin-zoom
```

```
composer run-script --no-dev post-install-cmd;
```

# Production Process
- Build Production Image
```
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
```


**Enjoy!**