<?php require_once 'db.php'; require_role(['admin', 'staff']); include 'header.php'; require_once 'analytics_data.php';
$a = get_analytics($pdo); $total = $a['total'];
function bars($rows) {
    if (!$rows) { echo '<p class="empty">No data yet.</p>'; return; }
    $max = max(array_column($rows, 'n')) ?: 1;
    foreach ($rows as $r) {
        $w = round($r['n'] / $max * 100);
        echo '<div style="display:flex;align-items:center;gap:8px;margin:6px 0"><span style="width:150px;font-size:.9rem">' . h($r['label']) . '</span>'
           . '<div style="flex:1;background:#e6ebfb;border-radius:99px"><div style="width:' . $w . '%;background:var(--cobalt);color:#fff;padding:2px 8px;border-radius:99px;font-size:.8rem;min-width:26px">' . $r['n'] . '</div></div></div>';
    }
}
?>
<div class="pagehead"><h2>Analytics</h2><a class="btn" href="export_word.php?report=analytics">Download Word report</a></div>
<p class="note">The Word report includes every table below with counts and shares, and each download is recorded in History.</p>
<div class="stats">
  <div class="card"><b><?= $total ?></b>Total appointments</div>
  <div class="card"><b><?= $total ? round($a['done'] / $total * 100) : 0 ?>%</b>Completion rate</div>
  <div class="card"><b><?= $total ? round($a['canc'] / $total * 100) : 0 ?>%</b>Cancellation rate</div>
  <div class="card"><b><?= $a['patients'] ?></b>Registered patients</div>
</div>
<div class="stats" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr))">
<?php foreach ($a['sets'] as $title => $rows): ?>
  <div class="card"><h3><?= h($title) ?></h3><?php bars($rows); ?></div>
<?php endforeach; ?>
</div>
<?php include 'footer.php'; ?>
