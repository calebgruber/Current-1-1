<?php
declare(strict_types=1);
use App\Core\{App, Request, Session, Migrator, Database};
require __DIR__ . '/../../src/bootstrap.php';
set_error_handler(function ($no, $str, $file, $line) { throw new ErrorException($str, 0, $no, $file, $line); });
$dbFile = __DIR__ . '/smoke.sqlite'; if (is_file($dbFile)) unlink($dbFile);
$config = ['db' => ['driver' => 'sqlite', 'path' => $dbFile], 'debug' => true];
$store = [];
$session = new Session($store);
$app = new App($config, $session, '');
$db = $app->db();
(new Migrator($db))->migrate();
$adminId = $db->insert('users', ['name' => 'Admin', 'email' => 'admin@x.test', 'password_hash' => password_hash('secret123', PASSWORD_DEFAULT), 'role' => 'admin', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
$session->put('user_id', $adminId);
$fail = 0;
function req($m, $uri, $data = [], $expect = [200, 302]) {
    global $app, $session, $fail;
    if ($m === 'POST') $data['_token'] = $session->csrfToken();
    $app->auth()->refresh();
    $r = $app->handle(Request::create($m, $uri, $data));
    $ref = new ReflectionObject($r); $st = null; $body = '';
    foreach ($ref->getProperties() as $p) { $p->setAccessible(true); $v = $p->getValue($r); if ($p->getName() === 'status') $st = $v; if (is_string($v) && strlen($v) > 50) $body = $v; }
    $ok = in_array($st, (array) $expect, true);
    if (! $ok) { $fail++; }
    $flash = $session->get('_flash_next') ?? [];
    echo ($ok ? 'OK  ' : 'FAIL') . " $m $uri -> $st " . json_encode(array_intersect_key($session->get('_flash_next', []) ?: [], ['success'=>1,'error'=>1])) . "\n";
    if (! $ok || preg_match('/Error 500|Something went wrong| in \/.*:\d+/', $body)) { echo substr(strip_tags($body), 0, 600), "\n"; }
    return $body;
}
req('GET', '/contacts');
req('GET', '/contacts/create');
req('POST', '/contacts', ['name' => 'Jo Director', 'email' => 'JO@x.test', 'organization' => 'Drama Soc', 'role' => 'Director']);
$cid = (int) $db->value('SELECT MAX(id) FROM contacts');
echo "contact email: " . $db->value('SELECT email FROM contacts WHERE id=?', [$cid]) . "\n";
req('GET', '/contacts/' . $cid);
req('GET', '/contacts?q=drama');
req('POST', '/contacts/' . $cid, ['name' => 'Jo D', 'email' => 'bad']);
req('POST', '/contacts/' . $cid, ['name' => 'Jo D', 'email' => 'jo@x.test', 'phone' => '555']);
req('GET', '/contacts/9999', [], 404);
req('GET', '/admin/users');
req('POST', '/admin/users', ['name' => 'Stu', 'email' => 'stu@x.test', 'role' => 'student', 'password' => 'password1']);
req('POST', '/admin/users', ['name' => 'Dup', 'email' => 'STU@x.test', 'role' => 'student', 'password' => 'password1']);
echo "users: " . $db->value('SELECT COUNT(*) FROM users') . "\n";
req('POST', '/admin/users', ['name' => 'Short', 'email' => 'short@x.test', 'role' => 'staff', 'password' => '1234']);
echo "users after short pw: " . $db->value('SELECT COUNT(*) FROM users') . "\n";
$stuId = (int) $db->value("SELECT id FROM users WHERE email='stu@x.test'");
req('POST', "/admin/users/$stuId", ['name' => 'Stu 2', 'email' => 'stu@x.test', 'role' => 'staff', 'active' => '1', 'password' => '']);
req('POST', "/admin/users/$adminId", ['name' => 'Admin', 'email' => 'admin@x.test', 'role' => 'staff', 'active' => '1']);
req('POST', "/admin/users/$adminId", ['name' => 'Admin', 'email' => 'admin@x.test', 'role' => 'admin', 'active' => '0']);
echo "admin role still: " . $db->value('SELECT role FROM users WHERE id=?', [$adminId]) . "\n";
// last-admin guard: make 2nd admin, deactivate admin1 via admin2? simulate: promote stu to admin then demote stu ok, then try demote admin from admin2 session
req('POST', "/admin/users/$stuId", ['name' => 'Stu 2', 'email' => 'stu@x.test', 'role' => 'admin', 'active' => '1']);
$session->put('user_id', $stuId);
req('POST', "/admin/users/$adminId", ['name' => 'Admin', 'email' => 'admin@x.test', 'role' => 'admin', 'active' => '0']);
echo "admin1 active (expect 0): " . $db->value('SELECT active FROM users WHERE id=?', [$adminId]) . "\n";
$session->put('user_id', $stuId);
// now stu is the only active admin; direct test of last-admin guard on a hypothetical: reactivate admin1 as inactive admin then try deactivating stu from... self guard catches. Test last-admin guard: deactivate admin1 role->staff fine (inactive).
$db->update('users', $adminId, ['active' => 1]);
$db->update('users', $stuId, ['active' => 0]);
$session->put('user_id', $adminId);
// make stu active non-admin, admin1 sole admin; now change stu's data fine; guard via self is covered.
$session->put('user_id', $adminId);
req('POST', "/admin/users/$stuId", ['name' => 'Stu', 'email' => 'stu@x.test', 'role' => 'student', 'active' => '1', 'password' => 'newpass99']);
req('GET', '/admin/settings');
req('POST', '/admin/settings', ['shop_name' => 'My Shop', 'calendar_months' => '6', 'scan_sounds' => '0', 'shop_email' => '']);
echo "settings: " . $app->settings()->get('shop_name') . ' / ' . $app->settings()->get('calendar_months') . ' / sounds=' . $app->settings()->get('scan_sounds') . "\n";
req('POST', '/admin/settings', ['shop_name' => 'My Shop', 'calendar_months' => '13']);
req('GET', '/admin/database');
req('POST', '/admin/database/migrate');
req('POST', '/admin/database/templates');
req('POST', '/admin/database/templates');
req('POST', '/admin/database/demo');
req('POST', '/admin/database/demo');
req('GET', '/admin/database');
req('GET', '/contacts');
$sid = (int) $db->value('SELECT id FROM shows LIMIT 1');
if ($sid) { $db->insert('show_contacts', ['show_id' => $sid, 'contact_id' => $cid, 'role' => 'Director']); }
$b = req('GET', '/contacts/' . $cid);
echo "show listed on contact: " . (str_contains($b, '/shows/' . $sid) ? 'yes' : 'no') . "\n";
req('GET', '/admin/users');
req('POST', '/contacts/' . $cid . '/delete');
echo "contact gone: " . ($db->find('contacts', $cid) ? 'no' : 'yes') . "\n";
$session->put('user_id', $stuId);
req('GET', '/admin/users', [], 403);
req('POST', '/admin/database/migrate', [], 403);
req('GET', '/contacts');
req('GET', '/contacts/create', [], 403);
echo $fail ? "FAILURES: $fail\n" : "ALL PASSED\n";
