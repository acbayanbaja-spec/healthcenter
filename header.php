<?php require_once 'db.php'; require_login();
$page = basename($_SERVER['SCRIPT_NAME']);
$meSt = $pdo->prepare('SELECT * FROM users WHERE user_id=?'); $meSt->execute([$_SESSION['uid']]); $me = $meSt->fetch();
if (!$me) { header('Location: logout.php'); exit; }
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
maybe_run_reminders($pdo);
$soon = 0;
try { $soon = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status='Scheduled' AND TIMESTAMP(appt_date,appt_time) BETWEEN NOW() AND NOW() + INTERVAL 24 HOUR")->fetchColumn(); } catch (PDOException $e) {}
function nav($file, $label, $icon) {
    global $page;
    echo '<a href="'.$file.'" class="'.($page === $file ? 'on' : '').'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$icon.'</svg>'.$label.'</a>';
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Health Center</title>
<link rel="stylesheet" href="style.css"><script src="theme.js"></script><link rel="icon" type="image/svg+xml" href="logo.svg">
</head><body>
<nav class="rail">
  <a class="logo" href="<?= h(home_for(role())) ?>" aria-label="<?= h(CLINIC_NAME) ?> home"><?= logo_html(64) ?><span class="logo-name"><?= h(CLINIC_NAME) ?></span></a>
  <?php
  $r = role(); $staff = in_array($r, ['admin', 'staff'], true);
  nav(home_for($r),'Home','<path d="M3 11l9-8 9 8v10H3z"/>');
  if ($staff) nav('patients.php','Patients','<circle cx="9" cy="8" r="4"/><path d="M2 21c0-4 3-6 7-6s7 2 7 6M17 4a4 4 0 010 8M22 21c0-3-2-5-4-5.5"/>');
  if ($staff) nav('register_patient.php','Register','<path d="M15 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M8.5 11a4 4 0 100-8 4 4 0 000 8zM20 8v6M23 11h-6"/>');
  if ($staff) nav('doctors.php','Doctors','<path d="M6 3v6a5 5 0 0010 0V3M11 14v3a4 4 0 008 0v-1"/><circle cx="19" cy="14" r="2"/>');
  if ($staff || $r === 'doctor') nav('appointments.php','Visits','<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M3 10h18M8 3v4M16 3v4"/>');
  if ($staff || $r === 'doctor') nav('calendar.php','Calendar','<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>');
  if ($r === 'doctor') nav('availability.php','My hours','<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>');
  if ($staff) nav('analytics.php','Analytics','<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>');
  if ($r === 'admin') nav('history.php','History','<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>');
  if ($staff) nav('team.php','Team','<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 20c0-3.5 2.5-5.5 6-5.5s6 2 6 5.5M15 14.5c3 0 5 1.5 5 4.5"/>');
  if ($r === 'admin') nav('users.php','Users','<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>');
  if ($r === 'admin') nav('backup.php','Backup','<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>');
  if ($staff) nav('tutorial.php','Guide','<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 115 0c0 1.5-2.5 2-2.5 3.5M12 17h.01"/>');
  ?>
  <a href="logout.php" class="out"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>Log out</a>
</nav>
<div class="wrap">
<div class="top">
  <span class="date"><?= date('l, j F Y') ?></span>
  <div class="topr">
    <button class="bell" id="themeBtn" type="button" aria-label="Switch theme"></button>
    <?php if ($staff): ?><a class="bell" href="notifications.php" title="Alerts and text messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0112 0c0 7 3 8 3 8H3s3-1 3-8M10 21a2 2 0 004 0"/></svg><?php if ($soon): ?><i><?= $soon ?></i><?php endif; ?></a><?php endif; ?>
    <a class="chip" href="profile.php" title="Edit my profile"><?= avatar_html($me) ?><?= h($me['full_name']) ?> <small class="rolepill"><?= h(role_label(role())) ?></small></a>
  </div>
</div>
<main>
<?php if (isset($_GET['denied'])): ?><div class="msg err">You don't have access to that page.</div><?php endif; ?>
