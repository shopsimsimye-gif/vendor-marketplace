#!/usr/bin/env sh
# Wrapper for CI / local: runs the architecture test against the plugin root.
set -e
DIR="$(cd "$(dirname "$0")/.." && pwd)"
php "$DIR/scripts/architecture-test.php" --root="$DIR" "$@"
