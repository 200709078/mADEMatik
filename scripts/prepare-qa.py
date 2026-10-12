#!/usr/bin/env python3
"""Prepare only a fresh, project-owned QA MariaDB; preserve every existing DB."""

import argparse
import base64
import contextlib
import fcntl
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import signal
import stat
import subprocess
import sys
import time


ROOT = Path(__file__).resolve().parent.parent
LOCAL = ROOT / ".local"
RUNTIME = ROOT / "storage/runtime"
CONTEXT = LOCAL / "qa-context.json"
USERS = LOCAL / "qa-users.json"
ENV = ROOT / ".env.qa"
SERVER = Path("/usr/sbin/mariadbd")
INSTALLER = Path("/usr/bin/mariadb-install-db")
CLIENT = Path("/usr/bin/mariadb")
ORIGIN = "http://127.0.0.1:8015"


class SafetyError(Exception):
    pass


def safe_path(path):
    if path.resolve() != path.absolute():
        raise SafetyError("QA yollarında sembolik bağlantı var; işlem yapılmadı.")
    if not path.is_relative_to(ROOT):
        raise SafetyError("QA yolu proje dışında; işlem yapılmadı.")


def private_file(path):
    safe_path(path)
    info = path.stat()
    if not stat.S_ISREG(info.st_mode) or info.st_uid != os.getuid() or stat.S_IMODE(info.st_mode) != 0o600:
        raise SafetyError("QA gizli dosyasının sahipliği veya 600 izni doğrulanamadı.")


def write_private(path, text):
    safe_path(path)
    flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW
    descriptor = os.open(path, flags, 0o600)
    with os.fdopen(descriptor, "w", encoding="utf-8") as handle:
        handle.write(text)


def refuse_existing():
    for path in (ENV, CONTEXT, USERS):
        if path.exists() or path.is_symlink():
            raise SafetyError("Mevcut QA dosyaları korunuyor; yeni kurulum reddedildi. --start, --stop veya --status kullanın.")


def prerequisites():
    if os.geteuid() == 0:
        raise SafetyError("QA kurulumu normal proje kullanıcısıyla çalıştırılmalıdır; root desteklenmez.")
    for binary in (SERVER, INSTALLER, CLIENT):
        if not binary.is_file() or not os.access(binary, os.X_OK):
            raise SafetyError("MariaDB sunucusu, istemcisi veya kurulum aracı eksik; sistem değiştirilmedi.")
    if not shutil.which("php") or not (ROOT / "vendor/autoload.php").is_file():
        raise SafetyError("PHP ve bu projenin Composer bağımlılıkları önce hazır olmalıdır.")
    if (ROOT / "bootstrap/cache/config.php").exists():
        raise SafetyError("Laravel config cache var. Projenin yapılandırma önbelleğini temizleyip tekrar deneyin.")
    for path in (LOCAL, RUNTIME):
        safe_path(path)


