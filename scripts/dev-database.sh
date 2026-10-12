#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"
runtime_dir="${project_root}/storage/runtime"
data_dir="${runtime_dir}/mariadb"
socket_file="${runtime_dir}/mariadb.sock"
pid_file="${runtime_dir}/mariadb.pid"
log_file="${runtime_dir}/mariadb.log"
lock_file="${runtime_dir}/mariadb-control.lock"
server_bin="/usr/sbin/mariadbd"
admin_bin="/usr/bin/mariadb-admin"
installer_bin="/usr/bin/mariadb-install-db"
action="${1:-status}"

fail() {
    printf '%s\n' "Hata: $*" >&2
    exit 1
}

[[ $# -le 1 ]] || fail 'Kullanım: ./scripts/dev-database.sh start|stop|status'
case "$action" in
    start|stop|status) ;;
    *) fail 'Kullanım: ./scripts/dev-database.sh start|stop|status' ;;
esac

for task_path in "$runtime_dir" "$data_dir" "$socket_file" "$pid_file" "$log_file" "$lock_file"; do
    [[ "$(realpath -m -- "$task_path")" == "$task_path" ]] \
        || fail 'Runtime yollarında sembolik bağlantı var; güvenli kapsam doğrulanamadı.'
done

if [[ ! -d "$runtime_dir" && "$action" != start ]]; then
    printf '%s\n' 'Projeye ait yerel MariaDB çalışmıyor.'
    exit 0
fi

mkdir -p -- "$runtime_dir"
chmod 700 -- "$runtime_dir"
command -v flock >/dev/null || fail 'Çakışma kilidi için flock bulunamadı.'
exec 9>"$lock_file"
flock -n 9 || fail 'Başka bir yerel veritabanı denetim işlemi sürüyor.'

read_pid() {
    local stored_pid
    [[ -f "$pid_file" ]] || return 1
    stored_pid="$(cat -- "$pid_file")"
    [[ "$stored_pid" =~ ^[1-9][0-9]*$ ]] || fail 'Projeye ait PID dosyası geçersiz; işlem yapılmadı.'
    printf '%s' "$stored_pid"
}

process_is_owned() {
    local candidate_pid="$1"
    local actual_executable
    local argument
    local has_data_dir=false
    local has_socket=false
    local -a arguments

    [[ -d "/proc/${candidate_pid}" ]] || return 1
    actual_executable="$(readlink -f -- "/proc/${candidate_pid}/exe" 2>/dev/null)" || return 1
    [[ "$actual_executable" == "$(readlink -f -- "$server_bin")" ]] || return 1
    mapfile -d '' -t arguments < "/proc/${candidate_pid}/cmdline" || return 1
    for argument in "${arguments[@]}"; do
        [[ "$argument" != "--datadir=${data_dir}" ]] || has_data_dir=true
        [[ "$argument" != "--socket=${socket_file}" ]] || has_socket=true
    done
    [[ "$has_data_dir" == true && "$has_socket" == true ]]
}

server_responds() {
    [[ -S "$socket_file" && -x "$admin_bin" ]] || return 1
    "$admin_bin" --no-defaults --socket="$socket_file" --user=root \
        --connect-timeout=2 ping >/dev/null 2>&1
}

[[ ! -e "$pid_file" || -f "$pid_file" ]] \
    || fail 'PID yolunda beklenmeyen bir dosya var; işlem yapılmadı.'
pid=''
if [[ -f "$pid_file" ]]; then
    pid="$(read_pid)"
fi
if [[ -n "$pid" && -d "/proc/${pid}" ]]; then
    process_is_owned "$pid" \
        || fail 'PID farklı bir sürece ait veya kapsamı doğrulanamadı; işlem yapılmadı.'
fi

