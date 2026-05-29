#!/usr/bin/env bash
#
# build.sh — Package the plugin into a clean WordPress.org-ready zip.
#
# Usage:
#   ./build.sh
#
# Produces:
#   ./dist/netpeak-analytics-kit.zip
#
# Requirements: bash, zip, rsync, composer (macOS has these or via brew).
#

set -euo pipefail

# ----------------------------------------------------------------------------
# Config
# ----------------------------------------------------------------------------

PLUGIN_SLUG="netpeak-analytics-kit"
BUILD_DIR="dist"
STAGING_DIR="${BUILD_DIR}/${PLUGIN_SLUG}"
ZIP_FILE="${BUILD_DIR}/${PLUGIN_SLUG}.zip"

# Files / folders to exclude from the package.
# Note: composer.json is INCLUDED (wp.org reviewers require it for transparency).
EXCLUDES=(
    ".git"
    ".github"
    ".gitignore"
    ".gitattributes"
    ".asset-backups"
    ".gitkeep"
    ".editorconfig"
    ".distignore"
    ".wp-env.json"
    ".idea"
    ".vscode"
    ".DS_Store"
    ".phpcs.cache"
    "node_modules"
    "tests"
    "docs"
    "vendor/bin"
    "composer.lock"
    "package.json"
    "package-lock.json"
    "phpcs.xml"
    "phpcs.xml.dist"
    "phpunit.xml"
    "phpunit.xml.dist"
    "phpstan.neon"
    "build.sh"
    "assets.sh"
    "README.md"
    "CHANGELOG.md"
    "CONTRIBUTING.md"
    "TODO.md"
    "wordpress-org-review.md"
    "dist"
)

# ----------------------------------------------------------------------------
# Colors for output
# ----------------------------------------------------------------------------

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

info()  { echo -e "${GREEN}==>${NC} $1"; }
warn()  { echo -e "${YELLOW}!${NC}  $1"; }
error() { echo -e "${RED}✗${NC}  $1" >&2; }

# ----------------------------------------------------------------------------
# Pre-flight checks
# ----------------------------------------------------------------------------

if [[ ! -f "netpeak-analytics-kit.php" ]]; then
    error "Main plugin file 'netpeak-analytics-kit.php' not found. Run this from the plugin root."
    exit 1
fi

for cmd in zip rsync composer; do
    if ! command -v "$cmd" &> /dev/null; then
        error "Required command '$cmd' not found. Install it (brew install $cmd)."
        exit 1
    fi
done

# ----------------------------------------------------------------------------
# Clean previous build
# ----------------------------------------------------------------------------

info "Cleaning previous build..."
rm -rf "${BUILD_DIR}"
mkdir -p "${STAGING_DIR}"

# ----------------------------------------------------------------------------
# Install production Composer dependencies
# ----------------------------------------------------------------------------

info "Installing production Composer dependencies..."
composer install --no-dev --prefer-dist --classmap-authoritative --optimize-autoloader --quiet

# ----------------------------------------------------------------------------
# Build rsync exclude args
# ----------------------------------------------------------------------------

RSYNC_EXCLUDES=()
for item in "${EXCLUDES[@]}"; do
    RSYNC_EXCLUDES+=(--exclude "$item")
done

# ----------------------------------------------------------------------------
# Copy plugin files into staging
# ----------------------------------------------------------------------------

info "Copying plugin files to staging..."
rsync -a "${RSYNC_EXCLUDES[@]}" ./ "${STAGING_DIR}/"

# ----------------------------------------------------------------------------
# Strip composer temporary files (composer occasionally leaves tmp-*.zip~)
# ----------------------------------------------------------------------------

info "Stripping Composer temp files..."
find "${STAGING_DIR}/vendor" -name "tmp-*" -delete 2>/dev/null || true
find "${STAGING_DIR}/vendor" -name "*.zip~" -delete 2>/dev/null || true
find "${STAGING_DIR}/vendor" -name "*~" -delete 2>/dev/null || true

# ----------------------------------------------------------------------------
# Remove macOS metadata files anywhere in the tree (._* and .DS_Store)
# ----------------------------------------------------------------------------

info "Stripping macOS metadata..."
find "${STAGING_DIR}" -name ".DS_Store" -type f -delete
find "${STAGING_DIR}" -name "._*" -type f -delete

# ----------------------------------------------------------------------------
# Create the zip (no macOS resource forks thanks to COPYFILE_DISABLE)
# ----------------------------------------------------------------------------

info "Creating zip archive..."
( cd "${BUILD_DIR}" && COPYFILE_DISABLE=1 zip -rq "${PLUGIN_SLUG}.zip" "${PLUGIN_SLUG}" -x "*.DS_Store" )

# ----------------------------------------------------------------------------
# Restore dev dependencies (so your local env keeps phpcs etc.)
# ----------------------------------------------------------------------------

info "Restoring dev dependencies..."
composer install --quiet

# ----------------------------------------------------------------------------
# Report
# ----------------------------------------------------------------------------

ZIP_SIZE=$(du -h "${ZIP_FILE}" | cut -f1)

echo ""
info "Build complete!"
echo "    File: ${ZIP_FILE}"
echo "    Size: ${ZIP_SIZE}"
echo ""
warn "Verify contents before submitting:"
echo "    unzip -l ${ZIP_FILE}"
echo ""
warn "Make sure composer.json IS inside the archive:"
echo "    unzip -l ${ZIP_FILE} | grep composer.json"
echo ""
