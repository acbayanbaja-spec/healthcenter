<?php require_once 'db.php'; require_role(['admin', 'staff']); include 'header.php';
$c = fn($sql) => $pdo->query($sql)->fetchColumn();
$hr = (int)date('G');
$greet = $hr < 12 ? 'Good morning' : ($hr < 18 ? 'Good afternoon' : 'Good evening');
$today = $c("SELECT COUNT(*) FROM appointments WHERE appt_date=CURDATE() AND status='Scheduled'");
$base = "SELECT a.*, p.full_name patient, d.full_name doctor FROM appointments a JOIN patients p USING(patient_id) JOIN doctors d USING(doctor_id)";
$slots = $pdo->query("$base WHERE a.appt_date=CURDATE() AND a.status='Scheduled' ORDER BY a.appt_time")->fetchAll();
$next  = $pdo->query("$base WHERE a.appt_date>CURDATE() AND a.status='Scheduled' ORDER BY a.appt_date, a.appt_time LIMIT 5")->fetchAll();
?>
<?php if (!empty($_COOKIE['newuser'])): ?>
<div class="welcome"><span><b>New here?</b> Take the quick guide to learn how to add doctors, register patients, and book visits.</span><a class="btn" href="tutorial.php">Start the guide</a></div>
<?php endif; ?>
<section class="hero"<?= !empty($me['cover_photo']) ? ' style="background-image:linear-gradient(rgba(16,25,53,.74),rgba(16,25,53,.74)),url(\'' . photo_url($me['cover_photo']) . '\');background-size:cover;background-position:center"' : '' ?>>
  <h2><?= $greet ?>, <?= h(explode(' ', $_SESSION['user'])[0]) ?>.</h2>
  <p><?= $today ? "You have <b>$today</b> visit".($today > 1 ? 's' : '')." on the schedule today." : 'No visits are scheduled for today.' ?></p>
  <svg class="ecg" viewBox="0 0 600 110" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60 H120 L140 60 L158 20 L182 100 L204 8 L224 60 H330 L346 60 L362 34 L380 84 L396 60 H600"/></svg>
</section>
<div class="strip">
  <div><b><?= $c('SELECT COUNT(*) FROM patients') ?></b><span>Patients on record</span></div>
  <div><b><?= $c('SELECT COUNT(*) FROM doctors') ?></b><span>Doctors on duty roster</span></div>
  <div><b><?= $c("SELECT COUNT(*) FROM appointments WHERE status='Scheduled'") ?></b><span>Visits scheduled</span></div>
</div>
<div class="card"><h3>Today's schedule</h3>
<?php if (!$slots): ?><p class="empty">Nothing booked today. <a href="appointments.php">Book a visit</a></p><?php else: ?>
<ul class="slots"><?php foreach ($slots as $a): ?>
<li><time><?= h(substr($a['appt_time'],0,5)) ?></time><div><b><?= h($a['patient']) ?></b><small>with <?= h($a['doctor']) ?><?= $a['reason'] ? ' · '.h($a['reason']) : '' ?></small></div></li>
<?php endforeach; ?></ul><?php endif; ?></div>
<div class="card"><h3>Coming up</h3>
<?php if (!$next): ?><p class="empty">No future visits yet.</p><?php else: ?>
<ul class="slots"><?php foreach ($next as $a): ?>
<li><time><?= h(date('j M', strtotime($a['appt_date']))) ?></time><div><b><?= h($a['patient']) ?></b><small><?= h(substr($a['appt_time'],0,5)) ?> with <?= h($a['doctor']) ?><?= $a['reason'] ? ' · '.h($a['reason']) : '' ?></small></div></li>
<?php endforeach; ?></ul><?php endif; ?></div>
<?php include 'footer.php'; ?>
