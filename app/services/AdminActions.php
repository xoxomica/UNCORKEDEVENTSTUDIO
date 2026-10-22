<?php
function admin_action(array $u,string $page,array $modules): void {
 csrf();$action=scalar_post('action');
 if($action==='logout'){audit('Signed out');$_SESSION=[];session_regenerate_id(true);header('Location: ../login.php');exit;}
 if(!allowed($page,$u))throw new RuntimeException('Your account cannot perform this action.');
 if($action==='save_master' && isset($modules[$page])){
  $module=$modules[$page];$values=[];$id=int_post('id');
  foreach($module['fields'] as $field=>[$label,$type,$required]){
   if($type==='money')$v=cents_post($field);
   elseif($type==='number')$v=int_post($field,$field==='capacity'?1:0);
   elseif($type==='active'){$v=int_post($field);if(!in_array($v,[0,1],true))throw new RuntimeException('Invalid availability.');}
   else{$v=$required?text_post($field,$type==='textarea'?3000:200):scalar_post($field);if(mb_strlen($v)>3000)throw new RuntimeException('Field is too long.');if($type==='email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email.');if($field==='email')$v=strtolower($v);}
   $values[$field]=$v;
  }
  if($id){if(!run('SELECT id FROM '.$module['table'].' WHERE id=?',[$id])->fetch())throw new RuntimeException('Record not found.');$sets=implode(',',array_map(fn($f)=>"$f=?",array_keys($values)));run('UPDATE '.$module['table'].' SET '.$sets.' WHERE id=?',[...array_values($values),$id]);}
  else{if($page==='customers')$values['created_at']=date('c');$fields=implode(',',array_keys($values));$marks=implode(',',array_fill(0,count($values),'?'));run('INSERT INTO '.$module['table']." ($fields) VALUES ($marks)",array_values($values));}
  audit(($id?'Updated ':'Created ').$page.' record');return;
 }
 if($page==='reservations'){
  if($action==='status'){change_status(int_post('id',1),text_post('status'));return;}
  if($action==='event_details'){
   transaction(function(){ $id=int_post('id',1);$r=run('SELECT * FROM reservations WHERE id=?',[$id])->fetch();if(!$r)throw new RuntimeException('Reservation not found.');if(!in_array($r['status'],['Pending','Cancelled'],true))throw new RuntimeException('Return the reservation to Pending before editing its schedule.');$venue=int_post('venue_id',1);$v=active_record('venues',$venue);$package=int_post('package_id');if($package)active_record('packages',$package);$date=text_post('date');$start=text_post('start');$end=text_post('end');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$dt||$dt->format('Y-m-d')!==$date||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end)||$start>=$end)throw new RuntimeException('Check event date and same-day time range.');$guests=int_post('guests',1);if($guests>$v['capacity'])throw new RuntimeException('Guest count exceeds capacity.');$total=cents_post('total');$paid=(int)run('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id=?',[$id])->fetchColumn();if($total<$paid)throw new RuntimeException('Total cannot be less than recorded payments.');if(($date!==$r['date']||$start!==$r['start']||$end!==$r['end'])&&run('SELECT COUNT(*) FROM assignments WHERE reservation_id=?',[$id])->fetchColumn())throw new RuntimeException('Remove existing staff assignments before rescheduling, then assign the new schedule.');run('UPDATE reservations SET venue_id=?,package_id=?,total=?,date=?,start=?,end=?,guests=?,notes=? WHERE id=?',[$venue,$package?:null,$total,$date,$start,$end,$guests,scalar_post('notes'),$id]);audit('Updated event details',$id);});return;
  }
 }
 if($page==='inventory'&&$action==='movement'){
  transaction(function()use($u){$product=int_post('product_id',1);active_record('products',$product);$qty=int_post('quantity',1);$direction=text_post('direction');if(!in_array($direction,['in','out'],true))throw new RuntimeException('Invalid stock direction.');if($direction==='out')$qty=-$qty;if(stock($product)+$qty<0)throw new RuntimeException('This adjustment would make stock negative.');$reason=text_post('reason',500);run('INSERT INTO movements(product_id,quantity,reason,user_id,created_at) VALUES(?,?,?,?,?)',[$product,$qty,$reason,$u['id'],date('c')]);audit('Stock movement: '.$qty.' for item '.$product);});return;
 }
 if($page==='purchases'&&$action==='purchase'){receive_purchase(int_post('supplier_id',1),int_post('product_id',1),int_post('quantity',1),cents_post('unit_cost'),text_post('reference'),$u['id']);return;}
 if($page==='sales'){
  if($action==='sale'){sell(int_post('product_id',1),int_post('quantity',1),int_post('reservation_id')?:null,text_post('status'),$u['id']);return;}
  if($action==='settle'){settle_sale(int_post('id',1));return;}
 }
 if($page==='payments'&&$action==='payment'){record_payment(int_post('reservation_id',1),cents_post('amount'),text_post('method'),text_post('reference'),$u['id']);return;}
 if($page==='assignments'&&$action==='remove_assignment'){ $id=int_post('id',1);run('DELETE FROM assignments WHERE id=?',[$id]);audit('Removed staff assignment: '.$id);return;}
 if($page==='assignments'&&$action==='assignment'){
  transaction(function(){ $employee=int_post('employee_id',1);active_record('employees',$employee);$id=int_post('reservation_id',1);$r=run("SELECT * FROM reservations WHERE id=? AND status IN ('Pending','Confirmed')",[$id])->fetch();if(!$r)throw new RuntimeException('Select an active event.');$start=$r['date'].'T'.text_post('start');$end=$r['date'].'T'.text_post('end');foreach([$start,$end] as $time){$t=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$time);if(!$t||$t->format('Y-m-d\TH:i')!==$time)throw new RuntimeException('Enter valid assignment times.');}if($start>=$end)throw new RuntimeException('End must be later than start.');$q=run("SELECT COUNT(*) FROM assignments a JOIN reservations r ON r.id=a.reservation_id WHERE a.employee_id=? AND a.start<? AND a.end>? AND r.status NOT IN ('Cancelled','Archived')",[$employee,$end,$start])->fetchColumn();if($q)throw new RuntimeException('Employee already has an overlapping assignment.');run('INSERT INTO assignments(employee_id,reservation_id,duty,start,end) VALUES(?,?,?,?,?)',[$employee,$id,text_post('duty'),$start,$end]);audit('Created staff assignment',$id);});return;
 }
 if($page==='users'&&$action==='user'){
  $id=int_post('id');$name=text_post('name',100);$email=strtolower(text_post('email',150));$role=text_post('role');$active=int_post('active');$password=scalar_post('password');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!in_array($role,['Administrator','Manager','Staff'],true)||!in_array($active,[0,1],true))throw new RuntimeException('Check email, role, and status.');if($id===$u['id']&&($role!=='Administrator'||!$active))throw new RuntimeException('You cannot remove your own administrator access.');if(($password!==''||!$id)&&strlen($password)<12)throw new RuntimeException('Passwords must contain at least 12 characters.');
  transaction(function()use($id,$name,$email,$role,$active,$password){if($id){if(!run('SELECT id FROM users WHERE id=?',[$id])->fetch())throw new RuntimeException('User not found.');if(($role!=='Administrator'||!$active)&&run("SELECT COUNT(*) FROM users WHERE role='Administrator' AND active=1 AND id<>?",[$id])->fetchColumn()==0)throw new RuntimeException('Keep at least one active administrator.');run('UPDATE users SET name=?,email=?,role=?,active=? WHERE id=?',[$name,$email,$role,$active,$id]);if($password!=='')run('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);}else run('INSERT INTO users(name,email,password,role,active,created_at) VALUES(?,?,?,?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT),$role,$active,date('c')]);audit('User account saved: '.$email);});return;
 }
 if($page==='settings'&&$action==='settings'){
  foreach(['address','phone','email','hours','facebook','about'] as $key){$value=scalar_post($key);if(strlen($value)>3000)throw new RuntimeException('Setting is too long.');if($key==='facebook'&&$value!==''&&(!filter_var($value,FILTER_VALIDATE_URL)||!str_starts_with($value,'https://')))throw new RuntimeException('Use an HTTPS Facebook URL.');run('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$key,$value]);}audit('Updated business settings');return;
 }
 throw new RuntimeException('Unsupported action.');
}
