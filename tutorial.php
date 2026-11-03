<?php
require_once 'db.php';
if (isset($_GET['done'])) setcookie('newuser', '', time() - 3600, '/');
include 'header.php';
$steps = [
 ['Add your doctors', 'doctors.php', 'Open Doctors',
  'Start with the people who see patients. Enter each doctor\'s name, specialty, phone, and weekly schedule, then choose Save.',
  'A visit needs a doctor, so add at least one before booking.'],
 ['Register your patients', 'patients.php', 'Open Patients',
  'Add each patient with their birth date, gender, phone, and address. Use the search box to find someone by name or phone, and Edit to fix details.',
  'Deleting a patient also deletes their appointments.'],
 ['Book a visit', 'appointments.php', 'Open Visits',
  'Choose a patient, a doctor, a date, and a time, add the reason, and select Book visit.',
  'A doctor cannot be booked twice at the same date and time. If you see that message, pick another slot.'],
 ['Keep statuses up to date', 'appointments.php', 'Open Visits',
  'After a visit, change its status from Scheduled to Completed. If the patient cancels, set it to Cancelled so the slot is clear in your reports.',
  'Only Scheduled visits appear on the dashboard.'],
 ['Let patients know by text', 'notifications.php', 'Open Alerts',
  'When you book a visit, leave "Text the patient a confirmation" ticked. Patients are also texted if the visit is cancelled, and reminded 24 hours and 2 hours before. The bell in the top bar shows how many visits start in the next 24 hours.',
  'Make sure each patient has a phone number. Without one the text is skipped.'],
 ['Read the numbers', 'analytics.php', 'Open Analytics',
  'See completion and cancellation rates, which doctors are busiest, and which months and reasons for visit are most common.',
  'Analytics update as soon as you change a record.'],
 ['Protect the records', 'index.php', 'Go to Home',
  'Keep your password private and select Log out when you leave a shared computer. Patient records are confidential under the User Agreement.',
  'You can reopen this guide any time from Guide in the side menu.'],
];
$n = count($steps);
?>
<h2>Quick guide</h2>
<div class="card" id="tour">
  <div class="tour-bar"><i id="bar"></i></div>
  <?php foreach ($steps as $i => $s): ?>
  <section class="step<?= $i === 0 ? ' on' : '' ?>">
    <span class="num"><?= $i + 1 ?></span>
    <h3><?= h($s[0]) ?></h3>
    <p><?= h($s[3]) ?></p>
    <div class="tip"><?= h($s[4]) ?></div>
    <a class="btn gray" href="<?= h($s[1]) ?>"><?= h($s[2]) ?></a>
  </section>
  <?php endforeach; ?>
  <div class="tour-nav">
    <button type="button" id="prev" class="gray btn">Back</button>
    <button type="button" id="next">Next step</button>
    <a id="finish" class="btn" href="tutorial.php?done=1" style="display:none">Finish guide</a>
    <span class="count" id="count"></span>
  </div>
</div>
<script>
(function(){
  var steps=[].slice.call(document.querySelectorAll('.step')),i=0,n=steps.length;
  var prev=document.getElementById('prev'),next=document.getElementById('next'),fin=document.getElementById('finish');
  function show(k){
    i=k;steps.forEach(function(s,j){s.classList.toggle('on',j===i)});
    document.getElementById('bar').style.width=((i+1)/n*100)+'%';
    document.getElementById('count').textContent='Step '+(i+1)+' of '+n;
    prev.style.visibility=i===0?'hidden':'visible';
    next.style.display=i===n-1?'none':'inline-block';
    fin.style.display=i===n-1?'inline-block':'none';
  }
  prev.onclick=function(){show(i-1)};next.onclick=function(){show(i+1)};show(0);
})();
</script>
<?php include 'footer.php'; ?>
