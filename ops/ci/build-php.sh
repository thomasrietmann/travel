#!/usr/bin/env bash
set -euo pipefail

# CI only: compile the exact Metanet patch versions without replacing system PHP.
: "${RUNNER_TEMP:?GitHub Actions RUNNER_TEMP is required}"
: "${GITHUB_PATH:?GitHub Actions GITHUB_PATH is required}"
: "${TC_PHP_VERSION:?An exact PHP version is required}"
case "$TC_PHP_VERSION" in
  8.3.21) TC_PHP_SHA256=4dfb329f209a552c3716394fc123bb62e80a468b55ce27fc8cb0fd5f30b9dcd6 ;;
  8.3.36) TC_PHP_SHA256=dd54693aa334c5c4d72e9aee96b082c2473750a18a6c1d105789c93a3b1692fd ;;
  *) printf 'Unsupported CI PHP version: %s\n' "$TC_PHP_VERSION" >&2; exit 1 ;;
esac

sudo apt-get update -qq
sudo apt-get install --no-install-recommends -y \
  build-essential pkg-config curl xz-utils \
  libxml2-dev libsqlite3-dev libssl-dev libcurl4-openssl-dev \
  libonig-dev libc-client2007e-dev libkrb5-dev zlib1g-dev

TC_BUILD_DIR=$(mktemp -d "$RUNNER_TEMP/tripcontrol-php-build.XXXXXX")
TC_PREFIX="$RUNNER_TEMP/tripcontrol-php-$TC_PHP_VERSION"
cd "$TC_BUILD_DIR"
curl --fail --silent --show-error --location --retry 3 \
  "https://www.php.net/distributions/php-$TC_PHP_VERSION.tar.xz" -o php.tar.xz
printf '%s  php.tar.xz\n' "$TC_PHP_SHA256" | sha256sum --check --strict
tar -xJf php.tar.xz
cd "php-$TC_PHP_VERSION"

./configure --prefix="$TC_PREFIX" \
  --with-config-file-path="$TC_PREFIX/etc" \
  --with-config-file-scan-dir="$TC_PREFIX/etc/conf.d" \
  --disable-all --enable-cli --disable-cgi --disable-phpdbg \
  --enable-filter --enable-session --enable-tokenizer --enable-ctype \
  --enable-fileinfo --enable-mbstring --enable-dom --enable-xml \
  --enable-xmlreader --enable-xmlwriter --enable-simplexml --with-libxml \
  --enable-pdo --with-pdo-mysql=mysqlnd --with-pdo-sqlite --with-sqlite3 \
  --with-iconv --with-openssl --with-curl --with-zlib --enable-phar \
  --with-imap --with-imap-ssl --with-kerberos
make -j2
make install
mkdir -p "$TC_PREFIX/etc/conf.d"
"$TC_PREFIX/bin/php" -r 'if (PHP_VERSION !== getenv("TC_PHP_VERSION")) { fwrite(STDERR, "Unexpected PHP build: ".PHP_VERSION."\n"); exit(1); }'
printf '%s/bin\n' "$TC_PREFIX" >> "$GITHUB_PATH"
