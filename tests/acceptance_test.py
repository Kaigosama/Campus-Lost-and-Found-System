"""
End-to-end acceptance checks for the professor's revisions, run against a disposable copy of the app.

    1. Start a test container on its own database (never your real one), e.g.:
         docker run -d --name clafs-test --network clafs_default -p 8081:80 \
           -v "$PWD:/var/www/html" -e DB_HOST=db -e DB_USER=root -e DB_PASS=root \
           -e DB_NAME=clafs_test -e APP_URL=http://localhost:8081 clafs-app
    2. python tests/acceptance_test.py            (resets clafs_test first; standard library only)

Talks to the site over HTTP like a browser would, and reads the database and the mail log through `docker exec`
to check what was stored. Uses Cloudflare's test CAPTCHA keys (always pass with the dummy token). Prints one line
per check and exits 1 if any failed.
"""
import html as htmllib
import http.cookiejar
import json
import re
import subprocess
import sys
import time
import urllib.parse
import urllib.request

BASE = 'http://localhost:8081'
CONTAINER = 'clafs-test'
DB = 'clafs_test'
DUMMY_TOKEN = 'XXXX.DUMMY.TOKEN.XXXX'   # accepted only by Cloudflare's always-pass test secret
PASSWORD = 'Test!Pass4word'             # set for the sample staff/admin accounts below
results = []


def check(name, ok, detail=''):
    results.append(ok)
    print(('PASS ' if ok else 'FAIL ') + name + ('' if ok else '  -> ' + str(detail)[:300]))


def sql(query):
    # The app runs MySQL sessions in Manila time (src/database.php); so does this, or NOW() would be 8 hours off.
    out = subprocess.run(['docker', 'exec', 'clafs-db-1', 'mysql', '-uroot', '-proot', '-N', '-B', DB, '-e', "SET time_zone = '+08:00'; " + query],
                         capture_output=True, text=True)
    if out.returncode:
        raise RuntimeError(out.stderr)
    return [line.split('\t') for line in out.stdout.strip().splitlines()]


def one(query):
    rows = sql(query)
    return rows[0][0] if rows else None


class Browser:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())

    def request(self, path, data=None, as_json=False):
        body, headers = None, {}
        if data is not None:
            if as_json:
                body, headers['Content-Type'] = json.dumps(data).encode(), 'application/json'
            else:
                body = urllib.parse.urlencode(data, doseq=True).encode()
        req = urllib.request.Request(BASE + path, data=body, headers=headers)
        try:
            resp = self.opener.open(req)
            return resp.status, resp.read().decode(), resp.headers
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode(), e.headers

    def get(self, path):
        status, text, headers = self.request(path)
        return status, htmllib.unescape(text), headers

    def post(self, path, data):
        status, text, headers = self.request(path, data)
        return status, htmllib.unescape(text), headers

    def api(self, endpoint, data):
        status, text, _ = self.request('/api/' + endpoint, data, as_json=True)
        try:
            return status, json.loads(text)
        except ValueError:
            return status, {'raw': text[:300]}

    def login(self, email, password=PASSWORD):
        status, _, headers = self.post('/login.php', {'email': email, 'password': password, 'cf-turnstile-response': DUMMY_TOKEN})
        return status == 302 and 'login.php' not in (headers.get('Location') or '')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def reset_database():
    subprocess.run(['docker', 'exec', 'clafs-db-1', 'mysql', '-uroot', '-proot', '-e', f'DROP DATABASE IF EXISTS {DB}'], capture_output=True)
    subprocess.run(['docker', 'exec', CONTAINER, 'sh', '-c', 'rm -f /tmp/clafs-*'])   # rate-limit and mail-throttle stamps
    out = subprocess.run(['docker', 'exec', CONTAINER, 'php', '/var/www/html/database/seed.php'], capture_output=True, text=True)
    if 'Applying migration 014' not in out.stderr:
        sys.exit('Database reset failed:\n' + out.stderr)
    hashed = subprocess.run(['docker', 'exec', CONTAINER, 'php', '-r', f'echo password_hash("{PASSWORD}", PASSWORD_DEFAULT);'],
                            capture_output=True, text=True).stdout
    sql(f"UPDATE users SET password_hash = '{hashed}'")


def last_verification_link(email):
    logs = subprocess.run(['docker', 'logs', CONTAINER], capture_output=True, text=True).stderr
    links = re.findall(r'To: ' + re.escape(email) + r'.*?(/verify_email\.php\?token=[0-9a-f]{64})', logs, re.S)
    return links[-1] if links else None


