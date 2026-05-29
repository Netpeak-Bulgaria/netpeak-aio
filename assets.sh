#!/usr/bin/env bash
#
# assets.sh — Auto-update bundled JS libraries with backup/restore support.
#
# Downloads the latest non-beta releases of Alpine.js (3.x) and Chart.js (4.x)
# from jsDelivr, keeps timestamped backups, and updates assets/README.md.
#
# Usage:
#   ./assets.sh                   # Update both libraries (with auto-backup)
#   ./assets.sh --lib alpine      # Update only Alpine.js
#   ./assets.sh --lib chart       # Update only Chart.js
#   ./assets.sh --no-backup       # Skip backup before update
#   ./assets.sh --list-backups    # Show available backups
#   ./assets.sh --restore <id>    # Restore a specific backup
#   ./assets.sh --restore latest  # Restore the most recent backup
#   ./assets.sh --prune           # Delete backups older than 30 days
#   ./assets.sh --help            # Show help
#

set -euo pipefail

# ----------------------------------------------------------------------------
# Config
# ----------------------------------------------------------------------------

ASSETS_DIR="assets/admin/js"
README_FILE="assets/README.md"
BACKUP_ROOT=".asset-backups"
PRUNE_DAYS=30

ALPINE_FILE="${ASSETS_DIR}/alpine.min.js"
CHART_FILE="${ASSETS_DIR}/chart.min.js"

# ----------------------------------------------------------------------------
# Colors
# ----------------------------------------------------------------------------

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

info()    { echo -e "${GREEN}==>${NC} $1"; }
warn()    { echo -e "${YELLOW}!${NC}  $1"; }
error()   { echo -e "${RED}✗${NC}  $1" >&2; }
step()    { echo -e "${BLUE}::${NC} $1"; }
hint()    { echo -e "${CYAN}»${NC}  $1"; }

# ----------------------------------------------------------------------------
# Usage
# ----------------------------------------------------------------------------

show_help() {
    cat <<HELP
assets.sh — Manage bundled JS libraries

USAGE:
    ./assets.sh [OPTIONS]

OPTIONS:
    --lib <name>          Update only specified library: alpine, chart
    --no-backup           Skip backup creation before update
    --list-backups        Show all available backups
    --restore <id>        Restore backup by ID, or 'latest' for the most recent
    --prune               Delete backups older than ${PRUNE_DAYS} days
    -h, --help            Show this help

EXAMPLES:
    ./assets.sh
    ./assets.sh --lib alpine
    ./assets.sh --list-backups
    ./assets.sh --restore latest
    ./assets.sh --restore 20260530-143022
HELP
}

# ----------------------------------------------------------------------------
# Pre-flight checks
# ----------------------------------------------------------------------------

check_dependencies() {
    for cmd in curl jq; do
        if ! command -v "$cmd" &> /dev/null; then
            error "Required command '$cmd' not found. Install it (brew install $cmd)."
            exit 1
        fi
    done
}

check_assets_dir() {
    if [[ ! -d "${ASSETS_DIR}" ]]; then
        error "Assets directory '${ASSETS_DIR}' not found. Run this from the plugin root."
        exit 1
    fi
}

# ----------------------------------------------------------------------------
# Backup
# ----------------------------------------------------------------------------

create_backup() {
    local backup_id
    backup_id=$(date +"%Y%m%d-%H%M%S")
    local backup_path="${BACKUP_ROOT}/${backup_id}"

    mkdir -p "${backup_path}"

    local copied=0

    if [[ -f "${ALPINE_FILE}" ]]; then
        cp "${ALPINE_FILE}" "${backup_path}/alpine.min.js"
        copied=$((copied + 1))
    fi

    if [[ -f "${CHART_FILE}" ]]; then
        cp "${CHART_FILE}" "${backup_path}/chart.min.js"
        copied=$((copied + 1))
    fi

    if [[ -f "${README_FILE}" ]]; then
        cp "${README_FILE}" "${backup_path}/README.md"
        copied=$((copied + 1))
    fi

    if [[ "${copied}" -eq 0 ]]; then
        rmdir "${backup_path}"
        warn "No files to backup — skipping."
        return 0
    fi

    # Write metadata for the backup
    local alpine_version=""
    local chart_version=""

    if [[ -f "${backup_path}/alpine.min.js" ]]; then
        alpine_version=$(grep -oE 'version:"[^"]+"' "${backup_path}/alpine.min.js" | head -1 | sed 's/version:"\(.*\)"/\1/' || echo "unknown")
    fi

    if [[ -f "${backup_path}/chart.min.js" ]]; then
        chart_version=$(grep -oE 'Chart\.js v[0-9.]+' "${backup_path}/chart.min.js" | head -1 | sed 's/Chart.js v//' || echo "unknown")
    fi

    cat > "${backup_path}/.meta" <<META
created_at=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
alpine_version=${alpine_version:-unknown}
chart_version=${chart_version:-unknown}
META

    info "Backup created: ${backup_id} (alpine=${alpine_version:-none}, chart=${chart_version:-none})"
}