@contextlib.contextmanager
def control_lock():
    LOCAL.mkdir(mode=0o700, exist_ok=True)
    safe_path(LOCAL)
    lock = LOCAL / "qa-control.lock"
    safe_path(lock)
    descriptor = os.open(lock, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
    try:
        try:
            fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError as error:
            raise SafetyError("Başka bir QA denetim işlemi sürüyor.") from error
        yield
    finally:
        os.close(descriptor)


def read_context():
    private_file(CONTEXT)
    context = json.loads(CONTEXT.read_text(encoding="utf-8"))
    if not isinstance(context, dict):
        raise SafetyError("QA context biçimi geçersiz.")
    runtime = Path(context["runtime"])
    safe_path(runtime)
    if runtime.parent != RUNTIME or not re.fullmatch(r"qa-[a-f0-9]{8}", runtime.name):
        raise SafetyError("QA runtime sahipliği doğrulanamadı.")
    expected = {"datadir": runtime / "mysql", "socket": runtime / "mysql.sock", "pid": runtime / "mysql.pid", "log": runtime / "mysql.log"}
    for key, path in expected.items():
        if context.get(key) != str(path):
            raise SafetyError("QA context yolları aynı özel instance'a ait değil.")
        safe_path(path)
    if context.get("database") != "madematik_v2_qa_" + runtime.name[3:]:
        raise SafetyError("QA veritabanı adı özel runtime ile eşleşmiyor.")
    if not re.fullmatch(r"[a-z0-9_]{1,32}", context.get("username", "")) or context["username"] == "root":
        raise SafetyError("QA bağlantısı ayrı bir uygulama kullanıcısı gerektirir.")
    if any(not isinstance(context.get(key), str) or not context[key] for key in ("password", "root_password", "app_key")):
        raise SafetyError("QA özel bağlantı bilgileri eksik.")
    return context


def private_environment(context):
    # Inherited DB_URL, SMTP or APP_ENV variables must not override .env.qa.
    environment = {key: os.environ[key] for key in ("PATH", "LANG", "LC_ALL", "LC_CTYPE", "TZ") if key in os.environ}
    environment["TMPDIR"] = context["runtime"]
    environment["APP_ENV"] = "qa"
    return environment


def pid_from_file(context):
    path = Path(context["pid"])
    safe_path(path)
    if not path.exists():
        return None
    info = path.stat()
    if not stat.S_ISREG(info.st_mode) or info.st_uid != os.getuid():
        raise SafetyError("QA PID dosyasının sahipliği doğrulanamadı.")
    value = path.read_text(encoding="ascii").strip()
    if not re.fullmatch(r"[1-9][0-9]*", value):
        raise SafetyError("QA PID dosyası geçersiz; işlem yapılmadı.")
    return int(value)


def process_owned(context, pid):
    process = Path(f"/proc/{pid}")
    if not process.exists():
        return False
    try:
        executable = (process / "exe").resolve(strict=True)
        arguments = (process / "cmdline").read_bytes().split(b"\0")
        expected = ["--no-defaults", "--skip-networking", f"--datadir={context['datadir']}", f"--socket={context['socket']}", f"--pid-file={context['pid']}"]
        owned = process.stat().st_uid == os.getuid() and executable == SERVER.resolve() and all(argument.encode() in arguments for argument in expected)
    except (FileNotFoundError, PermissionError):
        return False
    if not owned:
        raise SafetyError("QA PID farklı bir sürece ait; bu sürece işlem yapılmadı.")
    return True


def terminate_owned(context, pid):
    # Recheck immediately before signalling; never use killall or system services.
    if not process_owned(context, pid):
        return False
    os.kill(pid, signal.SIGTERM)
    for _ in range(100):
        if not process_owned(context, pid):
            return True
        time.sleep(0.2)
    raise SafetyError("QA kapanışı sürüyor. Veri korundu; zorla sonlandırılmadı.")


def stop_server(context):
    pid = pid_from_file(context)
    if pid is None or not process_owned(context, pid):
        if Path(context["socket"]).exists():
            raise SafetyError("QA soketi var ancak sahibi doğrulanamadı; kapatma yapılmadı.")
        return False
    return terminate_owned(context, pid)


def sql_config(context, initial=False):
    runtime = Path(context["runtime"])
    config = runtime / ("initial-root.cnf" if initial else "root.cnf")
    socket = context["socket"].replace("\\", "\\\\").replace('"', '\\"')
    text = f'[client]\nuser=root\nsocket="{socket}"\nprotocol=socket\n'
    if not initial:
        # SQL and option-file metacharacters never occur in generated hex passwords.
        if not re.fullmatch(r"[a-f0-9]+", context["root_password"]):
            raise SafetyError("QA root anahtarı biçimi desteklenmiyor.")
        text += f"password={context['root_password']}\n"
    if not config.exists():
        write_private(config, text)
    else:
        private_file(config)
        if config.read_text(encoding="utf-8") != text:
            raise SafetyError("Özel QA istemci ayarı farklı; üzerine yazılmadı.")
    return config


def logged_run(command, context, label, input_text=None):
    log_path = Path(context["runtime"]) / "setup.log"
    safe_path(log_path)
    descriptor = os.open(log_path, os.O_WRONLY | os.O_CREAT | os.O_APPEND | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, "ab") as log:
        log.write(("\n" + label + "\n").encode())
        try:
            result = subprocess.run(command, cwd=ROOT, env=private_environment(context), input=input_text.encode() if input_text is not None else None, stdout=log, stderr=log, timeout=90)
        except subprocess.TimeoutExpired as error:
            raise SafetyError(f"{label} zaman aşımı. Veriler korundu; özel setup.log dosyasını inceleyin.") from error
        if result.returncode != 0:
            raise SafetyError(f"{label} başarısız. Veriler korundu; özel setup.log dosyasını inceleyin.")


def start_server(context, initial=False):
    pid = pid_from_file(context)
    if pid is not None and process_owned(context, pid):
        return False
    if Path(context["socket"]).exists():
        raise SafetyError("QA soketi zaten var ancak sahibi doğrulanamadı; yeni süreç başlatılmadı.")
    datadir = Path(context["datadir"])
    if not (datadir / "mysql").is_dir():
        raise SafetyError("QA MariaDB veri dizini kurulmamış; mevcut dizin değiştirilmedi.")
    config = sql_config(context, initial)
    log_path = Path(context["runtime"]) / "server-output.log"
    safe_path(log_path)
    descriptor = os.open(log_path, os.O_WRONLY | os.O_CREAT | os.O_APPEND | os.O_NOFOLLOW, 0o600)
    with os.fdopen(descriptor, "ab") as output:
        process = subprocess.Popen([str(SERVER), "--no-defaults", f"--datadir={context['datadir']}", f"--socket={context['socket']}", f"--pid-file={context['pid']}", f"--log-error={context['log']}", "--skip-networking", "--skip-name-resolve", "--innodb-buffer-pool-size=64M"], stdin=subprocess.DEVNULL, stdout=output, stderr=output, cwd=ROOT, env=private_environment(context), start_new_session=True)
    try:
        for _ in range(100):
            if process.poll() is not None:
                raise SafetyError("QA MariaDB başlatılamadı; özel günlükler ve veri dizini korundu.")
            pid = pid_from_file(context)
            if pid is not None and process_owned(context, pid):
                ping = subprocess.run([str(CLIENT), f"--defaults-file={config}", "--connect-timeout=2", "--batch", "--skip-column-names", "--execute=SELECT 1"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=private_environment(context), timeout=5)
                if ping.returncode == 0:
                    return True
            time.sleep(0.2)
        raise SafetyError("QA MariaDB soketi hazır olmadı; özel veri dizini korundu.")
    except BaseException:
        # Even if MariaDB failed before writing its PID file, verify the launched PID.
        if process_owned(context, process.pid):
            terminate_owned(context, process.pid)
        raise


def validate_environment(context):
    private_file(ENV)
    values = {}
    for line in ENV.read_text(encoding="utf-8").splitlines():
        if line.strip() and not line.lstrip().startswith("#") and "=" in line:
            key, value = line.split("=", 1)
            values[key.strip()] = value.strip().strip('"').strip("'")
    expected = {"APP_ENV": "qa", "APP_DEBUG": "false", "APP_URL": ORIGIN, "DB_CONNECTION": "mysql", "DB_DATABASE": context["database"], "DB_OWNERSHIP": context["database"], "DB_SOCKET": context["socket"], "DB_USERNAME": context["username"], "DB_PASSWORD": context["password"], "APP_KEY": context["app_key"], "MAIL_MAILER": "log"}
    if any(values.get(key) != value for key, value in expected.items()) or values.get("DB_URL") not in (None, "", "null"):
        raise SafetyError("QA ortamı özel context veya log mailer ile eşleşmiyor; işlem yapılmadı.")


FIXTURES = r'''<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.qa');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c=json_decode(file_get_contents(base_path('.local/qa-context.json')),true,512,JSON_THROW_ON_ERROR);
if (!app()->environment('qa') || config('database.default')!=='mysql' || config('database.connections.mysql.database')!==$c['database'] || config('database.connections.mysql.unix_socket')!==$c['socket'] || config('mail.default')!=='log') throw new RuntimeException('Yalnız ayrı QA veritabanı ve log mailer.');
$actual=rtrim(Illuminate\Support\Facades\DB::selectOne('SELECT @@datadir AS path')->path,'/');
if (realpath($actual)!==realpath($c['datadir'])) throw new RuntimeException('QA datadir doğrulanamadı.');
if (App\Models\User::count()!==0 || file_exists(base_path('.local/qa-users.json'))) throw new RuntimeException('Mevcut fixture korundu; üzerine yazılmadı.');
if (!App\Models\Project::where('slug','draw')->where('is_published',true)->exists()) throw new RuntimeException('QA proje seed eksik.');
$credentials=['origin'=>'http://127.0.0.1:8015','projectSlug'=>'draw'];
Illuminate\Support\Facades\DB::transaction(function()use(&$credentials){
    foreach (['member','admin'] as $role) {
        $password=bin2hex(random_bytes(16)).'!Aa1';
        $user=new App\Models\User();
        $user->forceFill(['name'=>$role==='admin'?'Test Yöneticisi':'Test Üyesi','email'=>$role.'@example.test','password'=>$password,'email_verified_at'=>now(),'role'=>$role==='admin'?'super_user':'member','locale'=>'tr'])->save();
        $user->notificationPreference()->create(['site_notifications'=>true,'comment_mail'=>false,'reply_mail'=>false,'project_mail'=>false]);
        $credentials[$role]=['email'=>$user->email,'password'=>$password];
    }
    $owner=App\Models\User::where('role','member')->firstOrFail();
    $comment=$owner->comments()->create(['body'=>'Tarayıcı testine ait onaylı ziyaretçi görüşü.']);
    $comment->forceFill(['status'=>'approved','approved_at'=>now()])->save();
    App\Services\Settings::put('contact',['tr'=>['title'=>'Birlikte konuşalım.','lead'=>'Yerel test iletişim sayfası.','email'=>'contact@example.test']]);
    App\Models\ContactMessage::create(['email'=>'browser-contact@example.test','name'=>'Test Ziyaretçisi','subject'=>'Tarayıcı testi mesajı','body'=>'Yalnız izole QA ortamındaki test mesajı.','status'=>'new']);
});
$previous=umask(0077);
$file=fopen(base_path('.local/qa-users.json'),'x');
if ($file===false) throw new RuntimeException('QA erişim dosyası yazılamadı; mevcut veriler korundu.');
fwrite($file,json_encode($credentials,JSON_THROW_ON_ERROR));fclose($file);umask($previous);
echo "Yalnız ayrı QA veritabanındaki sahte fixture hazır.\n";
'''


def prepare():
    refuse_existing()
    prerequisites()
    with control_lock():
        refuse_existing()
        RUNTIME.mkdir(mode=0o700, parents=True, exist_ok=True)
        suffix = secrets.token_hex(4)
        runtime = RUNTIME / ("qa-" + suffix)
        runtime.mkdir(mode=0o700, exist_ok=False)
        datadir = runtime / "mysql"
        datadir.mkdir(mode=0o700)
        context = {"runtime": str(runtime), "datadir": str(datadir), "socket": str(runtime / "mysql.sock"), "pid": str(runtime / "mysql.pid"), "log": str(runtime / "mysql.log"), "database": "madematik_v2_qa_" + suffix, "username": "madematik_qa_" + suffix, "password": secrets.token_hex(32), "root_password": secrets.token_hex(32), "app_key": "base64:" + base64.b64encode(secrets.token_bytes(32)).decode()}
        if len(os.fsencode(context["socket"])) >= 104:
            raise SafetyError("Proje yolu Unix soketi için çok uzun. Oluşturulan boş özel dizin korundu.")
        write_private(CONTEXT, json.dumps(context, indent=2) + "\n")
        values = {"APP_NAME": '"mADEMatik Solutions QA"', "APP_ENV": "qa", "APP_KEY": context["app_key"], "APP_DEBUG": "false", "APP_URL": ORIGIN, "APP_LOCALE": "tr", "APP_FALLBACK_LOCALE": "tr", "DB_CONNECTION": "mysql", "DB_HOST": "127.0.0.1", "DB_PORT": "3306", "DB_DATABASE": context["database"], "DB_USERNAME": context["username"], "DB_PASSWORD": context["password"], "DB_SOCKET": '"' + context["socket"].replace("\\", "\\\\").replace('"', '\\"') + '"', "DB_OWNERSHIP": context["database"], "CACHE_STORE": "database", "SESSION_DRIVER": "database", "MAIL_MAILER": "log", "MAIL_FROM_ADDRESS": "qa@example.test", "MAIL_FROM_NAME": '"mADEMatik QA"', "QUEUE_CONNECTION": "sync", "LOG_CHANNEL": "single"}
        write_private(ENV, "\n".join(f"{key}={value}" for key, value in values.items()) + "\n")
        try:
            logged_run([str(INSTALLER), "--no-defaults", f"--datadir={datadir}", "--auth-root-authentication-method=normal", "--skip-test-db"], context, "Yeni özel MariaDB ilk kurulumu")
            start_server(context, initial=True)
            sql = f"CREATE DATABASE `{context['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nCREATE USER '{context['username']}'@'localhost' IDENTIFIED BY '{context['password']}';\nGRANT ALL PRIVILEGES ON `{context['database']}`.* TO '{context['username']}'@'localhost';\nDELETE FROM mysql.global_priv WHERE User='';\nALTER USER 'root'@'localhost' IDENTIFIED BY '{context['root_password']}';\nFLUSH PRIVILEGES;\n"
            logged_run([str(CLIENT), f"--defaults-file={sql_config(context, initial=True)}"], context, "Yalnız yeni QA instance içinde ayrı DB ve kullanıcı", sql)
            sql_config(context)
            logged_run([shutil.which("php"), "artisan", "--env=qa", "migrate", "--seed", "--force", "--no-interaction"], context, "Yalnız yeni QA şemasının migration ve seed işlemi")
            logged_run([shutil.which("php")], context, "Yalnız sahte QA üyesi, yönetici, yorum ve iletişim fixture", FIXTURES)
        except BaseException:
            try:
                stop_server(context)
            except SafetyError:
                print("QA süreci güvenli biçimde kapatılamadı; veri korunuyor. --status ile sahipliği inceleyin.", file=sys.stderr)
            raise
    print("Yeni QA ortamı hazır; özel MariaDB çalışıyor, TCP ve gerçek SMTP kapalı. Ayrıntılar: docs/qa-environment.md")


def main():
    parser = argparse.ArgumentParser(description="Yalnız bu projeye ait yeni ve izole QA MariaDB ortamını hazırlar; mevcut dosyaların üzerine yazmaz.")
    actions = parser.add_mutually_exclusive_group()
    actions.add_argument("--start", action="store_true", help="Mevcut özel QA MariaDB'yi veri değiştirmeden başlat.")
    actions.add_argument("--stop", action="store_true", help="Sahipliği doğrulanmış özel QA MariaDB'yi kapat; veri silme.")
    actions.add_argument("--status", action="store_true", help="Özel QA MariaDB süreç durumunu göster; gizli bilgi yazdırma.")
    args = parser.parse_args()
    if not any((args.start, args.stop, args.status)):
        prepare()
        return
    with control_lock():
        context = read_context()
        if args.stop:
            stopped = stop_server(context)
            print("Özel QA MariaDB güvenle kapatıldı; veriler korundu." if stopped else "Özel QA MariaDB zaten duruyor; veriler korundu.")
        elif args.start:
            prerequisites()
            validate_environment(context)
            started = start_server(context)
            print("Özel QA MariaDB başlatıldı; TCP kapalı." if started else "Özel QA MariaDB zaten çalışıyor.")
        else:
            pid = pid_from_file(context)
            running = pid is not None and process_owned(context, pid)
            if not running and Path(context["socket"]).exists():
                raise SafetyError("QA soketi var ancak süreç sahipliği doğrulanamadı.")
            print("Özel QA MariaDB çalışıyor." if running else "Özel QA MariaDB duruyor.")


if __name__ == "__main__":
    try:
        main()
    except (SafetyError, OSError, KeyError, ValueError, subprocess.SubprocessError) as error:
        # Underlying exceptions may include connection details; keep terminal output generic.
        message = str(error) if isinstance(error, SafetyError) else "QA kurulumu/denetimi başarısız; mevcut veriler korundu. Özel günlükleri yerel olarak inceleyin."
        print("Hata: " + message, file=sys.stderr)
        sys.exit(1)
