#!/usr/bin/env bash
#
# Manage bundled third-party JS (Alpine.js, Chart.js): check for updates, update with backup, roll back.
# Works in Git Bash on Windows, macOS and Linux. Needs: bash, curl, sha256sum or shasum.
#
# Usage: ./assets.sh <command> [args]   (./assets.sh help)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
JS_DIR="$ROOT/assets/admin/js"
BACKUP_DIR="$ROOT/.asset-backups"
ASSETS_PHP="$ROOT/src/Support/Assets.php"
README_ASSETS="$ROOT/assets/README.md"
KEEP_BACKUPS="${KEEP_BACKUPS:-3}"
CDN="${ASSET_CDN:-https://cdn.jsdelivr.net/npm}"
REGISTRY="${ASSET_REGISTRY:-https://registry.npmjs.org}"
MIN_BYTES=10000

LIBS=(alpine chart)

# Per-library config. Accessed through lib_get.
lib_get() { # <lib> <field>
  case "$1:$2" in
    alpine:npm)  echo "alpinejs" ;;
    alpine:path) echo "dist/cdn.min.js" ;;
    alpine:file) echo "alpine.min.js" ;;
    alpine:name) echo "Alpine.js" ;;
    alpine:src)  echo "https://github.com/alpinejs/alpine" ;;
    alpine:const) echo "ALPINE_VER" ;;
    chart:npm)   echo "chart.js" ;;
    chart:path)  echo "dist/chart.umd.min.js" ;;
    chart:file)  echo "chart.min.js" ;;
    chart:name)  echo "Chart.js" ;;
    chart:src)   echo "https://github.com/chartjs/Chart.js" ;;
    chart:const) echo "CHART_VER" ;;
    *) die "unknown library/field: $1:$2" ;;
  esac
}

die()  { echo "error: $*" >&2; exit 1; }
info() { echo "==> $*"; }
warn() { echo "warning: $*" >&2; }

DRY_RUN=0
ALLOW_MAJOR=0
ASSUME_YES=0

sha256() {
  if command -v sha256sum >/dev/null 2>&1; then sha256sum "$1" | cut -d' ' -f1
  else shasum -a 256 "$1" | cut -d' ' -f1; fi
}

valid_lib() {
  local l
  for l in "${LIBS[@]}"; do [ "$l" = "$1" ] && return 0; done
  return 1
}

valid_version() { [[ "$1" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; }

lib_path() { echo "$JS_DIR/$(lib_get "$1" file)"; }

# Version string baked into a built file. Alpine: version:"x.y.z". Chart.js: "Chart.js vx.y.z" in the header.
file_version() { # <lib> <file>
  [ -f "$2" ] || return 0
  case "$1" in
    alpine) grep -o 'version:"[0-9]*\.[0-9]*\.[0-9]*"' "$2" | head -n1 | sed 's/[^0-9.]//g' ;;
    chart)  head -c 400 "$2" | grep -o 'Chart\.js v[0-9]*\.[0-9]*\.[0-9]*' | head -n1 | sed 's/^Chart\.js v//' ;;
  esac
}

installed_version() { file_version "$1" "$(lib_path "$1")"; }

# Version used for ?ver= cache busting in Assets.php.
const_version() { # <lib>
  sed -n "s/.*$(lib_get "$1" const) *= *'\([^']*\)'.*/\1/p" "$ASSETS_PHP" | head -n1
}

latest_version() { # <lib>
  curl -fsSL --max-time 20 "$REGISTRY/$(lib_get "$1" npm)/latest" \
    | grep -o '"version":"[0-9]*\.[0-9]*\.[0-9]*"' | head -n1 | sed 's/[^0-9.]//g'
}

major() { echo "${1%%.*}"; }

# Writes the installed versions into Assets.php (?ver=) and assets/README.md.
sync_meta() {
  local lib ver tmp
  for lib in "${LIBS[@]}"; do
    ver="$(installed_version "$lib")"
    [ -n "$ver" ] || { warn "cannot read installed version of $lib, skipping sync for it"; continue; }
    tmp="$(mktemp)"
    sed "s/\($(lib_get "$lib" const) *= *'\)[^']*'/\1$ver'/" "$ASSETS_PHP" > "$tmp"
    cat "$tmp" > "$ASSETS_PHP"
    rm -f "$tmp"
  done

  {
    echo "# Bundled JavaScript Libraries"
    for lib in "${LIBS[@]}"; do
      echo
      echo "## $(lib_get "$lib" name)"
      echo "- Version: $(installed_version "$lib")"
      echo "- License: MIT"
      echo "- Source: $(lib_get "$lib" src)"
      echo "- Distribution: Official CDN minified build (jsDelivr, $(lib_get "$lib" npm)/$(lib_get "$lib" path))"
    done
  } > "$README_ASSETS"
}

# ---------------------------------------------------------------- backups

