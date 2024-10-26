#!/usr/bin/with-contenv bash
# shellcheck shell=bash

function fixperms() {
  for folder in $@; do
    if $(find ${folder} ! -user flarum -o ! -group flarum | egrep '.' -q); then
      echo "Fixing permissions in $folder..."
      chown -R flarum. "${folder}"
    else
      echo "Permissions already fixed in ${folder}"
    fi
  done
}

# From https://github.com/docker-library/mariadb/blob/master/docker-entrypoint.sh#L21-L41
# usage: file_env VAR [DEFAULT]
#    ie: file_env 'XYZ_DB_PASSWORD' 'example'
# (will allow for "$XYZ_DB_PASSWORD_FILE" to fill in the value of
#  "$XYZ_DB_PASSWORD" from a file, especially for Docker's secrets feature)
file_env() {
  local var="$1"
  local fileVar="${var}_FILE"
  local def="${2:-}"
  if [ "${!var:-}" ] && [ "${!fileVar:-}" ]; then
    echo >&2 "error: both $var and $fileVar are set (but are exclusive)"
    exit 1
  fi
  local val="$def"
  if [ "${!var:-}" ]; then
    val="${!var}"
  elif [ "${!fileVar:-}" ]; then
    val="$(<"${!fileVar}")"
  fi
  export "$var"="$val"
  unset "$fileVar"
}

TZ=${TZ:-UTC}
MEMORY_LIMIT=${MEMORY_LIMIT:-256M}
UPLOAD_MAX_SIZE=${UPLOAD_MAX_SIZE:-16M}
CLEAR_ENV=${CLEAR_ENV:-yes}
OPCACHE_MEM_SIZE=${OPCACHE_MEM_SIZE:-128}
LISTEN_IPV6=${LISTEN_IPV6:-true}
REAL_IP_FROM=${REAL_IP_FROM:-0.0.0.0/32}
REAL_IP_HEADER=${REAL_IP_HEADER:-X-Forwarded-For}
LOG_IP_VAR=${LOG_IP_VAR:-remote_addr}

FLARUM_DEBUG=${FLARUM_DEBUG:-false}
#FLARUM_BASE_URL=${FLARUM_BASE_URL:-http://flarum.docker}
FLARUM_POWEREDBY_HEADER="${FLARUM_POWEREDBY_HEADER:-true}"
FLARUM_REFERRER_POLICY="${FLARUM_REFERRER_POLICY:-same-origin}"
FLARUM_COOKIE_SAMESITE="${FLARUM_COOKIE_SAMESITE:-lax}"

#DB_HOST=${DB_HOST:-localhost}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-flarum}
DB_USER=${DB_USER:-flarum}
#DB_PASSWORD=${DB_PASSWORD:-asupersecretpassword}
DB_PREFIX=${DB_PREFIX:-flarum_}
DB_NOPREFIX=${DB_NOPREFIX:-true}
DB_TIMEOUT=${DB_TIMEOUT:-60}

DOMAINS=${DOMAINS:-}

# Timezone
echo "Setting timezone to ${TZ}..."
ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime
echo ${TZ} >/etc/timezone

# PHP
echo "Setting PHP-FPM configuration..."
sed -e "s/@MEMORY_LIMIT@/$MEMORY_LIMIT/g" \
  -e "s/@UPLOAD_MAX_SIZE@/$UPLOAD_MAX_SIZE/g" \
  -e "s/@CLEAR_ENV@/$CLEAR_ENV/g" \
  /tpls/etc/php82/php-fpm.d/www.conf >/etc/php82/php-fpm.d/www.conf

echo "Setting PHP INI configuration..."
sed -i "s|memory_limit.*|memory_limit = ${MEMORY_LIMIT}|g" /etc/php82/php.ini
sed -i "s|;date\.timezone.*|date\.timezone = ${TZ}|g" /etc/php82/php.ini

# OpCache
echo "Setting OpCache configuration..."
sed -e "s/@OPCACHE_MEM_SIZE@/$OPCACHE_MEM_SIZE/g" \
  /tpls/etc/php82/conf.d/opcache.ini >/etc/php82/conf.d/opcache.ini

# Nginx
echo "Setting Nginx configuration..."
sed -e "s#@UPLOAD_MAX_SIZE@#$UPLOAD_MAX_SIZE#g" \
  -e "s#@REAL_IP_FROM@#$REAL_IP_FROM#g" \
  -e "s#@REAL_IP_HEADER@#$REAL_IP_HEADER#g" \
  -e "s#@LOG_IP_VAR@#$LOG_IP_VAR#g" \
  /tpls/etc/nginx/nginx.conf >/etc/nginx/nginx.conf

if [ "$LISTEN_IPV6" != "true" ]; then
  sed -e '/listen \[::\]:/d' -i /etc/nginx/nginx.conf
fi

echo "Initializing files and folders..."
mkdir -p /data/domains
# For each domain, create a configuration file

cp -Rf /opt/flarum/domains /data
rm -rf /opt/flarum/domains
ln -sf /data/domains /opt/flarum/domains
chown -h flarum. /opt/flarum/public /opt/flarum/domains /opt/flarum/storage
fixperms /data/public /data/storage /opt/flarum/vendor /opt/flarum/domains


# For each domain, check if there is a folder with the name of the domain in /opt/flarum/domains
# If not, create it and copy the default folders

