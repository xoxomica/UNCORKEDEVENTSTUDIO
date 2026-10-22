<?php
declare(strict_types=1);
// Reopen an old database twice to verify additive migration and preservation.
$temporary=sys_get_temp_dir().'/uncorked-migration-'.bin2hex(random_bytes(6));
mkdir($temporary,0700);$path=$temporary.'/test.sqlite';
putenv('UNCORKED_DB_PATH='.$path);putenv('UNCORKED_SESSION_PATH='.$temporary.'/sessions');
$legacy=new PDO('sqlite:'.$path);
$legacy->exec("CREATE TABLE sales(id INTEGER PRIMARY KEY,reference TEXT UNIQUE NOT NULL,reservation_id INTEGER,product_id INTEGER NOT NULL,quantity INTEGER NOT NULL,unit_price INTEGER NOT NULL,status TEXT NOT NULL,user_id INTEGER NOT NULL,created_at TEXT NOT NULL)");
$legacy->exec("INSERT INTO sales VALUES(1,'LEGACY',NULL,1,2,15000,'Paid',1,'2026-09-01T12:00:00+08:00')");
$legacy=null;
require __DIR__.'/../app/bootstrap.php';
try {
 $expected=['id'=>1,'reference'=>'LEGACY','reservation_id'=>null,'product_id'=>1,'quantity'=>2,'unit_price'=>15000,'status'=>'Paid','user_id'=>1,'created_at'=>'2026-09-01T12:00:00+08:00','paid_at'=>null];
 foreach([1,2] as $pass){
  $row=run('SELECT * FROM sales WHERE id=1')->fetch();
  if($row!==$expected)throw new RuntimeException('Legacy sale changed during migration.');
  echo "PASS: migration open $pass preserves legacy sale and unknown collection date\n";
  db(true);
 }
} finally {
 db(true);session_write_close();
 foreach(glob($temporary.'/sessions/sess_*') as $file)unlink($file);
 if(is_dir($temporary.'/sessions'))rmdir($temporary.'/sessions');
 unlink($path);rmdir($temporary);
}
