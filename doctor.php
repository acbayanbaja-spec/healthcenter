<?php require_once 'db.php'; require_role(['doctor']);
$did = (int)($_SESSION['doctor_id'] ?? 0);
include 'header.php';
$hr = (int)date('G'); $greet = $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');
if (!$did): ?><div class="msg err">Your account is not linked to a doctor record yet. Ask an admin to link it in Users.</div>
<?php else:
$base = "SELECT a.*, p.full_name patient FROM appointments a JOIN patients p USING(patient_id) WHERE a.doctor_id=? AND a.status='Scheduled'";
$t = $pdo->prepare("$base AND a.appt_date=? ORDER BY a.appt_time"); $t->execute([$did, date('Y-m-d')]); $today = $t->fetchAll();
$u = $pdo->prepare("$base AND a.appt_date>? AND a.appt_date<=? ORDER BY a.appt_date, a.appt_time"); $u->execute([$did, date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))]); $soon = $u->fetchAll();
$c = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id=? AND status='Completed'"); $c->execute([$did]); $done = (int)$c->fetchColumn();
$pc = $pdo->prepare('SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id=?'); $pc->execute([$did]); $pts = (int)$pc->fetchColumn();
?>
<section class="hero"><h2><?= $greet ?>, <?= h($me['full_name']) ?>.</h2>
<p><?= count($today) ? 'You have <b>' . count($today) . '</b> visit' . (count($today) > 1 ? 's' : '') . ' scheduled today.' : 'No visits are scheduled for you today.' ?></p>
<svg class="ecg" viewBox="0 0 600 110" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60 H120 L140 60 L158 20 L182 100 L204 8 L224 60 H330 L346 60 L362 34 L380 84 L396 60 H600"/></svg></section>
<div class="strip">
  <div><b><?= count($today) ?></b><span>Today</span></div>
  <div><b><?= count($soon) ?></b><span>Next 7 days</span></div>
  <div><b><?= $done ?></b><span>Completed visits</span></div>
  <div><b><?= $pts ?></b><span>Patients seen or booked</span></div>
</div>
<div class="card"><h3>Today's schedule</h3>
<?php if (!$today): ?><p class="empty">Nothing booked today.</p><?php else: ?><ul class="slots"><?php foreach ($today as $a): ?>
<li><time><?= h(substr($a['appt_time'], 0, 5)) ?></time><div><b><a href="patient.php?id=<?= $a['patient_id'] ?>"><?= h($a['patient']) ?></a></b><small><?= h($a['reason']) ?: 'No reason given' ?></small></div></li>
<?php endforeach; ?></ul><?php endif; ?><p><a class="btn" href="appointments.php">Open my visits</a> <a class="btn gray" href="calendar.php">Calendar</a></p></div>
<div class="card"><h3>Coming up this week</h3>
<?php if (!$soon): ?><p class="empty">No visits in the next 7 days.</p><?php else: ?><ul class="slots"><?php foreach ($soon as $a): ?>
<li><time><?= h(date('j M', strtotime($a['appt_date']))) ?></time><div><b><a href="patient.php?id=<?= $a['patient_id'] ?>"><?= h($a['patient']) ?></a></b><small><?= h(substr($a['appt_time'], 0, 5)) ?><?= $a['reason'] ? ' · ' . h($a['reason']) : '' ?></small></div></li>
<?php endforeach; ?></ul><?php endif; ?></div>
<?php endif; ?>
<?php include 'footer.php'; ?>
