#!/usr/bin/env python3
"""Check the shipped Apache rewrite rules against disposable local directories."""
from pathlib import Path
import grp, json, os, pwd, shutil, subprocess, tempfile, time
from urllib.request import urlopen, Request
from urllib.error import HTTPError, URLError

project=Path(__file__).resolve().parent.parent
runtime=project/'storage/runtime'
runtime.mkdir(parents=True,exist_ok=True)
fixture=Path(tempfile.mkdtemp(prefix='routing-qa-',dir=runtime))
public=fixture/'public';public.mkdir()
shutil.copy2(project/'public/.htaccess',public/'.htaccess')
(public/'index.php').write_text('<?php header("Content-Type: text/plain"); echo "QA_LARAVEL_FALLBACK:" . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);')
apps=['drAW','GeoMatik2D','GeoMatik3D','avCELL']
for name in apps:
    directory=public/name;directory.mkdir()
    (directory/'index.html').write_text('QA_INDEPENDENT_'+name)
    (directory/'app.js').write_text('QA_ASSET_'+name)
(public/'health.txt').write_text('QA_PHYSICAL_FILE')
user=pwd.getpwuid(os.getuid()).pw_name;group=grp.getgrgid(os.getgid()).gr_name
configuration=f'''ServerRoot "{fixture}"
DefaultRuntimeDir "{fixture}"
PidFile "{fixture}/apache.pid"
Listen 127.0.0.1:8016
ServerName 127.0.0.1
LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so
LoadModule authz_core_module /usr/lib/apache2/modules/mod_authz_core.so
LoadModule dir_module /usr/lib/apache2/modules/mod_dir.so
LoadModule mime_module /usr/lib/apache2/modules/mod_mime.so
LoadModule rewrite_module /usr/lib/apache2/modules/mod_rewrite.so
LoadModule php_module /usr/lib/apache2/modules/libphp8.4.so
User {user}
Group {group}
ServerLimit 1
StartServers 1
MinSpareServers 1
MaxSpareServers 2
MaxRequestWorkers 5
ErrorLog "{fixture}/error.log"
TypesConfig /etc/mime.types
DocumentRoot "{public}"
DirectoryIndex index.html index.php
<Directory "{public}">
    AllowOverride All
    Options -Indexes
    Require all granted
</Directory>
<FilesMatch "\\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>
'''
conf=fixture/'apache.conf';conf.write_text(configuration)
check=subprocess.run(['/usr/sbin/apache2','-f',str(conf),'-t'],capture_output=True,text=True)
if check.returncode:raise SystemExit('İzole Apache test yapılandırması açılamadı: '+check.stderr)
report={'checks':[],'scope':'Disposable project-local Apache fixture; original applications and production never accessed.'}
with (fixture/'server.log').open('wb') as log:
    server=subprocess.Popen(['/usr/sbin/apache2','-f',str(conf),'-DFOREGROUND'],stdout=log,stderr=log,start_new_session=True)
    try:
        for attempt in range(40):
            try:
                with urlopen('http://127.0.0.1:8016/health.txt',timeout=1) as response:break
            except (URLError,ConnectionError):time.sleep(.1)
        def verify(url,expected):
            with urlopen('http://127.0.0.1:8016'+url,timeout=3) as response:
                body=response.read().decode();assert response.status==200 and body==expected,(url,response.status,body)
                report['checks'].append({'path':url,'passed':True,'final_path':response.url.split(':8016')[-1]})
        verify('/health.txt','QA_PHYSICAL_FILE')
        for name in apps:
            verify('/'+name,'QA_INDEPENDENT_'+name)
            verify('/'+name+'/','QA_INDEPENDENT_'+name)
            verify('/'+name+'/app.js','QA_ASSET_'+name)
        verify('/hakkimda','QA_LARAVEL_FALLBACK:/hakkimda')
        verify('/projelerim/test-project','QA_LARAVEL_FALLBACK:/projelerim/test-project')
        verify('/draw','QA_LARAVEL_FALLBACK:/draw')
        verify('/drAW/?source=fixture','QA_INDEPENDENT_drAW')
        report['passed']=len(report['checks'])
    finally:
        server.terminate()
        try:server.wait(timeout=5)
        except subprocess.TimeoutExpired:raise SystemExit('İzole Apache kapanışı sürüyor; zorla sonlandırılmadı.')
(runtime/'routing-report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print(json.dumps({'passed':report['passed'],'report':'storage/runtime/routing-report.json'}))
