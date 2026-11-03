<?php
require_once 'db.php'; require_login();
$uid = (int)$_SESSION['uid'];
$msg = ''; $err = ''; $pwmsg = ''; $pwerr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $s = $pdo->prepare('SELECT password FROM users WHERE user_id=?'); $s->execute([$uid]); $hash = $s->fetchColumn();
    $new = $_POST['new_password'] ?? '';
    if (!$hash || !password_verify($_POST['current_password'] ?? '', $hash)) $pwerr = 'Your current password is not correct.';
    elseif (strlen($new) < 6) $pwerr = 'The new password must be at least 6 characters.';
    elseif ($new !== ($_POST['confirm_password'] ?? '')) $pwerr = 'The new passwords do not match.';
    elseif (password_verify($new, $hash)) $pwerr = 'Choose a password different from the current one.';
    else {
        $pdo->prepare('UPDATE users SET password=? WHERE user_id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        log_event($pdo, 'Updated', 'users', $uid, 'Password changed');
        session_regenerate_id(true);
        $pwmsg = 'Password changed.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['full_name'] ?? '');
        if ($name !== '' && $name !== $_SESSION['user']) {
            $pdo->prepare('UPDATE users SET full_name=? WHERE user_id=?')->execute([$name, $uid]);
            log_event($pdo, 'Updated', 'users', $uid, "full_name: {$_SESSION['user']} → $name");
            $_SESSION['user'] = $name;
        }
        foreach (['profile' => 2, 'cover' => 4] as $kind => $mb) {
            if (!empty($_POST["remove_$kind"])) {
                remove_image($pdo, $uid, $kind);
                log_event($pdo, 'Updated', 'users', $uid, ucfirst($kind) . ' photo removed');
            } else {
                [$okk, $m] = save_image($pdo, $uid, $kind, $_FILES["{$kind}_photo"] ?? null, $mb * 1048576);
                if ($okk === false) $err .= ucfirst($kind) . " photo: $m ";
                if ($okk === true) log_event($pdo, 'Updated', 'users', $uid, ucfirst($kind) . ' photo changed');
            }
        }
        if (!$err) $msg = 'Profile saved.';
    } catch (PDOException $e) {
        $err = 'Could not save. Run migrate_photos.sql in phpMyAdmin first.';
    }
}
include 'header.php';
$ecg = '<svg class="ecg" viewBox="0 0 600 110" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60 H120 L140 60 L158 20 L182 100 L204 8 L224 60 H330 L346 60 L362 34 L380 84 L396 60 H600"/></svg>';
$hasCover = !empty($me['cover_photo']); $hasPhoto = !empty($me['profile_photo']);
?>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<div class="cover" id="coverBox"<?= $hasCover ? ' style="background-image:url(\'' . photo_url($me['cover_photo']) . '\')"' : '' ?>><?= $hasCover ? '' : $ecg ?></div>
<div class="me">
  <span id="avatarBox"><?= avatar_html($me, 'xl') ?></span>
  <div><h2 id="nameOut"><?= h($me['full_name']) ?></h2><span class="note">@<?= h($me['username']) ?></span></div>
</div>
<div class="card"><h3>Edit profile</h3>
<form method="post" enctype="multipart/form-data" class="grid">
  <div><label class="note">Full name</label><input name="full_name" value="<?= h($me['full_name']) ?>" required></div>
  <div><label class="note">Profile photo (JPG, PNG or WebP, up to 2 MB)</label>
    <input type="file" id="pp" name="profile_photo" accept="image/jpeg,image/png,image/webp">
    <?php if ($hasPhoto): ?><label class="agree"><input type="checkbox" name="remove_profile" value="1"> Remove current photo</label><?php endif; ?></div>
  <div><label class="note">Cover photo (JPG, PNG or WebP, up to 4 MB)</label>
    <input type="file" id="cp" name="cover_photo" accept="image/jpeg,image/png,image/webp">
    <?php if ($hasCover): ?><label class="agree"><input type="checkbox" name="remove_cover" value="1"> Remove current cover</label><?php endif; ?></div>
  <div><button>Save profile</button> <a class="btn gray" href="team.php">See the team</a></div>
</form></div>
<div class="card"><h3>Change password</h3>
<?php if ($pwmsg): ?><div class="msg ok"><?= h($pwmsg) ?></div><?php endif; ?>
<?php if ($pwerr): ?><div class="msg err"><?= h($pwerr) ?></div><?php endif; ?>
<form method="post" class="grid" autocomplete="off">
  <input type="hidden" name="change_password" value="1">
  <input type="password" name="current_password" placeholder="Current password" required autocomplete="current-password">
  <input type="password" name="new_password" placeholder="New password (min 6)" required autocomplete="new-password">
  <input type="password" name="confirm_password" placeholder="Confirm new password" required autocomplete="new-password">
  <div><button>Change password</button></div>
</form></div>
<script>
document.getElementById('pp').onchange=function(){var f=this.files[0];if(!f)return;var inp=this,img=new Image();img.onload=function(){var s=Math.min(img.width,img.height),c=document.createElement('canvas');c.width=c.height=Math.min(s,512);c.getContext('2d').drawImage(img,(img.width-s)/2,(img.height-s)/2,s,s,0,0,c.width,c.height);c.toBlob(function(b){if(b&&window.DataTransfer){var dt=new DataTransfer();dt.items.add(new File([b],'avatar.jpg',{type:'image/jpeg'}));inp.files=dt.files;}document.getElementById('avatarBox').innerHTML='<img class="av xl" alt="" src="'+URL.createObjectURL(b||f)+'">';},'image/jpeg',.9);};img.src=URL.createObjectURL(f);};
document.getElementById('cp').onchange=function(){var f=this.files[0];if(!f)return;var b=document.getElementById('coverBox');b.innerHTML='';b.style.backgroundImage='url('+URL.createObjectURL(f)+')'};
</script>
<?php include 'footer.php'; ?>
