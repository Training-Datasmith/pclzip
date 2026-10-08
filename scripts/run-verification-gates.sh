#!/usr/bin/env bash
# PclZip verification harness: Docker-only vendor install and PHPUnit gates.
# PHP 5.6 (Stretch): Debian archive keys 10/11, Composer 2.2.30 with SHA-256 verify.
# PHP 7.4 (Bullseye): stock apt sources; PHPUnit 8.5.41 installed in-container only.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP56_IMAGE='php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143'
PHP74_IMAGE='php@sha256:620a6b9f4d4feef2210026172570465e9d0c1de79766418d3affd09190a7fda5'
COMPOSER_VERSION='2.2.30'
COMPOSER_SHA256='8c2b4478b64f8f7cdf1574838fdb0033b29049ca821dad452db7a3dcfcdbffc2'
COMPOSER_URL="https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar"
RANDOM_SEED='20261008'

DOCKER=(docker)
if ! docker info >/dev/null 2>&1; then
  DOCKER=(sudo docker)
fi

run_gates_php56() {
  "${DOCKER[@]}" run --rm -i -v "${REPO_ROOT}:/work" -w /work "$PHP56_IMAGE" bash -s <<'EOS'
set -euo pipefail
COMPOSER_VERSION='2.2.30'
COMPOSER_SHA256='8c2b4478b64f8f7cdf1574838fdb0033b29049ca821dad452db7a3dcfcdbffc2'
COMPOSER_URL="https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar"

cat >/etc/apt/sources.list <<'SRC'
deb [check-valid-until=no] http://archive.debian.org/debian stretch main
deb [check-valid-until=no] http://archive.debian.org/debian-security stretch/updates main
SRC
printf 'Acquire::Check-Valid-Until "false";\n' >/etc/apt/apt.conf.d/99no-check-valid-until
curl -fsSL -o /tmp/debian-archive-keyring.deb \
  http://archive.debian.org/debian/pool/main/d/debian-archive-keyring/debian-archive-keyring_2019.1+deb10u1_all.deb
dpkg -i /tmp/debian-archive-keyring.deb
apt-get update -qq
apt-get install -y -qq --no-install-recommends ca-certificates curl gnupg git unzip libzip-dev zlib1g-dev
docker-php-ext-install -j"$(nproc)" zip
curl -fsSL -o /tmp/archive-key-10.asc https://ftp-master.debian.org/keys/archive-key-10.asc
curl -fsSL -o /tmp/archive-key-11.asc https://ftp-master.debian.org/keys/archive-key-11.asc
cat /tmp/archive-key-10.asc /tmp/archive-key-11.asc | gpg --dearmor -o /etc/apt/trusted.gpg.d/archive-10-11.gpg
apt-get update -qq

tmp="$(mktemp)"
curl -fsSL "$COMPOSER_URL" -o "$tmp"
echo "${COMPOSER_SHA256}  ${tmp}" | sha256sum -c -
install -m 0755 "$tmp" /usr/local/bin/composer.phar
rm -f "$tmp"

rm -rf vendor
rm -f composer.lock
php /usr/local/bin/composer.phar update --no-interaction --no-progress

for i in 1 2 3 4; do
  echo "===== PHP 5.6 default run ${i}/4 ====="
  vendor/bin/phpunit -c phpunit.xml.dist
done
EOS
}

