#!/usr/bin/env bash
#
# SmartGro: local MySQL setup for development and tests.
#
# Run from the project root:   bash docs/operations/scripts/setup-local-mysql.sh
#
# What it does:
#   1. Creates databases `smartgro` and `smartgro_testing` (utf8mb4) on the local MySQL server.
#   2. Creates or updates the MySQL user `smartgro` (localhost and 127.0.0.1) with a NEW random
#      password and grants it access to those two databases only.
#   3. Writes the DB_* settings into your local .env (a copy is kept in .env.backup).
#   4. Runs the migrations on the development database.
#
# The password is generated here, sent to MySQL over stdin and written only to .env.
# It is never printed, logged or committed. Re-running the script rotates the password.
#
# Requires sudo access to the local MySQL server (Ubuntu default: root via auth_socket).

set -euo pipefail

if [[ ! -f artisan || ! -f .env ]]; then
    echo "Run this from the SmartGro project root (where artisan and .env live)." >&2
    exit 1
fi

DB_NAME="${SMARTGRO_DB_NAME:-smartgro}"
TEST_DB_NAME="${DB_NAME}_testing"
DB_USER="${SMARTGRO_DB_USER:-smartgro}"
DB_PASSWORD="$(openssl rand -hex 24)"

sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS \`${TEST_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost', '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${TEST_DB_NAME}\`.* TO '${DB_USER}'@'localhost', '${DB_USER}'@'127.0.0.1';
SQL

cp .env .env.backup

SMARTGRO_DB_NAME="${DB_NAME}" SMARTGRO_DB_USER="${DB_USER}" SMARTGRO_DB_PASSWORD="${DB_PASSWORD}" php -r '
    $path = ".env";
    $pattern = "/^\s*#?\s*DB_(CONNECTION|HOST|PORT|DATABASE|USERNAME|PASSWORD|SOCKET|URL)=/";
    $kept = [];
    $insertAt = null;

    foreach (preg_split("/\r\n|\n/", file_get_contents($path)) as $line) {
        if (preg_match($pattern, $line)) {
            $insertAt ??= count($kept);

            continue;
        }

        $kept[] = $line;
    }

    array_splice($kept, $insertAt ?? count($kept), 0, [
        "DB_CONNECTION=mysql",
        "DB_HOST=127.0.0.1",
        "DB_PORT=3306",
        "DB_DATABASE=".getenv("SMARTGRO_DB_NAME"),
        "DB_USERNAME=".getenv("SMARTGRO_DB_USER"),
        "DB_PASSWORD=".getenv("SMARTGRO_DB_PASSWORD"),
    ]);

    file_put_contents($path, implode(PHP_EOL, $kept));
'

unset DB_PASSWORD

php artisan config:clear --no-interaction >/dev/null
php artisan migrate --no-interaction

echo
echo "MySQL is configured: databases '${DB_NAME}' and '${TEST_DB_NAME}', user '${DB_USER}'."
echo "Your previous .env is saved as .env.backup (git-ignored)."
echo "Next: php artisan test --compact"