# Copies both libraries plus Assets.php and README into .asset-backups/<stamp>_<label>/.
backup() { # <label>
  local label="${1:-manual}" id dir lib
  local stamp n=1
  stamp="$(date +%Y%m%d-%H%M%S)"
  id="${stamp}_$label"
  while [ -e "$BACKUP_DIR/$id" ]; do n=$((n + 1)); id="${stamp}-${n}_$label"; done
  dir="$BACKUP_DIR/$id"
  mkdir -p "$dir"
  for lib in "${LIBS[@]}"; do
    [ -f "$(lib_path "$lib")" ] && cp "$(lib_path "$lib")" "$dir/"
  done
  [ -f "$ASSETS_PHP" ] && cp "$ASSETS_PHP" "$dir/Assets.php"
  [ -f "$README_ASSETS" ] && cp "$README_ASSETS" "$dir/README.md"
  {
    for lib in "${LIBS[@]}"; do
      echo "$lib $(installed_version "$lib") $( [ -f "$(lib_path "$lib")" ] && sha256 "$(lib_path "$lib")" )"
    done
  } > "$dir/manifest.txt"
  echo "$id"
  prune_backups
}

list_backup_ids() { [ -d "$BACKUP_DIR" ] && ls -1 "$BACKUP_DIR" | sort || true; }

prune_backups() {
  local ids count excess
  ids="$(list_backup_ids)"
  [ -n "$ids" ] || return 0
  count="$(echo "$ids" | wc -l | tr -d ' ')"
  excess=$((count - KEEP_BACKUPS))
  [ "$excess" -gt 0 ] || return 0
  echo "$ids" | head -n "$excess" | while read -r old; do rm -rf "${BACKUP_DIR:?}/$old"; done
}

restore_backup() { # <id>
  local dir="$BACKUP_DIR/$1" lib
  [ -d "$dir" ] || die "backup not found: $1 (see: ./assets.sh list)"
  for lib in "${LIBS[@]}"; do
    [ -f "$dir/$(lib_get "$lib" file)" ] && cp "$dir/$(lib_get "$lib" file)" "$(lib_path "$lib")"
  done
  [ -f "$dir/Assets.php" ] && cp "$dir/Assets.php" "$ASSETS_PHP"
  [ -f "$dir/README.md" ] && cp "$dir/README.md" "$README_ASSETS"
  return 0
}

# ---------------------------------------------------------------- commands

cmd_status() {
  local lib inst cons
  printf '%-10s %-12s %-14s\n' "library" "installed" "Assets.php ver"
  for lib in "${LIBS[@]}"; do
    inst="$(installed_version "$lib")"; cons="$(const_version "$lib")"
    printf '%-10s %-12s %-14s' "$lib" "${inst:-missing}" "${cons:-?}"
    [ -n "$inst" ] && [ "$inst" != "$cons" ] && printf '  <- out of sync, run: ./assets.sh sync'
    echo
  done
}

cmd_check() { # fetch latest versions, exit 10 if updates exist
  local lib inst latest updates=0 note
  printf '%-10s %-12s %-12s\n' "library" "installed" "latest"
  for lib in "${LIBS[@]}"; do
    inst="$(installed_version "$lib")"
    latest="$(latest_version "$lib")" || latest=""
    [ -n "$latest" ] || { printf '%-10s %-12s %-12s\n' "$lib" "${inst:-missing}" "fetch failed"; continue; }
    note=""
    if [ "$inst" != "$latest" ]; then
      updates=1; note="update available"
      [ -n "$inst" ] && [ "$(major "$inst")" != "$(major "$latest")" ] && note="MAJOR update (needs --major, test the admin UI)"
    fi
    printf '%-10s %-12s %-12s %s\n' "$lib" "${inst:-missing}" "$latest" "$note"
  done
  [ "$updates" -eq 0 ] || exit 10
}

download() { # <lib> <version> <dest>
  local url="$CDN/$(lib_get "$1" npm)@$2/$(lib_get "$1" path)"
  curl -fsSL --max-time 60 "$url" -o "$3" || die "download failed: $url"
  [ "$(wc -c < "$3")" -ge "$MIN_BYTES" ] || die "$url returned a suspiciously small file"
  [ "$(file_version "$1" "$3")" = "$2" ] || die "$url does not contain version $2 (got '$(file_version "$1" "$3")')"
}

