<?php require_once 'db.php'; require_role(['patient']);
$pid = (int)($_SESSION['patient_id'] ?? 0);
$msg = ''; $err = '';
if ($pid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['cancel'])) {
            $id = (int)$_POST['cancel'];
            $o = $pdo->prepare('SELECT patient_id FROM appointments WHERE appointment_id=?'); $o->execute([$id]);
            if ((int)$o->fetchColumn() !== $pid) $err = 'That appointment is not yours.';
            elseif (!appt_is_upcoming($pdo, $id)) $err = 'Only upcoming visits can be cancelled.';
            else $msg = set_status($pdo, $id, 'Cancelled', ' (by patient)');
        } elseif (isset($_POST['contact'])) {
            $o = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $o->execute([$pid]); $old = $o->fetch();
            $new = ['phone' => trim($_POST['phone'] ?? ''), 'address' => trim($_POST['address'] ?? '')];
            $pdo->prepare('UPDATE patients SET phone=?, address=? WHERE patient_id=?')->execute([$new['phone'], $new['address'], $pid]);
            log_event($pdo, 'Updated', 'patients', $pid, changes($old, $new) . ' (by patient)');
            $msg = 'Contact details saved.';
        }
    } catch (PDOException $e) { $err = 'Database error.'; }
}
include 'header.php';
$s = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $s->execute([$pid]); $p = $s->fetch();
if (!$p): ?><div class="msg err">Your account is not linked to a patient record yet. Please ask the front desk to link it.</div>
<?php else:
$v = $pdo->prepare('SELECT a.*, d.full_name doctor, d.specialty FROM appointments a JOIN doctors d USING(doctor_id) WHERE a.patient_id=? ORDER BY a.appt_date DESC, a.appt_time DESC'); $v->execute([$pid]); $visits = $v->fetchAll();
$next = null; $upc = 0; $done = 0;
foreach ($visits as $r) {
    if ($r['status'] === 'Completed') $done++;
    if ($r['status'] === 'Scheduled' && strtotime($r['appt_date'] . ' ' . $r['appt_time']) > time()) { $upc++; $next = $r; }
}
$hr = (int)date('G'); $greet = $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');
?>
<section class="hero"><h2><?= $greet ?>, <?= h(explode(' ', $p['full_name'])[0]) ?>.</h2>
<p><?= $next ? 'Your next visit is <b>' . h(date('D j M', strtotime($next['appt_date'])) . ' at ' . date('g:i A', strtotime($next['appt_time']))) . '</b> with ' . h($next['doctor']) . '.' : 'You have no upcoming visits. Please contact the front desk to book one.' ?></p>
<svg class="ecg" viewBox="0 0 600 110" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60 H120 L140 60 L158 20 L182 100 L204 8 L224 60 H330 L346 60 L362 34 L380 84 L396 60 H600"/></svg></section>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<div class="strip"><div><b><?= $upc ?></b><span>Upcoming visits</span></div><div><b><?= $done ?></b><span>Completed visits</span></div><div><b><?= count($visits) ?></b><span>Total visits</span></div></div>
<div class="card"><h3>Need a visit?</h3><p>To book or change an appointment, please contact the front desk of <?= h(CLINIC_NAME) ?>. You can cancel an upcoming visit yourself below.</p></div>
<?php
$cm = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['m'] ?? '') ? $_GET['m'] : date('Y-m');
$cf = strtotime("$cm-01"); $cdays = (int)date('t', $cf); $coff = (int)date('w', $cf);
$pm = date('Y-m', strtotime('-1 month', $cf)); $nm = date('Y-m', strtotime('+1 month', $cf));
$sel = $_GET['d'] ?? (date('Y-m') === $cm ? date('Y-m-d') : "$cm-01");
if (substr($sel, 0, 7) !== $cm || !strtotime($sel)) $sel = "$cm-01";
$byDay = [];
foreach ($visits as $r) if (substr($r['appt_date'], 0, 7) === $cm) $byDay[$r['appt_date']][] = $r;
$availOk = true; $av = [];
try {
    $data = avail_load($pdo, "$cm-01", sprintf('%s-%02d', $cm, $cdays));
    for ($n = 1; $n <= $cdays; $n++) { $dt = sprintf('%s-%02d', $cm, $n); $av[$dt] = avail_day($data, $dt); }
} catch (PDOException $e) { $availOk = false; }
?>
<div class="card"><div class="pagehead"><h3 style="margin:0"><?= h(date('F Y', $cf)) ?></h3>
  <div class="cal-nav"><a class="btn gray" href="?m=<?= $pm ?>" aria-label="Previous month">‹</a><a class="btn gray" href="?m=<?= date('Y-m') ?>">Today</a><a class="btn gray" href="?m=<?= $nm ?>" aria-label="Next month">›</a></div></div>
