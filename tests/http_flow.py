"""Exercise the complete local HTTP workflow against a temporary database."""
from pathlib import Path
import http.cookiejar
import os
import re
import shutil
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

root = Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='uncorked-http-') as temporary:
    database = Path(temporary) / 'test.sqlite'
    env = dict(os.environ, UNCORKED_DB_PATH=str(database), UNCORKED_SESSION_PATH=str(Path(temporary) / 'sessions'))
    log = open(Path(temporary) / 'server.log', 'w+')
    server = subprocess.Popen([shutil.which('php') or r'C:\xampp\php\php.exe', '-S', '127.0.0.1:8092', '-t', str(root / 'public')], cwd=root, env=env, stdout=log, stderr=log)
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    base = 'http://127.0.0.1:8092/'
    count = 0
    def request(path, data=None):
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = opener.open(base + path, data=encoded, timeout=45)
            return response.status, response.read(), response.headers
        except urllib.error.HTTPError as error:
            return error.code, error.read(), error.headers
    def check(condition, label):
        global count
        if not condition:
            raise AssertionError(label)
        count += 1
        print('PASS:', label)
    def token(path):
        code, body, _ = request(path)
        check(code == 200, 'Page loads: ' + path)
        match = re.search(rb'name="csrf" value="([^"]+)"', body)
        if not match:
            raise AssertionError('Missing CSRF: ' + path)
        return match[1].decode()
    try:
        for attempt in range(40):
            try:
                request('setup.php')
                break
            except urllib.error.URLError:
                time.sleep(.1)
        csrf = token('setup.php')
        code, body, _ = request('setup.php', dict(csrf=csrf, name='HTTP Test Admin', email='admin@example.test', password='temporary-test-password'))
        check(code == 200 and b'Sign in' in body, 'Administrator setup redirects to login')
        code, body, _ = request('login.php', dict(csrf=csrf, email='admin@example.test', password='temporary-test-password'))
        check(code == 200 and b'Overview' in body, 'Login opens protected dashboard')
        csrf = token('admin/')
        masters = {
            'venues': dict(name='Test studio', capacity=50, description='Test only', active=1),
            'packages': dict(name='Test package', price='1500.00', inclusions='Test inclusions', active=1),
            'suppliers': dict(name='Test supplier', contact='Test person', email='supplier@example.test', phone='09123456789', active=1),
            'products': dict(name='Test beverage', sku='HTTP-TEST', category='Beverage', unit='bottle', price='150.00', minimum=3, active=1),
            'employees': dict(name='Test employee', position='Host', phone='09123456789', active=1),
        }
        for module, fields in masters.items():
            code, body, _ = request('admin/?page=' + module, dict(csrf=csrf, action='save_master', id=0, **fields))
            check(code == 200 and b'Saved successfully' in body, 'Create master record: ' + module)
        code, body, _ = request('admin/?page=purchases', dict(csrf=csrf, action='purchase', supplier_id=1, product_id=1, quantity=10, unit_cost='60.00', reference='HTTP-PURCHASE'))
        check(code == 200 and b'Saved successfully' in body, 'Purchase receipt posts stock')
        code, body, _ = request('api.php', dict(csrf='bad'))
        check(code == 403, 'Forged CSRF is rejected')
        home_csrf = token('index.php')
        fields = dict(csrf=home_csrf, name='HTTP Customer', email='customer@example.test', phone='09123456789', event_type='Birthday', date='2030-12-20', start='18:00', end='20:00', guests=20, notes='Integration test', consent=1, venue_id=1, package_id=1)
        import json
        code, body, _ = request('api.php', fields)
        data = json.loads(body)
        check(code == 200 and data['ok'], 'Online reservation persists successfully')
        reference = re.search(r'UNC-[A-F0-9]{8}', data['message'])[0]
        code, body, _ = request('track.php', dict(csrf=csrf, reference=reference, email='customer@example.test'))
        check(code == 200 and b'Pending' in body and b'1,500.00' in body, 'Customer tracks status and package total')
        code, body, _ = request('admin/?page=reservations', dict(csrf=csrf, action='status', id=1, status='Confirmed'))
        check(code == 200 and b'Saved successfully' in body, 'Staff confirms requested venue')
        code, body, _ = request('availability.php?venue=1&date=2030-12-20&start=19:00&end=21:00')
        check(code == 200 and not json.loads(body)['available'], 'Availability reflects confirmed booking')
        code, body, _ = request('admin/?page=payments', dict(csrf=csrf, action='payment', reservation_id=1, amount='500.00', method='Cash', reference='HTTP-DEPOSIT'))
        check(code == 200 and b'Saved successfully' in body, 'Verified deposit recorded')
        code, body, _ = request('admin/?page=sales', dict(csrf=csrf, action='sale', product_id=1, quantity=2, reservation_id=1, status='Paid'))
        check(code == 200 and b'Saved successfully' in body, 'Bar sale recorded')
        with sqlite3.connect(database) as conn:
            check(conn.execute('SELECT SUM(quantity) FROM movements WHERE product_id=1').fetchone()[0] == 8, 'Purchase and sale stock reconciles to eight')
        conn.close()
        code, body, _ = request('admin/?page=sales', dict(csrf=csrf, action='sale', product_id=1, quantity=1, reservation_id=1, status='Unpaid'))
        check(code == 200 and b'Saved successfully' in body, 'Unpaid bar order recorded')
        with sqlite3.connect(database) as conn:
            late_id = conn.execute('SELECT MAX(id) FROM sales').fetchone()[0]
            conn.execute("UPDATE sales SET created_at='2000-01-01T12:00:00+08:00' WHERE id=?", (late_id,))
        conn.close()
        code, body, _ = request('admin/?page=sales', dict(csrf=csrf, action='settle', id=late_id))
        check(code == 200 and b'Saved successfully' in body, 'Mark paid endpoint settles old order')
        with sqlite3.connect(database) as conn:
            status, created_at, paid_at = conn.execute('SELECT status,created_at,paid_at FROM sales WHERE id=?', (late_id,)).fetchone()
            check(status == 'Paid' and paid_at is not None and created_at.startswith('2000-01-01'), 'HTTP settlement stores collection date independently')
        conn.close()
        code, body, _ = request('admin/?page=reports&from=2000-01-01&to=2000-01-01')
        check(code == 200 and b'<p>Bar collections</p><strong>PHP 0.00</strong>' in body, 'Historical report excludes later settlement')
        for page in ['dashboard','reservations','customers','venues','packages','products','inventory','suppliers','purchases','sales','payments','employees','assignments','reports','alerts','users','audit','backup','settings']:
            code, body, _ = request('admin/?page=' + page)
            check(code == 200 and b'Fatal error' not in body and b'Warning:' not in body, 'HTTP admin module: ' + page)
        code, body, _ = request('receipt.php?id=1')
        check(code == 200 and b'1,000.00' in body, 'Printable event statement shows remaining balance')
        code, body, headers = request('export.php?from=2030-01-01&to=2030-12-31')
        check(code == 200 and reference.encode() in body and 'text/csv' in headers['Content-Type'], 'Reservation report exports CSV')
        code, body, _ = request('backup.php', dict(csrf=csrf, action='backup'))
        check(code == 200 and body.startswith(b'SQLite format 3'), 'Backup downloads a valid SQLite file')
        backup = Path(temporary) / 'backup.sqlite'
        backup.write_bytes(body)
        restored = Path(temporary) / 'restored.sqlite'
        # Never invoke the restore utility against the actual business database.
        restore_env = dict(env, UNCORKED_DB_PATH=str(restored))
        php = shutil.which('php') or r'C:\xampp\php\php.exe'
        def restore(source):
            return subprocess.run([php, str(root / 'database/restore.php'), str(source)], env=restore_env, capture_output=True, text=True, timeout=30)
        restored.write_bytes(body)
        with sqlite3.connect(restored) as conn:
            conn.execute("UPDATE settings SET value='changed' WHERE key='about'")
            conn.execute("INSERT OR REPLACE INTO settings(key,value) VALUES('restore_test','before restore')")
        conn.close()
        result = restore(backup)
        check(result.returncode == 0, 'Restore utility replaces isolated database: ' + result.stderr)
        with sqlite3.connect(backup) as original, sqlite3.connect(restored) as recovered:
            tables = original.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name").fetchall()
            check(recovered.execute('PRAGMA integrity_check').fetchone()[0] == 'ok', 'Restored database passes integrity check')
            check(all(original.execute(f'SELECT * FROM "{name}" ORDER BY rowid').fetchall() == recovered.execute(f'SELECT * FROM "{name}" ORDER BY rowid').fetchall() for (name,) in tables), 'All restored tables match downloaded snapshot')
        original.close()
        recovered.close()
        reopen = subprocess.run([php, '-r', "require 'app/bootstrap.php'; echo run('SELECT COUNT(*) FROM reservations')->fetchColumn();"], cwd=root, env=restore_env, capture_output=True, text=True, timeout=30)
        check(reopen.returncode == 0 and reopen.stdout == '1' and not reopen.stderr, 'Application reopens restored database successfully')
        copies = list(Path(temporary).glob('restored.sqlite.before-restore-*.bak'))
        check(len(copies) == 1, 'Restore retains previous database safety copy')
        with sqlite3.connect(copies[0]) as conn:
            check(conn.execute("SELECT value FROM settings WHERE key='restore_test'").fetchone()[0] == 'before restore', 'Safety copy retains pre-restore data')
        conn.close()
        before = restored.read_bytes()
        invalid = Path(temporary) / 'invalid.sqlite'
        invalid.write_bytes(b'not a SQLite database')
        check(restore(invalid).returncode != 0 and restored.read_bytes() == before, 'Invalid backup cannot overwrite target')
        with sqlite3.connect(Path(temporary) / 'incomplete.sqlite') as conn:
            conn.execute('CREATE TABLE example(id INTEGER)')
        conn.close()
        check(restore(Path(temporary) / 'incomplete.sqlite').returncode != 0 and restored.read_bytes() == before, 'Incomplete backup cannot overwrite target')
        # Exercise every module for each role through the actual controller.
        modules = ['dashboard','reservations','customers','venues','packages','products','inventory','suppliers','purchases','sales','payments','employees','assignments','reports','alerts','users','audit','backup','settings']
        staff_modules = {'dashboard','reservations','customers','inventory','sales','assignments','alerts'}
        for role in ['Manager', 'Staff']:
            with sqlite3.connect(database) as conn:
                conn.execute('UPDATE users SET role=? WHERE id=1', (role,))
            conn.close()
            permitted = set(modules) - {'users','settings','backup','audit'} if role == 'Manager' else staff_modules
            for module in modules:
                code, _, _ = request('admin/?page=' + module)
                check(code == (200 if module in permitted else 403), role + ' module permission: ' + module)
                if module not in permitted:
                    code, _, _ = request('admin/?page=' + module, dict(csrf=csrf, action='settings', about='unauthorized'))
                    check(code == 403, role + ' cannot POST to restricted module: ' + module)
            code, _, _ = request('backup.php', dict(csrf=csrf, action='backup'))
            check(code == 403, role + ' cannot download backup directly')
            code, _, _ = request('export.php?from=2030-01-01&to=2030-12-31')
            check(code == (200 if role == 'Manager' else 403), role + ' direct export permission')
        with sqlite3.connect(database) as conn:
            conn.execute("UPDATE users SET role='Administrator',active=0 WHERE id=1")
        conn.close()
        code, body, _ = request('admin/')
        check(code == 200 and b'Sign in' in body, 'Deactivated user loses access on next request')
        with sqlite3.connect(database) as conn:
            conn.execute('UPDATE users SET active=1 WHERE id=1')
        conn.close()
        code, body, _ = request('setup.php')
        check(code == 403, 'First-admin setup locks after initialization')
        code, body, _ = request('admin/', dict(csrf=csrf, action='logout'))
        check(code == 200 and b'Sign in' in body, 'Logout returns to staff sign-in')
        code, body, _ = request('backup.php', dict(csrf=csrf))
        check(code == 403, 'Signed-out users cannot download business data')
        print(f'\n{count} HTTP checks passed; business database unchanged.')
    except Exception:
        log.flush()
        log.seek(0)
        print(log.read())
        raise
    finally:
        server.terminate()
        server.wait(timeout=45)
        log.close()
