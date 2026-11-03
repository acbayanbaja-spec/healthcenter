<?php require_once 'db.php'; ?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Agreement</title><link rel="stylesheet" href="style.css"><script src="theme.js"></script><link rel="icon" type="image/svg+xml" href="logo.svg"></head><body>
<div class="legal">
<a class="back" href="<?= empty($_SESSION['user']) ? 'login.php' : h(home_for(role())) ?>">Back</a>
<div class="card">
<h2>User Agreement</h2>
<p>Last updated: <?= date('j F Y') ?>. By creating an account or using the Health Center Information and Appointment Management System, you agree to the terms below.</p>

<h3>1. Who may use the system</h3>
<p>The system is for health center staff, doctors, and registered patients. Each person can see only what their role allows. You must give accurate details when you register and keep them up to date.</p>

<h3>2. Your account</h3>
<ul><li>Keep your password private and do not share your account.</li>
<li>Log out when you use a shared computer.</li>
<li>Tell an administrator right away if you think someone else used your account.</li></ul>

<h3>3. Patient privacy</h3>
<p>Patient and appointment records are confidential. Access only the records you need for your work. Do not copy, share, or discuss patient information outside the health center unless the law or the patient allows it.</p>

<h3>4. Accurate records</h3>
<p>Enter patient, doctor, and appointment details correctly. Keep appointment statuses current so schedules and reports stay reliable.</p>

<h3>5. Acceptable use</h3>
<ul><li>Do not try to access accounts or data you are not authorized to see.</li>
<li>Do not disrupt, test, or attempt to break the system.</li>
<li>Do not enter false or misleading information.</li></ul>

<h3>6. Monitoring</h3>
<p>The health center may review system activity to protect patient data and make sure these terms are followed.</p>

<h3>7. Suspension</h3>
<p>The health center may suspend or remove an account that breaks this agreement.</p>

<h3>8. Changes</h3>
<p>This agreement may be updated. Continued use of the system means you accept the current version.</p>

<p><small style="color:var(--muted)">This is a general template for a school or small-clinic project. Have it reviewed against your local privacy and health-data laws before real use.</small></p>
</div></div></body></html>
