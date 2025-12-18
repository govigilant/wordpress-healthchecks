#!/usr/bin/env bash
set -euo pipefail

WEB_ROOT="/var/www/html"
PLUGIN_DIR="$WEB_ROOT/wp-content/plugins/vigilant-healthchecks"
WORDPRESS_SOURCE="/usr/src/wordpress"

if [ ! -e "$WEB_ROOT/wp-settings.php" ]; then
    echo "Syncing WordPress core files..."
    rsync -a --delete \
        --exclude 'wp-content/plugins/' \
        --exclude 'wp-content/uploads/' \
        "$WORDPRESS_SOURCE/" "$WEB_ROOT/"
fi

if [ -d "$PLUGIN_DIR" ]; then
    cd "$PLUGIN_DIR"
    if [ ! -d vendor ]; then
        composer install --no-interaction --prefer-dist --no-progress
    fi
fi

cd "$WEB_ROOT"

DB_HOST="${WORDPRESS_DB_HOST:-db}"
DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"
DB_USER="${WORDPRESS_DB_USER:-wordpress}"
DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-wordpress}"
DB_PREFIX="${WORDPRESS_TABLE_PREFIX:-wp_}"
DB_CHARSET="${WORDPRESS_DB_CHARSET:-utf8mb4}"
DB_COLLATE="${WORDPRESS_DB_COLLATE:-}"
DB_SKIP_SSL_VERIFY="${WORDPRESS_DB_SKIP_SSL_VERIFY:-1}"

DB_HOST_ONLY="${DB_HOST%%:*}"
DB_PORT_PART="${DB_HOST##*:}"
if [ "$DB_HOST_ONLY" = "$DB_PORT_PART" ]; then
    DB_PORT="3306"
else
    DB_PORT="$DB_PORT_PART"
fi

MYSQLADMIN_SSL_FLAGS=()
if [ "$DB_SKIP_SSL_VERIFY" = "1" ]; then
    MYSQLADMIN_SSL_FLAGS+=(--skip-ssl-verify-server-cert)
fi

printf 'Waiting for database connection'
for attempt in $(seq 1 60); do
    if mysqladmin ping -h"$DB_HOST_ONLY" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" "${MYSQLADMIN_SSL_FLAGS[@]}" >/dev/null 2>&1; then
        printf '\nDatabase is ready.\n'
        ready=1
        break
    fi
    printf '.'
    sleep 2
done

if [ -z "${ready:-}" ]; then
    printf '\nDatabase connection could not be established.\n' >&2
    exit 1
fi

if [ ! -f wp-config.php ]; then
    wp --allow-root config create \
        --dbname="$DB_NAME" \
        --dbuser="$DB_USER" \
        --dbpass="$DB_PASSWORD" \
        --dbhost="$DB_HOST" \
        --dbprefix="$DB_PREFIX" \
        --dbcharset="$DB_CHARSET" \
        --dbcollate="$DB_COLLATE" \
        --skip-check \
        --skip-salts
fi

SITE_URL="${WP_SITE_URL:-http://localhost:8000}"
SITE_TITLE="${WP_SITE_TITLE:-Vigilant Healthchecks Dev}"
ADMIN_USER="${WP_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-secret}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

if [ -n "${WORDPRESS_CONFIG_EXTRA:-}" ] && ! grep -Fq "${WORDPRESS_CONFIG_EXTRA}" wp-config.php 2>/dev/null; then
    printf '\n%s\n' "$WORDPRESS_CONFIG_EXTRA" >> wp-config.php
fi

wp --allow-root config set WP_HOME "'${SITE_URL}'" --type=constant --raw >/dev/null 2>&1 || true
wp --allow-root config set WP_SITEURL "'${SITE_URL}'" --type=constant --raw >/dev/null 2>&1 || true

wp --allow-root db create >/dev/null 2>&1 || true

if ! wp --allow-root core is-installed >/dev/null 2>&1; then
    wp --allow-root core install \
        --url="$SITE_URL" \
        --title="$SITE_TITLE" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$ADMIN_PASSWORD" \
        --admin_email="$ADMIN_EMAIL" \
        --skip-email
fi

wp --allow-root option update siteurl "$SITE_URL" >/dev/null 2>&1 || wp --allow-root option add siteurl "$SITE_URL" >/dev/null 2>&1 || true
wp --allow-root option update home "$SITE_URL" >/dev/null 2>&1 || wp --allow-root option add home "$SITE_URL" >/dev/null 2>&1 || true

wp --allow-root plugin activate vigilant-healthchecks >/dev/null 2>&1 || true
wp --allow-root rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || true
