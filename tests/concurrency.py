"""Compete independent PHP processes against an isolated SQLite database."""
from pathlib import Path
import json
import os
import shutil
import sqlite3
import subprocess
import tempfile
import time

root = Path(__file__).resolve().parents[1]
php = shutil.which('php') or r'C:\xampp\php\php.exe'
count = 0

def check(condition, label):
    global count
    if not condition:
        raise AssertionError(label)
    count += 1
    print('PASS:', label)

with tempfile.TemporaryDirectory(prefix='uncorked-race-') as temporary:
    directory = Path(temporary)
    database = directory / 'test.sqlite'
    with sqlite3.connect(database) as conn:
        conn.executescript((root / 'database/schema.sql').read_text())
        conn.execute("INSERT INTO users VALUES(1,'Test','test@example.test','unused','Administrator',1,'2026-10-21')")
        conn.execute("INSERT INTO venues(id,name,capacity) VALUES(1,'Test venue',50)")
        conn.execute("INSERT INTO products(id,name,sku,category,unit,price) VALUES(1,'Test product','RACE','Test','unit',10000)")
        conn.execute("INSERT INTO movements(product_id,quantity,reason,user_id,created_at) VALUES(1,1,'Opening',1,'2026-10-21')")
        for identity in [1, 2]:
            conn.execute("INSERT INTO reservations(id,reference,name,email,phone,event_type,date,start,end,guests,created_at,venue_id,total) VALUES(?,?,'Test','test@example.test','09123456789','Birthday','2030-12-20','18:00','20:00',10,'2026-10-21',1,10000)", (identity, 'RACE-' + str(identity)))
    conn.close()

    def race(label, actions):
        gate = directory / (label + '.gate')
        workers = []
        try:
            for index, action in enumerate(actions):
                ready = directory / (label + str(index) + '.ready')
                env = dict(os.environ, UNCORKED_DB_PATH=str(database), UNCORKED_SESSION_PATH=str(directory / (label + str(index) + '-sessions')), TEST_GATE=str(gate), TEST_READY=str(ready))
                code = """
require 'app/bootstrap.php';require 'app/services/Operations.php';
db();$_SESSION['user_id']=1;session_write_close();
file_put_contents(getenv('TEST_READY'),'ready');
$deadline=microtime(true)+15;while(!is_file(getenv('TEST_GATE'))){if(microtime(true)>$deadline)exit(2);usleep(10000);}
try{ACTION;echo json_encode(['ok'=>true]);}
catch(RuntimeException $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
""".replace('ACTION', action)
                workers.append((subprocess.Popen([php, '-r', code], cwd=root, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True), ready))
            deadline = time.monotonic() + 20
            while not all(ready.exists() for _, ready in workers):
                if time.monotonic() > deadline or any(worker.poll() is not None for worker, _ in workers):
                    raise AssertionError('Workers failed to reach barrier: ' + label)
                time.sleep(.01)
            gate.touch()
            results = []
            for worker, _ in workers:
                out, error = worker.communicate(timeout=20)
                check(worker.returncode == 0 and not error, label + ' worker exits cleanly: ' + error)
                results.append(json.loads(out))
            check(sum(result['ok'] for result in results) == 1, label + ' accepts exactly one competing operation')
            return results
        finally:
            for worker, _ in workers:
                if worker.poll() is None:
                    worker.kill()
                    worker.communicate()

    results = race('booking', ["change_status(1,'Confirmed')", "change_status(2,'Confirmed')"])
    check(any('overlapping confirmed event' in result.get('error', '') for result in results), 'Competing booking rejected for actual schedule conflict')
    results = race('stock', ["sell(1,1,null,'Paid',1)"] * 2)
    check(any('Insufficient stock' in result.get('error', '') for result in results), 'Last-unit loser rejected for insufficient stock')
    results = race('payment', ["record_payment(1,10000,'Cash','RACE',1)"] * 2)
    check(any('remaining event balance' in result.get('error', '') for result in results), 'Competing payment rejected for actual balance limit')
    with sqlite3.connect(database) as conn:
        check(conn.execute("SELECT COUNT(*) FROM reservations WHERE status='Confirmed'").fetchone()[0] == 1, 'Exactly one overlapping reservation confirmed')
        check(conn.execute('SELECT SUM(quantity) FROM movements WHERE product_id=1').fetchone()[0] == 0, 'Stock stays at zero after competing sales')
        check(conn.execute('SELECT COUNT(*) FROM sales').fetchone()[0] == 1, 'Rejected sale leaves no extra sale record')
        check(conn.execute('SELECT SUM(amount) FROM payments WHERE reservation_id=1').fetchone()[0] == 10000, 'Competing payments do not exceed agreed total')
        check(conn.execute('PRAGMA integrity_check').fetchone()[0] == 'ok', 'Database integrity preserved after competing writes')
    conn.close()
    print(f'\n{count} concurrency checks passed; business database unchanged.')
