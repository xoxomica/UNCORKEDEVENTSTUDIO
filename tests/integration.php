<?php
declare(strict_types=1);
// Isolated database: this test never uses business records.
$temporary=sys_get_temp_dir().'/uncorked-test-'.bin2hex(random_bytes(6));mkdir($temporary,0700);$path=$temporary.'/test.sqlite';putenv('UNCORKED_DB_PATH='.$path);putenv('UNCORKED_SESSION_PATH='.$temporary.'/sessions');
require __DIR__.'/../app/bootstrap.php';require __DIR__.'/../app/services/Operations.php';require __DIR__.'/../app/services/AdminActions.php';
$count=0;
function check(bool $condition,string $message): void { global $count;if(!$condition)throw new RuntimeException('FAIL: '.$message);$count++;echo "PASS: $message\n"; }
function rejected(callable $fn,string $message): void { $failed=false;try{$fn();}catch(RuntimeException){$failed=true;}check($failed,$message); }
try{
 run('INSERT INTO users(name,email,password,role,created_at) VALUES(?,?,?,?,?)',['Test Admin','admin@example.test',password_hash('test-password-long',PASSWORD_DEFAULT),'Administrator',date('c')]);$_SESSION['user_id']=1;
 run('INSERT INTO venues(name,capacity) VALUES(?,?)',['Test venue',50]);run('INSERT INTO venues(name,capacity) VALUES(?,?)',['Second venue',20]);
 run('INSERT INTO products(name,sku,category,unit,price,minimum) VALUES(?,?,?,?,?,?)',['Test beverage','TEST-1','Beverage','bottle',15000,3]);run('INSERT INTO suppliers(name) VALUES(?)',['Test supplier']);
 receive_purchase(1,1,10,5000,'TEST-PURCHASE',1);check(stock(1)===10,'Received purchase adds stock');
 sell(1,3,null,'Paid',1);check(stock(1)===7,'Sale deducts stock');check((int)run('SELECT unit_price FROM sales WHERE id=1')->fetchColumn()===15000,'Sale snapshots the selling price');
 rejected(fn()=>sell(1,20,null,'Paid',1),'Overselling is rejected');check(stock(1)===7,'Rejected sale leaves inventory unchanged');check((int)run('SELECT COUNT(*) FROM sales')->fetchColumn()===1,'Rejected sale leaves no transaction');
 $future=date('Y-m-d',strtotime('+10 days'));
 foreach([['TEST-A',1,10,'18:00','20:00'],['TEST-B',1,10,'19:00','21:00'],['TEST-C',1,10,'20:00','22:00'],['TEST-D',2,10,'19:00','21:00'],['TEST-E',2,30,'12:00','14:00']] as [$ref,$venue,$guests,$start,$end])run('INSERT INTO reservations(reference,name,email,phone,event_type,date,start,end,guests,notes,created_at,venue_id,total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$ref,'Test customer','customer@example.test','09123456789','Birthday',$future,$start,$end,$guests,'',date('c'),$venue,100000]);
 change_status(1,'Confirmed');check(run('SELECT status FROM reservations WHERE id=1')->fetchColumn()==='Confirmed','Reservation confirmation works');
 rejected(fn()=>change_status(2,'Confirmed'),'Overlapping reservation is rejected');rejected(fn()=>change_status(2,'Completed'),'Completed status cannot bypass conflict checks');change_status(3,'Confirmed');check(run('SELECT status FROM reservations WHERE id=3')->fetchColumn()==='Confirmed','Back-to-back events are allowed');change_status(4,'Confirmed');check(run('SELECT status FROM reservations WHERE id=4')->fetchColumn()==='Confirmed','Independent venues can overlap');rejected(fn()=>change_status(5,'Confirmed'),'Guest capacity enforced');
 record_payment(1,40000,'Cash','TEST-DEPOSIT',1);check((int)run('SELECT SUM(amount) FROM payments WHERE reservation_id=1')->fetchColumn()===40000,'Deposit saved');rejected(fn()=>record_payment(1,70000,'Cash','TOO-MUCH',1),'Overpayment rejected');record_payment(1,60000,'E-wallet','TEST-BALANCE',1);check((int)run('SELECT SUM(amount) FROM payments WHERE reservation_id=1')->fetchColumn()===100000,'Balance payment saved');
 check(allowed('users',['role'=>'Administrator']),'Administrator can manage users');check(!allowed('users',['role'=>'Manager']),'Manager cannot manage users');check(!allowed('payments',['role'=>'Staff']),'Staff cannot record event payments');check(allowed('inventory',['role'=>'Staff']),'Staff can record stock movements');
 $modules=require __DIR__.'/../app/modules.php';$_POST=['csrf'=>$_SESSION['csrf'],'action'=>'movement','product_id'=>'1','quantity'=>'99','direction'=>'out','reason'=>'Invalid deduction'];rejected(fn()=>admin_action(user(),'inventory',$modules),'Manual negative stock is blocked');
 $_POST=['csrf'=>$_SESSION['csrf'],'action'=>'save_master','id'=>'0','name'=>'Test customer','email'=>'customer@example.test','phone'=>'09123456789'];admin_action(user(),'customers',$modules);check((int)run('SELECT COUNT(*) FROM customers')->fetchColumn()===1,'Customer creation works');
 $_POST=['csrf'=>$_SESSION['csrf'],'action'=>'assignment','employee_id'=>'1','reservation_id'=>'1','duty'=>'Host','start'=>'18:00','end'=>'20:00'];run('INSERT INTO employees(name,position) VALUES(?,?)',['Test employee','Host']);admin_action(user(),'assignments',$modules);check((int)run('SELECT COUNT(*) FROM assignments')->fetchColumn()===1,'Staff assignment saved');$_POST['reservation_id']='4';rejected(fn()=>admin_action(user(),'assignments',$modules),'Overlapping staff assignment rejected');
 check((int)run('SELECT COUNT(*) FROM audit')->fetchColumn()>0,'Mutations produce activity records');
 // Cancelled/archived events retain assignments; every blocking status must recheck them.
 change_status(1,'Cancelled');$_POST=['csrf'=>$_SESSION['csrf'],'action'=>'assignment','employee_id'=>'1','reservation_id'=>'4','duty'=>'Host','start'=>'19:00','end'=>'21:00'];admin_action(user(),'assignments',$modules);
 foreach(['Cancelled','Archived'] as $inactive){
  change_status(1,$inactive);
  foreach(['Pending','Confirmed','Completed'] as $next){
   $before=(int)run('SELECT COUNT(*) FROM audit')->fetchColumn();
   rejected(fn()=>change_status(1,$next),$inactive.' to '.$next.' rejects retained staff conflict');
   check(run('SELECT status FROM reservations WHERE id=1')->fetchColumn()===$inactive,'Rejected reactivation preserves status');
   check((int)run('SELECT COUNT(*) FROM audit')->fetchColumn()===$before,'Rejected reactivation leaves no success audit');
  }
 }
 run('DELETE FROM assignments WHERE reservation_id=4');
 run('INSERT INTO employees(name,position) VALUES(?,?)',['Other employee','Host']);
 $_POST['employee_id']='2';admin_action(user(),'assignments',$modules);
 change_status(1,'Confirmed');check(run('SELECT status FROM reservations WHERE id=1')->fetchColumn()==='Confirmed','Reactivation allows overlapping events with different employees');
 change_status(1,'Cancelled');$_POST=['csrf'=>$_SESSION['csrf'],'action'=>'assignment','employee_id'=>'1','reservation_id'=>'3','duty'=>'Host','start'=>'20:00','end'=>'22:00'];admin_action(user(),'assignments',$modules);
 change_status(1,'Pending');check(run('SELECT status FROM reservations WHERE id=1')->fetchColumn()==='Pending','Reactivation allows back-to-back staff duties');
 // Render every admin module with the isolated database to catch view/SQL failures.
 $u=user();$error='';$flash='';$_GET=[];$_POST=[];
 function input_field(string $a,string $b,string $c='text',mixed $d='',bool $e=true):void{}
 function select_field(string $a,string $b,array $c,mixed $d='',bool $e=true):void{}
 function hidden(string $a):void{}
 function choices(string $a,string $b='name',bool $c=false):array{return [];}
 function table_view(array $a,array $b):void{}
 // Reports must attribute late settlement to payment date, while product sales retain order date.
 check(run('SELECT paid_at=created_at FROM sales WHERE id=1')->fetchColumn()==1,'Initially paid sale records collection timestamp');
 sell(1,1,null,'Unpaid',1);$lateId=(int)run('SELECT MAX(id) FROM sales')->fetchColumn();
 check(run('SELECT paid_at FROM sales WHERE id=?',[$lateId])->fetchColumn()===null,'Unpaid sale has no collection date');
 run("UPDATE sales SET created_at='2000-01-01T12:00:00+08:00' WHERE id=?",[$lateId]);
 $_POST=['csrf'=>$_SESSION['csrf'],'action'=>'settle','id'=>(string)$lateId];admin_action(user(),'sales',$modules);
 $paidAt=run('SELECT paid_at FROM sales WHERE id=?',[$lateId])->fetchColumn();
 check(substr($paidAt,0,10)===date('Y-m-d'),'Late settlement records current payment date');
 check(run('SELECT created_at FROM sales WHERE id=?',[$lateId])->fetchColumn()==='2000-01-01T12:00:00+08:00','Settlement preserves original order date');
 $audits=(int)run('SELECT COUNT(*) FROM audit')->fetchColumn();admin_action(user(),'sales',$modules);
 check(run('SELECT paid_at FROM sales WHERE id=?',[$lateId])->fetchColumn()===$paidAt&&(int)run('SELECT COUNT(*) FROM audit')->fetchColumn()===$audits,'Repeated settlement preserves timestamp and audit');
 rejected(fn()=>settle_sale(999999),'Missing sale settlement is rejected');
 $_GET=['from'=>'2000-01-01','to'=>'2000-01-01'];ob_start();require __DIR__.'/../app/views-reports.php';ob_end_clean();
 check($bar===0,'Old order period excludes later bar collection');
 check((int)$products[0]['quantity']===1,'Products sold remain in original order period');
 $_GET=['from'=>date('Y-m-d'),'to'=>date('Y-m-d')];ob_start();require __DIR__.'/../app/views-reports.php';ob_end_clean();
 check($bar===60000,'Current period includes initial paid sale and later settlement');
 run('UPDATE sales SET paid_at=NULL WHERE id=1');ob_start();require __DIR__.'/../app/views-reports.php';$report=ob_get_clean();
 check($bar===15000&&str_contains($report,'unknown collection date'),'Unknown legacy payment dates are excluded with report notice');
 $allPaid=(int)run("SELECT SUM(quantity*unit_price) FROM sales WHERE status='Paid'")->fetchColumn();check($allPaid===60000,'Unknown payment date preserves all-time paid totals');
 foreach(['dashboard','reservations','customers','venues','packages','products','inventory','suppliers','purchases','sales','payments','employees','assignments','reports','alerts','users','audit','backup','settings'] as $page){ob_start();require __DIR__.'/../app/views.php';ob_end_clean();check(true,'Admin view renders: '.$page);}
 echo "\n$count integration checks passed.\n";
}finally{db(true);session_write_close();if(is_file($path))unlink($path);foreach(glob($temporary.'/sessions/sess_*') as $sessionFile)unlink($sessionFile);rmdir($temporary.'/sessions');rmdir($temporary);}