<p class="note">Select a day to see which doctors are available. <span class="avl">✓</span> means at least one doctor is available.</p>
<div class="cal">
  <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w): ?><div class="cal-h"><?= $w ?></div><?php endforeach; ?>
  <?php for ($i = 0; $i < $coff; $i++): ?><div class="cal-day off"></div><?php endfor; ?>
  <?php for ($n = 1; $n <= $cdays; $n++): $dt = sprintf('%s-%02d', $cm, $n); $dl = $byDay[$dt] ?? []; $na = count($av[$dt] ?? []); ?>
  <a class="cal-day<?= $dt === date('Y-m-d') ? ' today' : '' ?><?= $dt === $sel ? ' sel' : '' ?>" href="?m=<?= h($cm) ?>&d=<?= $dt ?>"><b><?= $n ?></b><?php if ($dl): ?><span class="cnt"><?= count($dl) ?></span><?php endif; ?>
    <?php if ($na): ?><span class="avl">✓ <?= $na ?><span class="avtxt"> doctor<?= $na > 1 ? 's' : '' ?></span></span><?php endif; ?>
    <?php foreach (array_slice($dl, 0, 1) as $r): ?><i class="c s-<?= strtolower($r['status']) ?>">Your visit <?= h(substr($r['appt_time'], 0, 5)) ?></i><?php endforeach; ?>
  </a>
  <?php endfor; ?>
</div></div>
<div class="card"><h3><?= h(date('l, j F Y', strtotime($sel))) ?></h3>
<?php if ($sel < date('Y-m-d')): ?><p class="empty">This day has already passed.</p>
<?php elseif (!$availOk): ?><p class="empty">Doctor availability is not available yet. Please contact the front desk.</p>
<?php elseif (!$av[$sel]): ?><p class="empty">No doctors are available on this day. Please choose another date.</p>
<?php else: ?><ul class="slots"><?php foreach ($av[$sel] as $d): ?>
<li><time>✓</time><div><b><?= h($d['name']) ?></b><small><?= h($d['specialty']) ?> · <?= h(implode(', ', $d['blocks'])) ?> · <?= (int)$d['open'] ?> open time<?= $d['open'] > 1 ? 's' : '' ?></small></div></li>
<?php endforeach; ?></ul><p class="note">To book a visit, please contact the front desk.</p><?php endif; ?>
<?php foreach ($byDay[$sel] ?? [] as $r): ?><p><b>Your visit:</b> <?= h(substr($r['appt_time'], 0, 5)) ?> with <?= h($r['doctor']) ?> (<?= h($r['status']) ?>)</p><?php endforeach; ?></div>
<div class="card"><h3>My appointments</h3>
<table><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Reason</th><th>Status</th><th></th></tr>
<?php foreach ($visits as $r): $up = $r['status'] === 'Scheduled' && strtotime($r['appt_date'] . ' ' . $r['appt_time']) > time(); ?>
<tr><td><?= h($r['appt_date']) ?></td><td><?= h(substr($r['appt_time'], 0, 5)) ?></td><td><?= h($r['doctor']) ?> <small class="note">(<?= h($r['specialty']) ?>)</small></td><td><?= h($r['reason']) ?></td>
<td><span class="pill s-<?= strtolower($r['status']) ?>"><?= h($r['status']) ?></span></td>
<td><a class="btn gray" href="slip.php?id=<?= $r['appointment_id'] ?>" target="_blank">Slip</a>
<?php if ($up): ?><form method="post" style="display:inline" onsubmit="return confirm('Cancel this appointment?')"><button class="red" name="cancel" value="<?= $r['appointment_id'] ?>">Cancel</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$visits) echo '<tr><td colspan="6">No appointments yet.</td></tr>'; ?></table></div>
<div class="card"><h3>My contact details</h3>
<form method="post" class="grid"><input type="hidden" name="contact" value="1">
  <input name="phone" placeholder="Phone" value="<?= h($p['phone']) ?>">
  <input name="address" placeholder="Address" value="<?= h($p['address']) ?>">
  <div><button>Save</button></div></form>
<p class="note">To change your name or birth date, please ask the front desk.</p></div>
<?php endif; ?>
<?php include 'footer.php'; ?>
