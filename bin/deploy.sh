#!/usr/bin/env bash
# bin/deploy.sh — deploy the alexmchong.ca static marketing site.
#
# Usage:
#   bin/deploy.sh staging                # → staging.alexmchong.ca (Basic Auth)
#   bin/deploy.sh prod                   # → alexmchong.ca         (public)
#   bin/deploy.sh <target> --dry-run     # preview without uploading
#   bin/deploy.sh <target> --no-delete   # upsert only, do not remove orphans
#
# Requires the SSH host alias 'alexmchong-ca' in ~/.ssh/config
# (set up once during Phase 1 — see DEPLOYMENT.md).
#
# What it does:
#   1. Assembles a per-target deploy directory in /tmp (landing.html
#      becomes index.html; the right .htaccess is picked per target).
#   2. Normalizes file modes to 644/755.
#   3. Rsyncs to the remote webroot, preserving server-managed files
#      (.dh-diag, .ftpquota, .well-known/, .htpasswd) and Alex's live
#      experiments (_archive/, _labs/, _files/).
#   4. By default, deletes orphan files on the server that are not in
#      the local source — pass --no-delete to disable.

set -euo pipefail
shopt -s nullglob

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# ── Args ────────────────────────────────────────────────────────────
TARGET="${1:-}"
case "$TARGET" in
  staging)
    REMOTE_DIR="staging.alexmchong.ca/"
    HTACCESS_SRC="deploy/staging.htaccess"
    PUBLIC_URL="https://staging.alexmchong.ca"
    ;;
  prod|production)
    REMOTE_DIR="alexmchong.ca/"
    HTACCESS_SRC="site/.htaccess"
    PUBLIC_URL="https://alexmchong.ca"
    ;;
  *)
    cat <<EOF
Usage: $0 {staging|prod} [--dry-run] [--no-delete]
EOF
    exit 2
    ;;
esac
shift

DRY=""
DELETE="--delete"
for arg in "$@"; do
  case "$arg" in
    --dry-run|-n) DRY="--dry-run" ;;
    --no-delete)  DELETE="" ;;
    *) echo "Unknown flag: $arg"; exit 2 ;;
  esac
done

# ── Build the local staging directory ───────────────────────────────
STAGE="$(mktemp -d -t alexmchong-deploy-XXXX)"
trap 'rm -rf "$STAGE"' EXIT

echo "==> Assembling $TARGET deploy in $STAGE"

# Marketing pages (landing → index on upload)
cp site/_pages/about.html              "$STAGE/"
cp site/_pages/coaching.html           "$STAGE/"
cp site/_pages/landing.html            "$STAGE/index.html"
cp site/_pages/newsletter-confirmed.html "$STAGE/"
cp site/_pages/newsletter.html         "$STAGE/"
cp site/_pages/resume.html             "$STAGE/"
cp site/_pages/work-with-me.html       "$STAGE/"
cp site/_pages/404.html                "$STAGE/"

# Asset folder for marketing pages
mkdir -p "$STAGE/_layout"
cp -R site/_pages/_layout/. "$STAGE/_layout/"

# Design system showcase
mkdir -p "$STAGE/_ds"
cp -R site/_design-system/. "$STAGE/_ds/"

# Target-specific .htaccess
cp "$HTACCESS_SRC" "$STAGE/.htaccess"

# Normalize modes — rsync's --chmod also enforces these on the server
find "$STAGE" -type f -exec chmod 644 {} \;
find "$STAGE" -type d -exec chmod 755 {} \;

# ── Show what we're about to ship ───────────────────────────────────
echo "==> Local source tree (top two levels):"
find "$STAGE" -mindepth 1 -maxdepth 2 -print | sed "s|^$STAGE/|  |" | sort

# ── Rsync ───────────────────────────────────────────────────────────
EXCLUDES=(
  # DreamHost system files
  --exclude='.dh-diag'
  --exclude='.dh-diag.txt'
  --exclude='.ftpquota'
  # Let's Encrypt SSL renewal challenge dir — DO NOT TOUCH
  --exclude='.well-known/'
  --exclude='.well-known/**'
  # Staging Basic Auth (lives on staging server only, never in source)
  --exclude='.htpasswd'
  # Alex's live experiments (kept as-is per Phase 1 decisions)
  --exclude='_archive/'
  --exclude='_archive/**'
  --exclude='_labs/'
  --exclude='_labs/**'
  --exclude='_files/'
  --exclude='_files/**'
)

echo
echo "==> Rsync to alexmchong-ca:$REMOTE_DIR"
echo "    dry-run: ${DRY:-no}    delete-orphans: ${DELETE:-no}"
echo

# Modes were already normalized in the local stage via chmod above;
# rsync -a preserves them on upload, so no --chmod flag is needed
# (macOS ships rsync 2.6.9 which doesn't grok the modern --chmod
# syntax anyway).
rsync -av $DRY $DELETE "${EXCLUDES[@]}" \
  "$STAGE/" "alexmchong-ca:$REMOTE_DIR"

if [ -z "$DRY" ]; then
  echo
  echo "==> Deploy to $TARGET complete."
  echo "    Public URL: $PUBLIC_URL"
fi
