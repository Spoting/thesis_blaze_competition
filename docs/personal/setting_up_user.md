## Default Insert

Hash your Password:
php bin/console security:hash-password SuperAdmin%123

u admin@symfony.com
p SuperAdmin%123

INSERT INTO "user" (id, email, roles, password) VALUES (gen_random_uuid(), 'admin@symfony.com', '["ROLE_ADMIN"]', '$2y$13$fcv5vjOtL/siIAdEIJgDMeARDhju767Kia.QQ75IDFSZNAEJpsOIC');





## Doctrine Fixtures
src/DataFixtures/UserFixtures.php

By default the load command purges the database, removing all data from every table. To append your fixtures' data add the --append option.

php bin/console doctrine:fixtures:load

php bin/console doctrine:fixtures:load --group=UserFixtures