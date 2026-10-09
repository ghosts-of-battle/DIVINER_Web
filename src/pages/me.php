<?php
/**
 * Your own details.
 *
 * WHAT A PERSON MAY CHANGE ABOUT THEMSELVES is deliberately short - a
 * preferred name and two ways to be contacted, all optional. Rank, role,
 * group, skills and awards are things the unit gives you, and a roster where
 * people set their own rank is not a roster.
 *
 * THE WRITE IS NOT TRUSTED FROM HERE. ghostd_set_self_path checks the field is
 * allowed and that the uid is the signed-in Steam id, so this page cannot be
 * talked into editing somebody else by a crafted POST.
 */

declare(strict_types=1);

require_once __DIR__ . '/../system.php';

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../records.php';   // ghostd_rank_insignia()

$cfg     = ghostd_config();
$storeId = $cfg['unit'];
$uid     = ghostd_self_uid();

$msg = null;
$err = null;

require_once __DIR__ . '/../photos.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $what = (string) ($_POST['what'] ?? 'details');
        if ($what === 'photo') {
            $msg = ghostd_photo_put_self($_FILES['photo'] ?? []);
        } elseif ($what === 'photo_remove') {
            ghostd_photo_remove_self();
            $msg = 'Photo removed.';
        }
        $saved = [];
        foreach ($what === 'details' ? GHOSTD_SELF_FIELDS : [] as $field => $label) {
            if (!array_key_exists($field, $_POST)) {
                continue;
            }
            $value = trim((string) $_POST[$field]);

            if ($field === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $err = 'That does not look like an email address. Leave it empty if you would rather not give one.';
                continue;
            }
            if ($field === 'discordId' && $value !== '' && !preg_match('/^\d{5,25}$/', $value)) {
                $err = 'A Discord id is the long number, not your handle - Discord, '
                     . 'Settings, Advanced, Developer Mode, then right-click yourself and Copy User ID.';
                continue;
            }

            ghostd_set_self_path($storeId, $uid, $field, $value);
            $saved[] = $label;
        }
        if ($saved !== [] && $err === null) {
            $msg = implode(' and ', $saved) . ' saved.';
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$store  = null;
$me     = null;
$dbDown = null;
try {
    $store = ghostd_get($storeId);
    $me    = $store['players'][$uid] ?? null;
} catch (Throwable $e) {
    $dbDown = $e->getMessage();
}

$labels = static function (string $section): array {
    try {
        $doc = ghostd_get(ghostd_config()['unit'] . '.' . $section);
    } catch (Throwable $e) {
        return [];
    }
    $items = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];
    $out = [];
    foreach ($items as $id => $it) {
        $out[(string) $id] = is_array($it) ? (string) ($it['name'] ?? $it['title'] ?? $id) : (string) $id;
    }
    return $out;
};

ghostd_head('My details', 'me');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

if ($dbDown !== null) {
    ghostd_flash('bad', 'The roster cannot be read right now: ' . $dbDown);
    ghostd_foot();
    return;
}

if ($me === null) {
    ?>
    <div class="card">
      <h2>You are not on the roster yet</h2>
      <p>Steam says you are <?= steamlink($uid) ?>, and that id has no
      record with this unit. A record is created the first time you join the
      server - so if you have played here, it will already exist under a
      different Steam account than the one you just used.</p>
      <p class="dim">If you are joining, an admin can add you before your first
      session: give them the id above.</p>
    </div>
    <?php
    ghostd_foot();
    return;
}

$rankNames   = $labels('ranks');
$statusNames = $labels('statuses');
?>
<div class="tiles">
  <div class="tile"><span class="n"><?= h((string) ($me['operatorId'] ?? '-')) ?></span>operator id</div>
  <div class="tile"><span class="n"><?= ghostd_rank_insignia((string) ($me['rankId'] ?? '')) ?><?= h($rankNames[(string) ($me['rankId'] ?? '')] ?? '-') ?></span>rank</div>
  <div class="tile"><span class="n"><?= h($statusNames[(string) ($me['statusId'] ?? '')] ?? '-') ?></span>status</div>
  <div class="tile"><span class="n"><?= h((string) ($me['enlistedAt'] ?? '-')) ?></span>enlisted</div>
</div>

<h2>What you can change</h2>
<p class="dim">All of it is optional. The unit already has your Steam id; the
rest is what you choose to share.</p>

