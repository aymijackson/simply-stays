#!/usr/bin/env bash
set -euo pipefail

# Syncs public/ into the domain's document root, which on this host lives as
# a sibling folder next to the app rather than as public/'s parent — e.g.:
#
#   /home/user/short-stay/          <- this app (APP_DIR)
#   /home/user/stays.hitmoh.com/    <- TARGET_DIR, sibling, serves as document root
#
# Run this ON THE SERVER after every deploy (composer install / npm run build /
# migrate), not on the dev machine — it operates on sibling directories that
# only exist in the hosting layout.
#
# Usage: scripts/deploy-public.sh [target-folder-name]
#   target-folder-name defaults to "stays.hitmoh.com" and is resolved as a
#   sibling of this app's directory.

TARGET_NAME="${1:-stays.hitmoh.com}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
APP_NAME="$(basename "$APP_DIR")"
PARENT_DIR="$(dirname "$APP_DIR")"
TARGET_DIR="$PARENT_DIR/$TARGET_NAME"
PUBLIC_DIR="$APP_DIR/public"

if [ ! -d "$PUBLIC_DIR" ]; then
  echo "error: $PUBLIC_DIR does not exist" >&2
  exit 1
fi

mkdir -p "$TARGET_DIR"

echo "Syncing $PUBLIC_DIR/ -> $TARGET_DIR/"
rsync -a --delete \
  --exclude '.well-known' \
  --exclude 'storage' \
  "$PUBLIC_DIR"/ "$TARGET_DIR"/

# Symlinks aren't available on this host, so uploads are written directly
# into $TARGET_DIR/storage by the app (see PUBLIC_DISK_ROOT in .env — it must
# point here). Only ensure the directory exists; never touch its contents,
# since it holds live user uploads rather than deploy artifacts.
mkdir -p "$TARGET_DIR/storage"

# index.php's require paths assume public/ is a child of the app
# (../vendor, ../bootstrap/app.php, ../storage/framework/maintenance.php).
# Rewrite them to go through the app folder name now that public/ is copied
# out to a sibling instead.
INDEX="$TARGET_DIR/index.php"
sed -i \
  -e "s#__DIR__\.'/\.\./storage/framework/maintenance\.php'#__DIR__.'/../$APP_NAME/storage/framework/maintenance.php'#" \
  -e "s#__DIR__\.'/\.\./vendor/autoload\.php'#__DIR__.'/../$APP_NAME/vendor/autoload.php'#" \
  -e "s#__DIR__\.'/\.\./bootstrap/app\.php'#__DIR__.'/../$APP_NAME/bootstrap/app.php'#" \
  "$INDEX"

ENV_FILE="$APP_DIR/.env"
EXPECTED_ROOT="$TARGET_DIR/storage"
if [ -f "$ENV_FILE" ] && ! grep -qF "PUBLIC_DISK_ROOT=$EXPECTED_ROOT" "$ENV_FILE"; then
  echo "warning: $ENV_FILE does not set PUBLIC_DISK_ROOT=$EXPECTED_ROOT" >&2
  echo "         uploads will be written to storage/app/public instead and won't be served here." >&2
fi

echo "Done. $TARGET_NAME now serves $APP_NAME/public's contents."
