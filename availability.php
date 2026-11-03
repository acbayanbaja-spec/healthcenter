<?php require_once 'db.php'; require_role(['admin', 'staff', 'doctor']);
$role = role(); $msg = ''; $err = '';
$ready = true; try { $pdo->query('SELECT 1 FROM doctor_availability LIMIT 1'); } catch (PDOException $e) { $ready = false; }
$doctors = $pdo->query('SELECT doctor_id, full_name, specialty FROM doctors ORDER BY full_name')->fetchAll();
$did = $role === 'doctor' ? (int)($_SESSION['doctor_id'] ?? 0) : (int)($_POST['doctor_id'] ?? $_GET['doctor'] ?? 0);
if (!$did && $role !== 'doctor' && $doctors) $did = (int)$doctors[0]['doctor_id'];
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
$tm = fn($v) => (bool)preg_match('/^\d{2}:\d{2}$/', (string)$v);
if ($ready && $did && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $do = $_POST['do'] ?? '';
        if ($do === 'hours') {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM doctor_availability WHERE doctor_id=?')->execute([$did]);
            $ins = $pdo->prepare('INSERT INTO doctor_availability (doctor_id, weekday, start_time, end_time) VALUES (?,?,?,?)');
            foreach ($days as $w => $n) {
                if (empty($_POST['works'][$w])) continue;
                foreach ([1, 2] as $b) {
                    $s = $_POST["s$b"][$w] ?? ''; $e = $_POST["e$b"][$w] ?? '';
                    if ($tm($s) && $tm($e) && $s < $e) $ins->execute([$did, $w, $s . ':00', $e . ':00']);
                }
            }
            $pdo->commit();
            log_event($pdo, 'Updated', 'doctors', $did, 'Working hours updated');
            $msg = 'Working hours saved.';
        } elseif ($do === 'dayoff') {
            $d = $_POST['off_date'] ?? '';
            if (!strtotime($d)) $err = 'Choose a date.';
            else {
                $pdo->prepare('INSERT IGNORE INTO doctor_days_off (doctor_id, off_date, reason) VALUES (?,?,?)')->execute([$did, date('Y-m-d', strtotime($d)), mb_substr(trim($_POST['reason'] ?? ''), 0, 100)]);
                log_event($pdo, 'Updated', 'doctors', $did, 'Day off added: ' . date('Y-m-d', strtotime($d)));
                $msg = 'Day off added.';
            }
        } elseif ($do === 'remove_off') {
            $pdo->prepare('DELETE FROM doctor_days_off WHERE off_id=? AND doctor_id=?')->execute([(int)$_POST['off_id'], $did]);
            log_event($pdo, 'Updated', 'doctors', $did, 'Day off removed');
            $msg = 'Day off removed.';
        }
    } catch (PDOException $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $err = 'Could not save. Please try again.'; }
}
include 'header.php';
$cur = []; $offs = [];
if ($ready && $did) {
    $q = $pdo->prepare('SELECT weekday, start_time, end_time FROM doctor_availability WHERE doctor_id=? ORDER BY weekday, start_time'); $q->execute([$did]);
    foreach ($q->fetchAll() as $r) $cur[(int)$r['weekday']][] = [substr($r['start_time'], 0, 5), substr($r['end_time'], 0, 5)];
    $q = $pdo->prepare('SELECT * FROM doctor_days_off WHERE doctor_id=? AND off_date >= ? ORDER BY off_date'); $q->execute([$did, date('Y-m-d')]); $offs = $q->fetchAll();
}
?>
<h2><?= $role === 'doctor' ? 'My working hours' : 'Doctor working hours' ?></h2>
<p class="note">Patients see a doctor as available only on days they work, outside their days off, and while time slots are still open (<?= SLOT_MINUTES ?> minutes each).</p>
<?php if (!$ready): ?><div class="msg err">Working hours are not set up yet. Run <b>migrate_availability.sql</b> in phpMyAdmin, then reload this page.</div><?php endif; ?>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<?php if ($ready && !$did): ?><div class="msg err">No doctor selected. <?= $role === 'doctor' ? 'Your account is not linked to a doctor record yet. Ask an admin.' : 'Add a doctor first.' ?></div><?php endif; ?>
<?php if ($ready && $did): ?>
<?php if ($role !== 'doctor'): ?><div class="card"><form method="get" class="grid"><select name="doctor" onchange="this.form.submit()" aria-label="Doctor">
  <?php foreach ($doctors as $d): ?><option value="<?= $d['doctor_id'] ?>" <?= $did === (int)$d['doctor_id'] ? 'selected' : '' ?>><?= h($d['full_name']) ?> (<?= h($d['specialty']) ?>)</option><?php endforeach; ?></select></form></div><?php endif; ?>
<div class="card"><h3>Weekly hours</h3>
<form method="post"><input type="hidden" name="do" value="hours"><input type="hidden" name="doctor_id" value="<?= $did ?>">
<table><tr><th>Day</th><th>Works</th><th>From – to</th><th>Second block (optional)</th></tr>
<?php foreach ($days as $w => $n): $b = $cur[$w] ?? []; ?>
<tr><td><?= $n ?></td><td><input type="checkbox" name="works[<?= $w ?>]" value="1" <?= $b ? 'checked' : '' ?> style="width:auto" aria-label="Works on <?= $n ?>"></td>
<td><div style="display:flex;gap:6px"><input type="time" name="s1[<?= $w ?>]" value="<?= h($b[0][0] ?? '08:00') ?>"><input type="time" name="e1[<?= $w ?>]" value="<?= h($b[0][1] ?? '12:00') ?>"></div></td>
<td><div style="display:flex;gap:6px"><input type="time" name="s2[<?= $w ?>]" value="<?= h($b[1][0] ?? '') ?>"><input type="time" name="e2[<?= $w ?>]" value="<?= h($b[1][1] ?? '') ?>"></div></td></tr>
<?php endforeach; ?></table>
<p class="note">Use the second block for a split day, like 8:00–12:00 and 13:00–17:00. Leave it empty if not needed.</p>
<button>Save working hours</button></form></div>
<div class="card"><h3>Days off</h3>
<form method="post" class="grid"><input type="hidden" name="do" value="dayoff"><input type="hidden" name="doctor_id" value="<?= $did ?>">
  <input type="date" name="off_date" min="<?= date('Y-m-d') ?>" required><input name="reason" maxlength="100" placeholder="Reason (optional)"><div><button>Add day off</button></div></form>
<table><?php foreach ($offs as $o): ?>
<tr><td><?= h(date('D, j M Y', strtotime($o['off_date']))) ?></td><td><?= h($o['reason']) ?></td>
<td><form method="post"><input type="hidden" name="do" value="remove_off"><input type="hidden" name="doctor_id" value="<?= $did ?>"><input type="hidden" name="off_id" value="<?= $o['off_id'] ?>"><button class="gray">Remove</button></form></td></tr>
<?php endforeach; if (!$offs) echo '<tr><td colspan="3">No upcoming days off.</td></tr>'; ?></table></div>
<?php endif; ?>
<?php include 'footer.php'; ?>
