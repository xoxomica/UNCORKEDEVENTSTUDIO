<?php
require __DIR__.'/../app/bootstrap.php';
require __DIR__.'/../app/services/Operations.php';
header('Content-Type: application/json');
function reply(bool $ok, string $message, int $code=200): never { http_response_code($code); echo json_encode(compact('ok','message')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') reply(false,'Use the reservation form.',405);
if (!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) reply(false,'Session expired. Refresh this page and try again.',403);
$data=[];
foreach (['name','email','phone','event_type','date','start','end','guests','notes'] as $key) { if (isset($_POST[$key]) && !is_string($_POST[$key])) reply(false,'Invalid form values.',422); $data[$key]=trim($_POST[$key]??''); }
extract($data);
if (!$name || strlen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150 || !preg_match('/^[+0-9 ()-]{7,25}$/',$phone)) reply(false,'Enter a valid name, email, and contact number.',422);
if (!in_array($event_type,['Birthday','Private gathering','Corporate event','Other celebration'],true)) reply(false,'Choose an occasion.',422);
$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
if (!$parsed || $parsed->format('Y-m-d')!==$date || $date<date('Y-m-d')) reply(false,'Choose today or a future date.',422);
if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$end) || $end<=$start) reply(false,'End time must be later than start time on the same day.',422);
if (!ctype_digit($guests) || (int)$guests<1 || (int)$guests>500 || strlen($notes)>2000 || ($_POST['consent']??'')!=='1') reply(false,'Check guest count, notes, and consent.',422);
if (time()-($_SESSION['last_request']??0)<30) reply(false,'Please wait 30 seconds before sending another request.',429);
try {
 $venueId=int_post('venue_id',1);$packageId=int_post('package_id');$email=strtolower($email);
 $reference='UNC-'.strtoupper(bin2hex(random_bytes(4)));
 transaction(function()use($venueId,$packageId,$reference,$name,$email,$phone,$event_type,$date,$start,$end,$guests,$notes){
  $venue=active_record('venues',$venueId);if((int)$guests>$venue['capacity'])throw new RuntimeException('Guest count exceeds the selected venue capacity.');
  if($date===date('Y-m-d')&&$start<=date('H:i'))throw new RuntimeException('Choose a future event start time.');
  $busy=run("SELECT COUNT(*) FROM reservations WHERE venue_id=? AND date=? AND status IN ('Confirmed','Completed') AND start<? AND end>?",[$venueId,$date,$end,$start])->fetchColumn();if($busy)throw new RuntimeException('This schedule is already booked. Choose another time.');
  $total=$packageId?(int)active_record('packages',$packageId)['price']:0;
  run('INSERT INTO reservations(reference,name,email,phone,event_type,date,start,end,guests,notes,created_at,venue_id,package_id,total) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$reference,$name,$email,$phone,$event_type,$date,$start,$end,(int)$guests,$notes,date('c'),$venueId,$packageId?:null,$total]);$id=(int)db()->lastInsertId();
  // Preserve an existing customer profile; the request still retains its submitted contact details.
  run('INSERT INTO customers(name,email,phone,created_at) VALUES(?,?,?,?) ON CONFLICT(email) DO NOTHING',[$name,$email,$phone,date('c')]);audit('Online reservation requested',$id);
 });
 $_SESSION['last_request']=time();reply(true,"Request received! Your reference is $reference. Your booking is pending team review; this is not yet a confirmation.");
} catch (PDOException $e) { error_log($e->getMessage()); reply(false,'Unable to save your request. Please try again later.',500); }
catch (RuntimeException $e) { reply(false,$e->getMessage(),422); }
catch (Throwable $e) { error_log($e->getMessage()); reply(false,'Unable to save your request. Please try again later.',500); }
