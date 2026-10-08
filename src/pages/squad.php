<?php
/**
 * One squad, in sections.
 *
 * SET THE NUMBER, THEN FILL THE SLOTS. A squad is "eight men" before it is a
 * list of eight roles. The count is its own little form so changing it does not
 * risk what is already typed; shrinking drops the slots off the end, growing
 * adds empty ones, and nothing is written until the slots are saved.
 *
 * ROLES ARE DROPDOWNS, never typed. A role id that matches no role document is
 * a slot nobody can fill and nothing in game says why.
 *
 * ITS CHANNELS ARE PART OF IT. They live in <unit>.radio, not the ORBAT, but a
 * squad without a radio is half a squad, so they are edited here and written
 * there.
 */

declare(strict_types=1);

require_once __DIR__ . '/../orbat.php';
require_once __DIR__ . '/../roles.php';
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

$name = (string) ($_GET['sq'] ?? ($_POST['sq'] ?? ''));
$new  = ($name === '' || $name === '+');

// ONE SECTION ON SCREEN AT A TIME - the section is part of the address, so a
// save comes back to where it was made and a link can point at one.
$sec = (string) ($_GET['sec'] ?? ($_POST['sec'] ?? 'identity'));
if (!isset(GHOSTD_SQUAD_SECTIONS[$sec])) {
    $sec = 'identity';
}

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    $what = (string) ($_POST['what'] ?? '');

    try {
        switch ($what) {

            // THE ARSENAL AND THE MOTORPOOL ARE EDITED ON THIS PAGE, in the
            // boxes themselves - see src/pages/_edit_inline.php. The document
            // is named after the squad, so there is nothing here to choose:
            // the renderer wrote which one in the form.
            case 'inlineedit':
                $msg = ghostd_inline_save($_POST);
                break;

            // A squad is created by name alone; everything else follows.
            case 'create':
                $wanted = trim((string) ($_POST['newname'] ?? ''));
                if ($wanted === '') {
                    throw new RuntimeException('A squad needs a name - it is what a platoon lists it by.');
                }
                if (ghostd_squad($variant, $wanted) !== null) {
                    throw new RuntimeException('A squad called "' . $wanted . '" already exists.');
                }
                ghostd_orbat_edit($variant, static function (array &$doc) use ($wanted) {
                    $g = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
                    $g[] = [$wanted, [], 'true'];
                    $doc['groups'] = $g;
                });
                header('Location: ?page=squad&sq=' . urlencode($wanted) . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
                exit;

            case 'identity':
                $to   = trim((string) ($_POST['newname'] ?? $name));
                $cond = trim((string) ($_POST['cond'] ?? '')) ?: 'true';
                if ($to === '') {
                    throw new RuntimeException('A squad needs a name.');
                }
                if ($to !== $name && ghostd_squad($variant, $to) !== null) {
                    throw new RuntimeException('A squad called "' . $to . '" already exists.');
                }

                // THE KIND OF ELEMENT IT IS - infantry, mechanised, air. It is
                // part of the squad (user, 2026-09-09: "the words inf mech air
                // ectr ect are part of the squd config") and it is what the
                // blue force tracker draws the group with.
                $type = strtolower(trim((string) ($_POST['type'] ?? '')));

                ghostd_orbat_edit($variant, static function (array &$doc) use ($name, $to, $cond, $type) {
                    $groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
                    foreach ($groups as $i => $g) {
                        if ((string) ($g[0] ?? '') === $name) {
                            $groups[$i][0] = $to;
                            $groups[$i][2] = $cond;
                            $groups[$i][3] = $type;
                        }
                    }
                    // A rename has to follow into every platoon that lists it,
                    // or the platoon quietly loses a squad.
                    if ($to !== $name) {
                        $ps = is_array($doc['platoons'] ?? null) ? $doc['platoons'] : [];
                        foreach ($ps as $pi => $p) {
                            foreach ((array) ($p[4] ?? []) as $si => $sq) {
                                if ((string) $sq === $name) { $ps[$pi][4][$si] = $to; }
                            }
                        }
                        $doc['platoons'] = $ps;
                    }
                    $doc['groups'] = $groups;
                });

                // And its channels, or it loses its radio with nothing to say why.
                if ($to !== $name) {
                    ghostd_radio_edit(static function (array &$items) use ($name, $to) {
                        foreach (['srSquadChannel', 'tfarNets'] as $list) {
                            $rows = is_array($items[$list] ?? null) ? $items[$list] : [];
                            foreach ($rows as $i => $r) {
                                if (is_array($r) && (string) ($r[0] ?? '') === $name) {
                                    $rows[$i][0] = $to;
                                }
                            }
                            $items[$list] = $rows;
                        }
                    });
                    header('Location: ?page=squad&sq=' . urlencode($to) . ($variant !== '' ? '&v=' . urlencode($variant) : '') . '&renamed=1');
                    exit;
                }
                $msg = 'Identity saved.';
                break;

            case 'slots':
                $roles = [];
                foreach ((array) ($_POST['roles'] ?? []) as $i => $r) {
                    $r = trim((string) $r);
                    if ($r !== '') { $roles[(int) $i] = $r; }
                }
                ksort($roles);
                $roles = array_values($roles);

                ghostd_orbat_edit($variant, static function (array &$doc) use ($name, $roles) {
                    $groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
                    foreach ($groups as $i => $g) {
                        if ((string) ($g[0] ?? '') === $name) { $groups[$i][1] = $roles; }
                    }
                    $doc['groups'] = $groups;
                });
                $msg = count($roles) . ' slots saved.';
                break;

            case 'radio':
                ghostd_radio_edit(static function (array &$items) use ($name) {
                    $acre = trim((string) ($_POST['acre'] ?? ''));
                    ghostd_channel_set($items, 'srSquadChannel', $name,
                        $acre === '' ? null : [(int) $acre]);

                    $sw = trim((string) ($_POST['tfar_sw'] ?? ''));
                    $lr = trim((string) ($_POST['tfar_lr'] ?? ''));
                    ghostd_channel_set($items, 'tfarNets', $name,
                        ($sw === '' && $lr === '') ? null : [(int) $sw, (int) $lr]);
                });
                $msg = 'Channels saved.';
                break;

            case 'copy':
                $to = trim((string) ($_POST['to'] ?? ''));
                if ($to === '') {
                    throw new RuntimeException('The copy needs a name.');
                }
                if (ghostd_squad($variant, $to) !== null) {
                    throw new RuntimeException('"' . $to . '" already exists.');
                }
                ghostd_orbat_edit($variant, static function (array &$doc) use ($name, $to) {
                    $groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
                    foreach ($groups as $g) {
                        if ((string) ($g[0] ?? '') === $name) {
                            $copy = $g;
                            $copy[0] = $to;
                            $groups[] = $copy;
                            break;
                        }
                    }
                    $doc['groups'] = $groups;
                });
                ghostd_radio_edit(static function (array &$items) use ($name, $to) {
                    foreach (['srSquadChannel', 'tfarNets'] as $list) {
                        $rows = is_array($items[$list] ?? null) ? $items[$list] : [];
                        foreach ($rows as $r) {
                            if (is_array($r) && (string) ($r[0] ?? '') === $name) {
                                $copy = $r;
                                $copy[0] = $to;
                                $rows[] = $copy;
                                break;
                            }
                        }
                        $items[$list] = $rows;
                    }
                });
                header('Location: ?page=squad&sq=' . urlencode($to) . ($variant !== '' ? '&v=' . urlencode($variant) : '') . '&copied=1');
                exit;

            case 'delete':
                ghostd_orbat_edit($variant, static function (array &$doc) use ($name) {
                    $groups = is_array($doc['groups'] ?? null) ? $doc['groups'] : [];
                    $doc['groups'] = array_values(array_filter($groups,
                        static fn($g) => (string) ($g[0] ?? '') !== $name));
                    // And out of the platoons that listed it.
                    $ps = is_array($doc['platoons'] ?? null) ? $doc['platoons'] : [];
                    foreach ($ps as $pi => $p) {
                        $ps[$pi][4] = array_values(array_filter((array) ($p[4] ?? []),
                            static fn($s) => (string) $s !== $name));
                    }
                    $doc['platoons'] = $ps;
                });
                ghostd_radio_edit(static function (array &$items) use ($name) {
                    ghostd_channel_set($items, 'srSquadChannel', $name, null);
                    ghostd_channel_set($items, 'tfarNets', $name, null);
                });
                header('Location: ?page=orbat&s=squads' . ($variant !== '' ? '&v=' . urlencode($variant) : ''));
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
    ghostd_head('New squad', 'orbat');
    if ($err !== null) { ghostd_flash('bad', $err); }
    ?>
    <p class="dim"><a href="?page=orbat&amp;s=squads<?= $vq ?>">&larr; Squads</a></p>
    <h2>A new squad</h2>
    <p class="note">The name is what a platoon lists it by and what the group menu
    shows. Its slots, channels and arsenal come next, on its own page.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="v" value="<?= h($variant) ?>">
      <input type="hidden" name="what" value="create">
      <div class="fields">
        <label>Name <span class="dim">GHOST 6, BANSHEE 1-1</span>
          <input type="text" name="newname" required></label>
      </div>
      <div class="actions">
        <button type="submit">Create it</button>
        <a class="btnlink" href="?page=orbat&amp;s=squads<?= $vq ?>">Cancel</a>
      </div>
    </form>
    <?php
    ghostd_foot();
    return;
}

$sq = ghostd_squad($variant, $name);
if ($sq === null) {
    ghostd_head('Squad', 'orbat');
    ghostd_flash('bad', 'No squad called "' . h($name) . '" in this ORBAT.');
    echo '<p><a href="?page=orbat&amp;s=squads' . $vq . '">Back to the squads</a></p>';
    ghostd_foot();
    return;
}

$radio = ghostd_radio_items();
$acre  = ghostd_acre_of($radio)[$name] ?? null;
$tfar  = ghostd_tfar_of($radio)[$name] ?? [0, 0];

$roleIds = ghostd_role_ids();
$roleNames = [];
foreach ($roleIds as $rid) {
    $rr = ghostd_role($rid);
    $roleNames[$rid] = $rr['name'] !== '' ? $rr['name'] : $rid;
}

// How many slots to draw. The count field posts it back; otherwise as many as
// there are, and at least one so a new squad has somewhere to start.
$want = (int) ($_GET['n'] ?? max(1, count($sq['roles'])));
$want = max(1, min(40, $want));

// Which platoon claims it - the question the ORBAT is always half answering.
$inPlatoon = [];
foreach (ghostd_orbat($variant)['platoons'] as $p) {
    if (in_array($name, (array) ($p[4] ?? []), true)) {
        $inPlatoon[] = (string) ($p[1] ?? $p[0] ?? '');
    }
}

$arsenalVar = ghostd_squad_variant($name);
$arsenalHas   = ghostd_variant_summary('arsenal', $arsenalVar);
$motorpoolHas = ghostd_variant_summary('motorpool', $arsenalVar);

$csrf = ghostd_csrf_token();
$open = static function (string $what) use ($sec, $csrf, $name, $variant) {
    echo '<form method="post">'
       . '<input type="hidden" name="csrf" value="' . h($csrf) . '">'
       . '<input type="hidden" name="sq" value="' . h($name) . '">'
       . '<input type="hidden" name="v" value="' . h($variant) . '">'
       . '<input type="hidden" name="sec" value="' . h($sec) . '">'
       . '<input type="hidden" name="what" value="' . h($what) . '">';
};

ghostd_head($name, 'orbat');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if (isset($_GET['renamed'])) { ghostd_flash('good', 'Renamed, with its platoon, nets and channels.'); }
if (isset($_GET['copied'])) { ghostd_flash('good', 'Copied. Its channels came with it - change them.'); }
?>
<p class="dim"><a href="?page=orbat&amp;s=squads<?= $vq ?>">&larr; Squads</a> &middot;
<code><?= h(ghostd_orbat_doc_id($variant)) ?></code> &middot;
<?= $inPlatoon === []
      ? '<span class="bad">no platoon lists it - it will not appear in the group menu</span>'
      : 'in ' . h(implode(', ', $inPlatoon)) ?></p>

<nav class="subrail">
  <?php foreach (GHOSTD_SQUAD_SECTIONS as $k => $label): ?>
    <a class="<?= $sec === $k ? 'on' : '' ?>"
       href="?page=squad&amp;sq=<?= urlencode($name) ?>&amp;sec=<?= h($k) ?><?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ------------------------------------------------------------ identity -->
<?php if ($sec === 'identity'): ?>
<section class="tsection" id="identity">
  <h2>Identity</h2>
  <?php $open('identity'); ?>
    <div class="fields">
      <label>Name <span class="dim">renaming takes its platoon, nets and channels with it</span>
        <input type="text" name="newname" value="<?= h($sq['name']) ?>" required></label>
      <label>Kind <span class="dim">what it is - the icon the blue force tracker draws it with</span>
        <input type="text" name="type" list="bfticons" value="<?= h((string) ($sq['type'] ?? '')) ?>"
               placeholder="inf"></label>
      <label>Offered when
        <span class="dim">leave it <code>true</code> unless this squad only exists on some nights</span>
        <input type="text" name="cond" value="<?= h($sq['cond']) ?>"></label>
    </div>
    <datalist id="bfticons">
      <?php foreach (['inf', 'motor_inf', 'mech_inf', 'air', 'armor', 'recon', 'antiair', 'art',
                      'hq', 'installation', 'maint', 'med', 'mortar', 'naval', 'ordnance',
                      'plane', 'service', 'support', 'uav', 'unknown'] as $ic): ?>
        <option value="<?= h($ic) ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <div class="actions"><button type="submit">Save identity</button></div>
  </form>
  <p class="note"><strong>Offered when</strong> is SQF that has to be true for
  this squad to be drawn on the role screen - it is compiled and run each time
  the screen lists this squad's slots. <code>true</code> means always, which is
  what almost every squad wants. It is the escape hatch for a squad that only
  exists on some nights: <code>count allPlayers &gt; 24</code> for one you only
  field when enough people turn up. A squad whose condition is false is not
  drawn at all and nobody can slot into it.</p>
</section>
<?php endif; ?>

<!-- --------------------------------------------------------------- slots -->
<?php if ($sec === 'slots'): ?>
<section class="tsection" id="slots">
  <h2>Slots <span class="dim"><?= count($sq['roles']) ?> filled</span></h2>
  <p class="dim">In order - slot 1 is the squad leader's, and the game fills
  them in this order.</p>

  <form method="get" class="inline">
    <input type="hidden" name="page" value="squad">
    <input type="hidden" name="sq" value="<?= h($name) ?>">
    <?php if ($variant !== ''): ?><input type="hidden" name="v" value="<?= h($variant) ?>"><?php endif; ?>
    <label for="n">Number of roles</label>
    <input type="number" id="n" name="n" min="1" max="40" value="<?= $want ?>">
    <button type="submit">Set</button>
    <span class="dim">Fewer drops the slots off the end, more adds empty ones.
    Nothing is written until you save the slots.</span>
  </form>

  <?php if ($roleIds === []): ?>
    <p class="note readonly">No role documents exist, so there is nothing to
    choose. <a href="?page=role&amp;id=%2B">Create a role</a> first.</p>
  <?php endif; ?>

  <?php $open('slots'); ?>
    <table class="grid">
      <thead><tr><th>Slot</th><th>Role</th><th></th></tr></thead>
      <tbody>
      <?php for ($i = 0; $i < $want; $i++): ?>
        <?php $sel = (string) ($sq['roles'][$i] ?? ''); ?>
        <tr>
          <td><strong><?= $i + 1 ?></strong><?= $i === 0 ? ' <span class="dim">lead</span>' : '' ?></td>
          <td>
            <select name="roles[<?= $i ?>]">
              <option value="">- empty -</option>
              <?php foreach ($roleIds as $rid): ?>
                <option value="<?= h($rid) ?>" <?= $sel === $rid ? 'selected' : '' ?>>
                  <?= h($roleNames[$rid]) ?> (<?= h($rid) ?>)</option>
              <?php endforeach; ?>
              <?php if ($sel !== '' && !in_array($sel, $roleIds, true)): ?>
                <option value="<?= h($sel) ?>" selected><?= h($sel) ?> - no document</option>
              <?php endif; ?>
            </select>
          </td>
          <td class="dim">
            <?php if ($sel !== '' && in_array($sel, $roleIds, true)): ?>
              <a href="?page=role&amp;id=<?= urlencode($sel) ?>">edit the role</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endfor; ?>
      </tbody>
    </table>
    <div class="actions"><button type="submit">Save slots</button></div>
  </form>
</section>
<?php endif; ?>

<!-- --------------------------------------------------------------- radio -->
<?php if ($sec === 'radio'): ?>
<section class="tsection" id="radio">
  <h2>Radio</h2>
  <p class="dim">Written to <code><?= h(ghostd_radio_doc_id()) ?></code>, which is
  where the mod reads it. Set the frequencies themselves on the
  <a href="?page=orbat&amp;s=radio<?= $vq ?>">Radio tab</a>.</p>
  <?php $open('radio'); ?>
    <div class="fields">
      <label>ACRE short range <span class="dim">the channel number on the 148/152; blank for none</span>
        <input type="number" name="acre" min="0" value="<?= h($acre === null ? '' : (string) $acre) ?>"></label>
      <label>TFAR short range <span class="dim">slot number, 1 to 8</span>
        <input type="number" name="tfar_sw" min="0" max="8" value="<?= h((string) ($tfar[0] ?: '')) ?>"></label>
      <label>TFAR long range
        <input type="number" name="tfar_lr" min="0" max="8" value="<?= h((string) ($tfar[1] ?: '')) ?>"></label>
    </div>
    <div class="actions"><button type="submit">Save channels</button></div>
  </form>
</section>
<?php endif; ?>

<!-- ------------------------------------------------------------- arsenal -->
<?php if ($sec === 'arsenal'): ?>
<section class="tsection" id="arsenal">
  <h2>Arsenal</h2>
  <p class="dim">The squad's own, on top of what everyone draws and its
  platoon's. <code><?= h(ghostd_template_doc_id('arsenal', $arsenalVar)) ?></code>,
  named after the squad.</p>
  <?php
    $ilKey = 'arsenal'; $ilVariant = $arsenalVar; $ilLabel = 'squad arsenal';
    $ilHidden = ['sq' => $name, 'v' => $variant, 'sec' => $sec];
    require __DIR__ . '/_edit_inline.php';
  ?>
</section>
<?php endif; ?>

<!-- ----------------------------------------------------------- motorpool -->
<?php if ($sec === 'motorpool'): ?>
<section class="tsection" id="motorpool">
  <h2>Motorpool</h2>
  <p class="dim">The vehicles this squad may draw.
  <code><?= h(ghostd_template_doc_id('motorpool', $arsenalVar)) ?></code>,
  named after the squad.</p>
  <?php
    $ilKey = 'motorpool'; $ilVariant = $arsenalVar; $ilLabel = 'squad motorpool';
    $ilHidden = ['sq' => $name, 'v' => $variant, 'sec' => $sec];
    require __DIR__ . '/_edit_inline.php';
  ?>
</section>
<?php endif; ?>

<!-- ---------------------------------------------------------------- copy -->
<?php if ($sec === 'copy'): ?>
<section class="tsection" id="copy">
  <h2>Copy</h2>
  <p class="dim">Takes its slots, its condition and its channels. Four rifle
  squads differ by a digit; this is how you make the other three.</p>
  <?php $open('copy'); ?>
    <div class="fields">
      <label>Copy to <span class="dim">the new squad's name</span>
        <input type="text" name="to" required></label>
    </div>
    <div class="actions"><button type="submit">Copy <?= h($name) ?></button></div>
  </form>
</section>
<?php endif; ?>

<?php if ($sec === 'remove'): ?>
<section class="tsection" id="remove">
  <h2>Remove</h2>
  <form method="post" class="danger">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="sq" value="<?= h($name) ?>">
    <input type="hidden" name="v" value="<?= h($variant) ?>">
    <input type="hidden" name="what" value="delete">
    <p class="dim">Takes it out of its platoons and its nets, and drops its
    channels. The squad arsenal document is left alone.</p>
    <button type="submit" class="hot">Delete <?= h($name) ?></button>
  </form>
</section>
<?php endif; ?>
<?php
ghostd_foot();