<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

  <label for="f_milsimName">Preferred name <span class="dim">optional - what the unit calls you, e.g. "Cpl J. Miller"</span></label>
  <input type="text" id="f_milsimName" name="milsimName" maxlength="64"
         value="<?= h((string) ($me['milsimName'] ?? '')) ?>">

  <label for="f_discordId">Discord id <span class="dim">optional - the long number, not your handle. Lets the Discord bot match you to this record.</span></label>
  <input type="text" id="f_discordId" name="discordId" inputmode="numeric" maxlength="25"
         value="<?= h((string) ($me['discordId'] ?? '')) ?>">

  <label for="f_email">Email <span class="dim">optional - only for the unit to contact you. Never shown to other players and never posted by the bot.</span></label>
  <input type="text" id="f_email" name="email" maxlength="120"
         value="<?= h((string) ($me['email'] ?? '')) ?>">

  <div class="actions"><button type="submit">Save</button></div>
</form>

<?php $photo = ghostd_photo($uid); ?>
<h2>Your photo</h2>
<p class="dim">Optional. It goes on the roster card, on this site and on the
unit's public website, so pick one you are happy for anyone to see. PNG,
JPEG or WebP; a head shot works best. Once you pick a file you can drag it
about and zoom it so the right part fills the card's frame.</p>
<div class="card photo-card">
  <div class="photo-cur">
    <?php if ($photo !== null): ?>
      <img src="<?= h(ghostd_photo_data_url($photo)) ?>" alt="Your photo">
    <?php else: ?>
      <span class="dim">no photo</span>
    <?php endif; ?>
  </div>
  <div class="photo-forms">
    <form method="post" enctype="multipart/form-data" class="fields" id="photo-form">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="what" value="photo">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= GHOSTD_PHOTO_MAX ?>">
      <label for="f_photo"><?= $photo !== null ? 'Replace it' : 'Add one' ?></label>
      <input type="file" id="f_photo" name="photo" accept="image/png,image/jpeg,image/webp" required>
      <div class="crop" id="crop" hidden>
        <canvas id="crop-c" width="240" height="280" aria-label="Your photo, as the card will frame it"></canvas>
        <div class="crop-tools">
          <label for="crop-z">Zoom</label>
          <input type="range" id="crop-z" min="0.6" max="3" step="0.01" value="1">
          <span class="dim">Drag the picture to place it. The frame is what the card shows; zoom out and the rest is the card's slate.</span>
        </div>
      </div>
      <div class="actions"><button type="submit">Upload</button></div>
    </form>
    <?php if ($photo !== null): ?>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="what" value="photo_remove">
      <button type="submit" class="btnquiet">Remove the photo</button>
      <span class="dim">added <?= h($photo['at']) ?></span>
    </form>
    <?php endif; ?>
  </div>
