<?php
require __DIR__.'/../app/bootstrap.php';$u=user();if(!$u||!allowed('backup',$u)||$_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(403);exit('Access denied.');}csrf();
$path=__DIR__.'/../storage/backup-'.bin2hex(random_bytes(8)).'.sqlite';try{audit('Downloaded database backup');db()->exec('VACUUM INTO '.db()->quote($path));header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="uncorked-backup-'.date('Y-m-d-His').'.sqlite"');header('Cache-Control: no-store');readfile($path);}finally{if(is_file($path))unlink($path);}