def register(browser, account_type, school_id, email, password, extra=None):
    """Step 2 of register.php (the server looks the number up again). Returns (None, status, html, headers)."""
    data = {'action': 'register', 'account_type': account_type, 'school_id': school_id, 'email': email,
            'password': password, 'password_confirm': password, 'agree': '1', 'cf-turnstile-response': DUMMY_TOKEN}
    data.update(extra or {})
    status, html, headers = browser.post('/register.php', data)
    return None, status, html, headers


def verify_account(email):
    link = last_verification_link(email)
    b = Browser()
    return b.post(link, {})[0] == 302


def main():
    reset_database()
    staff_email = one("SELECT email FROM users WHERE role = 'staff' LIMIT 1")
    admin_email = one("SELECT email FROM users WHERE role = 'admin' LIMIT 1")
    staff_id = one(f"SELECT user_id FROM users WHERE email = '{staff_email}'")

    staff, admin = Browser(), Browser()
    check('CAPTCHA + credentials: staff logs in', staff.login(staff_email))
    check('CAPTCHA + credentials: admin logs in', admin.login(admin_email))

    # ------------------------------------------------------------------ CAPTCHA and login states
    anon = Browser()
    _, html, _ = anon.post('/login.php', {'email': '', 'password': '', 'cf-turnstile-response': DUMMY_TOKEN})
    check('Missing credentials prevent authentication', 'Enter your email and password.' in html, html[:200])
    _, html, _ = anon.post('/login.php', {'email': staff_email, 'password': PASSWORD})
    check('Missing CAPTCHA token is rejected (no login)', 'security check failed' in html and not anon.jar._cookies, html[:200])
    # Cloudflare's always-pass test secret accepts any token, so a bad token is checked with its always-fail secret.
    verify = lambda secret: subprocess.run(['docker', 'exec', '-e', 'TURNSTILE_SECRET_KEY=' + secret, CONTAINER, 'php', '-r',
        'require "/var/www/html/src/bootstrap.php"; $_POST["cf-turnstile-response"] = "' + DUMMY_TOKEN + '"; echo turnstile_passed() ? "pass" : "fail";'],
        capture_output=True, text=True).stdout
    check('Server-side Turnstile verification: Cloudflare says pass', verify('1x0000000000000000000000000000000AA') == 'pass')
    check('Server-side Turnstile verification: invalid / expired / used token fails', verify('2x0000000000000000000000000000000AA') == 'fail')
    check('Server-side Turnstile verification: token already spent fails', verify('3x0000000000000000000000000000000AA') == 'fail')
    _, html, _ = anon.post('/login.php', {'email': staff_email, 'password': 'Wrong!Pass1', 'cf-turnstile-response': DUMMY_TOKEN})
    check('Passed CAPTCHA + wrong password = invalid credentials, not success', 'Incorrect email or password.' in html)
    sql(f"UPDATE users SET failed_login_attempts = 0 WHERE email = '{staff_email}'")
    _, html, _ = anon.get('/login.php')
    check('Login page CAPTCHA starts as "runs when you submit", not success', 'runs when you submit' in html and 'Success' not in html)

    # ------------------------------------------------------------------ Categories
    status, r = admin.api('categories.php', {'action': 'create', 'name': '  Sports   Equipment ', 'description': 'Balls, rackets'})
    sports = r.get('category_id')
    check('Admin creates a category (name trimmed)', status == 201 and one(f'SELECT name FROM categories WHERE category_id = {sports}') == 'Sports Equipment', r)
    status, r = admin.api('categories.php', {'action': 'create', 'name': 'sports equipment'})
    check('Duplicate category name (case-insensitive) is refused', status == 409, r)
    status, r = admin.api('categories.php', {'action': 'create', 'name': ''})
    check('Empty category name is refused', status == 422, r)
    status, r = staff.api('categories.php', {'action': 'create', 'name': 'Staff Made'})
    check('Staff cannot manage categories', status == 403, r)
    status, r = Browser().api('categories.php', {'action': 'create', 'name': 'Guest Made'})
    check('Guests cannot manage categories', status == 401, r)
    status, r = admin.api('categories.php', {'action': 'set_active', 'category_id': sports, 'is_active': 0})
    check('Admin deactivates a category', status == 200 and one(f'SELECT is_active FROM categories WHERE category_id = {sports}') == '0', r)
    _, html, _ = staff.get('/report.php?type=found')
    check('Inactive category is not offered on the new found-item form', 'Sports Equipment' not in html and 'Electronics' in html)
    found = {'type': 'found', 'item_name': 'Tennis racket', 'category_id': sports, 'date_found': time.strftime('%Y-%m-%d'),
             'location_found': 'Gymnasium', 'storage_location': 'Bin 4 (Misc)', 'description': 'Blue tennis racket with grip tape.',
             'private_details': 'Initials R.M. on the handle under the tape.'}
    status, r = staff.api('add_item.php', found)
    check('Inactive category cannot be selected for a new record (server)', status == 422 and 'category_id' in r.get('errors', {}), r)
    status, r = admin.api('categories.php', {'action': 'set_active', 'category_id': sports, 'is_active': 1})
    status, r = staff.api('add_item.php', found)
    racket = r.get('id')
    check('Reactivated category can be used again', status == 201, r)
    admin.api('categories.php', {'action': 'set_active', 'category_id': sports, 'is_active': 0})
    _, html, _ = staff.get(f'/view_item.php?type=found&id={racket}')
    check('Existing record keeps showing its (now inactive) category', 'Sports Equipment' in html)
    status, r = admin.api('categories.php', {'action': 'delete', 'category_id': sports})
    check('Category in use cannot be deleted (history intact)', status == 409 and one(f'SELECT COUNT(*) FROM categories WHERE category_id = {sports}') == '1', r)
    _, r = admin.api('categories.php', {'action': 'create', 'name': 'Temporary'})
    status, r2 = admin.api('categories.php', {'action': 'delete', 'category_id': r['category_id']})
    check('Unused category can be deleted', status == 200, r2)
    _, html, _ = staff.get(f'/report.php?type=found&id={racket}')
    check('Editing a record keeps its inactive category selectable', 'Sports Equipment (no longer offered)' in html)

    # ------------------------------------------------------------------ School registration
    b = Browser()
    _, html, _ = b.post('/register.php', {'action': 'lookup', 'account_type': 'student', 'school_id': '2023100001'})
    check('Valid student number retrieves the correct record', 'Andrea Villareal' in html and 'BS Information Technology' in html)
    _, html, _ = b.post('/register.php', {'action': 'lookup', 'account_type': 'employee', 'school_id': 'emp-10001'})
    check('Valid employee number retrieves the correct record', 'Teresa Gonzales' in html and 'School of Information Technology' in html)
    _, html, _ = b.post('/register.php', {'action': 'lookup', 'account_type': 'student', 'school_id': '2099999999'})
    check('Unknown student number cannot register', "couldn't verify this student number" in html and 'Registrar' in html)
    _, html, _ = b.post('/register.php', {'action': 'lookup', 'account_type': 'student', 'school_id': 'abc'})
    check('Badly formatted identifier is refused before lookup', "doesn't look like a student number" in html)
    _, html, _ = b.post('/register.php', {'action': 'lookup', 'account_type': 'student', 'school_id': '2020100009'})
    check('Ineligible record (graduated) cannot register', 'graduated' in html and 'Step 2' not in html)
    _, status, html, _ = register(b, 'employee', '2023100001', 'tamper@example.com', 'Good!Pass9word')
    check('A student number sent as an employee is refused (type comes from the records)', status == 200 and 'employee number' in html
          and one("SELECT COUNT(*) FROM users WHERE email = 'tamper@example.com'") == '0')
    _, status, html, _ = register(b, 'student', '2023100001', 'andrea@example.com', 'Good!Pass9word', {'role': 'admin', 'first_name': 'Hacker'})
    row = sql("SELECT u.role, u.first_name, u.last_name, u.student_number, u.email_verified, s.first_name FROM users u"
              " JOIN students s ON s.student_number = u.student_number WHERE u.email = 'andrea@example.com'")
    check('Student registers: one users row linked to the student record, name not copied, role forced to user, unverified',
          status == 302 and row and row[0] == ['user', 'NULL', 'NULL', '2023100001', '0', 'Andrea'], row)
    _, html, _ = Browser().post('/register.php', {'action': 'lookup', 'account_type': 'student', 'school_id': '2023100001'})
    check('Same student number cannot register twice', 'waiting for its email to be confirmed' in html)
    _, status, html, _ = register(Browser(), 'student', '2023100003', 'andrea@example.com', 'Good!Pass9word')
    check('Duplicate email does not create a second account', status == 302 and one("SELECT COUNT(*) FROM users WHERE email = 'andrea@example.com'") == '1'
          and one("SELECT COUNT(*) FROM users WHERE student_number = '2023100003'") == '0')
    _, status, _, _ = register(Browser(), 'employee', 'EMP-10002', 'ramon@example.com', 'Good!Pass9word')
    check('Employee registers as Student / Faculty (no staff or admin role)', status == 302 and one("SELECT role FROM users WHERE email = 'ramon@example.com'") == 'user')
    try:
        sql("INSERT INTO users (email, student_number, password_hash) VALUES ('dup@example.com', '2023100001', 'x')")
        check('Database unique key blocks a second account for one student number', False)
    except RuntimeError as e:
        check('Database unique key blocks a second account for one student number', 'Duplicate entry' in str(e), e)
    try:
        sql("INSERT INTO users (first_name, last_name, email, student_number, password_hash) VALUES ('X', 'Y', 'copy@example.com', '2023100004', 'x')")
        check('Database refuses a copied name on an account linked to a school record', False)
    except RuntimeError as e:
        check('Database refuses a copied name on an account linked to a school record', 'chk_users_name_source' in str(e), e)

    # ------------------------------------------------------------------ Password policy (server side)
    cases = [('weak', 'password', 'uppercase'), ('first name', 'Miguel!2024x', 'name or birthday'), ('last name', 'Santiago#99X', 'name or birthday'),
             ('look-alike name', 'M1gu3l!Xyz9', 'name or birthday'), ('birthday 20041025', 'Xq!20041025', 'name or birthday'),
             ('birthday 25102004', 'Xq!25102004', 'name or birthday'), ('birthday 10252004', 'Xq!10252004', 'name or birthday'),
             ('birthday 6-digit 251004 (server only)', 'Xq!251004ab', 'name or birthday')]
    for label, pw, expect in cases:
        _, status, html, _ = register(Browser(), 'student', '2023100002', 'miguel@example.com', pw)
        check(f'Password rejected: {label}', status == 200 and expect in html and one("SELECT COUNT(*) FROM users WHERE email = 'miguel@example.com'") == '0')
    _, status, _, _ = register(Browser(), 'student', '2023100002', 'miguel@example.com', 'Blue!Sky1025x')
    check('Legitimate password (4 digits, no name) is accepted', status == 302)
    hashed = one("SELECT password_hash FROM users WHERE email = 'miguel@example.com'")
    check('Password stored only as a bcrypt hash', hashed.startswith('$2y$') and 'Blue' not in hashed)
    logs = subprocess.run(['docker', 'logs', CONTAINER], capture_output=True, text=True).stderr
    check('Plaintext passwords never appear in the logs', 'Blue!Sky1025x' not in logs and 'Miguel!2024x' not in logs)

    # ------------------------------------------------------------------ Email verification
    expiry = one("SELECT TIMESTAMPDIFF(MINUTE, NOW(), email_verification_expires_at) FROM users WHERE email = 'miguel@example.com'")
    check('Verification link lifetime is 30 minutes', expiry in ('29', '30'), expiry)
    _, html, _ = Browser().post('/login.php', {'email': 'miguel@example.com', 'password': 'Blue!Sky1025x', 'cf-turnstile-response': DUMMY_TOKEN})
    check('Unverified account cannot log in', 'Confirm your email address' in html)
    first_link = last_verification_link('miguel@example.com')
    sql("UPDATE users SET email_verification_expires_at = NOW() - INTERVAL 1 MINUTE WHERE email = 'miguel@example.com'")
    status, html, _ = Browser().get(first_link)
    check('Expired link shows the expiry message', status == 410 and 'Your email verification link has expired. Request a new verification email to continue.' in html)
    status, _, _ = Browser().post(first_link, {})
    check('Expired link cannot verify the account', status == 410 and one("SELECT email_verified FROM users WHERE email = 'miguel@example.com'") == '0')
    sql("UPDATE users SET email_verification_expires_at = NOW() + INTERVAL 30 MINUTE WHERE email = 'miguel@example.com'")
    subprocess.run(['docker', 'exec', CONTAINER, 'sh', '-c', 'rm -f /tmp/clafs-verify-*'])
    Browser().post('/resend_verification.php', {'email': 'miguel@example.com'})
    new_link = last_verification_link('miguel@example.com')
    check('Resending sends a new link', new_link and new_link != first_link)
    status, _, _ = Browser().get(first_link)
    check('Previous link is invalidated by the resend', status == 400)
    check('New link verifies the account', Browser().post(new_link, {})[0] == 302 and one("SELECT email_verified FROM users WHERE email = 'miguel@example.com'") == '1')
    status, _, _ = Browser().get(new_link)
    check('Used link cannot be reused', status == 400)
    check('Verified Andrea and Ramon too', verify_account('andrea@example.com') and verify_account('ramon@example.com'))

    andrea, miguel, ramon = Browser(), Browser(), Browser()
    check('Verified accounts log in', andrea.login('andrea@example.com', 'Good!Pass9word') and miguel.login('miguel@example.com', 'Blue!Sky1025x')
          and ramon.login('ramon@example.com', 'Good!Pass9word'))
    _, html, _ = miguel.post('/?tab=account', {'current_password': 'Blue!Sky1025x', 'new_password': 'Miguel!2025x', 'new_password_confirm': 'Miguel!2025x'})
    check('Changing password to one with your name is refused', 'name or birthday' in html)

    # ------------------------------------------------------------------ Claims: rejection history, grouping, verification, return
    item = 1   # Black JBL earbuds case, in storage
    proof = 'It is my black case with a deep scratch on the lid and a chipped right earbud stem.'
    ids = {}
    for name, b in (('andrea', andrea), ('miguel', miguel), ('ramon', ramon)):
        status, r = b.api('claims.php', {'action': 'create', 'item_id': item, 'proof_description': proof, 'confirm_truth': 1})
        ids[name] = r.get('claim_id')
    check('Three different claimants claim the same item', all(ids.values()), ids)
    status, r = admin.api('claims.php', {'action': 'review', 'claim_id': ids['andrea'], 'decision': 'reject', 'review_note': 'Not enough detail.'})
    check('Admin (monitor only) cannot review claims', status == 403, r)
    for reviewer_note in ('Insufficient ownership evidence.', 'Additional information required.'):
        status, r = staff.api('claims.php', {'action': 'review', 'claim_id': ids['andrea'], 'decision': 'reject', 'review_note': reviewer_note})
    check('Rejected claim keeps the Pending main status', status == 200 and one(f"SELECT status FROM claims WHERE claim_id = {ids['andrea']}") == 'pending', r)
    history = sql(f"SELECT outcome, reviewer_id, reason, previous_outcome FROM claim_reviews WHERE claim_id = {ids['andrea']} ORDER BY review_id")
    check('Each rejection is a separate history row (not overwritten)', len(history) == 2 and history[0][2] == 'Insufficient ownership evidence.'
          and history[1][3] == 'rejected' and history[0][1] == staff_id, history)
    staff.api('claims.php', {'action': 'review', 'claim_id': ids['miguel'], 'decision': 'reject', 'review_note': 'Description does not match.'})
    _, html, _ = staff.get('/?tab=queue&status=rejected')
    check('Rejected listing shows the item once for multiple rejected claims', html.count('Black JBL earbuds case') == 1 and '3 events' in html, html.count('Black JBL earbuds case'))
    check('Grouped row keeps distinct claims and claimants', f"#{ids['andrea']} Andrea Villareal" in html and f"#{ids['miguel']} Miguel Santiago" in html)
    _, html, _ = staff.get(f'/view_item.php?type=found&id={item}')
    check('Item page shows each rejection with reason and reviewer', 'Insufficient ownership evidence.' in html and 'Additional information required.' in html)
    status, r = andrea.api('claims.php', {'action': 'withdraw', 'claim_id': ids['andrea']})
    check('A reviewed claim cannot be withdrawn (history kept)', status == 409, r)

    status, r = staff.api('update_status.php', {'type': 'found', 'id': item, 'status': 'disposed', 'disposal_reason': 'Unclaimed for a long time.', 'confirm_disposal': 1})
    check('Item with a claim awaiting review cannot be disposed of', status == 409 and 'awaiting review' in r.get('error', ''), r)

    staff.api('claims.php', {'action': 'review', 'claim_id': ids['ramon'], 'decision': 'approve', 'review_note': 'Details match. Bring your ID.'})
    check('Approved claim is "approved" (for collection)', one(f"SELECT status FROM claims WHERE claim_id = {ids['ramon']}") == 'approved')
    status, r = staff.api('claims.php', {'action': 'review', 'claim_id': ids['miguel'], 'decision': 'approve', 'review_note': 'Second approval attempt.'})
    check('A second claim on the same item cannot also be approved', status == 409, r)
    status, r = staff.api('claims.php', {'action': 'return', 'claim_id': ids['ramon'], 'confirm_handover': 1})
    check('Item cannot be returned before the ID is verified', status == 409, r)
    status, r = staff.api('claims.php', {'action': 'verify', 'claim_id': ids['ramon'], 'id_type': 'school_id', 'id_last4': '10002', 'id_result': 'verified'})
    check('ID check needs exactly the last 4 characters', status == 422 and 'id_last4' in r.get('errors', {}), r)
    status, r = staff.api('claims.php', {'action': 'verify', 'claim_id': ids['ramon'], 'id_type': 'school_id', 'id_last4': '0002', 'id_result': 'failed',
                                         'id_notes': 'Photo did not match the claimant.'})
    check('Failed ID check is recorded and the claim stays approved', status == 200 and one(f"SELECT status FROM claims WHERE claim_id = {ids['ramon']}") == 'approved', r)
    status, r = staff.api('claims.php', {'action': 'verify', 'claim_id': ids['ramon'], 'id_type': 'school_id', 'id_last4': '0002', 'id_result': 'verified',
                                         'verified_by': 1, 'verified_at': '2001-01-01 00:00:00'})
    v = sql(f"SELECT id_type, id_reference, verified_by, TIMESTAMPDIFF(SECOND, verified_at, NOW()) FROM claim_verifications WHERE claim_id = {ids['ramon']} AND result = 'verified'")
    check('ID verified: type, masked number, validator = signed-in staff, server time', status == 200 and v and v[0][0] == 'school_id'
          and v[0][1] == '••••0002' and v[0][2] == staff_id and int(v[0][3]) < 60, v)
    events = one("SELECT GROUP_CONCAT(CAST(metadata AS CHAR)) FROM security_events WHERE event_type LIKE 'claim_identity%'")
    check('ID numbers are not written to the activity log', events and '0002' not in events, events)
    _, html, _ = andrea.get(f'/view_item.php?type=found&id={item}')
    check('Claimants never see ID numbers', '0002' not in html)

    status, r = staff.api('claims.php', {'action': 'return', 'claim_id': ids['ramon'], 'confirm_handover': 1})
    row = sql(f"SELECT f.status, f.returned_by, f.return_claim_id, c.status FROM found_items f JOIN claims c ON c.claim_id = f.return_claim_id WHERE f.item_id = {item}")
    check('Return updates item and claim together (who, when, which claim)', status == 200 and row == [['returned', staff_id, str(ids['ramon']), 'returned']], (r, row))
    check('Other open claims get a rejection and stay pending', one(f"SELECT status FROM claims WHERE claim_id = {ids['miguel']}") == 'pending'
          and one(f"SELECT reason FROM claim_reviews WHERE claim_id = {ids['miguel']} ORDER BY review_id DESC LIMIT 1") is not None)
    _, dash, _ = staff.get('/')
    _, returned, _ = staff.get('/?tab=returned')
    check('Completed return appears on the dashboard and in Returned Items', 'Successful returns' in dash and 'Black JBL earbuds case' in dash and 'Black JBL earbuds case' in returned)
    _, admin_dash, _ = admin.get('/')
    check('Admin dashboard shows successful returns too', 'Successful returns' in admin_dash and 'Black JBL earbuds case' in admin_dash)
    status, r = staff.api('update_item.php', dict(found, id=item, category_id=1))
    check('Returned item cannot be edited through the API', status == 409, r)
    status, html, _ = staff.get(f'/report.php?type=found&id={item}')
    check('Returned item edit page is refused', status == 409)
    _, html, _ = staff.get(f'/view_item.php?type=found&id={item}')
    check('Returned item page has no Edit button but keeps its history', 'report.php?type=found&amp;id=1' not in html and 'Item history' in html)
    status, r = staff.api('update_status.php', {'type': 'found', 'id': item, 'status': 'returned'})
    check('Items cannot be set to returned outside the hand-over', status == 422, r)

    # ------------------------------------------------------------------ Disposal
    status, r = andrea.api('update_status.php', {'type': 'found', 'id': 5, 'status': 'disposed', 'disposal_reason': 'I want it gone.', 'confirm_disposal': 1})
    check('Students cannot dispose of items', status == 403, r)
    status, r = admin.api('update_status.php', {'type': 'found', 'id': 5, 'status': 'disposed', 'disposal_reason': 'Admin attempt here.', 'confirm_disposal': 1})
    check('Admins (monitor only) cannot dispose of items', status == 403, r)
    status, r = staff.api('update_status.php', {'type': 'found', 'id': racket, 'status': 'disposed', 'disposal_reason': 'Logged today, too new.', 'confirm_disposal': 1})
    check('Item inside the holding period cannot be disposed of', status == 409 and 'held for at least' in r.get('error', ''), r)
    status, r = staff.api('update_status.php', {'type': 'found', 'id': 5, 'status': 'disposed', 'disposal_reason': 'Unclaimed after the holding period.'})
    check('Disposal needs explicit confirmation', status == 422 and 'confirm_disposal' in r.get('errors', {}), r)
    status, r = staff.api('update_status.php', {'type': 'found', 'id': 5, 'status': 'disposed', 'disposal_reason': 'Unclaimed after the holding period.',
                                                'disposal_notes': 'Donated.', 'confirm_disposal': 1})
    row = sql("SELECT status, disposed_by, disposal_reason, disposed_at IS NOT NULL FROM found_items WHERE item_id = 5")
    check('Eligible item is disposed of with who, when and why', status == 200 and row == [['disposed', staff_id, 'Unclaimed after the holding period.', '1']], (r, row))
    _, html, _ = admin.get('/?tab=disposed')
    check('Disposed item appears in Disposed Items (admin can view)', 'Silver keychain with 3 keys' in html and 'Unclaimed after the holding period.' in html)
    _, html, _ = admin.get('/?tab=disposed&q=keychain&from=' + time.strftime('%Y-%m-%d'))
    check('Disposed Items can be searched and filtered by date', 'Silver keychain with 3 keys' in html)
    status, r = andrea.api('claims.php', {'action': 'create', 'item_id': 5, 'proof_description': proof, 'confirm_truth': 1})
    check('Disposed items cannot be claimed', status == 404, r)
    _, html, _ = Browser().get('/browse.php')
    check('Disposed items are not in the public list', 'Silver keychain' not in html)
    status, r = staff.api('update_item.php', dict(found, id=5, category_id=6))
    check('Disposed item cannot be edited', status == 409, r)

    # ------------------------------------------------------------------ Admin monitoring (read-only)
    for path, text in (('/?tab=items', 'Found items'), ('/?tab=queue&status=all', 'Claims'), ('/?tab=reports', 'Lost reports'), ('/?tab=returned', 'Returned items'),
                       (f'/view_item.php?type=found&id={item}', 'Intake record'), ('/?tab=stats', 'Claim outcomes')):
        status, html, _ = admin.get(path)
        check(f'Admin can monitor {path}', status == 200 and text in html, status)
    _, html, _ = admin.get(f'/view_item.php?type=found&id={item}')
    check('Admin sees ID checks and item history', 'Identity checks at pickup' in html and 'Item history' in html)
    _, html, _ = admin.get('/view_item.php?type=found&id=2')
    check('Admin gets no review / dispose forms', 'Record ID check' not in html and 'Dispose of item' not in html and 'data-api="review"' not in html)
    status, _, _ = andrea.get('/?tab=queue')
    check('Students cannot open the Claims monitoring tab', status == 403)
    status, _, _ = andrea.get('/?tab=items')
    check('Students cannot open the Found Items management section', status == 403)

    # ------------------------------------------------------------------ Dashboard navigation
    for who, b in (('Admin', admin), ('Staff', staff)):
        _, html, _ = b.get('/')
        check(f'{who}: Found Items is in the dashboard section bar, not the site header',
              'href="/?tab=items">Found Items</a>' in html and 'browse.php?manage=1' not in html and '<summary>' not in html)
    for path, target in (('/browse.php?manage=1&category=Electronics', '/?tab=items&category=Electronics'),
                         ('/browse.php?manage=1&status=disposed', '/?tab=disposed'),
                         ('/browse.php?type=lost&q=earbuds', '/?tab=reports&q=earbuds'),
                         ('/browse.php', '/?tab=items'),
                         ('/?tab=queue&status=returned', '/?tab=returned')):
        status, _, headers = staff.get(path)
        check(f'Legacy {path} redirects to {target}', status == 302 and headers.get('Location', '').endswith(target), (status, headers.get('Location')))
    _, html, _ = staff.get('/?tab=items')
    check('Found Items lists only items in storage (no disposed or returned)', 'Silver keychain' not in html and 'Black JBL earbuds case' not in html)
    status, r = andrea.api('add_item.php', {'type': 'lost', 'item_name': 'Brown leather wallet', 'category_id': 1, 'date_lost': time.strftime('%Y-%m-%d'),
                                            'location_lost': 'Library', 'description': 'Brown leather wallet with a student ID inside.'})
    report = r.get('id')
    staff.get('/?tab=reports&status=open&q=wallet')
    _, html, _ = staff.get(f'/view_item.php?type=lost&id={report}')
    check('Lost report: Back returns to Lost Reports with its filters', 'href="/?tab=reports&status=open&q=wallet"' in html.replace('&amp;', '&')
          and 'aria-current="true">Lost Reports' in html and f'<span>Report #{report}</span>' in html, re.findall(r'class="btn btn-ghost btn-sm btn-back"[^>]*>', html))
    _, html, _ = staff.get(f'/view_item.php?type=found&id={item}&from=queue')
    check('Item opened from Claims: Back returns to Claims', 'aria-current="true">Claims' in html and 'Claims</a></span>' in html)
    _, html, _ = staff.get('/view_item.php?type=found&id=5')
    check('Disposed item: Back to Disposed Items, disposal details, no claim-review prompt',
          'aria-current="true">Disposed Items' in html and 'Disposal details' in html and 'Staff view' not in html
          and 'Review claims' not in html and 'Dispose of this item' not in html and 'data-api="review"' not in html)

    # ------------------------------------------------------------------ Unified users table (migration 015)
    roles = dict(sql("SELECT role, COUNT(*) FROM users GROUP BY role"))
    check('Every login is a users row: seeded staff and admins next to registered students and faculty',
          roles.get('staff') == '1' and roles.get('admin') == '2' and roles.get('master_admin') == '1' and roles.get('user') == '3', roles)
    check('Student / Faculty accounts link to exactly one school record',
          one("SELECT COUNT(*) FROM users WHERE role = 'user' AND (student_number IS NULL) = (employee_number IS NULL)") == '0'
          and one("SELECT COUNT(*) FROM users u JOIN employees e ON e.employee_number = u.employee_number WHERE u.email = 'ramon@example.com'") == '1')
    _, html, _ = andrea.get('/')
    check('Display name comes from the linked student record', 'Hello, Andrea' in html)
    status, html, _ = staff.get(f"/view_item.php?type=found&id={item}")
    check('Staff see claimants by their school-record name', 'Andrea Villareal' in html)
    sql("INSERT INTO users (first_name, last_name, email, password_hash, role, email_verified) VALUES ('Legacy', 'Signup', 'legacy@example.com', 'x', 'user', 1)")
    _, users_html, _ = admin.get('/?tab=users')
    _, dash_html, _ = admin.get('/')
    check('An account with no school record is flagged for review, not deleted',
          'No school record' in users_html and 'Not linked to a school record' in dash_html)
    sql("DELETE FROM users WHERE email = 'legacy@example.com'")
    gone = one("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
               " AND table_name IN ('visitors', 'student_records', 'employee_records')")
    check('Visitor records removed; school records renamed students / employees', gone == '0', gone)
    check('No visitor column left on the security log',
          one("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND column_name = 'visitor_id'") == '0')
    _, html, _ = Browser().get('/')
    check('Cookie banner no longer mentions a visitor ID', 'visitor ID' not in html and 'Cookies on' in html)
    status, _, _ = admin.get('/?tab=visitors')
    check('Visitors admin page is gone', status == 404)

    # ------------------------------------------------------------------ Data integrity
    orphans = one("""SELECT (SELECT COUNT(*) FROM lost_reports r LEFT JOIN categories c ON c.category_id = r.category_id WHERE c.category_id IS NULL)
                          + (SELECT COUNT(*) FROM found_items f LEFT JOIN categories c ON c.category_id = f.category_id WHERE c.category_id IS NULL)
                          + (SELECT COUNT(*) FROM claim_reviews r LEFT JOIN claims c ON c.claim_id = r.claim_id WHERE c.claim_id IS NULL)
                          + (SELECT COUNT(*) FROM lost_reports r LEFT JOIN users u ON u.user_id = r.reported_by WHERE u.user_id IS NULL)
                          + (SELECT COUNT(*) FROM claims c LEFT JOIN users u ON u.user_id = c.claimant_id WHERE u.user_id IS NULL)
                          + (SELECT COUNT(*) FROM found_items f LEFT JOIN users u ON u.user_id = f.added_by WHERE u.user_id IS NULL)
                          + (SELECT COUNT(*) FROM user_sessions s LEFT JOIN users u ON u.user_id = s.user_id WHERE u.user_id IS NULL)
                          + (SELECT COUNT(*) FROM users u LEFT JOIN students s ON s.student_number = u.student_number
                                 WHERE u.student_number IS NOT NULL AND s.student_number IS NULL)""")
    check('No orphaned foreign keys (reports, claims, posts, sessions, school links, reviews)', orphans == '0', orphans)
    check('All 15 migrations recorded', one('SELECT COUNT(*) FROM schema_migrations') == '15')

    failed = results.count(False)
    print(f'\n{len(results) - failed} of {len(results)} checks passed' + (f', {failed} failed' if failed else ''))
    sys.exit(1 if failed else 0)


if __name__ == '__main__':
    main()