</div>
<script>
// The frame is the roster card's photo box (6:7). The member places the
// picture in it here - drag to move, slider to zoom - and what is sent is
// that frame, rendered at 480x560 JPEG: cropped and shrunk in the browser,
// since the server has no GD. Without this script the file goes up as it is
// and the server's 1.5 MB cap decides.
(() => {
  const form = document.getElementById('photo-form');
  const input = document.getElementById('f_photo');
  const box = document.getElementById('crop');
  const c = document.getElementById('crop-c');
  const zoom = document.getElementById('crop-z');
  if (!form || !input || !c || !window.DataTransfer || !window.createImageBitmap) return;
  const ctx = c.getContext('2d');
  const W = c.width, H = c.height;
  let bmp = null, base = 1, z = 1, ox = 0, oy = 0, ready = false;

  // The picture stays inside the frame when it is smaller than it (zoomed
  // out) and covers it when it is larger: the offset is kept inside
  // whichever slack the current zoom leaves. What it does not cover is the
  // card's own slate, so the roster shows a border, not a hole.
  const SLATE = '#2c3532';
  const clamp = () => {
    const s = base * z, w = bmp.width * s, h = bmp.height * s;
    ox = Math.min(Math.max(0, W - w), Math.max(Math.min(0, W - w), ox));
    oy = Math.min(Math.max(0, H - h), Math.max(Math.min(0, H - h), oy));
  };
  const draw = () => {
    if (!bmp) return;
    clamp();
    ctx.fillStyle = SLATE; ctx.fillRect(0, 0, W, H);
    const s = base * z;
    ctx.drawImage(bmp, ox, oy, bmp.width * s, bmp.height * s);
  };

  input.addEventListener('change', async () => {
    ready = false; bmp = null; box.hidden = true;
    const f = input.files && input.files[0];
    if (!f || !/^image\/(png|jpeg|webp)$/.test(f.type)) return;
    try {
      bmp = await createImageBitmap(f, { imageOrientation: 'from-image' });
    } catch (e) { return; }
    base = Math.max(W / bmp.width, H / bmp.height);
    z = 1; zoom.value = '1';
    ox = (W - bmp.width * base) / 2; oy = (H - bmp.height * base) / 2;
    box.hidden = false; draw();
  });

  zoom.addEventListener('input', () => {
    if (!bmp) return;
    // Zoom about the centre of the frame, so the subject stays put.
    const nz = Number(zoom.value), k = nz / z;
    ox = W / 2 - (W / 2 - ox) * k; oy = H / 2 - (H / 2 - oy) * k;
    z = nz; draw();
  });

  let drag = null;
  c.addEventListener('pointerdown', (e) => { if (!bmp) return; drag = { x: e.clientX - ox, y: e.clientY - oy }; c.setPointerCapture(e.pointerId); });
  c.addEventListener('pointermove', (e) => { if (!drag) return; ox = e.clientX - drag.x; oy = e.clientY - drag.y; draw(); });
  const stop = () => { drag = null; };
  c.addEventListener('pointerup', stop); c.addEventListener('pointercancel', stop);

  form.addEventListener('submit', async (e) => {
    if (!bmp || ready) return;          // nothing framed, or already rendered: let it go
    e.preventDefault();
    try {
      const out = document.createElement('canvas'); out.width = 480; out.height = 560;
      const k = out.width / W, s = base * z * k, o = out.getContext('2d');
      o.fillStyle = SLATE; o.fillRect(0, 0, out.width, out.height);
      o.drawImage(bmp, ox * k, oy * k, bmp.width * s, bmp.height * s);
      const blob = await new Promise((r) => out.toBlob(r, 'image/jpeg', 0.88));
      if (blob) {
        const dt = new DataTransfer();
        const name = (input.files[0].name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
        dt.items.add(new File([blob], name, { type: 'image/jpeg' }));
        input.files = dt.files;
      }
    } catch (err) { /* the original is sent instead */ }
    ready = true;
    form.submit();
  });
})();
</script>

<h2>My PAC requests</h2>
<?php
  require_once __DIR__ . '/../tickets.php';
  $mine = ghostd_my_tickets($uid);
?>
<?php if ($mine === []): ?>
  <p class="dim">You have not raised any.
  <a href="?page=tickets">Raise one</a> - leave, an award recommendation, a
  request or a problem.</p>
<?php else: ?>
  <table class="grid">
    <thead><tr><th>Id</th><th>Kind</th><th>Subject</th><th>State</th><th>Replies</th><th>Raised</th></tr></thead>
    <tbody>
    <?php foreach ($mine as $t): ?>
      <?php $st = (string) ($t['status'] ?? 'open'); ?>
      <tr>
        <td><a href="?page=ticket&amp;id=<?= urlencode((string) ($t['id'] ?? '')) ?>"><code><?= h((string) ($t['id'] ?? '')) ?></code></a></td>
        <td><?= h(ghostd_ticket_kinds()[(string) ($t['kind'] ?? '')]['label'] ?? '') ?></td>
        <td><a href="?page=ticket&amp;id=<?= urlencode((string) ($t['id'] ?? '')) ?>"><?= h((string) ($t['subject'] ?? '')) ?></a></td>
        <td><span class="pill <?= $st === 'open' ? '' : ($st === 'declined' ? 'hot' : 'dimpill') ?>"><?= h(GHOSTD_TICKET_STATUSES[$st] ?? $st) ?></span></td>
        <td><?= count($t['replies'] ?? []) ?></td>
        <td class="dim"><?= h((string) ($t['createdAt'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="dim">You see the public replies on your own requests. Admins may
  also keep private notes, which you will not see.</p>
<?php endif; ?>

<h2>What only an admin can change</h2>
<table class="kv">
  <tr><th>Name</th><td><?= cell($me['name'] ?? null) ?></td></tr>
  <tr><th>Rank</th><td><?= ghostd_rank_insignia((string) ($me['rankId'] ?? '')) ?><?= h($rankNames[(string) ($me['rankId'] ?? '')] ?? (string) ($me['rankId'] ?? '')) ?></td></tr>
  <tr><th>Role</th><td><?= cell($me['roleId'] ?? null) ?></td></tr>
  <tr><th>Group</th><td><?= cell($me['groupId'] ?? null) ?></td></tr>
  <tr><th>Skills</th><td><?= is_array($me['skillIds'] ?? null) ? count($me['skillIds']) : 0 ?></td></tr>
  <tr><th>Awards</th><td><?= is_array($me['awards'] ?? null) ? count($me['awards']) : 0 ?></td></tr>
</table>
<p class="note">Ask an admin in game if any of that is wrong. Changes you make
here are read by the game at the next mission start.</p>
<?php
ghostd_foot();
