# Database Useful


## Connect to Database
`psql -h 127.0.0.1 -p 5432 -U app -d app`


## Export Backup
`docker compose exec database /bin/bash;`
or
`make shell database`

<!-- pg_dump -U $POSTGRES_USER -h $DATABASE_HOST -d $POSTGRES_DB -f backup.sql -->
`pg_dump -U $POSTGRES_USER -h localhost -d $POSTGRES_DB -f backup.sql;`

`docker cp database:/backup.sql .`

## Import
`psql -h 127.0.0.1 -p 5432 -U app -d app -f backup.sql`


## Doctrine Migrations
`php bin/console doctrine:cache:clear-metadata`

`php bin/console doctrine:migrations:diff`

`php bin/console doctrine:migrations:migrate`


## Default User Admin Insert

Hash your Password:

`php bin/console security:hash-password SuperAdmin%123`

<!-- u admin@symfony.com
p SuperAdmin%123 -->
Create Admin User

`INSERT INTO "user" (id, email, roles, password) VALUES (gen_random_uuid(), 'admin@symfony.com', '["ROLE_ADMIN"]', '$2y$13$fcv5vjOtL/siIAdEIJgDMeARDhju767Kia.QQ75IDFSZNAEJpsOIC');`



## Doctrine Fixtures
By default the load command purges the database, removing all data from every table. To append your fixtures' data add the --append option.

`php bin/console doctrine:fixtures:load --group=UserFixtures`