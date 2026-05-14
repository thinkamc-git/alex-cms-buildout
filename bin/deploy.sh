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

# Phase 3: PHP front controller + libraries + config + migrations.
# Config files config.{local,staging,production}.php live on each server
# (one per env, hand-placed, never committed — see .gitignore). They are
# NOT shipped from source; only config.php (the resolver) and
# config.example.php (the template) ship.
cp site/index.php "$STAGE/"
mkdir -p "$STAGE/config" "$STAGE/lib" "$STAGE/db/migrations"
cp site/config/config.php          "$STAGE/config/"
cp site/config/config.example.php  "$STAGE/config/"
cp site/config/.htaccess           "$STAGE/config/"
cp site/lib/db.php                 "$STAGE/lib/"
cp site/lib/router.php             "$STAGE/lib/"
cp site/db/migrate.php             "$STAGE/db/"
cp site/db/migrations/*.sql        "$STAGE/db/migrations/"

# Phase 4: auth library, /cms/* pages, one-shot setup.
# setup.php self-deletes on the server after first password change. Once
# that's happened, rsync's --delete will NOT recreate it because the local
# source still has it and the server-side delete is final until next deploy
# — which is fine; setup.php is harmless when no user row exists.
cp site/lib/auth.php               "$STAGE/lib/"
cp site/lib/csrf.php               "$STAGE/lib/"
cp site/setup.php                  "$STAGE/"
mkdir -p "$STAGE/cms"
cp site/cms/index.php              "$STAGE/cms/"
cp site/cms/login.php              "$STAGE/cms/"
cp site/cms/logout.php             "$STAGE/cms/"
cp site/cms/account.php            "$STAGE/cms/"
cp site/cms/unlock-account.php     "$STAGE/cms/"

# Phase 5: admin shell partials + CMS-specific stylesheet.
# The 5 partials (sidebar/topbar/view-header/filter-bar/table) compose every
# admin view from Phase 6a onward. style-cms.css layers CMS-only overrides
# on top of /_ds/css/* (which is already deployed by the design-system step).
mkdir -p "$STAGE/cms/partials" "$STAGE/cms/_assets"
cp site/cms/partials/*.php         "$STAGE/cms/partials/"
cp site/cms/_assets/style-cms.css  "$STAGE/cms/_assets/"

# Phase 6a: cms/.htaccess (deny direct HTTP access to PHP under /cms/),
# Articles CRUD lib + views + Tiptap editor assets.
cp site/cms/.htaccess              "$STAGE/cms/"
cp site/lib/content.php            "$STAGE/lib/"
cp site/lib/sanitize.php           "$STAGE/lib/"
cp site/lib/uploads.php            "$STAGE/lib/"
mkdir -p "$STAGE/cms/views"
cp site/cms/views/*.php            "$STAGE/cms/views/"
cp site/cms/_assets/tiptap.css     "$STAGE/cms/_assets/"
cp site/cms/_assets/tiptap-setup.js "$STAGE/cms/_assets/"

# Phase 6b: public Article rendering. Templates live under /templates/
# (PHP wrappers + per-block partials). lib/render.php is the entry point
# called from index.php's /writing/:slug route. lib/author.php hydrates
# the single-row author config. The article stylesheet rides along at
# /_templates/style-articles.css (only style-articles.css is shipped —
# article.html and layouts.html are design references, never deployed).
cp site/lib/author.php             "$STAGE/lib/"
cp site/lib/render.php             "$STAGE/lib/"
mkdir -p "$STAGE/templates/partials" "$STAGE/_templates"
cp site/templates/.htaccess           "$STAGE/templates/"
cp site/templates/master-layout.php   "$STAGE/templates/"
cp site/templates/article-standard.php "$STAGE/templates/"
cp site/templates/partials/*.php   "$STAGE/templates/partials/"
cp site/_templates/style-articles.css "$STAGE/_templates/"

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
  # Per-environment DB config — each is hand-placed on its own server.
  # Never in source. Without these excludes, --delete wipes them on every
  # deploy and the next request 500s with "Missing config file: …".
  --exclude='config/config.local.php'
  --exclude='config/config.staging.php'
  --exclude='config/config.production.php'
  # Server-only runtime folders (created by app, never shipped from source).
  # See .gitignore for the source-side counterpart.
  --exclude='uploads/'
  --exclude='uploads/**'
  --exclude='content/'
  --exclude='content/**'
  --exclude='logs/'
  --exclude='logs/**'
  --exclude='backups/'
  --exclude='backups/**'
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