list_backups() {
    if [[ ! -d "${BACKUP_ROOT}" ]]; then
        warn "No backups directory found (${BACKUP_ROOT})."
        return 0
    fi

    local backups
    backups=$(find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d | sort -r)

    if [[ -z "${backups}" ]]; then
        warn "No backups available."
        return 0
    fi

    step "Available backups (newest first):"
    echo ""

    while IFS= read -r backup; do
        local id
        id=$(basename "${backup}")

        local alpine_v="?"
        local chart_v="?"
        local created="?"

        if [[ -f "${backup}/.meta" ]]; then
            alpine_v=$(grep '^alpine_version=' "${backup}/.meta" | cut -d'=' -f2)
            chart_v=$(grep '^chart_version=' "${backup}/.meta" | cut -d'=' -f2)
            created=$(grep '^created_at=' "${backup}/.meta" | cut -d'=' -f2)
        fi

        printf "  %s  alpine=%-8s  chart=%-8s  (created %s)\n" "${id}" "${alpine_v}" "${chart_v}" "${created}"
    done <<< "${backups}"

    echo ""
    hint "Restore with: ./assets.sh --restore <id>"
    hint "Restore latest with: ./assets.sh --restore latest"
}

restore_backup() {
    local target="$1"

    if [[ ! -d "${BACKUP_ROOT}" ]]; then
        error "No backups directory found."
        exit 1
    fi

    # Resolve 'latest' to the newest backup ID
    if [[ "${target}" == "latest" ]]; then
        target=$(find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d | sort -r | head -1 | xargs -n1 basename || echo "")

        if [[ -z "${target}" ]]; then
            error "No backups available to restore."
            exit 1
        fi

        info "Latest backup: ${target}"
    fi

    local backup_path="${BACKUP_ROOT}/${target}"

    if [[ ! -d "${backup_path}" ]]; then
        error "Backup not found: ${target}"
        hint "Run './update-assets.sh --list-backups' to see available IDs."
        exit 1
    fi

    step "Restoring backup ${target}..."

    local restored=0

    if [[ -f "${backup_path}/alpine.min.js" ]]; then
        cp "${backup_path}/alpine.min.js" "${ALPINE_FILE}"
        info "Restored: ${ALPINE_FILE}"
        restored=$((restored + 1))
    fi

    if [[ -f "${backup_path}/chart.min.js" ]]; then
        cp "${backup_path}/chart.min.js" "${CHART_FILE}"
        info "Restored: ${CHART_FILE}"
        restored=$((restored + 1))
    fi

    if [[ -f "${backup_path}/README.md" ]]; then
        cp "${backup_path}/README.md" "${README_FILE}"
        info "Restored: ${README_FILE}"
        restored=$((restored + 1))
    fi

    if [[ "${restored}" -eq 0 ]]; then
        warn "Backup is empty — nothing restored."
        exit 1
    fi

    echo ""
    info "Restore complete (${restored} file(s) restored)."
}

prune_backups() {
    if [[ ! -d "${BACKUP_ROOT}" ]]; then
        warn "No backups directory found."
        return 0
    fi

    step "Pruning backups older than ${PRUNE_DAYS} days..."

    local deleted=0

    while IFS= read -r backup; do
        rm -rf "${backup}"
        info "Deleted: $(basename "${backup}")"
        deleted=$((deleted + 1))
    done < <(find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d -mtime +${PRUNE_DAYS})

    if [[ "${deleted}" -eq 0 ]]; then
        info "No old backups to prune."
    else
        info "Pruned ${deleted} backup(s)."
    fi
}

# ----------------------------------------------------------------------------
# NPM helpers
# ----------------------------------------------------------------------------

get_latest_stable() {
    local package="$1"
    local version
    version=$(curl -sL "https://registry.npmjs.org/${package}/latest" | jq -r '.version')

    if [[ -z "${version}" || "${version}" == "null" ]]; then
        error "Failed to fetch latest version for ${package}"
        return 1
    fi

    if [[ "${version}" =~ (beta|rc|alpha|dev|next|pre) ]]; then
        error "Latest version of ${package} is a pre-release (${version}). Aborting."
        return 1
    fi

    echo "${version}"
}

