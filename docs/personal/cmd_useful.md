## Services Parameters
php bin/console debug:container --parameters


# Restart Workers
curl -X POST http://localhost:2019/frankenphp/workers/restart




## Error Pages in Symfony. Not shown in DEV by default.
### Use url/_error/404 to see actual page.
```
# config/routes/framework.yaml
when@dev:
    _errors:
        resource: '@FrameworkBundle/Resources/config/routing/errors.php'
        type:     php
        prefix:   /_error
```



## Migrations
php bin/console doctrine:cache:clear-metadata

php bin/console doctrine:migrations:diff

<!-- php bin/console make:migration -->

php bin/console doctrine:migrations:migrate


##
php bin/console cache:pool:clear --all