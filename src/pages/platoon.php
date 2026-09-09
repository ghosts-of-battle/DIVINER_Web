<?php
/**
 * One platoon, in sections.
 *
 * A PLATOON IS A LIST OF SQUADS AND A RADIO. Set how many squads it holds, pick
 * them from the squads that exist, give it its net and its long-range channel.
 * Nothing here is typed free-hand that could be picked instead: a squad name
 * typed by hand that matches no squad is a platoon with a hole in it, and the
 * only symptom is a group missing from the menu.
 *
 * ITS ARSENAL AND MOTORPOOL are documents named after it - plt_<NAME> - and are
 * summarised here with a button into the one editor that already exists, rather
 * than that editor being repeated in a third place.
 */

declare(strict_types=1);

require_once __DIR__ . '/../orbat.php';
require_once __DIR__ . '/../inline_edit.php';

$unit = ghostd_config()['unit'];

$variant = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
// NO VERSION NAMED MEANS THE DEFAULT ONE. Every order of battle on this unit is
// named, so the unnamed document does not exist and reading it found no squad
// at all (2026-09-09).
if ($variant === '' || !ghostd_variant_ok($variant)) {
    $variant = ghostd_default_orbat_id();
}

$vq = $variant !== '' ? '&amp;v=' . urlencode($variant) : '';

$pid = (string) ($_GET['id'] ?? ($_POST['id'] ?? ''));
$new = ($pid === '' || $pid === '+');

