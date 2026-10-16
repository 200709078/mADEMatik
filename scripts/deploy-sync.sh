#!/usr/bin/env bash
# Seçici canlı sync: staging klondan canlı dizine yalnız değişen dosyaları aktarır.
# Kullanım: ./scripts/deploy-sync.sh <kaynak-klon> <canli-dizin> [taban-commit]
# Örnek:   ./scripts/deploy-sync.sh ~/repositories/mADEMatik ~/public_html
# İlk çalıştırmada canlı gerideyse taban verilir:
#          ./scripts/deploy-sync.sh ~/repositories/mADEMatik ~/public_html 6296151
#
# Kurallar:
# - Migration (database/migrations) veya composer.lock değişmişse DURUR; bunlar ayrıca onay ister.
# - Korumalı yollar asla kopyalanmaz: .env*, storage/, bootstrap/cache/,
#   public/{drAW,GeoMatik2D,GeoMatik3D,avCELL,awa,storage}.
# - Silinen dosyalar canlıdan silinmez, listelenir.
# - public/build/manifest.json en son kopyalanır.
# - Sonunda hedefte `optimize:clear` çalışır (migrate/seed içermez).
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

cd -- "$SRC"
[[ "$(git branch --show-current)" == "main" ]] || fail 'Kaynak main dalında değil.'
git fetch origin
PRE_MERGE="$(git rev-parse HEAD)"
AFTER="$(git rev-parse origin/main)"
if [[ "$PRE_MERGE" == "$AFTER" && -z "$BASE_ARG" ]]; then
    printf '%s\n' "Değişiklik yok ($AFTER)."
    exit 0
fi

CHANGED="$(git diff --name-only "$PRE_MERGE" "$AFTER")"
git merge --ff-only origin/main
BEFORE="$PRE_MERGE"
if [[ -n "$BASE_ARG" ]]; then
    git cat-file -e "$BASE_ARG^{commit}" 2>/dev/null || fail "Taban commit bulunamadı: $BASE_ARG"
    BEFORE="$BASE_ARG"
    CHANGED="$(git diff --name-only "$BEFORE" "$AFTER")"
fi

NEEDS_APPROVAL="$(grep -E '^(database/migrations/|composer\.lock)' <<<"$CHANGED" || true)"
if [[ -n "$NEEDS_APPROVAL" ]]; then
    printf '%s\n' 'Migrate/composer gerektiren değişiklik var, duruldu:'
    printf '%s\n' "$NEEDS_APPROVAL"
    exit 1
fi

BLOCKED='^(\.env($|\.)|storage/|bootstrap/cache/|public/(drAW|GeoMatik2D|GeoMatik3D|avCELL|awa|storage)(/|$))'
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
    printf '%s\n' 'Kopyalanacak dosya yok.'
    exit 0
fi

REST="$(grep -v 'public/build/manifest.json' <<<"$FILES" || true)"
LAST="$(grep 'public/build/manifest.json' <<<"$FILES" || true)"
{
    [[ -n "$REST" ]] && printf '%s\n' "$REST"
    [[ -n "$LAST" ]] && printf '%s\n' "$LAST"
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

php -- "$DST/artisan" optimize:clear
printf '%s\n' "Tamam: $BEFORE -> $AFTER"
