#!/usr/bin/env bash
# Downloads and runs a pinned phpDocumentor.phar instead of requiring
# phpdocumentor/phpdocumentor as a composer dependency. This repo's own
# transitive deps (paratest/nunomaduro pulled in by pestphp) already lock
# symfony/console to ^7/^8, and phpDocumentor 3.10.0 -- its latest release
# -- caps at ^6.0, so `composer require` can never resolve regardless of
# which version is asked for. The phar sidesteps that tree entirely.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

PHPDOC_VERSION="3.10.0"
PHPDOC_URL="https://github.com/phpDocumentor/phpDocumentor/releases/download/v${PHPDOC_VERSION}/phpDocumentor.phar"
PHPDOC_SHA256="fe1e7c23ba3329aa6f19ac3c807446159a431a195ec5d9163b0c281a15105207"

CACHE_DIR="${HOME}/.cache/phpdocumentor"
PHAR_PATH="${CACHE_DIR}/phpDocumentor-${PHPDOC_VERSION}.phar"

mkdir -p "$CACHE_DIR"

if [ ! -f "$PHAR_PATH" ]; then
  curl -fsSL -o "$PHAR_PATH.tmp" "$PHPDOC_URL"
  echo "${PHPDOC_SHA256}  ${PHAR_PATH}.tmp" | sha256sum -c -
  mv "$PHAR_PATH.tmp" "$PHAR_PATH"
fi

php "$PHAR_PATH" "$@"
