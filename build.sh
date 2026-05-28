#!/usr/bin/env bash
#
# build.sh — Package the plugin into a clean WordPress.org-ready zip.
#
# Usage:
#   ./build.sh
#
# Produces:
#   ./dist/netpeak-aio.zip
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
EXCLUDES=(
    ".git"
    ".github"
    ".gitignore"
    ".gitattributes"
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
    "composer.json"
    "composer.lock"
    "package.json"
    "package-lock.json"
    "phpcs.xml"
    "phpcs.xml.dist"
    "phpunit.xml"
    "phpunit.xml.dist"
    "phpstan.neon"
    "build.sh"
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

if [[ ! -f "${PLUGIN_SLUG}.php" ]]; then
    error "Main plugin file '${PLUGIN_SLUG}.php' not found. Run this from the plugin root."
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
# Install production Composer dependencies into a temp vendor
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
