#!/usr/bin/env bash
set -Eeuo pipefail

release="${1:?release identifier is required}"
case "$release" in
    (*[!0-9-]*) echo "Invalid release identifier" >&2; exit 2 ;;
esac

app_dir="/var/www/cevre-takip"
archive="/home/ulusdeploy/portal-build-${release}.tar.gz"
stage_dir="/home/ulusdeploy/deploy-${release}"
previous_build="${app_dir}/public/build.previous.${release}"

cd "$app_dir"
test -f "$archive"
test -f public/build/manifest.json
test ! -e "$previous_build"

git fetch origin main
incoming_files="$(git diff --name-only HEAD..origin/main)"
expected_migration='database/migrations/2026_11_27_090000_add_request_reason_to_visit_approval_requests.php'
migration_files="$(grep '^database/migrations/' <<< "$incoming_files" || true)"
if [[ -n "$migration_files" && "$migration_files" != "$expected_migration" ]]; then
    echo "An unreviewed database migration is present. Deployment stopped for manual review:" >&2
    printf '%s\n' "$migration_files" >&2
    exit 3
fi
if grep -Fxq 'config/waste_backup.php' <<< "$incoming_files"; then
    echo "The incoming update touches the server-specific backup configuration. Deployment stopped." >&2
    exit 4
fi

tracked_changes="$(git status --porcelain --untracked-files=no | grep -v 'config/waste_backup.php' || true)"
if [[ -n "$tracked_changes" ]]; then
    echo "Other server-side tracked changes were found. Deployment stopped to preserve them:" >&2
    printf '%s\n' "$tracked_changes" >&2
    exit 5
fi

echo "Creating database backup..."
php artisan backup:run --only-db
bash /usr/local/bin/uluscevre-google-drive-backup

echo "Updating application code..."
git merge --ff-only origin/main

if [[ -n "$migration_files" ]]; then
    echo "Applying the reviewed visit deletion reason migration..."
    php artisan migrate --force --path="$expected_migration"
fi

mkdir -p "$stage_dir"
tar -xzf "$archive" -C "$stage_dir"
test -f "$stage_dir/build/manifest.json"
test -d "$stage_dir/build/assets"

echo "Preserving the current frontend at $previous_build"
cp -a public/build "$previous_build"
cp -a "$stage_dir/build/assets/." public/build/assets/
install -m 644 "$stage_dir/build/manifest.json" public/build/manifest.json.new
mv -f public/build/manifest.json.new public/build/manifest.json

php artisan config:clear
php artisan route:clear
php artisan view:clear

http_code="$(curl -sS -L -o /dev/null -w '%{http_code}' https://portal.uluscevre.com.tr)"
printf 'Portal HTTP %s\n' "$http_code"
case "$http_code" in
    2??) ;;
    *) echo "Portal health check failed. Previous frontend remains at $previous_build" >&2; exit 6 ;;
esac

printf 'Live commit: '
git rev-parse --short HEAD
echo "Database backup copied to Google Drive; prior frontend preserved."
