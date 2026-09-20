#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

log() {
    printf '[deploy] %s\n' "$*"
}

fail() {
    printf '[deploy:error] %s\n' "$*" >&2
    exit 1
}

: "${DEPLOY_BASE_PATH:?DEPLOY_BASE_PATH is required}"
: "${DEPLOY_RELEASE_ID:?DEPLOY_RELEASE_ID is required}"
: "${DEPLOY_RELEASE_SHA:?DEPLOY_RELEASE_SHA is required}"
: "${DEPLOY_ARCHIVE:?DEPLOY_ARCHIVE is required}"
: "${DEPLOY_ARCHIVE_SHA256:?DEPLOY_ARCHIVE_SHA256 is required}"
: "${DEPLOY_HEALTHCHECK_URL:?DEPLOY_HEALTHCHECK_URL is required}"

DEPLOY_DRY_RUN="${DEPLOY_DRY_RUN:-0}"
DEPLOY_SSR_BUNDLE_PATH="${DEPLOY_SSR_BUNDLE_PATH:-}"
DEPLOY_SSR_RESTART_FILE="${DEPLOY_SSR_RESTART_FILE:-}"
DEPLOY_KEEP_RELEASES="${DEPLOY_KEEP_RELEASES:-5}"

BASE="${DEPLOY_BASE_PATH%/}"
RELEASES="$BASE/releases"
SHARED="$BASE/shared"
CURRENT="$BASE/current"
INCOMING="$BASE/.incoming"
RELEASE="$RELEASES/$DEPLOY_RELEASE_ID"
CURRENT_NEXT="$BASE/.current-next"
DEPLOY_META="$BASE/.deploy"
PREVIOUS=""
SWITCHED=0
MAINTENANCE=0
LOCK_DIR=""

case "$BASE" in
    ""|"/"|"/home"|"/home/"|"/home3"|"/home3/")
        fail "Refusing unsafe DEPLOY_BASE_PATH: $BASE"
        ;;
esac

if [[ ! "$DEPLOY_RELEASE_ID" =~ ^[A-Za-z0-9._-]+$ ]]; then
    fail "Unsafe release id."
fi

if [[ ! "$DEPLOY_RELEASE_SHA" =~ ^[0-9a-f]{40}$ ]]; then
    fail "DEPLOY_RELEASE_SHA must be a full Git commit SHA."
fi

if [[ "$DEPLOY_DRY_RUN" != "0" && "$DEPLOY_DRY_RUN" != "1" ]]; then
    fail "DEPLOY_DRY_RUN must be 0 or 1."
fi

command -v php >/dev/null 2>&1 || fail "php is not available on the server."
command -v tar >/dev/null 2>&1 || fail "tar is not available on the server."
command -v curl >/dev/null 2>&1 || fail "curl is not available on the server."
command -v sha256sum >/dev/null 2>&1 || fail "sha256sum is not available on the server."

mkdir -p "$RELEASES" "$SHARED" "$INCOMING" "$DEPLOY_META"

release_lock() {
    if [[ -n "$LOCK_DIR" && -d "$LOCK_DIR" ]]; then
        rmdir "$LOCK_DIR" 2>/dev/null || true
    fi
}

acquire_lock() {
    if command -v flock >/dev/null 2>&1; then
        exec 9>"$BASE/.deploy.lock"
        flock -n 9 || fail "Another production deployment is already running."
        return
    fi

    LOCK_DIR="$BASE/.deploy.lockdir"
    mkdir "$LOCK_DIR" 2>/dev/null || fail "Another production deployment may already be running."
}

current_target() {
    if [[ -L "$CURRENT" ]]; then
        readlink -f "$CURRENT"
        return
    fi

    if [[ -e "$CURRENT" ]]; then
        fail "$CURRENT exists but is not a symlink. Atomic deployment requires current -> releases/<id>."
    fi
}

restart_ssr_from() {
    local root="$1"

    if [[ -z "$DEPLOY_SSR_BUNDLE_PATH" ]]; then
        return 0
    fi

    [[ -s "$root/bootstrap/ssr/ssr.js" ]] || fail "SSR bundle is missing in $root."

    mkdir -p "$(dirname "$DEPLOY_SSR_BUNDLE_PATH")"
    local tmp="${DEPLOY_SSR_BUNDLE_PATH}.new"
    install -m 0644 "$root/bootstrap/ssr/ssr.js" "$tmp"
    mv -f "$tmp" "$DEPLOY_SSR_BUNDLE_PATH"

    if [[ -n "$DEPLOY_SSR_RESTART_FILE" ]]; then
        mkdir -p "$(dirname "$DEPLOY_SSR_RESTART_FILE")"
        touch "$DEPLOY_SSR_RESTART_FILE"
    fi
}

bring_app_up() {
    local root="$1"

    if [[ -f "$root/artisan" ]]; then
        php "$root/artisan" up >/dev/null 2>&1 || true
    fi

    MAINTENANCE=0
}

rollback_after_error() {
    local status="$?"
    trap - ERR

    printf '[deploy:error] Deployment failed with exit code %s.\n' "$status" >&2

    if [[ "$SWITCHED" == "1" && -n "$PREVIOUS" && -d "$PREVIOUS" ]]; then
        printf '[deploy] Rolling application symlink back to %s\n' "$PREVIOUS" >&2
        rm -f "$CURRENT_NEXT"
        ln -s "$PREVIOUS" "$CURRENT_NEXT"
        mv -Tf "$CURRENT_NEXT" "$CURRENT"

        restart_ssr_from "$PREVIOUS" || true
        php "$CURRENT/artisan" queue:restart >/dev/null 2>&1 || true
    fi

    if [[ "$MAINTENANCE" == "1" ]]; then
        if [[ -L "$CURRENT" ]]; then
            bring_app_up "$CURRENT"
        elif [[ -d "$RELEASE" ]]; then
            bring_app_up "$RELEASE"
        fi
    fi

    printf '[deploy:error] Database migrations are intentionally not rolled back automatically.\n' >&2
    exit "$status"
}

