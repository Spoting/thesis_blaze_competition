
# From DB Pod
psql -h 127.0.0.1 -p 5432 -U app -d app


# List DBs
\l


# List Tables
\dt


# Columns of Table
\d <table>

# Rows of Table
\dt <table>


## 
php bin/console doctrine:migrations:migrate

## Query by Symfony
php bin/console doctrine:query:sql 'SELECT * from "user"'

## Execute Doctrine Fixtures
php bin/console doctrine:fixtures:load --group=UserFixtures




## Export / Import Database

### Export 
pg_dump -U $POSTGRES_USER -h $DATABASE_HOST -d $POSTGRES_DB -f backup.sql


### Import
create database