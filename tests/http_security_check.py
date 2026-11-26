#!/usr/bin/env python3
"""Bella Vista - HTTP-level security and flow check (optional, needs Python 3, no extra packages).
   Run against a running copy of the site, e.g.:   python3 tests/http_security_check.py http://localhost/Bella-Vista-Oasis-Spa
   It registers throw-away customers (bkhttp.*@gmail.com) and books/pays in the test database. Do not run it against real data.
   This checks behaviour of the real Apache + .htaccess + API; it is not a penetration test."""
import sys, json, http.client, urllib.parse, re, time, random
base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
u = urllib.parse.urlparse(base); HOST, PORT, ROOT = u.hostname, u.port or 80, u.path
ok = bad = 0
def check(label, cond, extra=""):
    global ok, bad
    ok, bad = (ok + 1, bad) if cond else (ok, bad + 1)
    print(("  [PASS] " if cond else "  [FAIL] ") + label + ("" if cond else "   " + str(extra)))
class Client:
    def __init__(self): self.cookie = ""
    def req(self, method, path, body=None, headers=None, raw=False, api=True):
        h = {"Accept": "application/json"}
        if api and method not in ("GET", "HEAD"):
            h.update({"Content-Type": "application/json", "X-Requested-With": "bella-vista"})
        if self.cookie: h["Cookie"] = self.cookie
        h.update(headers or {})
        c = http.client.HTTPConnection(HOST, PORT, timeout=20)
        data = body if raw else (json.dumps(body) if body is not None else None)
        c.request(method, ROOT + path, data, h); r = c.getresponse(); txt = r.read().decode("utf8", "replace")
        for k, v in r.getheaders():
            if k.lower() == "set-cookie": self.cookie = v.split(";")[0]; self.last_set_cookie = v
        try: j = json.loads(txt)
        except Exception: j = None
        return r.status, j, txt, dict((k.lower(), v) for k, v in r.getheaders())
anon = Client()
print("1. Response headers and blocked paths (real Apache + .htaccess)")
s, _, _, h = anon.req("GET", "/index.html", api=False)
csp = h.get("content-security-policy", "")
check("CSP present, scripts limited to 'self' (no unsafe-inline, no stale hash)", "script-src 'self';" in csp and "unsafe-inline" not in csp.split("style-src")[0] and "sha256" not in csp, csp[:120])
check("X-Frame-Options DENY and nosniff", h.get("x-frame-options") == "DENY" and h.get("x-content-type-options") == "nosniff")
check("HTML is not cached (no-store)", "no-store" in h.get("cache-control", ""))
s, _, txt, _ = anon.req("GET", "/index.html", api=False)
check("index.html loads js/boot.js and has no inline script", "js/boot.js" in txt and not re.search(r"<script(?![^>]*\ssrc=)[^>]*>\s*\S", txt))
s, _, txt, _ = anon.req("GET", "/js/boot.js", api=False); check("js/boot.js is served", s == 200 and "classList.add" in txt)
for p in ["/php/config.php", "/php/lib.php", "/data/mail.log", "/database/schema.sql", "/database/seed.sql", "/docs/README-XAMPP.md", "/tools/migrate.php", "/tests/payment_suite.php", "/.htaccess", "/database/migrations/001_initial_schema.sql", "/data/ratelimit/x.json"]:
    s = anon.req("GET", p, api=False)[0]; check(f"blocked: {p} -> 403/404", s in (403, 404), s)
s, _, _, h = anon.req("GET", "/login.php", api=False); check("old login.php redirects to login.html (301)", s == 301 and h.get("location", "").endswith("login.html"), (s, h.get("location")))
s = anon.req("POST", "/api/auth/reset-direct", {"email": "a@gmail.com", "password": "Abcdef1!x"})[0]; check("no reset-direct route (404)", s == 404, s)

