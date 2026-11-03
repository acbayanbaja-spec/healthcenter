<?php require_once 'db.php'; require_role(['admin', 'staff', 'doctor']); include 'header.php';
$role = role(); $mine = (int)($_SESSION['doctor_id'] ?? 0); $manage = $role !== 'doctor';
$msg = ''; $err = '';
$editId = ($manage && isset($_GET['reschedule'])) ? (int)$_GET['reschedule'] : 0;
$pre = (int)($_GET['patient'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['status_id'])) {
            $o = $pdo->prepare('SELECT doctor_id FROM appointments WHERE appointment_id=?'); $o->execute([(int)$_POST['status_id']]);
            if (!$manage && (int)$o->fetchColumn() !== $mine) $err = 'You can only update your own visits.';
            else $msg = set_status($pdo, (int)$_POST['status_id'], $_POST['status']);
        } elseif (!$manage) {
            $err = 'Doctors can only update the status of their own visits.';
        } elseif (isset($_POST['delete'])) {
            if ($role !== 'admin') $err = 'Only an admin can delete appointments.';
            else $msg = delete_appointment($pdo, (int)$_POST['delete']);
        } elseif (isset($_POST['reschedule_id'])) {
            [$ok, $m] = reschedule_appointment($pdo, (int)$_POST['reschedule_id'], (int)$_POST['doctor_id'], $_POST['appt_date'], $_POST['appt_time'], $_POST['reason'], !empty($_POST['send_sms']));
            if ($ok) { $msg = $m; $editId = 0; } else { $err = $m; $editId = (int)$_POST['reschedule_id']; }
        } else {
            [$ok, $m] = book_appointment($pdo, (int)$_POST['patient_id'], (int)$_POST['doctor_id'], $_POST['appt_date'], $_POST['appt_time'], $_POST['reason'], !empty($_POST['send_sms']));
            if ($ok) $msg = $m; else $err = $m;
        }
    } catch (PDOException $e) { $err = 'Database error.'; }
}
$edit = null;
if ($editId) {
    $s = $pdo->prepare('SELECT a.*, p.full_name patient FROM appointments a JOIN patients p USING(patient_id) WHERE a.appointment_id=?');
    $s->execute([$editId]); $edit = $s->fetch() ?: null;
}
$patients = $pdo->query('SELECT patient_id, full_name FROM patients ORDER BY full_name')->fetchAll();
$doctors  = $pdo->query('SELECT doctor_id, full_name, specialty FROM doctors ORDER BY full_name')->fetchAll();
$base = 'SELECT a.*, p.full_name patient, d.full_name doctor%s FROM appointments a
  JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id)%s ORDER BY a.appt_date DESC, a.appt_time';
$where = $manage ? '' : ' WHERE a.doctor_id=' . $mine;
try {
    $rows = $pdo->query(sprintf($base, ', (SELECT n.status FROM notifications n WHERE n.appointment_id=a.appointment_id ORDER BY n.notif_id DESC LIMIT 1) last_sms', $where))->fetchAll();
} catch (PDOException $e) { $rows = $pdo->query(sprintf($base, '', $where))->fetchAll(); }
?>
<h2><?= $manage ? 'Appointments' : 'My visits' ?></h2>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<?php if ($manage): ?>
<div class="card"><h3><?= $edit ? 'Reschedule visit' : 'Book Appointment' ?></h3>
<form method="post" class="grid">
  <?php if ($edit): ?>
    <input type="hidden" name="reschedule_id" value="<?= (int)$edit['appointment_id'] ?>">
    <input value="<?= h($edit['patient']) ?>" disabled aria-label="Patient">
  <?php else: ?>
    <select name="patient_id" required><option value="">Patient…</option>
      <?php foreach ($patients as $p): ?><option value="<?= $p['patient_id'] ?>" <?= $pre === (int)$p['patient_id'] ? 'selected' : '' ?>><?= h($p['full_name']) ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <select name="doctor_id" required><option value="">Doctor…</option>
    <?php foreach ($doctors as $d): ?><option value="<?= $d['doctor_id'] ?>" <?= $edit && (int)$edit['doctor_id'] === (int)$d['doctor_id'] ? 'selected' : '' ?>><?= h($d['full_name']) ?> (<?= h($d['specialty']) ?>)</option><?php endforeach; ?></select>
  <input type="date" name="appt_date" min="<?= date('Y-m-d') ?>" value="<?= h($edit['appt_date'] ?? ($_GET['date'] ?? '')) ?>" required>
  <input type="time" name="appt_time" value="<?= h($edit ? substr($edit['appt_time'], 0, 5) : '') ?>" required>
  <input name="reason" placeholder="Reason for visit" value="<?= h($edit['reason'] ?? '') ?>">
  <label class="agree"><input type="checkbox" name="send_sms" value="1" checked> <span><?= $edit ? 'Text the patient about the change' : 'Text the patient a confirmation' ?></span></label>
  <div><button><?= $edit ? 'Save new time' : 'Book visit' ?></button> <?php if ($edit): ?><a class="btn gray" href="appointments.php">Cancel</a><?php endif; ?></div>
</form></div>
<?php endif; ?>
<div class="card"><table><tr><th>Date</th><th>Time</th><th>Patient</th><th>Doctor</th><th>Reason</th><th>Status</th><?php if ($manage): ?><th>Text</th><?php endif; ?><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= h($r['appt_date']) ?></td><td><?= h(substr($r['appt_time'], 0, 5)) ?></td><td><a href="patient.php?id=<?= $r['patient_id'] ?>"><?= h($r['patient']) ?></a></td><td><?= h($r['doctor']) ?></td><td><?= h($r['reason']) ?></td>
<td><form method="post"><input type="hidden" name="status_id" value="<?= $r['appointment_id'] ?>">
<select name="status" class="pill s-<?= strtolower($r['status']) ?>" onchange="this.form.submit()"><?php foreach (['Scheduled', 'Completed', 'Cancelled'] as $s): ?>
<option <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></form></td>
<?php if ($manage): ?><td><?php if (!empty($r['last_sms'])): ?><span class="pill <?= sms_pill($r['last_sms']) ?>"><?= h($r['last_sms']) ?></span><?php else: ?>—<?php endif; ?></td><?php endif; ?>
<td><?php if ($manage): ?><a class="btn" href="?reschedule=<?= $r['appointment_id'] ?>">Reschedule</a> <?php endif; ?>
<a class="btn gray" href="slip.php?id=<?= $r['appointment_id'] ?>" target="_blank">Slip</a>
<?php if ($role === 'admin'): ?><a class="btn gray" href="history.php?table=appointments&id=<?= $r['appointment_id'] ?>">History</a>
<form method="post" style="display:inline" onsubmit="return confirm('Delete this appointment?')">
<button class="red" name="delete" value="<?= $r['appointment_id'] ?>">Delete</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php include 'footer.php'; ?>
