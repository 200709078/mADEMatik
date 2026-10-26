#!/usr/bin/env bash
# madematik.com'u yerelden yayınlar: main'i GitHub'a gönderir, cPanel'e depoyu çektirir ve
# .cpanel.yml ile scripts/deploy-sync.sh'yi çalıştırtır, yayın kaydını yazar.
# Kullanım: npm run deploy               → yayınla
#           npm run deploy -- --dry-run  → canlıya göre neyin kopyalanacağını listeler (gönderme/yayın yok)
# Bağlantı: depoya girmeyen .env.deploy dosyası:
#   CPANEL_HOST=mt-aqua.guzelhosting.com  CPANEL_USER=mademati  CPANEL_TOKEN=…
#   CPANEL_REPO=/home/mademati/repositories/mADEMatik
set -euo pipefail
cd -- "$(dirname -- "$0")/.."

fail() { printf '✗ %s\n' "$*" >&2; exit 1; }

DRY_RUN=false
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=true

[[ -f .env.deploy ]] || fail '.env.deploy yok (CPANEL_HOST, CPANEL_USER, CPANEL_TOKEN, CPANEL_REPO).'
set -a; . ./.env.deploy; set +a
for name in CPANEL_HOST CPANEL_USER CPANEL_TOKEN CPANEL_REPO; do
    [[ -n "${!name:-}" ]] || fail ".env.deploy içinde $name yok."
done
STATE_FILE="${CPANEL_STATE_FILE:-/home/$CPANEL_USER/.madematik-deployed-commit}"

# cPanel UAPI çağrısı. Token komut satırına yazılmaz (curl ayarı stdin'den okunur).
api() {
    local fn=$1; shift
    local args=() pair
    for pair in "$@"; do args+=(--data-urlencode "$pair"); done
    printf 'header = "Authorization: cpanel %s:%s"\n' "$CPANEL_USER" "$CPANEL_TOKEN" |
        curl -fsS --max-time 120 --retry 3 --retry-connrefused --retry-delay 3 -K - -G "${args[@]}" "https://$CPANEL_HOST:2083/execute/$fn" ||
        fail "cPanel'e ulaşılamadı ($fn)."
}
ok() { jq -e '.status == 1' >/dev/null <<<"$1" || fail "$2: $(jq -c '.errors // .' <<<"$1")"; }
read_remote() {
    local res
    res="$(api Fileman/get_file_content "dir=$(dirname -- "$1")" "file=$(basename -- "$1")")"
    ok "$res" "$1 okunamadı"
    jq -r '.data.content' <<<"$res"
}

[[ "$(git branch --show-current)" == "main" ]] || fail 'main dalında değilsiniz.'
[[ -z "$(git status --porcelain)" ]] || fail "Commit'lenmemiş değişiklik var; önce commit'leyin."
HEAD_SHA="$(git rev-parse HEAD)"

# Canlıdaki son yayın ve yayınlanacak değişiklikler (deploy-sync.sh'nin kurallarıyla).
DEPLOYED="$(read_remote "$STATE_FILE" | tr -d '[:space:]')"
git cat-file -e "$DEPLOYED^{commit}" 2>/dev/null || git fetch -q origin
git cat-file -e "$DEPLOYED^{commit}" 2>/dev/null || fail "Canlıdaki commit ($DEPLOYED) yerelde bulunamadı."
printf 'Canlıda: %s\n' "$(git log -1 --format='%h %ad %s' --date=short "$DEPLOYED")"
if [[ "$(git rev-parse "$DEPLOYED^{commit}")" == "$HEAD_SHA" ]]; then
    printf '%s\n' '✓ Canlı güncel; yayınlanacak bir şey yok.'
    exit 0
fi
printf '%s\n' 'Yayınlanacak commit'"'"'ler:'
git --no-pager log --format='  %h %s' "$DEPLOYED..HEAD"