IFS=',' read -ra ADDR <<< "$DOMAINS"
echo "Creating domain folders for ${DOMAINS}..."
for domain in "${ADDR[@]}"; do
  domainPath="/data/domains/${domain}"
  if [ ! -d "${domainPath}" ]; then
    echo "Creating domain folder for ${domain}..."
    mkdir -p "${domainPath}"
    cp -Rf /opt/flarum/storage "${domainPath}/storage"
    mkdir -p "${domainPath}/public"
    cp -Rf /opt/flarum/public/assets "${domainPath}/public/assets"

    ln -sf /opt/flarum/public/index.php "${domainPath}/public/index.php"
    ln -sf /opt/flarum/public/.htaccess "${domainPath}/public/.htaccess"
    ln -sf /opt/flarum/public/web.config "${domainPath}/public/web.config"
    chown -h flarum. "${domainPath}/public/index.php" "${domainPath}/public/.htaccess" "${domainPath}/public/web.config"
    fixperms "${domainPath}/storage" "${domainPath}/public"

    echo "Domain folder for ${domain} created!"
  else
    echo "Domain folder for ${domain} already exists!"
  fi
done

echo "Checking database connection..."
if [ -z "$DB_HOST" ]; then
  echo >&2 "ERROR: DB_HOST must be defined"
  exit 1
fi
file_env 'DB_USER'
file_env 'DB_PASSWORD'
if [ -z "$DB_PASSWORD" ]; then
  echo >&2 "ERROR: Either DB_PASSWORD or DB_PASSWORD_FILE must be defined"
  exit 1
fi
dbcmd="mysql -h ${DB_HOST} -P ${DB_PORT} -u "${DB_USER}" "-p${DB_PASSWORD}""

echo "Waiting ${DB_TIMEOUT}s for database to be ready..."

# Enforce no prefix for db
if [ "$DB_NOPREFIX" = "true" ]; then
  DB_PREFIX=""
fi

# create config.php file
cat > /opt/flarum/config.php <<EOL
<?php

global \$domain;

return array(
    'debug' => ${FLARUM_DEBUG},
    'offline' => false,
    'database' => array(
        'driver' => 'mysql',
        'host' => '${DB_HOST}',
        'database' =>  "\$domain",
        'username' => '${DB_USER}',
        'password' => '${DB_PASSWORD}',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '${DB_PREFIX}',
        'port' => '${DB_PORT}',
        'strict' => false,
    ),
    'url' => 'https://' . \$domain,
    'paths' => array(
        'api' => 'api',
        'admin' => 'admin',
    ),
);
EOL

if [ -f /opt/flarum/config.php ]; then
      mv /opt/flarum/config.php /opt/flarum/backup.config.php
fi

counter=1
IFS=',' read -ra ADDR <<< "$DOMAINS"

yasu flarum:flarum mv /opt/flarum/domain.php /opt/flarum/domain.php.bak

for domain in "${ADDR[@]}"; do
  while ! ${dbcmd} -e "show databases;" >/dev/null 2>&1; do
    sleep 1
    counter=$((counter + 1))
    if [ ${counter} -gt ${DB_TIMEOUT} ]; then
      echo >&2 "ERROR: Failed to connect to database on $DB_HOST"
      exit 1
    fi
  done
  echo "Database ready for domain $domain!"

  if ! echo 'SHOW DATABASES' | ${dbcmd} | grep -q "^${domain}$"; then
    echo "Creating database ${domain}..."
    echo "CREATE DATABASE \`${domain}\`;" | ${dbcmd}
  fi

  tableCount=$(echo 'SHOW TABLES' | ${dbcmd} "$domain" | wc -l)

  yasu flarum:flarum cat >/opt/flarum/domain.php <<EOL
<?php
\$domain = "${domain}";
EOL

  if [ "$tableCount" -eq 0 ]; then
    echo "First install detected for domain ${domain}!"

    yasu flarum:flarum cat >/tmp/config.yml <<EOL
debug: ${FLARUM_DEBUG}
baseUrl: https://${domain}
databaseConfiguration:
  driver: mysql
  host: ${DB_HOST}
  database: ${domain}
  username: ${DB_USER}
  password: ${DB_PASSWORD}
  prefix: ${DB_PREFIX}
  port: ${DB_PORT}
adminUser:
  username: admin
  password: Flarum123*!
  password_confirmation: Flarum123*!
  email: flarum@flarum.docker
settings:
  forum_title: ${domain}
EOL
      cd /opt/flarum && php flarum install --file=/tmp/config.yml
      yasu flarum:flarum touch /data/domains/"${domain}"/assets/rev-manifest.json
      # If config file exists, remove it
      if [ -f /opt/flarum/config.php ]; then
        rm /opt/flarum/config.php
      fi
      echo ">>"
      echo ">> WARNING: Flarum has been installed with the default credentials (flarum/flarum)"
      echo ">> Please connect to https://${domain} and change them!"
      echo ">>"
  else

    echo "Migrating database for domain ${domain}..."
    bash -c 'yasu flarum:flarum cd /opt/flarum && yasu flarum:flarum php flarum migrate'
    bash -c 'yasu flarum:flarum cd /opt/flarum && yasu flarum:flarum php flarum cache:clear'
  fi
  yasu flarum:flarum rm /opt/flarum/domain.php
done

yasu flarum:flarum mv /opt/flarum/domain.php.bak /opt/flarum/domain.php


# Delete config file and restore backup.config.php
if [ -f /opt/flarum/backup.config.php ]; then
  if [ -f /opt/flarum/config.php ]; then
    rm /opt/flarum/config.php
  fi
  mv /opt/flarum/backup.config.php /opt/flarum/config.php
fi