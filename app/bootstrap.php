<?php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessions=getenv('UNCORKED_SESSION_PATH') ?: __DIR__.'/../storage/sessions';
    if(!is_dir($sessions))mkdir($sessions,0700,true);
    session_save_path($sessions);
    session_name('UNCORKEDSESSID');
    ini_set('session.use_strict_mode','1');
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off']);
    session_start();
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function db(bool $close=false): ?PDO {
    static $db;
    if($close){$db=null;return null;}
    if (!$db) {
        $db = new PDO('sqlite:'.(getenv('UNCORKED_DB_PATH') ?: __DIR__.'/../storage/uncorked.sqlite'), null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA busy_timeout=5000');
        $db->exec("CREATE TABLE IF NOT EXISTS reservations (id INTEGER PRIMARY KEY, reference TEXT UNIQUE NOT NULL, name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL, event_type TEXT NOT NULL, date TEXT NOT NULL, start TEXT NOT NULL, end TEXT NOT NULL, guests INTEGER NOT NULL, notes TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'Pending', created_at TEXT NOT NULL)");
        $columns=array_column($db->query('PRAGMA table_info(reservations)')->fetchAll(),'name');
        foreach(['venue_id'=>'INTEGER REFERENCES venues(id)','package_id'=>'INTEGER REFERENCES packages(id)','total'=>'INTEGER NOT NULL DEFAULT 0'] as $name=>$type) if(!in_array($name,$columns,true)) $db->exec("ALTER TABLE reservations ADD COLUMN $name $type");
        $db->exec('CREATE TABLE IF NOT EXISTS audit (id INTEGER PRIMARY KEY, reservation_id INTEGER, action TEXT, created_at TEXT)');
        $columns=array_column($db->query('PRAGMA table_info(audit)')->fetchAll(),'name');
        if(!in_array('user_id',$columns,true)) $db->exec('ALTER TABLE audit ADD COLUMN user_id INTEGER');
        $db->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
        // Legacy paid sales have no reliable collection date; leave it unknown.
        $columns=array_column($db->query('PRAGMA table_info(sales)')->fetchAll(),'name');
        if(!in_array('paid_at',$columns,true)) $db->exec('ALTER TABLE sales ADD COLUMN paid_at TEXT');
    }
    return $db;
}
function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function csrf(): void { if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Session expired. Refresh and try again.'); } }
function run(string $sql,array $args=[]): PDOStatement { $q=db()->prepare($sql);$q->execute($args);return $q; }
function money(int|float $value): string { return 'PHP '.number_format($value/100,2); }
function audit(string $action,?int $reservation=null): void { run('INSERT INTO audit(reservation_id,action,created_at,user_id) VALUES(?,?,?,?)',[$reservation,$action,date('c'),$_SESSION['user_id']??null]); }
function user(): ?array { return isset($_SESSION['user_id'])?(run('SELECT id,name,email,role,active FROM users WHERE id=? AND active=1',[$_SESSION['user_id']])->fetch()?:null):null; }
function require_login(): array { $u=user();if(!$u){header('Location: ../login.php');exit;} return $u; }
function allowed(string $module,?array $u=null): bool { $u??=user();if(!$u)return false;if($u['role']==='Administrator')return true;if(in_array($module,['users','settings','backup','audit'],true))return false;if($u['role']==='Manager')return true;return in_array($module,['dashboard','reservations','customers','inventory','sales','assignments','alerts'],true); }
function flash(string $message): void { $_SESSION['flash']=$message; }
function scalar_post(string $key,string $default=''): string { $v=$_POST[$key]??$default;if(!is_string($v))throw new RuntimeException('Invalid form value.');return trim($v); }
function text_post(string $key,int $max=200): string { $v=scalar_post($key);if($v===''||mb_strlen($v)>$max)throw new RuntimeException('Check the '.str_replace('_',' ',$key).' field.');return $v; }
function int_post(string $key,int $min=0): int { $v=scalar_post($key);if(!ctype_digit($v)||(int)$v<$min)throw new RuntimeException('Check the '.str_replace('_',' ',$key).' field.');return (int)$v; }
function cents_post(string $key): int { $v=scalar_post($key);if(!preg_match('/^\d{1,8}(\.\d{1,2})?$/',$v))throw new RuntimeException('Enter a valid non-negative amount.');return (int)round((float)$v*100); }
function transaction(callable $callback): mixed { $pdo=db();$pdo->exec('BEGIN IMMEDIATE');try{$result=$callback();$pdo->exec('COMMIT');return $result;}catch(Throwable $e){$pdo->exec('ROLLBACK');throw $e;} }
