<?php
require_once 'db.php'; require_login();
$s = $pdo->prepare('SELECT a.*, p.full_name patient, p.phone, d.full_name doctor, d.specialty FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id) WHERE a.appointment_id=?');
$s->execute([(int)($_GET['id'] ?? 0)]); $a = $s->fetch();
if (!$a) { http_response_code(404); exit('Appointment not found.'); }
$rl = role();
if (($rl === 'patient' && (int)$a['patient_id'] !== (int)($_SESSION['patient_id'] ?? 0)) || ($rl === 'doctor' && (int)$a['doctor_id'] !== (int)($_SESSION['doctor_id'] ?? 0))) { http_response_code(403); exit('You do not have access to this appointment.'); }
log_event($pdo, 'Exported', 'appointments', $a['appointment_id'], 'Appointment slip opened for printing');
$ref = 'HC-' . str_pad($a['appointment_id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Appointment slip <?= h($ref) ?></title><link rel="stylesheet" href="style.css">
<style>
body{background:#e9edf7}
.slip{max-width:520px;margin:30px auto;background:#fff;border:2px solid #101935;border-radius:24px 24px 6px 24px;overflow:hidden}
.slip header{background:#101935;color:#fff;padding:20px 26px;display:flex;justify-content:space-between;align-items:center}
.slip header b{font:800 1.3rem 'Bricolage Grotesque',sans-serif}.slip header span{color:#ffc23c;font-weight:700}
.slip .body{padding:24px 26px}
.slip dl{display:grid;grid-template-columns:120px 1fr;gap:10px 14px;margin:0}
.slip dt{color:#6b7491;font-size:.85rem}.slip dd{margin:0;font-weight:600;color:#101935}
.slip .when{font:800 1.8rem 'Bricolage Grotesque',sans-serif;color:#2f4bff;margin:0 0 18px}
.slip .cut{border-top:2px dashed #dce2f2;margin:20px 0 14px}.slip small{color:#6b7491}
.actions{max-width:520px;margin:0 auto 30px;display:flex;gap:10px}
.lg-ring{display:none}.lg *{animation:none!important}.lg-line{stroke-dashoffset:0!important}
@media print{body{background:#fff}.noprint{display:none}.slip{margin:0 auto;border-radius:0}}
</style></head><body>
<div class="slip">
  <header><div style="display:flex;align-items:center;gap:12px"><?= logo_html(40) ?><b><?= h(CLINIC_NAME) ?></b></div><span><?= h($ref) ?></span></header>
  <div class="body">
    <p class="when"><?= h(date('l, j F Y', strtotime($a['appt_date']))) ?><br><?= h(date('g:i A', strtotime($a['appt_time']))) ?></p>
    <dl>
      <dt>Patient</dt><dd><?= h($a['patient']) ?></dd>
      <dt>Doctor</dt><dd><?= h($a['doctor']) ?> (<?= h($a['specialty']) ?>)</dd>
      <?php if ($a['reason']): ?><dt>Reason</dt><dd><?= h($a['reason']) ?></dd><?php endif; ?>
      <dt>Status</dt><dd><?= h($a['status']) ?></dd>
    </dl>
    <div class="cut"></div>
    <small>Please arrive 10 minutes early and bring a valid ID. If you cannot come, contact <?= h(CLINIC_NAME) ?> to reschedule. Printed <?= h(date('j M Y, g:i A')) ?>.</small>
  </div>
</div>
<div class="actions noprint"><button onclick="window.print()">Print or save as PDF</button><a class="btn gray" href="<?= h($rl === 'patient' ? 'portal.php' : 'appointments.php') ?>">Back</a></div>
</body></html>
