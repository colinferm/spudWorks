#!/usr/bin/env bash
#
# Runs the spudWorks Magazine demo-data cleanup inside the project's Docker
# web container. Thin wrapper around build/cleanup-dev-data.php.
#
# Usage:
#     build/cleanup-dev-data.sh [container-name]
#
# Defaults to the container defined in docker-compose.yaml.

set -euo pipefail

CONTAINER="${1:-spudworks-web-server}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PATH="/var/www/html"
TARGET="/tmp/cleanup-dev-data.php"

if ! docker ps --format '{{.Names}}' | grep -q "^${CONTAINER}$"; then
	echo "Container '${CONTAINER}' is not running. Start the stack with 'docker compose up' first." >&2
	exit 1
fi

echo "Copying cleanup script into ${CONTAINER}..."
docker cp "${SCRIPT_DIR}/cleanup-dev-data.php" "${CONTAINER}:${TARGET}"

echo "Running cleanup via WP-CLI..."
docker exec "${CONTAINER}" sh -lc "wp --allow-root --path=${WP_PATH} eval-file ${TARGET}"

docker exec "${CONTAINER}" sh -lc "rm -f ${TARGET}"
echo "Cleanup complete."
