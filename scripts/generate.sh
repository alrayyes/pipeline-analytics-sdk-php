#!/usr/bin/env bash
# Regenerates generated/ from openapi/openapi.yaml with openapi-generator's
# "php" target (rules/sdk-generation.md: generator choice named, not
# defaulted -- see CONTRIBUTING.md's "Generator choice" for why php over
# php-nextgen). Runs via the official Docker image rather than a local JVM,
# same reasoning go-docker.sh gives for Go's toolchain: a pinned, exact
# generator version beats whatever happens to be on the host.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

GENERATOR_IMAGE="openapitools/openapi-generator-cli@sha256:2ab0a9680222de65dc9d3baf861aa02b99e1b80c211d8221ebf3ae8f8a102524" # v7.25.0

# Pre-create the cache dir as the invoking user. Docker auto-creates a
# missing bind-mount source itself (as root, via the daemon) the first time
# a fresh machine -- a CI runner, say -- hits this script, and a root-owned
# mount is one --user can't write into (alrayyes/dotfiles#609).
mkdir -p "${HOME}/.cache/openapi-generator-docker"

docker run --rm --user "$(id -u):$(id -g)" \
  -e HOME=/tmp \
  -v "$(pwd):/local" \
  -v "${HOME}/.cache/openapi-generator-docker:/tmp/.openapi-generator-cache" \
  "$GENERATOR_IMAGE" generate \
  -i /local/openapi/openapi.yaml \
  -g php \
  -o /local/generated \
  --additional-properties="invokerPackage=PipelineAnalytics\\Generated,packageName=PipelineAnalyticsGenerated,composerPackageName=alrayyes/pipeline-analytics-sdk-php,artifactVersion=0.1.0,library=guzzle,licenseName=MIT,srcBasePath=src" \
  --global-property apiTests=false,modelTests=false,apiDocs=false,modelDocs=false