print("2. CSRF and request validation")
s = anon.req("POST", "/api/auth/login", {"email": "a@gmail.com", "password": "x"}, {"Origin": "http://evil.example"})[0]; check("cross-origin POST is blocked (403)", s == 403, s)
s = anon.req("POST", "/api/auth/login", '{"email":"a@gmail.com","password":"x"}', {"Content-Type": "text/plain", "X-Requested-With": "bella-vista"}, raw=True)[0]; check("non-JSON content type is blocked (403)", s == 403, s)
s = anon.req("POST", "/api/auth/login", {"email": "a@gmail.com", "password": "x"}, {"X-Requested-With": ""})[0]; check("missing X-Requested-With is blocked (403)", s == 403, s)
s, j, txt, _ = anon.req("POST", "/api/auth/login", "{broken json", raw=True); check("malformed JSON -> 400 with a clean message, no stack trace", s == 400 and "Stack trace" not in txt and "PDOException" not in txt, (s, txt[:80]))
s, j, txt, _ = anon.req("GET", "/api/does/not/exist"); check("unknown API route -> 404 JSON, no path/SQL disclosure", s == 404 and "/home/" not in txt and "SQL" not in txt, (s, txt[:80]))

print("3. Registration, login, session cookie")
tag = "%d%d" % (int(time.time()), random.randint(10, 99))
A, B = Client(), Client()
def register(c, name):
    return c.req("POST", "/api/auth/register", {"fullName": "BKHTTP " + name, "email": f"bkhttp.{name}{tag}@gmail.com", "password": "Sp@Test-1234", "confirmPassword": "Sp@Test-1234"})
s, j, _, _ = register(A, "a"); check("customer A registers (201)", s == 201, (s, j)); 
sc = getattr(A, "last_set_cookie", ""); check("session cookie is HttpOnly and SameSite", "httponly" in sc.lower() and "samesite" in sc.lower(), sc)
s, j, _, _ = register(B, "b"); check("customer B registers (201)", s == 201, (s, j))
s, j, _, _ = Client().req("POST", "/api/auth/register", {"fullName": "x", "email": f"bkhttp.a{tag}@gmail.com", "password": "Sp@Test-1234", "confirmPassword": "Sp@Test-1234"}); check("duplicate email refused (409)", s == 409, s)
s, j, _, _ = Client().req("POST", "/api/auth/register", {"fullName": "x", "email": f"bkhttp.w{tag}@gmail.com", "password": "short", "confirmPassword": "short"}); check("weak password refused (400)", s == 400, s)
s, j, _, _ = Client().req("POST", "/api/auth/register", {"fullName": "x", "email": f"bkhttp.r{tag}@gmail.com", "password": "Sp@Test-1234", "confirmPassword": "Sp@Test-1234", "role": "ADMIN"}); check("registering with role=ADMIN does not create an admin", s == 201 and not (j.get("user") or {}).get("isAdmin"), j)
for payload in ["' OR '1'='1' -- ", "admin@gmail.com' OR 1=1 #", "\" OR \"\"=\""]:
    s, j, txt, _ = Client().req("POST", "/api/auth/login", {"email": payload, "password": payload}); check(f"SQL-injection text in login -> clean 400/401 ({payload[:14]}...)", s in (400, 401) and "SQL" not in txt, (s, txt[:80]))
s, j, _, _ = Client().req("POST", "/api/auth/login", {"email": f"bkhttp.a{tag}@gmail.com", "password": "wrong-Pass-1"}); check("wrong password -> 401", s == 401, s)
s, j, _, _ = Client().req("POST", "/api/auth/forgot", {"email": "nobody.exists.zz@gmail.com"}); s2, j2, _, _ = Client().req("POST", "/api/auth/forgot", {"email": f"bkhttp.a{tag}@gmail.com"})
check("forgot-password answers the same for known and unknown emails (no enumeration)", s == s2 and (j or {}).get("ok") == (j2 or {}).get("ok"), (s, j, s2, j2))

print("4. Authorization")
s = anon.req("GET", "/api/admin/data")[0]; check("anonymous -> admin data refused (401/403)", s in (401, 403), s)
s = A.req("GET", "/api/admin/data")[0]; check("customer -> admin data refused (403)", s == 403, s)
s = A.req("POST", "/api/admin/rooms", {"room_code": "HACK-1"})[0]; check("customer -> admin write refused (403)", s == 403, s)
s = anon.req("GET", "/api/portal")[0]; check("anonymous -> customer portal refused (401)", s == 401, s)
s = anon.req("POST", "/api/bookings", {"service": "Body Massage"})[0]; check("anonymous cannot book (401)", s == 401, s)

