from pathlib import Path
import os,json,subprocess,uuid,concurrent.futures,socketserver,threading
root=Path('/workspace/jmaccr');environment=dict(os.environ,DB_CONNECTION='mysql',DB_DATABASE='jmac_concurrency_test',APP_ENV='testing',MAIL_MAILER='smtp');created=False;messages=[]
def php(code):return subprocess.check_output(['php','-r',code],cwd=root,env=environment,text=True)
bootstrap=r"require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();"
class SMTP(socketserver.StreamRequestHandler):
 def handle(self):
  self.wfile.write(b'220 localhost controlled QA SMTP\r\n')
  while True:
   line=self.rfile.readline(65536)
   if not line:return
   command=line.split(b' ',1)[0].strip().upper()
   if command in [b'EHLO',b'HELO']:self.wfile.write(b'250-localhost\r\n250 SIZE 10485760\r\n')
   elif command==b'DATA':
    self.wfile.write(b'354 End with dot\r\n');data=[]
    while True:
     part=self.rfile.readline(65536)
     if part in [b'.\r\n',b'.\n'] or not part:break
     data.append(part)
    messages.append(b''.join(data));self.wfile.write(b'250 Accepted by controlled QA SMTP\r\n')
   elif command==b'QUIT':self.wfile.write(b'221 Bye\r\n');return
   else:self.wfile.write(b'250 OK\r\n')
server=socketserver.ThreadingTCPServer(('127.0.0.1',2527),SMTP);thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
try:
 php('$p=new PDO("mysql:unix_socket=/workspace/toolchain/mysql.sock","agent");if($p->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=\'jmac_concurrency_test\'")->fetch())throw new LogicException("Existing DB; refusing overwrite");$p->exec("CREATE DATABASE jmac_concurrency_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");');created=True
 subprocess.run(['php','artisan','migrate','--seed','--force'],cwd=root,env=environment,check=True,stdout=subprocess.DEVNULL)
 product=int(php(bootstrap+r'''Illuminate\Support\Facades\DB::table('site_settings')->insert(['key'=>'privacy.document','json_value'=>json_encode(['approved'=>true,'version'=>'qa-v1','body'=>'Política ficticia exclusivamente para la base de prueba, no se publica.'])]);Illuminate\Support\Facades\DB::table('site_settings')->insert(['key'=>'business','json_value'=>json_encode(['notify_email'=>'qa@example.test'])]);echo Illuminate\Support\Facades\DB::table('products')->insertGetId(['name'=>'Producto nuevo QA','sku'=>'NUEVO-QA','slug'=>'nuevo-qa','status'=>'published','search_text'=>'nuevo qa']);'''))
 shared=str(uuid.uuid4());keys=[str(uuid.uuid4()) for _ in range(6)]+[shared]*4
 def request(key):return json.loads(subprocess.check_output(['php','/tmp/jmac-sales-concurrency.php',key,str(product)],cwd=root,env=environment,text=True))
 with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:results=list(pool.map(request,keys))
 unique={result['code'] for result in results};assert len(unique)==7
 same=results[6:];assert len({result['code'] for result in same})==1;assert sum(result['created'] for result in same)==1
 sequence=json.loads(php(bootstrap+r"echo json_encode(Illuminate\Support\Facades\DB::table('preorders')->orderBy('seq')->pluck('seq'));") );assert sequence==list(range(1,8)),sequence
 config=r"config(['mail.default'=>'smtp','mail.mailers.smtp'=>['transport'=>'smtp','scheme'=>'smtp','host'=>'127.0.0.1','port'=>2527,'timeout'=>5],'mail.from.address'=>'qa@example.test']);"
 summary=json.loads(php(bootstrap+config+r"echo json_encode(app(App\Domain\Notifications\Outbox::class)->process(20));"));assert summary['sent']==7,summary;assert len(messages)==7
 result={'result':'PASSED','database':'MariaDB 11.8.6 local','parallel_workers':4,'simultaneous_attempts':10,'unique_preorders':7,'same_token_attempts':4,'same_token_records':1,'sequences':sequence,'smtp_transport':'controlled local SMTP; no external delivery','smtp_messages_accepted':len(messages),'outbox_summary':summary}
 (root/'docs/evidence/h5-concurrency-smtp.json').write_text(json.dumps(result,indent=2)+'\n');print(json.dumps(result))
finally:
 server.shutdown();server.server_close()
 if created:php('$p=new PDO("mysql:unix_socket=/workspace/toolchain/mysql.sock","agent");$p->exec("DROP DATABASE jmac_concurrency_test");')