case "$action" in
    status)
        if [[ -n "$pid" ]] && process_is_owned "$pid"; then
            if server_responds; then
                printf '%s\n' 'Projeye ait yerel MariaDB çalışıyor; özel soket hazır.'
            else
                printf '%s\n' 'Projeye ait MariaDB süreci çalışıyor; özel soket henüz hazır değil.'
                exit 1
            fi
        elif [[ -S "$socket_file" ]] && server_responds; then
            fail 'Özel soket yanıt veriyor ancak projeye ait PID doğrulanamadı.'
        else
            printf '%s\n' 'Projeye ait yerel MariaDB çalışmıyor.'
        fi
        ;;
    stop)
        if [[ -z "$pid" ]] || ! process_is_owned "$pid"; then
            if [[ -S "$socket_file" ]] && server_responds; then
                fail 'Özel soket yanıt veriyor ancak güvenle durdurulacak PID yok.'
            fi
            printf '%s\n' 'Projeye ait yerel MariaDB zaten duruyor.'
            exit 0
        fi

        process_is_owned "$pid" || fail 'Süreç kapsamı değişti; durdurma iptal edildi.'
        kill -TERM "$pid" || fail 'Projeye ait MariaDB süreci durdurulamadı.'
        for ((attempt = 0; attempt < 100; attempt++)); do
            if ! process_is_owned "$pid"; then
                printf '%s\n' 'Projeye ait yerel MariaDB güvenli biçimde durduruldu.'
                exit 0
            fi
            sleep 0.2
        done
        fail 'MariaDB kapanışı sürüyor; zorla sonlandırılmadı. Günlüğü yerel olarak inceleyin.'
        ;;
    start)
        [[ -x "$server_bin" && -x "$admin_bin" ]] \
            || fail 'MariaDB sunucu veya denetim programı bulunamadı.'
        if [[ -n "$pid" ]] && process_is_owned "$pid"; then
            printf '%s\n' 'Projeye ait yerel MariaDB zaten çalışıyor.'
            exit 0
        fi
        if [[ -S "$socket_file" ]]; then
            fail 'Özel soket var ancak sahip PID doğrulanamadı; ikinci sunucu başlatılmadı.'
        fi

        mkdir -p -- "$data_dir"
        initialized_now=false
        if [[ ! -d "${data_dir}/mysql" ]]; then
            [[ -z "$(find "$data_dir" -mindepth 1 -maxdepth 1 -print -quit)" ]] \
                || fail 'Veri dizini boş değil ve kurulum doğrulanamadı; üzerine yazılmadı.'
            [[ -x "$installer_bin" ]] || fail 'MariaDB ilk kurulum programı bulunamadı.'
            "$installer_bin" --no-defaults --datadir="$data_dir" \
                --auth-root-authentication-method=normal --skip-test-db \
                >> "$log_file" 2>&1 \
                || fail 'Özel MariaDB ilk kurulumu başarısız. Veri dizini korunuyor.'
            initialized_now=true
        fi

        [[ ! -e "$socket_file" || -S "$socket_file" ]] \
            || fail 'Soket yolunda beklenmeyen bir dosya var; üzerine yazılmadı.'
        [[ ! -e "$pid_file" || -f "$pid_file" ]] \
            || fail 'PID yolunda beklenmeyen bir dosya var; üzerine yazılmadı.'
        rm -f -- "$socket_file" "$pid_file"
        nohup "$server_bin" --no-defaults --datadir="$data_dir" \
            --socket="$socket_file" --pid-file="$pid_file" --log-error="$log_file" \
            --skip-networking --skip-name-resolve \
            </dev/null >> "$log_file" 2>&1 9>&- &

        ready=false
        for ((attempt = 0; attempt < 100; attempt++)); do
            pid=''
            if [[ -f "$pid_file" ]]; then
                pid="$(read_pid)"
            fi
            if [[ -n "$pid" ]] && process_is_owned "$pid" && server_responds; then
                ready=true
                break
            fi
            sleep 0.2
        done
        [[ "$ready" == true ]] \
            || fail 'MariaDB özel soketi hazır olmadı; veri dizini korunuyor. Yerel günlüğü inceleyin.'
        if [[ "$initialized_now" == true ]]; then
            (cd -- "$project_root" && php artisan madematik:initialize-local-db) \
                || fail 'Yeni uygulama DB kurulumu tamamlanamadı; MariaDB verisi korunuyor.'
        fi
        printf '%s\n' 'Projeye ait yerel MariaDB başlatıldı; TCP ağı kapalı.'
        ;;
esac
