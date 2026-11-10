#!/usr/bin/env bash
# Установка NetherGamesMC/PocketMine-MP без Docker: PHP от pmmp + сборка PHAR в каталог rubyx-server/.
# Запуск:  bash rubyx-server/install-nethergames-pm.sh
# Старт:   cd rubyx-server && ./start.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
DEST="${SCRIPT_DIR}"
SRC="${DEST}/PocketMine-MP-src"
RUNTIME="${DEST}/runtime"
REPO="https://github.com/NetherGamesMC/PocketMine-MP.git"
BRANCH="stable"

PM_PHP_TAG="pm5-php-8.4-latest"
PHP_URL_X64="https://github.com/pmmp/PHP-Binaries/releases/download/${PM_PHP_TAG}/PHP-8.4-Linux-x86_64-PM5.tar.gz"

echo "==> Каталог сервера: ${DEST}"

command -v git >/dev/null || { echo "Нужен git" >&2; exit 1; }
command -v curl >/dev/null || { echo "Нужен curl" >&2; exit 1; }
command -v tar >/dev/null || { echo "Нужен tar" >&2; exit 1; }

ARCH="$(uname -m)"
if [[ "${ARCH}" != "x86_64" ]]; then
  echo "Скрипт ожидает Linux x86_64 (сейчас: ${ARCH}). См. https://github.com/pmmp/PHP-Binaries/releases/tag/${PM_PHP_TAG}" >&2
  exit 1
fi

mkdir -p "${RUNTIME}" "${DEST}"

resolve_php() {
  find "${RUNTIME}" -path '*/bin/php' -type f 2>/dev/null | head -1
}

PHP_BIN="$(resolve_php)"
if [[ -z "${PHP_BIN}" || ! -f "${PHP_BIN}" ]]; then
  echo "==> Скачивание PHP (PM5, расширения для PocketMine)..."
  curl -fsSL -o "${RUNTIME}/php-linux.tar.gz" "${PHP_URL_X64}"
  tar -xzf "${RUNTIME}/php-linux.tar.gz" -C "${RUNTIME}"
  rm -f "${RUNTIME}/php-linux.tar.gz"
  PHP_BIN="$(resolve_php)"
fi

if [[ -z "${PHP_BIN}" || ! -f "${PHP_BIN}" ]]; then
  echo "Не найден интерпретатор php в ${RUNTIME}" >&2
  exit 1
fi
chmod +x "${PHP_BIN}" 2>/dev/null || true
echo "    PHP: ${PHP_BIN}"

# В сборке PHP от PMMP zend_extension=opcache.so часто ссылается на путь CI — на хосте .so не находится.
fix_php_ini_opcache() {
  local ini
  ini="$(find "${RUNTIME}" -name php.ini -path '*/bin/*' 2>/dev/null | head -1)"
  [[ -z "${ini}" || ! -f "${ini}" ]] && return 0
  if grep -q '^zend_extension=opcache.so' "${ini}" 2>/dev/null; then
    sed -i 's/^zend_extension=opcache.so/;zend_extension=opcache.so/' "${ini}"
    sed -i 's/^opcache.enable=1/opcache.enable=0/' "${ini}"
    sed -i 's/^opcache.enable_cli=1/opcache.enable_cli=0/' "${ini}"
    echo "    php.ini: отключена загрузка opcache (нет корректного .so на этой системе)"
  fi
}
fix_php_ini_opcache

if [[ ! -x "${RUNTIME}/composer" ]]; then
  echo "==> Загрузка Composer (под PHP от PMMP)..."
  curl -fsSL -o "${RUNTIME}/composer-setup.php" https://getcomposer.org/installer
  "${PHP_BIN}" "${RUNTIME}/composer-setup.php" --install-dir="${RUNTIME}" --filename=composer
  rm -f "${RUNTIME}/composer-setup.php"
fi
COMPOSER="${RUNTIME}/composer"

git config --global url."https://github.com/".insteadOf git@github.com: 2>/dev/null || true

if [[ ! -d "${SRC}/.git" ]]; then
  echo "==> Клонирование ${REPO} (${BRANCH})..."
  rm -rf "${SRC}"
  git clone --depth 1 --branch "${BRANCH}" "${REPO}" "${SRC}"
else
  echo "==> Обновление исходников..."
  git -C "${SRC}" fetch --depth 1 origin "${BRANCH}"
  git -C "${SRC}" reset --hard "origin/${BRANCH}"
fi

echo "==> composer install..."
(cd "${SRC}" && "${PHP_BIN}" "${COMPOSER}" install --no-dev --no-interaction --classmap-authoritative --ignore-platform-reqs)

echo "==> composer make-server (PocketMine-MP.phar)..."
(cd "${SRC}" && "${PHP_BIN}" "${COMPOSER}" make-server)

if [[ ! -f "${SRC}/PocketMine-MP.phar" ]]; then
  echo "PocketMine-MP.phar не собран." >&2
  exit 1
fi

cp -f "${SRC}/PocketMine-MP.phar" "${DEST}/PocketMine-MP.phar"
[[ -f "${SRC}/start.sh" ]] && cp -f "${SRC}/start.sh" "${DEST}/start.sh" || true
chmod +x "${DEST}/start.sh" 2>/dev/null || true

# Сборка из исходников = dev; иначе сервер остановится с требованием enable-dev-builds.
if [[ -f "${DEST}/pocketmine.yml" ]] && grep -q 'enable-dev-builds: false' "${DEST}/pocketmine.yml"; then
  sed -i 's/^  enable-dev-builds: false/  enable-dev-builds: true/' "${DEST}/pocketmine.yml"
  echo "    pocketmine.yml: enable-dev-builds = true"
fi

echo ""
echo "Готово."
echo "  PHAR:   ${DEST}/PocketMine-MP.phar"
echo "  Запуск: cd ${DEST} && ./start.sh"
echo "  Данные сервера (worlds, server.properties) появятся в: ${DEST}"