cleanup() {
    rm -f "$CURRENT_NEXT" 2>/dev/null || true
    rm -f "$DEPLOY_ARCHIVE" 2>/dev/null || true
    release_lock
}

trap rollback_after_error ERR
trap cleanup EXIT
acquire_lock

[[ -f "$SHARED/.env" ]] || fail "Missing $SHARED/.env. Complete the one-time host bootstrap first."

mkdir -p \
    "$SHARED/storage/app/public" \
    "$SHARED/storage/framework/cache/data" \
    "$SHARED/storage/framework/sessions" \
    "$SHARED/storage/framework/views" \
    "$SHARED/storage/logs" \
    "$SHARED/public-apk" \
    "$SHARED/deployment-exports"

chmod -R u+rwX "$SHARED/storage"

[[ -s "$DEPLOY_ARCHIVE" ]] || fail "Uploaded release archive is missing or empty."

ACTUAL_SHA256="$(sha256sum "$DEPLOY_ARCHIVE" | awk '{print $1}')"
[[ "$ACTUAL_SHA256" == "$DEPLOY_ARCHIVE_SHA256" ]] || fail "Release archive checksum mismatch."

PREVIOUS="$(current_target || true)"

if [[ -e "$RELEASE" ]]; then
    if [[ -n "$PREVIOUS" && "$PREVIOUS" == "$RELEASE" ]]; then
        fail "Refusing to overwrite the active release."
    fi
    rm -rf "$RELEASE"
fi

mkdir -p "$RELEASE"
tar -xzf "$DEPLOY_ARCHIVE" -C "$RELEASE"

rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"

rm -f "$RELEASE/.env"
ln -s "$SHARED/.env" "$RELEASE/.env"

mkdir -p "$RELEASE/public"
rm -rf "$RELEASE/public/storage" "$RELEASE/public/apk"
ln -s "$SHARED/storage/app/public" "$RELEASE/public/storage"
ln -s "$SHARED/public-apk" "$RELEASE/public/apk"

rm -rf "$RELEASE/deployment-exports"
ln -s "$SHARED/deployment-exports" "$RELEASE/deployment-exports"

mkdir -p "$RELEASE/bootstrap/cache"
chmod -R u+rwX "$RELEASE/bootstrap/cache"

[[ -f "$RELEASE/artisan" ]] || fail "artisan is missing from release."
[[ -f "$RELEASE/vendor/autoload.php" ]] || fail "vendor/autoload.php is missing from release."
[[ -f "$RELEASE/public/build/manifest.json" ]] || fail "Vite client manifest is missing from release."
[[ -s "$RELEASE/bootstrap/ssr/ssr.js" ]] || fail "Inertia SSR bundle is missing from release."

log "Validating PHP platform and Laravel bootstrap."
(
    cd "$RELEASE"
    php -r 'require "vendor/autoload.php";'
    php artisan package:discover --ansi
    php artisan config:clear
    php artisan about --only=environment
)

if [[ "$DEPLOY_DRY_RUN" == "1" ]]; then
    log "Dry run passed. Production symlink and database were not changed."
    rm -rf "$RELEASE"
    exit 0
fi

[[ -n "$PREVIOUS" ]] || fail "No active current symlink exists. Bootstrap the host before the first live deployment."

log "Entering Laravel maintenance mode for the short activation window."
php "$PREVIOUS/artisan" down --retry=10 --refresh=15
MAINTENANCE=1

log "Running production migrations."
(
    cd "$RELEASE"
    php artisan migrate --force
    php artisan config:cache
    php artisan view:cache
)

log "Switching current symlink atomically."
rm -f "$CURRENT_NEXT"
ln -s "$RELEASE" "$CURRENT_NEXT"
mv -Tf "$CURRENT_NEXT" "$CURRENT"
SWITCHED=1

log "Restarting workers and SSR."
php "$CURRENT/artisan" queue:restart >/dev/null 2>&1 || true
restart_ssr_from "$CURRENT"

bring_app_up "$CURRENT"

log "Running public health check."
curl \
    --fail \
    --silent \
    --show-error \
    --location \
    --connect-timeout 8 \
    --max-time 25 \
    --retry 6 \
    --retry-delay 5 \
    --retry-all-errors \
    "$DEPLOY_HEALTHCHECK_URL" >/dev/null

printf '%s\n' "$DEPLOY_RELEASE_ID" > "$DEPLOY_META/last-successful-release"
printf '%s\n' "$DEPLOY_RELEASE_SHA" > "$DEPLOY_META/last-successful-sha"
date -u '+%Y-%m-%dT%H:%M:%SZ' > "$DEPLOY_META/last-successful-at"

log "Health check passed. Deployment is live."

mapfile -t ALL_RELEASES < <(ls -1dt "$RELEASES"/* 2>/dev/null || true)
kept=0
for candidate in "${ALL_RELEASES[@]}"; do
    [[ -d "$candidate" ]] || continue

    if [[ "$candidate" == "$(readlink -f "$CURRENT")" ]]; then
        kept=$((kept + 1))
        continue
    fi

    kept=$((kept + 1))
    if (( kept > DEPLOY_KEEP_RELEASES )); then
        rm -rf "$candidate"
    fi
done

log "Production deployment completed successfully."
