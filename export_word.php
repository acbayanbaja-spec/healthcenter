<?php
require_once 'db.php'; require_login(); require_once 'analytics_data.php';
if (($_GET['report'] ?? 'analytics') === 'history' ? role() !== 'admin' : !in_array(role(), ['admin', 'staff'], true)) { http_response_code(403); exit('You do not have access to this report.'); }
$report = $_GET['report'] ?? 'analytics';
$now = date('j F Y, H:i');
$th = 'style="background:#2f4bff;color:#ffffff;padding:6px;border:1px solid #999;text-align:left"';
$td = 'style="padding:6px;border:1px solid #999;vertical-align:top"';
$tdb = 'style="padding:6px;border:1px solid #999;vertical-align:top;color:#2f4bff"';
$body = '';

if ($report === 'history') {
    $title = 'Record History Report';
    $rows = history_rows($pdo, $_GET, 2000);
    $body .= '<p>Entries: ' . count($rows) . '</p><table width="100%" style="border-collapse:collapse"><tr>';
    foreach (['When','User','Action','Record','Details'] as $c) $body .= "<th $th>$c</th>";
    $body .= '</tr>';
    foreach ($rows as $r) {
        $body .= "<tr><td $td>" . h($r['logged_at']) . "</td><td $td>" . h($r['user_name']) . "</td><td $td>" . h($r['action']) . "</td><td $td>"
              . h($r['table_name'] . ($r['record_id'] ? ' #' . $r['record_id'] : '')) . "</td><td $td>" . h($r['details']) . '</td></tr>';
    }
    $body .= '</table>';
} else {
    $title = 'Analytics Report';
    $a = get_analytics($pdo); $t = $a['total'];
    $body .= '<h2>Summary</h2><table style="border-collapse:collapse"><tr><th ' . $th . '>Metric</th><th ' . $th . '>Value</th></tr>';
    $sum = ['Total appointments' => $t, 'Completion rate' => ($t ? round($a['done'] / $t * 100) : 0) . '%',
            'Cancellation rate' => ($t ? round($a['canc'] / $t * 100) : 0) . '%', 'Registered patients' => $a['patients'], 'Doctors' => $a['doctors']];
    foreach ($sum as $k => $v) $body .= "<tr><td $td>" . h($k) . "</td><td $td>" . h($v) . '</td></tr>';
    $body .= '</table>';
    foreach ($a['sets'] as $name => $rows) {
        $body .= '<h2>' . h($name) . '</h2>';
        if (!$rows) { $body .= '<p>No data yet.</p>'; continue; }
        $sumN = array_sum(array_column($rows, 'n')) ?: 1; $max = max(array_column($rows, 'n')) ?: 1;
        $body .= '<table style="border-collapse:collapse"><tr><th ' . $th . '>Category</th><th ' . $th . '>Count</th><th ' . $th . '>Share</th><th ' . $th . '>Chart</th></tr>';
        foreach ($rows as $r) {
            $body .= "<tr><td $td>" . h($r['label']) . "</td><td $td>{$r['n']}</td><td $td>" . round($r['n'] / $sumN * 100) . "%</td><td $tdb>"
                  . str_repeat('█', max(1, (int)round($r['n'] / $max * 20))) . '</td></tr>';
        }
        $body .= '</table>';
    }
}
log_event($pdo, 'Exported', 'reports', null, "$title downloaded as a Word file");

$file = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d') . '.doc';
header('Content-Type: application/msword; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="utf-8"><title><?= h($title) ?></title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
<style>body{font-family:Calibri,Arial,sans-serif;font-size:11pt}h1{color:#101935}h2{color:#2f4bff;margin-top:18pt}table{margin-bottom:8pt}</style></head>
<body>
<h1>Health Center — <?= h($title) ?></h1>
<p>Generated on <?= h($now) ?> by <?= h($_SESSION['user']) ?></p>
<?= $body ?>
</body></html>
