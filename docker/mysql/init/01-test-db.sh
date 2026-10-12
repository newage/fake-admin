#!/bin/sh
# A separate DB for PHPUnit/Behat; runs only when the mysql-data volume is first created.
set -e
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS fake_admin_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON fake_admin_test.* TO '$MYSQL_USER'@'%';
SQL