verify_download() {
    local file="$1"
    local expected_version="$2"
    local lib_name="$3"

    if [[ ! -f "${file}" ]]; then
        error "${lib_name}: file not found after download: ${file}"
        return 1
    fi

    local size
    size=$(wc -c < "${file}" | tr -d ' ')

    if [[ "${size}" -lt 1000 ]]; then
        error "${lib_name}: downloaded file is suspiciously small (${size} bytes)"
        return 1
    fi

    if ! grep -q "${expected_version}" "${file}"; then
        warn "${lib_name}: version string '${expected_version}' not found in file (may use different marker)"
    fi

    info "${lib_name}: verified (${size} bytes)"
    return 0
}

# ----------------------------------------------------------------------------
# README updater
# ----------------------------------------------------------------------------

update_readme_version() {
    local lib_name="$1"
    local new_version="$2"

    if [[ ! -f "${README_FILE}" ]]; then
        warn "README file not found at ${README_FILE} — skipping README update."
        return 0
    fi

    perl -i -pe "
        BEGIN { \$in_section = 0; }
        if (/^## ${lib_name}\s*\$/) { \$in_section = 1; }
        elsif (/^## /) { \$in_section = 0; }
        if (\$in_section && /^- Version:/) {
            s/^- Version: .*/- Version: ${new_version}/;
            \$in_section = 0;
        }
    " "${README_FILE}"

    info "README updated: ${lib_name} → ${new_version}"
}

# ----------------------------------------------------------------------------
# Update routines
# ----------------------------------------------------------------------------

update_alpine() {
    step "Updating Alpine.js..."

    local version
    version=$(get_latest_stable "alpinejs") || return 1
    info "Latest stable: ${version}"

    local url="https://cdn.jsdelivr.net/npm/alpinejs@${version}/dist/cdn.min.js"
    info "Downloading: ${url}"

    if ! curl -sL -f -o "${ALPINE_FILE}" "${url}"; then
        error "Failed to download Alpine.js"
        return 1
    fi

    verify_download "${ALPINE_FILE}" "${version}" "Alpine.js" || return 1
    update_readme_version "Alpine.js" "${version}"
}

update_chart() {
    step "Updating Chart.js..."

    local version
    version=$(get_latest_stable "chart.js") || return 1
    info "Latest stable: ${version}"

    local url="https://cdn.jsdelivr.net/npm/chart.js@${version}/dist/chart.umd.min.js"
    info "Downloading: ${url}"

    if ! curl -sL -f -o "${CHART_FILE}" "${url}"; then
        error "Failed to download Chart.js"
        return 1
    fi

    verify_download "${CHART_FILE}" "${version}" "Chart.js" || return 1
    update_readme_version "Chart.js" "${version}"
}

# ----------------------------------------------------------------------------
# Argument parsing
# ----------------------------------------------------------------------------

LIB_TARGET="all"
DO_BACKUP=1
MODE="update"
RESTORE_ID=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --lib)
            if [[ -z "${2:-}" ]]; then
                error "--lib requires a value (alpine|chart)"
                exit 1
            fi
            LIB_TARGET="$2"
            shift 2
            ;;
        --no-backup)
            DO_BACKUP=0
            shift
            ;;
        --list-backups)
            MODE="list"
            shift
            ;;
        --restore)
            if [[ -z "${2:-}" ]]; then
                error "--restore requires a backup ID (or 'latest')"
                exit 1
            fi
            MODE="restore"
            RESTORE_ID="$2"
            shift 2
            ;;
        --prune)
            MODE="prune"
            shift
            ;;
        -h|--help)
            show_help
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            echo ""
            show_help
            exit 1
            ;;
    esac
done

# ----------------------------------------------------------------------------
# Dispatch
# ----------------------------------------------------------------------------

case "${MODE}" in
    list)
        list_backups
        exit 0
        ;;
    restore)
        check_assets_dir
        restore_backup "${RESTORE_ID}"
        exit 0
        ;;
    prune)
        prune_backups
        exit 0
        ;;
    update)
        check_dependencies
        check_assets_dir

        if [[ "${DO_BACKUP}" -eq 1 ]]; then
            step "Creating backup before update..."
            create_backup
            echo ""
        else
            warn "Skipping backup (--no-backup)"
            echo ""
        fi

        case "${LIB_TARGET}" in
            alpine)
                update_alpine
                ;;
            chart)
                update_chart
                ;;
            all)
                update_alpine
                echo ""
                update_chart
                ;;
            *)
                error "Unknown library: ${LIB_TARGET}"
                hint "Valid options: alpine, chart, all"
                exit 1
                ;;
        esac

        echo ""
        info "Done!"
        echo ""
        warn "Next steps:"
        echo "    1. Test admin UI on a clean WordPress install with WP_DEBUG=true"
        echo "    2. Check browser console for errors"
        echo "    3. Verify Dashboard charts render, Settings tabs work"
        echo "    4. If something broke: ./update-assets.sh --restore latest"
        echo "    5. Commit changes: git add ${ASSETS_DIR} ${README_FILE}"
        echo ""
        ;;
esac
