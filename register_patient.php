<?php require_once 'db.php'; require_role(['admin', 'staff']);
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$hasRole = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
$err = ''; $created = null;
$preId = (int)($_GET['patient'] ?? 0);
if ($hasRole && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $existing = (int)($_POST['existing_id'] ?? 0);
    $user = trim($_POST['username'] ?? ''); $pass = $_POST['password'] ?? '';
    $name = trim($_POST['full_name'] ?? ''); $birth = $_POST['birth_date'] ?? ''; $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? ''); $addr = trim($_POST['address'] ?? ''); $bts = strtotime($birth);
    try {
        $pat = null;
        if ($existing) { $s = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $s->execute([$existing]); $pat = $s->fetch(); }
        $dup = $pdo->prepare('SELECT 1 FROM users WHERE username=?'); $dup->execute([$user]);
        $has = $pdo->prepare('SELECT 1 FROM users WHERE patient_id=?'); $has->execute([$existing]);
        if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) $err = 'The form expired. Reload the page and try again.';
        elseif (empty($_POST['agree'])) $err = 'The patient must accept the User Agreement.';
        elseif ($user === '' || !preg_match('/^[A-Za-z0-9._-]{3,30}$/', $user)) $err = 'Username must be 3 to 30 letters, numbers, dots, dashes or underscores.';
        elseif (strlen($pass) < 6) $err = 'The temporary password must be at least 6 characters.';
        elseif ($dup->fetch()) $err = 'That username is already taken.';
        elseif ($existing && !$pat) $err = 'That patient record was not found.';
        elseif ($existing && $has->fetch()) $err = 'That patient already has a login.';
        elseif (!$existing && $name === '') $err = 'Enter the patient\'s full name.';
        elseif (!$existing && (!$bts || $bts > time() || $bts < strtotime('1900-01-01'))) $err = 'Enter a valid birth date.';
        elseif (!$existing && !in_array($gender, ['Male', 'Female', 'Other'], true)) $err = 'Choose a gender.';
        else {
            $pdo->beginTransaction();
            if ($existing) { $pid = $existing; $name = $pat['full_name']; }
            else {
                $pdo->prepare('INSERT INTO patients (full_name, birth_date, gender, phone, address) VALUES (?,?,?,?,?)')->execute([$name, date('Y-m-d', $bts), $gender, $phone, $addr]);
                $pid = (int)$pdo->lastInsertId();
                log_event($pdo, 'Created', 'patients', $pid, "full_name: $name (walk-in registration)");
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            try {
                $pdo->prepare("INSERT INTO users (username, password, full_name, role, patient_id, agreed_at) VALUES (?,?,?,'patient',?,NOW())")->execute([$user, $hash, $name, $pid]);
            } catch (PDOException $e) {
                $pdo->prepare("INSERT INTO users (username, password, full_name, role, patient_id) VALUES (?,?,?,'patient',?)")->execute([$user, $hash, $name, $pid]);
            }
            $uid = (int)$pdo->lastInsertId();
            $pdo->commit();
            try { $pdo->prepare('UPDATE users SET must_change_password=1 WHERE user_id=?')->execute([$uid]); } catch (PDOException $e) {}
            log_event($pdo, 'Registered', 'users', $uid, "Patient account created for walk-in: $user (accepted User Agreement)");
            $created = ['name' => $name, 'username' => $user, 'password' => $pass];
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = 'Could not create the account. Please try again.';
    }
}
include 'header.php';
$free = [];
if ($hasRole) $free = $pdo->query('SELECT p.patient_id, p.full_name, p.birth_date FROM patients p WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.patient_id = p.patient_id) ORDER BY p.full_name')->fetchAll();
?>
<h2>Register a walk-in patient</h2>
<p class="note">Only staff can create patient accounts. Fill in the patient's details with them at the front desk, then give them their username and temporary password.</p>
<?php if (!$hasRole): ?><div class="msg err">Roles are not set up yet. Run <b>migrate_roles.sql</b> in phpMyAdmin, then reload this page.</div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<?php if ($created): ?>
<div class="creds" id="credslip"><h3>Account ready for <?= h($created['name']) ?></h3>
  <p>Username: <code><?= h($created['username']) ?></code><br>Temporary password: <code><?= h($created['password']) ?></code></p>
  <p class="note">The patient will be asked to choose their own password at first login. This password is shown only once.</p>
  <button type="button" onclick="window.print()" class="noprint">Print this slip</button></div>
<?php endif; ?>
<?php if ($hasRole): ?>
<div class="card"><form method="post" class="grid" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
  <select name="existing_id" id="existing" aria-label="Existing patient record">
    <option value="">New patient (enter details below)</option>
    <?php foreach ($free as $p): ?><option value="<?= $p['patient_id'] ?>" <?= $preId === (int)$p['patient_id'] ? 'selected' : '' ?>>Existing: <?= h($p['full_name']) ?> (<?= h($p['birth_date']) ?>)</option><?php endforeach; ?>
  </select>
  <div id="newFields" style="display:contents">
    <input name="full_name" data-req placeholder="Full name">
    <input type="date" name="birth_date" data-req max="<?= date('Y-m-d') ?>" aria-label="Birth date">
    <select name="gender" data-req aria-label="Gender"><option value="">Gender…</option><option>Male</option><option>Female</option><option>Other</option></select>
    <input name="phone" placeholder="Phone (for appointment texts)">
    <input name="address" placeholder="Address">
  </div>
  <input name="username" placeholder="Username for the patient" required>
  <div style="display:flex;gap:6px"><input name="password" id="pw" placeholder="Temporary password (min 6)" required><button type="button" class="gray" id="gen">Generate</button></div>
  <label class="agree"><input type="checkbox" name="agree" required> <span>The patient has read and accepted the <a href="terms.php" target="_blank">User Agreement</a>.</span></label>
  <div><button>Create patient account</button></div>
</form></div>
<script>
(function(){
  var sel=document.getElementById('existing'),box=document.getElementById('newFields');
  function sync(){var ex=sel.value!=='';box.style.display=ex?'none':'contents';box.querySelectorAll('[data-req]').forEach(function(i){i.required=!ex});}
  sel.onchange=sync;sync();
  document.getElementById('gen').onclick=function(){var c='abcdefghjkmnpqrstuvwxyz23456789',p='';for(var i=0;i<8;i++)p+=c[Math.floor(Math.random()*c.length)];document.getElementById('pw').value=p;};
})();
</script>
<?php endif; ?>
<?php include 'footer.php'; ?>