// ONE SECTION ON SCREEN AT A TIME - the section is part of the address, so a
// save comes back to where it was made and a link can point at one.
$sec = (string) ($_GET['sec'] ?? ($_POST['sec'] ?? 'identity'));
if (!isset(GHOSTD_PLATOON_SECTIONS[$sec])) {
    $sec = 'identity';
}

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    $what = (string) ($_POST['what'] ?? '');

    try {
        switch ($what) {

            // The arsenal and the motorpool are edited in the boxes on this
            // page - src/pages/_edit_inline.php - not on a template page.
            case 'inlineedit':
                $msg = ghostd_inline_save($_POST);
                break;

            case 'create':
                $wanted = trim((string) ($_POST['newid'] ?? ''));
                if ($wanted === '') {
                    throw new RuntimeException('A platoon needs an id - it is what its squads are hung off.');
                }
                if (ghostd_platoon($variant, $wanted) !== null) {
                    throw new RuntimeException('A platoon called "' . $wanted . '" already exists.');
                }
                $pname = trim((string) ($_POST['name'] ?? '')) ?: $wanted;
                ghostd_orbat_edit($variant, static function (array &$doc) use ($wanted, $pname) {
                    $ps = is_array($doc['platoons'] ?? null) ? $doc['platoons'] : [];
                    $ps[] = [$wanted, $pname, '', '', []];
                    $doc['platoons'] = $ps;
                });
                header('Location: ?page=platoon&id=' . urlencode($wanted) . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
                exit;

            case 'identity':
                $to = trim((string) ($_POST['newid'] ?? $pid));
                if ($to === '') {
                    throw new RuntimeException('A platoon needs an id.');
                }
                if ($to !== $pid && ghostd_platoon($variant, $to) !== null) {
                    throw new RuntimeException('A platoon called "' . $to . '" already exists.');
                }
                $fields = [
                    trim((string) ($_POST['name'] ?? '')),
                    trim((string) ($_POST['callsign'] ?? '')),
                    trim((string) ($_POST['net'] ?? '')),
                ];
                ghostd_orbat_edit($variant, static function (array &$doc) use ($pid, $to, $fields) {
                    $ps = is_array($doc['platoons'] ?? null) ? $doc['platoons'] : [];
                    foreach ($ps as $i => $p) {
                        if ((string) ($p[0] ?? '') === $pid) {
                            $ps[$i][0] = $to;
                            $ps[$i][1] = $fields[0];
                            $ps[$i][2] = $fields[1];
                            $ps[$i][3] = $fields[2];
                        }
                    }
                    $doc['platoons'] = $ps;
                });
                if ($to !== $pid) {
                    // Its LR channel is keyed by the id, so it moves too.
                    ghostd_radio_edit(static function (array &$items) use ($pid, $to) {
                        $rows = is_array($items['lrPlatoonChannel'] ?? null) ? $items['lrPlatoonChannel'] : [];
                        foreach ($rows as $i => $r) {
                            if (is_array($r) && (string) ($r[0] ?? '') === $pid) { $rows[$i][0] = $to; }
                        }
                        $items['lrPlatoonChannel'] = $rows;
                    });
                    header('Location: ?page=platoon&id=' . urlencode($to) . ($variant !== '' ? '&v=' . urlencode($variant) : '') . '&renamed=1');
                    exit;
                }
                $msg = 'Identity saved.';
                break;

            case 'squads':
                $picked = [];
                foreach ((array) ($_POST['squads'] ?? []) as $i => $s) {
                    $s = trim((string) $s);
                    if ($s !== '') { $picked[(int) $i] = $s; }
                }
                ksort($picked);
                // A squad listed twice is one squad the platoon thinks it has
                // two of; the second is quietly the same group in game.
                $picked = array_values(array_unique($picked));

                ghostd_orbat_edit($variant, static function (array &$doc) use ($pid, $picked) {
                    $ps = is_array($doc['platoons'] ?? null) ? $doc['platoons'] : [];
                    foreach ($ps as $i => $p) {
                        if ((string) ($p[0] ?? '') === $pid) { $ps[$i][4] = $picked; }
                    }
                    $doc['platoons'] = $ps;
                });
                $msg = count($picked) . ' squads saved.';
                break;

            case 'radio':
                $ch = trim((string) ($_POST['lr'] ?? ''));
                ghostd_radio_edit(static function (array &$items) use ($pid, $ch) {
                    ghostd_channel_set($items, 'lrPlatoonChannel', $pid,
                        $ch === '' ? null : [(int) $ch]);
                });
                $msg = $ch === ''
                    ? 'Long range channel cleared - its people fall back to the plan default.'
                    : 'Long range channel saved.';
                break;

            case 'delete':
                ghostd_orbat_edit($variant, static function (array &$doc) use ($pid) {
                    $ps = is_array($doc['platoons'] ?? null) ? $doc['platoons'] : [];
                    $doc['platoons'] = array_values(array_filter($ps,
                        static fn($p) => (string) ($p[0] ?? '') !== $pid));
                });
                ghostd_radio_edit(static function (array &$items) use ($pid) {
                    ghostd_channel_set($items, 'lrPlatoonChannel', $pid, null);
                });
                header('Location: ?page=orbat&s=platoons' . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
                exit;

            default:
                throw new RuntimeException('Nothing said which section to save.');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// ---- new ------------------------------------------------------------------
if ($new) {
    ghostd_head('New platoon', 'orbat');
    if ($err !== null) { ghostd_flash('bad', $err); }
    ?>
    <p class="dim"><a href="?page=orbat&amp;s=platoons<?= $vq ?>">&larr; Platoons</a></p>
    <h2>A new platoon</h2>
    <p class="note">The id is what the mod hangs its squads off; the name is what
    a player reads. Both can be the same thing.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="v" value="<?= h($variant) ?>">
      <input type="hidden" name="what" value="create">
      <div class="fields">
        <label>Id <span class="dim">BANSHEE, 1PLT</span>
          <input type="text" name="newid" required></label>
        <label>Shown as
          <input type="text" name="name"></label>
      </div>
      <div class="actions">
        <button type="submit">Create it</button>
        <a class="btnlink" href="?page=orbat&amp;s=platoons<?= $vq ?>">Cancel</a>
      </div>
    </form>
    <?php
    ghostd_foot();
    return;
}

$p = ghostd_platoon($variant, $pid);
if ($p === null) {
    ghostd_head('Platoon', 'orbat');
    ghostd_flash('bad', 'No platoon called "' . h($pid) . '" in this ORBAT.');
    echo '<p><a href="?page=orbat&amp;s=platoons' . $vq . '">Back to the platoons</a></p>';
    ghostd_foot();
    return;
}

$o = ghostd_orbat($variant);

// Every squad that exists, and which platoon has already claimed it.
$allSquads = [];
foreach ($o['groups'] as $g) {
    $allSquads[] = (string) ($g[0] ?? '');
}
sort($allSquads);
$claimedBy = [];
foreach ($o['platoons'] as $q) {
    foreach ((array) ($q[4] ?? []) as $s) {
        if ((string) ($q[0] ?? '') !== $pid) {
            $claimedBy[(string) $s] = (string) ($q[1] ?? $q[0] ?? '');
        }
    }
}

$radio       = ghostd_radio_items();
$lrChannels  = ghostd_lr_channels($radio);
$lrOf        = ghostd_lrplt_of($radio);
$lr          = $lrOf[$pid] ?? null;

$netIds = [];
try {
    foreach (ghostd_template_items('nets') as $nid => $n) {
        $netIds[(string) $nid] = (string) ($n['name'] ?? '');
    }
} catch (Throwable $e) {
    $netIds = [];
}

$pv           = ghostd_platoon_variant($pid);
$arsenalHas   = ghostd_variant_summary('arsenal', $pv);
$motorpoolHas = ghostd_variant_summary('motorpool', $pv);

// How many squad rows to draw.
$want = (int) ($_GET['n'] ?? max(1, count($p['squads'])));
$want = max(1, min(20, $want));

$csrf = ghostd_csrf_token();
$open = static function (string $what) use ($sec, $csrf, $pid, $variant) {
    echo '<form method="post">'
       . '<input type="hidden" name="csrf" value="' . h($csrf) . '">'
       . '<input type="hidden" name="id" value="' . h($pid) . '">'
       . '<input type="hidden" name="v" value="' . h($variant) . '">'
       . '<input type="hidden" name="sec" value="' . h($sec) . '">'
       . '<input type="hidden" name="what" value="' . h($what) . '">';
};

ghostd_head($p['name'] !== '' ? $p['name'] : $pid, 'orbat');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if (isset($_GET['renamed'])) { ghostd_flash('good', 'Renamed, with its long range channel.'); }
?>
<p class="dim"><a href="?page=orbat&amp;s=platoons<?= $vq ?>">&larr; Platoons</a> &middot;
<code><?= h(ghostd_orbat_doc_id($variant)) ?></code> &middot;
<?= count($p['squads']) ?> squads</p>

<nav class="subrail">
  <?php foreach (GHOSTD_PLATOON_SECTIONS as $k => $label): ?>
    <a class="<?= $sec === $k ? 'on' : '' ?>"
       href="?page=platoon&amp;id=<?= urlencode($pid) ?>&amp;sec=<?= h($k) ?><?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ------------------------------------------------------------ identity -->
<?php if ($sec === 'identity'): ?>
<section class="tsection" id="identity">
  <h2>Identity</h2>
  <?php $open('identity'); ?>
    <div class="fields">
      <label>Id <span class="dim">what the mod hangs its squads off</span>
        <input type="text" name="newid" value="<?= h($p['id']) ?>" required></label>
      <label>Shown as <span class="dim">what a player reads</span>
        <input type="text" name="name" value="<?= h($p['name']) ?>"></label>
      <label>Callsign
        <input type="text" name="callsign" value="<?= h($p['callsign']) ?>"></label>
      <label>Messaging net <span class="dim">the TAC//MSG net it commands on</span>
        <select name="net">
          <option value="">- none -</option>
          <?php foreach ($netIds as $nid => $nname): ?>
            <option value="<?= h((string) $nid) ?>" <?= $p['net'] === (string) $nid ? 'selected' : '' ?>>
              <?= h((string) $nid) ?><?= $nname !== '' ? ' - ' . h($nname) : '' ?></option>
          <?php endforeach; ?>
          <?php if ($p['net'] !== '' && !isset($netIds[$p['net']])): ?>
            <option value="<?= h($p['net']) ?>" selected><?= h($p['net']) ?> (no such net)</option>
          <?php endif; ?>
        </select></label>
    </div>
    <div class="actions"><button type="submit">Save identity</button></div>
  </form>
  <p class="dim"><a href="?page=configedit&amp;t=nets">Edit the net list</a> to add one that is not offered.</p>
</section>
<?php endif; ?>

<!-- -------------------------------------------------------------- squads -->
<?php if ($sec === 'squads'): ?>
<section class="tsection" id="squads">
  <h2>Squads <span class="dim"><?= count($p['squads']) ?></span></h2>
  <p class="dim">Only squads that exist can be picked. A squad no platoon lists
  never appears in the group menu, and a platoon listing one that does not exist
  has a hole in it.</p>

  <form method="get" class="inline">
    <input type="hidden" name="page" value="platoon">
    <input type="hidden" name="id" value="<?= h($pid) ?>">
    <?php if ($variant !== ''): ?><input type="hidden" name="v" value="<?= h($variant) ?>"><?php endif; ?>
    <label for="n">Number of squads</label>
    <input type="number" id="n" name="n" min="1" max="20" value="<?= $want ?>">
    <button type="submit">Set</button>
    <span class="dim">Nothing is written until you save the squads.</span>
  </form>

  <?php if ($allSquads === []): ?>
    <p class="note readonly">No squads exist yet.
    <a href="?page=squad&amp;sq=%2B<?= $vq ?>">Create one</a> first.</p>
  <?php endif; ?>

  <?php $open('squads'); ?>
    <table class="grid">
      <thead><tr><th>#</th><th>Squad</th><th></th></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < $want; $i++): ?>
        <?php $sel = (string) ($p['squads'][$i] ?? ''); ?>
        <tr>
          <td><strong><?= $i + 1 ?></strong></td>
          <td>
            <select name="squads[<?= $i ?>]">
              <option value="">- empty -</option>
              <?php foreach ($allSquads as $s): ?>
                <option value="<?= h($s) ?>" <?= $sel === $s ? 'selected' : '' ?>>
                  <?= h($s) ?><?= isset($claimedBy[$s]) ? ' - already in ' . h($claimedBy[$s]) : '' ?></option>
              <?php endforeach; ?>
              <?php if ($sel !== '' && !in_array($sel, $allSquads, true)): ?>
                <option value="<?= h($sel) ?>" selected><?= h($sel) ?> - no such squad</option>
              <?php endif; ?>
            </select>
          </td>
          <td class="dim">
            <?php if ($sel !== '' && in_array($sel, $allSquads, true)): ?>
              <a href="?page=squad&amp;sq=<?= urlencode($sel) ?><?= $vq ?>">edit the squad</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
    <div class="actions"><button type="submit">Save squads</button></div>
  </form>
</section>
<?php endif; ?>

<!-- --------------------------------------------------------------- radio -->
<?php if ($sec === 'radio'): ?>
<section class="tsection" id="radio">
  <h2>Radio</h2>
  <p class="dim">The long-range channel everybody in this platoon is put on -
  their 117F, and any vehicle set they climb into. Leave it empty and they fall
  back to the plan's default, which is how it worked before.</p>
  <?php $open('radio'); ?>
    <div class="fields">
      <label>Long range channel
        <select name="lr">
          <option value="">- the plan default -</option>
          <?php foreach ($lrChannels as $idx => $label): ?>
            <option value="<?= (int) $idx ?>" <?= $lr === (int) $idx ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
          <?php if ($lr !== null && !isset($lrChannels[$lr])): ?>
            <option value="<?= (int) $lr ?>" selected><?= (int) $lr ?> - no such channel in the plan</option>
          <?php endif; ?>
        </select></label>
    </div>
    <?php if ($lrChannels === []): ?>
      <p class="note readonly">The plan has no long-range channels yet, so there
      is nothing to pick. Set them on the
      <a href="?page=orbat&amp;s=radio&amp;r=acre<?= $vq ?>">Radio tab</a>, under Long range.</p>
    <?php endif; ?>
    <div class="actions"><button type="submit">Save channel</button></div>
  </form>
  <p class="dim">Squad channels are short range and belong to the squad - set
  those on the squad's own page.</p>
</section>
<?php endif; ?>

<!-- ------------------------------------------------------------- arsenal -->
<?php if ($sec === 'arsenal'): ?>
<section class="tsection" id="arsenal">
  <h2>Arsenal</h2>
  <p class="dim">The platoon's own, on top of what everyone draws and under its
  squads'. <code><?= h(ghostd_template_doc_id('arsenal', $pv)) ?></code>, named
  after the platoon.</p>
  <?php
    $ilKey = 'arsenal'; $ilVariant = $pv; $ilLabel = 'platoon arsenal';
    $ilHidden = ['id' => $pid, 'v' => $variant, 'sec' => $sec];
    require __DIR__ . '/_edit_inline.php';
  ?>
</section>
<?php endif; ?>

<!-- ----------------------------------------------------------- motorpool -->
<?php if ($sec === 'motorpool'): ?>
<section class="tsection" id="motorpool">
  <h2>Motorpool</h2>
  <p class="dim">The vehicles this platoon may draw.
  <code><?= h(ghostd_template_doc_id('motorpool', $pv)) ?></code>, named after
  the platoon.</p>
  <?php
    $ilKey = 'motorpool'; $ilVariant = $pv; $ilLabel = 'platoon motorpool';
    $ilHidden = ['id' => $pid, 'v' => $variant, 'sec' => $sec];
    require __DIR__ . '/_edit_inline.php';
  ?>
</section>
<?php endif; ?>

<?php if ($sec === 'remove'): ?>
<section class="tsection" id="remove">
  <h2>Remove</h2>
  <form method="post" class="danger">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= h($pid) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="what" value="delete">
    <p class="dim">The squads themselves stay - they simply stop belonging to
    anything, and will not appear in the group menu until another platoon lists
    them.</p>
    <button type="submit" class="hot">Delete <?= h($pid) ?></button>
  </form>
</section>
<?php endif; ?>
<?php
ghostd_foot();