run_gates_php74() {
  "${DOCKER[@]}" run --rm -i -v "${REPO_ROOT}:/work" -w /work "$PHP74_IMAGE" bash -s <<EOS
set -euo pipefail
COMPOSER_VERSION='2.2.30'
COMPOSER_SHA256='8c2b4478b64f8f7cdf1574838fdb0033b29049ca821dad452db7a3dcfcdbffc2'
COMPOSER_URL="https://getcomposer.org/download/\${COMPOSER_VERSION}/composer.phar"
RANDOM_SEED='${RANDOM_SEED}'

apt-get update -qq
apt-get install -y -qq --no-install-recommends libzip-dev zlib1g-dev
docker-php-ext-install -j"\$(nproc)" zip

tmp="\$(mktemp)"
curl -fsSL "\$COMPOSER_URL" -o "\$tmp"
echo "\${COMPOSER_SHA256}  \${tmp}" | sha256sum -c -
install -m 0755 "\$tmp" /usr/local/bin/composer.phar
rm -f "\$tmp"

cp composer.json /tmp/composer.json.pinned
restore_composer_json() {
  if [ -f /tmp/composer.json.pinned ]; then
    mv -f /tmp/composer.json.pinned composer.json
  fi
}
trap restore_composer_json EXIT

rm -rf vendor
rm -f composer.lock
php /usr/local/bin/composer.phar update --no-interaction --no-progress
php /usr/local/bin/composer.phar config platform.php 7.4.33
php /usr/local/bin/composer.phar require --dev phpunit/phpunit:8.5.41 --no-interaction --no-progress --with-all-dependencies

for i in 1 2; do
  echo "===== PHP 7.4 default run \${i}/2 ====="
  vendor/bin/phpunit -c phpunit.xml.dist
done
for i in 1 2; do
  echo "===== PHP 7.4 random run \${i}/2 ====="
  vendor/bin/phpunit -c phpunit.xml.dist --order-by=random --random-order-seed=\${RANDOM_SEED}
done
echo "===== PHP 7.4 isolated testOptionDefaultThreshold ====="
vendor/bin/phpunit -c phpunit.xml.dist --filter testOptionDefaultThreshold
EOS
}

run_revert_proofs() {
  echo '===== Revert proofs (Docker, PHP 7.4) ====='
  "${DOCKER[@]}" run --rm -i -v "${REPO_ROOT}:/work" -w /work "$PHP74_IMAGE" bash -s <<'EOS'
set -euo pipefail
COMPOSER_VERSION='2.2.30'
COMPOSER_SHA256='8c2b4478b64f8f7cdf1574838fdb0033b29049ca821dad452db7a3dcfcdbffc2'
COMPOSER_URL="https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar"

apt-get update -qq
apt-get install -y -qq --no-install-recommends libzip-dev zlib1g-dev
docker-php-ext-install -j"$(nproc)" zip

tmp="$(mktemp)"
curl -fsSL "$COMPOSER_URL" -o "$tmp"
echo "${COMPOSER_SHA256}  ${tmp}" | sha256sum -c -
install -m 0755 "$tmp" /usr/local/bin/composer.phar
rm -f "$tmp"

cp composer.json /tmp/composer.json.pinned
restore_composer_json() {
  if [ -f /tmp/composer.json.pinned ]; then
    mv -f /tmp/composer.json.pinned composer.json
  fi
}
trap restore_composer_json EXIT

rm -rf vendor
rm -f composer.lock
php /usr/local/bin/composer.phar update --no-interaction --no-progress
php /usr/local/bin/composer.phar config platform.php 7.4.33
php /usr/local/bin/composer.phar require --dev phpunit/phpunit:8.5.41 --no-interaction --no-progress --with-all-dependencies

proof() {
  local name="$1"
  local filter="$2"
  shift 2
  cp pclzip.lib.php /tmp/pclzip.lib.php.bak
  "$@"
  set +e
  out=$(vendor/bin/phpunit -c phpunit.xml.dist --filter "$filter" 2>&1)
  code=$?
  set -e
  mv /tmp/pclzip.lib.php.bak pclzip.lib.php
  echo "--- revert proof: ${name} (expect failure, exit ${code}) ---"
  echo "$out" | tail -8
  if [ "$code" -eq 0 ]; then
    echo "ERROR: expected failing test for ${name}"
    exit 1
  fi
}

proof duplicate-instanceof testDuplicateByObject sed -i 's/$p_archive instanceof PclZip/(is_object($p_archive)) \&\& (get_class($p_archive) == '\''pclzip'\'')/' pclzip.lib.php
proof merge-instanceof testMergeByObject sed -i 's/$p_archive_to_add instanceof PclZip/(is_object($p_archive_to_add)) \&\& (get_class($p_archive_to_add) == '\''pclzip'\'')/' pclzip.lib.php
proof errorName-already-a-directory testAlreadyADirectory sed -i '/PCLZIP_ERR_ALREADY_A_DIRECTORY =>/d' pclzip.lib.php
EOS
}

main() {
  echo "Repo: ${REPO_ROOT}"
  echo "PHP 5.6 image: ${PHP56_IMAGE}"
  echo "PHP 7.4 image: ${PHP74_IMAGE}"
  echo "Composer ${COMPOSER_VERSION} SHA-256: ${COMPOSER_SHA256}"
  run_gates_php56
  run_gates_php74
  run_revert_proofs
  echo "All gates and revert proofs completed."
}

main "$@"