BLOCKED="$(grep -oP "^BLOCKED='\K[^']+" scripts/deploy-sync.sh)"
CHANGED="$(git diff --name-only "$DEPLOYED" HEAD)"
printf '%s\n' 'Dosyalar:'
while IFS= read -r f; do
    if [[ "$f" != .env.example ]] && grep -qE "$BLOCKED" <<<"$f"; then
        printf '  - %s (korunan, kopyalanmaz)\n' "$f"
    elif [[ ! -e "$f" ]]; then
        printf '  ! %s (silinmiş; canlıdan silinmez, elle bakın)\n' "$f"
    else
        printf '  ↑ %s\n' "$f"
    fi
done <<<"$CHANGED"
STOPPERS="$(grep -E '^(database/migrations/|composer\.lock)' <<<"$CHANGED" || true)"
[[ -z "$STOPPERS" ]] || printf '%s\n' '⚠ Migration veya composer.lock değişmiş: yayın sunucuda duracak (ayrı onaylı işlem).'

if $DRY_RUN; then
    printf '%s\n' 'Deneme (--dry-run): hiçbir şey gönderilmedi ya da yayınlanmadı.'
    exit 0
fi
[[ -z "$STOPPERS" ]] || fail 'Yayın yapılmadı.'

git push -q origin main
# cPanel'in güncellemesi her zaman çekmiyor (2026-10-26: "tamam" deyip eski commit'te kaldı); çekmezse
# sorun değil, .cpanel.yml'deki deploy-sync.sh GitHub'dan kendisi çeker. Sonuç sondaki kayıtla doğrulanır.
res="$(api VersionControl/update "repository_root=$CPANEL_REPO" "branch=main")"
ok "$res" "cPanel depoyu güncelleyemedi"
pulled="$(jq -r '.data.last_update.identifier // empty' <<<"$res")"
[[ -z "$pulled" || "$pulled" == "$HEAD_SHA" ]] ||
    printf '! cPanel deposu %s’da kaldı; deploy-sync.sh kendisi çekecek.\n' "${pulled:0:7}"
[[ "$(jq -r '.data.deployable // 1' <<<"$res")" != 0 ]] ||
    fail "cPanel bu depodan yayın yapamıyor (deployable 0): sunucudaki kopyada .cpanel.yml yok ya da kopya temiz değil."

res="$(api VersionControlDeployment/create "repository_root=$CPANEL_REPO")"
ok "$res" 'cPanel yayını başlatamadı'
deploy_id="$(jq -r '.data.deploy_id' <<<"$res")"
log_path="$(jq -r '.data.log_path' <<<"$res")"
printf 'Yayın başladı (%s)…\n' "$deploy_id"

result=''
for _ in $(seq 1 90); do
    sleep 2
    res="$(api VersionControlDeployment/retrieve)"
    result="$(jq -r --arg id "$deploy_id" '.data[]? | select((.deploy_id | tostring) == $id) | .timestamps
        | if .succeeded then "ok" elif (.failed or .canceled) then "fail" else empty end' <<<"$res")"
    [[ -n "$result" ]] && break
done
printf '%s\n' '--- yayın kaydı ---'
read_remote "$log_path" || true
printf '%s\n' '-------------------'
case "$result" in
    ok) ;;
    fail) fail 'Yayın başarısız (kayıt yukarıda).' ;;
    *) fail 'Yayın 3 dakikada bitmedi; cPanel → Git Version Control → mADEMatik → Deploy sekmesine bakın.' ;;
esac
live="$(read_remote "$STATE_FILE" | tr -d '[:space:]')"
[[ "$live" == "$HEAD_SHA" ]] || fail "Yayın bitti ama canlı kaydı ${live:0:7} (beklenen ${HEAD_SHA:0:7}); kayda bakın."
printf '✓ madematik.com yayınlandı: %s\n' "$(git log -1 --format='%h %s')"