print("5. Booking, payment and ownership over HTTP")
d = time.strftime("%Y-%m-%d", time.localtime(time.time() + 86400 * (20 + random.randint(0, 40))))
bk = {"service": "Body Massage", "duration": "60", "guests": 1, "date": d, "time": "14:00", "phone": "09171234567", "agreeTerms": True, "agreeHealth": True, "selectedRoomCode": "auto", "message": ""}
s, j, _, _ = A.req("POST", "/api/bookings", dict(bk, duration="70")); check("70-minute duration refused (400)", s == 400, (s, j))
s, j, _, _ = A.req("POST", "/api/bookings", dict(bk, selectedRoomCode="NOPE-9")); check("unknown room refused, not substituted (400)", s == 400, (s, j))
s, j, _, _ = A.req("POST", "/api/bookings", dict(bk, selectedRoomCode="CS-1")); check("1 guest into a couples suite refused (400)", s == 400, (s, j))
s, j, _, _ = A.req("POST", "/api/bookings", dict(bk, service="x'; DROP TABLE bookings; --")); check("SQL text as service name -> 400, database untouched", s == 400, (s, j))
s, j, _, _ = A.req("POST", "/api/bookings", bk); check("valid booking created (201) with a room and the server price", s == 201 and j["booking"]["amount"] == 800 and j["booking"]["roomId"], (s, j))
ref = (j or {}).get("booking", {}).get("id", "BK-0-0")
s, j, _, _ = A.req("GET", f"/api/portal/bookings/{ref}/payment"); check("owner reads payment summary: downpayment 500 (minimum applies)", s == 200 and j["payment"]["downpaymentRequired"] == 500, (s, j))
s, j, _, _ = B.req("GET", f"/api/portal/bookings/{ref}/payment"); check("other customer cannot read it (403/404)", s in (403, 404), s)
s, j, _, _ = B.req("POST", f"/api/portal/bookings/{ref}/pay", {"paymentMethod": "GCASH_DEMO", "paymentType": "DOWNPAYMENT"}); check("other customer cannot pay it (403/404)", s in (403, 404), s)
s, j, _, _ = B.req("POST", f"/api/portal/bookings/{ref}/cancel", {"reason": "x"}); check("other customer cannot cancel it (403/404)", s in (403, 404), s)
s, j, _, _ = A.req("POST", f"/api/portal/bookings/{ref}/pay", {"paymentMethod": "GCASH_DEMO", "paymentType": "DOWNPAYMENT", "amount": 1, "userId": 1}); check("owner pays; amount=1 and userId=1 in the request are ignored", s == 200 and j["transaction"]["amount"] == 500, (s, str(j)[:200]))
s, j, _, _ = A.req("POST", f"/api/portal/bookings/{ref}/pay", {"paymentMethod": "GCASH_DEMO", "paymentType": "DOWNPAYMENT"}); check("paying the downpayment twice is refused (400)", s == 400, (s, j))
s, j, _, _ = A.req("POST", f"/api/portal/bookings/{ref}/pay", {"paymentMethod": "GCASH_DEMO", "paymentType": "FULL_PAYMENT"}); check("FULL_PAYMENT after the downpayment pays only the balance or is refused, never over-charges", s in (200, 400) and (s != 200 or j["transaction"]["amount"] <= 300), (s, str(j)[:200]))
tid = ((A.req("GET", "/api/portal")[1] or {}).get("transactions") or [{}])[0].get("id", "TXN-0")
s, j, _, _ = B.req("GET", f"/api/portal/transactions/{tid}/receipt"); check("other customer cannot read the receipt (403/404)", s in (403, 404), s)
s, j, _, _ = A.req("POST", f"/api/portal/bookings/{ref}/cancel", {"reason": "plans changed"}); check("owner cancels (>24h notice): no forfeiture", s == 200, (s, str(j)[:160]))
s, j, _, _ = A.req("GET", f"/api/portal/bookings/{ref}/payment"); check("...payment status after cancel is not FORFEITED", s == 200 and j["payment"]["paymentStatus"] != "FORFEITED", j)
print("\n%d passed, %d failed" % (ok, bad)); sys.exit(1 if bad else 0)