cmd_update() { # [lib|all] [version]
  local target="${1:-all}" version="${2:-}" libs=() lib inst new backup_id
  if [ "$target" = "all" ]; then
    [ -z "$version" ] || die "a version can only be given for a single library"
    libs=("${LIBS[@]}")
  else
    valid_lib "$target" || die "unknown library '$target' (alpine|chart|all)"
    libs=("$target")
  fi
  [ -z "$version" ] || valid_version "$version" || die "bad version '$version' (expected x.y.z)"

  TMPDIR_UPDATE="$(mktemp -d)"
  trap 'rm -rf "$TMPDIR_UPDATE"' EXIT

  # Resolve and download everything first, so nothing changes if one download fails.
  local plan=()
  for lib in "${libs[@]}"; do
    inst="$(installed_version "$lib")"
    new="${version:-$(latest_version "$lib")}"
    [ -n "$new" ] || die "cannot resolve latest version of $lib"
    if [ "$inst" = "$new" ]; then info "$lib already at $new"; continue; fi
    if [ -n "$inst" ] && [ "$(major "$inst")" != "$(major "$new")" ] && [ "$ALLOW_MAJOR" -ne 1 ]; then
      warn "$lib $inst -> $new is a major update, skipped (use --major)"; continue
    fi
    info "$lib: ${inst:-none} -> $new"
    download "$lib" "$new" "$TMPDIR_UPDATE/$lib.js"
    plan+=("$lib")
  done
  [ "${#plan[@]}" -gt 0 ] || { info "nothing to update"; return 0; }

  if [ "$DRY_RUN" -eq 1 ]; then info "dry run: no files changed"; return 0; fi

  if [ "$ASSUME_YES" -ne 1 ] && [ -t 0 ]; then
    read -r -p "Apply? [y/N] " ans
    [[ "$ans" =~ ^[Yy]$ ]] || { info "cancelled"; return 0; }
  fi

  backup_id="$(backup "pre-update")"
  info "backup: .asset-backups/$backup_id"
  for lib in "${plan[@]}"; do cp "$TMPDIR_UPDATE/$lib.js" "$(lib_path "$lib")"; done
  sync_meta
  info "done. Roll back with: ./assets.sh rollback $backup_id"
  warn "check the admin dashboard (Alpine stores, Chart.js graphs) before committing"
}

cmd_rollback() { # [id|latest]
  local id="${1:-latest}" safety
  if [ "$id" = "latest" ]; then
    id="$(list_backup_ids | tail -n1)"
    [ -n "$id" ] || die "no backups in .asset-backups/"
  fi
  [ -d "$BACKUP_DIR/$id" ] || die "backup not found: $id (see: ./assets.sh list)"
  info "restoring $id"
  cat "$BACKUP_DIR/$id/manifest.txt"
  if [ "$DRY_RUN" -eq 1 ]; then info "dry run: no files changed"; return 0; fi
  if [ "$ASSUME_YES" -ne 1 ] && [ -t 0 ]; then
    read -r -p "Restore? [y/N] " ans
    [[ "$ans" =~ ^[Yy]$ ]] || { info "cancelled"; return 0; }
  fi
  safety="$(backup "pre-rollback")"
  restore_backup "$id"
  info "restored. Current state before rollback saved as .asset-backups/$safety"
}

cmd_list() {
  local ids id
  ids="$(list_backup_ids)"
  [ -n "$ids" ] || { echo "no backups"; return 0; }
  echo "$ids" | while read -r id; do
    echo "$id  [$(awk '{printf "%s=%s ", $1, $2}' "$BACKUP_DIR/$id/manifest.txt")]"
  done
}

cmd_verify() {
  local lib
  for lib in "${LIBS[@]}"; do
    [ -f "$(lib_path "$lib")" ] || { echo "$lib: MISSING"; continue; }
    echo "$lib $(installed_version "$lib") sha256=$(sha256 "$(lib_path "$lib")") bytes=$(wc -c < "$(lib_path "$lib")")"
  done
}

usage() {
  cat <<'EOF'
Usage: ./assets.sh [options] <command> [args]

Commands:
  status                       installed versions vs. Assets.php (?ver=) constants
  check                        fetch latest versions from npm (exit code 10 if updates exist)
  update [alpine|chart|all] [version]
                               backup, download, verify and install (default: all, latest).
                               A version can be pinned for one library: update chart 4.5.1
  backup [label]               manual backup into .asset-backups/
  list                         list backups
  rollback [id|latest]         restore a backup (current state is backed up first)
  sync                         write installed versions into Assets.php and assets/README.md
  verify                       print version, sha256 and size of the bundled files
  help

Options:
  -n, --dry-run                download and validate, change nothing
  -y, --yes                    no confirmation prompts
      --major                  allow major version bumps (Alpine 3 -> 4, Chart.js 4 -> 5)

Env: KEEP_BACKUPS (default 3), ASSET_CDN, ASSET_REGISTRY.
EOF
}

# ---------------------------------------------------------------- main

args=()
for a in "$@"; do
  case "$a" in
    -n|--dry-run) DRY_RUN=1 ;;
    -y|--yes)     ASSUME_YES=1 ;;
    --major)      ALLOW_MAJOR=1 ;;
    *)            args+=("$a") ;;
  esac
done
set -- ${args[@]+"${args[@]}"}

command -v curl >/dev/null 2>&1 || die "curl is required"
cmd="${1:-help}"; shift || true

case "$cmd" in
  status)   cmd_status ;;
  check)    cmd_check ;;
  update)   cmd_update "$@" ;;
  backup)   info "backup: .asset-backups/$(backup "${1:-manual}")" ;;
  list)     cmd_list ;;
  rollback) cmd_rollback "$@" ;;
  sync)     sync_meta; info "synced"; cmd_status ;;
  verify)   cmd_verify ;;
  help|-h|--help) usage ;;
  *) usage; exit 1 ;;
esac
