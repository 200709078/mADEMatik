#!/usr/bin/env bash
# Seçici canlı sync: staging klondan canlı dizine yalnız değişen dosyaları aktarır.
# Kullanım: ./scripts/deploy-sync.sh <kaynak-klon> <canli-dizin> [taban-commit]
# Örnek:   ./scripts/deploy-sync.sh ~/repositories/mADEMatik ~/public_html
# Normalde .cpanel.yml çalıştırır (yerelde `npm run deploy`, bkz. scripts/deploy.sh).
#
# Son yayınlanan commit <canli-dizin>'in üst klasöründe .madematik-deployed-commit dosyasında
# tutulur ve değişiklikler ondan bu yana hesaplanır. Kayıt yoksa taban, çekmeden önceki HEAD'dir;
# elle verilen taban-commit kaydın önüne geçer:
#          ./scripts/deploy-sync.sh ~/repositories/mADEMatik ~/public_html 6296151
# Yayın durursa (migration) kayıt ilerlemez; sonraki çalıştırma aynı değişiklikleri yeniden görür.
#
# Kurallar:
# - Migration (database/migrations) veya composer.lock değişmişse DURUR; bunlar ayrıca onay ister.
# - Korumalı yollar asla kopyalanmaz: .env*, storage/, bootstrap/cache/,
#   public/{drAW,drAW2D,drAW3D,awCELL,awa,storage}.
# - Silinen dosyalar canlıdan silinmez, listelenir.
# - public/build/manifest.json en son kopyalanır.
# - Sonunda hedefte önbellek temizliği çalışır (migrate/seed içermez). Bu hostingde
#   `optimize:clear` takıldığı için her yayında `view:clear`, config/routes/.env.example
#   değişmişse ek olarak `config:clear` ve `route:clear` uygulanır.
set -euo pipefail

SRC="${1:-}"
DST="${2:-}"
BASE_ARG="${3:-}"

fail() { printf '%s\n' "Hata: $*" >&2; exit 1; }

[[ -n "$SRC" && -n "$DST" ]] || fail 'Kullanım: ./scripts/deploy-sync.sh <kaynak-klon> <canli-dizin>'
[[ -d "$SRC/.git" ]] || fail "Kaynakta .git yok: $SRC"
[[ -f "$DST/artisan" ]] || fail "Hedefte artisan yok: $DST"
case "$DST" in
    */public/awa*|*/repositories/*) fail "Hedef yasaklı dizin: $DST" ;;
esac

STATE="${DEPLOY_STATE_FILE:-$(dirname -- "$DST")/.madematik-deployed-commit}"

cd -- "$SRC"
[[ "$(git rev-parse --abbrev-ref HEAD)" == "main" ]] || fail 'Kaynak main dalında değil.'
git fetch origin
PRE_MERGE="$(git rev-parse HEAD)"
AFTER="$(git rev-parse origin/main)"
if [[ -n "$BASE_ARG" ]]; then
    BEFORE="$BASE_ARG"
elif [[ -s "$STATE" ]]; then
    BEFORE="$(tr -d '[:space:]' <"$STATE")"
else
    BEFORE="$PRE_MERGE"
fi
git cat-file -e "$BEFORE^{commit}" 2>/dev/null || fail "Taban commit bulunamadı: $BEFORE"
BEFORE="$(git rev-parse "$BEFORE^{commit}")"
# Kayıt yoksa taban hemen yazılır: yayın durursa bir sonraki çalıştırma aynı aralığı görür.
[[ -s "$STATE" ]] || printf '%s\n' "$BEFORE" >"$STATE"
git merge --ff-only origin/main

if [[ "$BEFORE" == "$AFTER" ]]; then
    printf '%s\n' "Değişiklik yok ($AFTER)."
    exit 0
fi
CHANGED="$(git diff --name-only "$BEFORE" "$AFTER")"

NEEDS_APPROVAL="$(grep -E '^(database/migrations/|composer\.lock)' <<<"$CHANGED" || true)"
if [[ -n "$NEEDS_APPROVAL" ]]; then
    printf '%s\n' 'Migrate/composer gerektiren değişiklik var, duruldu:'
    printf '%s\n' "$NEEDS_APPROVAL"
    exit 1
fi

BLOCKED='^(\.env($|\.)|storage/|bootstrap/cache/|public/(drAW|drAW2D|drAW3D|awCELL|awa|storage)(/|$))'
SAFE_EXAMPLE='^\.env\.example$'
SKIP="$(grep -E "$BLOCKED" <<<"$CHANGED" | grep -vE "$SAFE_EXAMPLE" || true)"
if [[ -n "$SKIP" ]]; then
    printf '%s\n' 'Atlanan korumalı yollar:'
    printf '%s\n' "$SKIP"
fi
FILES="$(grep -vE "$BLOCKED" <<<"$CHANGED" || true)"
if grep -qE "$SAFE_EXAMPLE" <<<"$CHANGED"; then
    FILES="$(printf '%s\n.env.example' "$FILES")"
fi
if [[ -z "${FILES//[[:space:]]/}" ]]; then
    printf '%s\n' "$AFTER" >"$STATE"
    printf '%s\n' 'Kopyalanacak dosya yok.'
    exit 0
fi

REST="$(grep -v 'public/build/manifest.json' <<<"$FILES" || true)"
LAST="$(grep 'public/build/manifest.json' <<<"$FILES" || true)"
{
    [[ -n "$REST" ]] && printf '%s\n' "$REST"
    [[ -n "$LAST" ]] && printf '%s\n' "$LAST"
    true
} | while IFS= read -r f; do
    [[ -n "$f" ]] || continue
    if [[ ! -f "$SRC/$f" ]]; then
        printf '%s\n' "Silinmiş, atlandı (elle inceleyin): $f"
        continue
    fi
    mkdir -p "$DST/$(dirname -- "$f")"
    cp -- "$SRC/$f" "$DST/$f"
    printf '%s\n' "Kopyalandı: $f"
done

ARTISAN_TIMEOUT=""
if command -v timeout >/dev/null 2>&1; then ARTISAN_TIMEOUT="timeout 100"; fi
# shellcheck disable=SC2086
$ARTISAN_TIMEOUT php "$DST/artisan" view:clear
if grep -qE '^(\.env\.example$|config/|routes/)' <<<"$CHANGED"; then
    # shellcheck disable=SC2086
    $ARTISAN_TIMEOUT php "$DST/artisan" config:clear
    # shellcheck disable=SC2086
    $ARTISAN_TIMEOUT php "$DST/artisan" route:clear
fi
printf '%s\n' "$AFTER" >"$STATE"
printf '%s\n' "Tamam: $BEFORE -> $AFTER"
