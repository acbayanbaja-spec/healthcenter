<?php require_once 'db.php'; require_role(['admin', 'staff', 'doctor']);
$id = (int)($_GET['id'] ?? 0);
if (role() === 'doctor') {
    $c = $pdo->prepare('SELECT 1 FROM appointments WHERE patient_id=? AND doctor_id=? LIMIT 1'); $c->execute([$id, (int)($_SESSION['doctor_id'] ?? 0)]);
    if (!$c->fetch()) { header('Location: doctor.php?denied=1'); exit; }
}
include 'header.php';
$s = $pdo->prepare('SELECT * FROM patients WHERE patient_id=?'); $s->execute([$id]); $p = $s->fetch();
if (!$p) { echo '<div class="msg err">Patient not found. <a href="patients.php">Back to patients</a></div>'; include 'footer.php'; exit; }
log_event($pdo, 'Viewed', 'patients', $id, 'Opened patient record');
$v = $pdo->prepare('SELECT a.*, d.full_name doctor, d.specialty FROM appointments a JOIN doctors d USING(doctor_id) WHERE a.patient_id=?' . (role() === 'doctor' ? ' AND a.doctor_id=' . (int)($_SESSION['doctor_id'] ?? 0) : '') . ' ORDER BY a.appt_date DESC, a.appt_time DESC');
$v->execute([$id]); $visits = $v->fetchAll();
$age = (new DateTime($p['birth_date']))->diff(new DateTime())->y;
$done = $canc = 0; $next = null;
foreach ($visits as $r) {
    if ($r['status'] === 'Completed') $done++;
    if ($r['status'] === 'Cancelled') $canc++;
    if ($r['status'] === 'Scheduled' && strtotime($r['appt_date'] . ' ' . $r['appt_time']) > time()) $next = $r;   // list is newest first, so the last match is the soonest
}
?>
<div class="pagehead"><h2><?= h($p['full_name']) ?></h2>
  <div><?php if (in_array(role(), ['admin', 'staff'], true)): ?><a class="btn" href="appointments.php?patient=<?= $id ?>">Book a visit</a>
  <a class="btn gray" href="patients.php?edit=<?= $id ?>">Edit</a><?php endif; ?>
  <?php if (role() === 'admin'): ?><a class="btn gray" href="history.php?table=patients&id=<?= $id ?>">History</a>
  <a class="btn gray" href="export_word.php?report=history&table=patients&id=<?= $id ?>">Word history</a><?php endif; ?></div></div>
<div class="strip">
  <div><b><?= count($visits) ?></b><span>Total visits</span></div>
  <div><b><?= $done ?></b><span>Completed</span></div>
  <div><b><?= $canc ?></b><span>Cancelled</span></div>
  <div><b style="font-size:1.2rem"><?= $next ? h(date('j M', strtotime($next['appt_date'])) . ', ' . substr($next['appt_time'], 0, 5)) : '—' ?></b><span>Next visit</span></div>
</div>
<div class="card"><h3>Details</h3>
<table><tr><th>Age</th><th>Birth date</th><th>Gender</th><th>Phone</th><th>Address</th><?php if (isset($p['sms_optin'])): ?><th>Texts</th><?php endif; ?></tr>
<tr><td><?= $age ?></td><td><?= h($p['birth_date']) ?></td><td><?= h($p['gender']) ?></td><td><?= h($p['phone']) ?: '—' ?></td><td><?= h($p['address']) ?: '—' ?></td>
<?php if (isset($p['sms_optin'])): ?><td><span class="pill <?= $p['sms_optin'] ? 's-completed' : 's-other' ?>"><?= $p['sms_optin'] ? 'On' : 'Off' ?></span></td><?php endif; ?></tr></table></div>
<?php if (in_array(role(), ['admin', 'staff'], true)):
$acct = false; try { $q = $pdo->prepare("SELECT username FROM users WHERE patient_id=? AND role='patient' LIMIT 1"); $q->execute([$id]); $acct = $q->fetchColumn(); } catch (PDOException $e) {} ?>
<div class="card"><h3>Patient login</h3>
<?php if ($acct): ?><p>This patient has a login: <b>@<?= h($acct) ?></b></p>
<?php else: ?><p class="empty">No login yet.</p><a class="btn" href="register_patient.php?patient=<?= $id ?>">Create login</a><?php endif; ?></div>
<?php endif; ?>
<div class="card"><h3>Visit history</h3>
<table><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Reason</th><th>Status</th><th></th></tr>
<?php foreach ($visits as $r): ?>
<tr><td><?= h($r['appt_date']) ?></td><td><?= h(substr($r['appt_time'], 0, 5)) ?></td><td><?= h($r['doctor']) ?> <small class="note">(<?= h($r['specialty']) ?>)</small></td><td><?= h($r['reason']) ?></td>
<td><span class="pill s-<?= strtolower($r['status']) ?>"><?= h($r['status']) ?></span></td>
<td><a class="btn gray" href="slip.php?id=<?= $r['appointment_id'] ?>" target="_blank">Slip</a></td></tr>
<?php endforeach; if (!$visits) echo '<tr><td colspan="6">No visits yet.</td></tr>'; ?></table></div>
<?php include 'footer.php'; ?>
