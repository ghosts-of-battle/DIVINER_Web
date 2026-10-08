<?php
/**
 * One role, in sections - the report deck's arrangement, applied to a role.
 *
 * EACH SECTION SAVES ITSELF. A role is eight different things (who it is, who
 * may take it, its nets, its tiles, its traits, its variables, its loadout, its
 * arsenal) and they are filled in at different times by different people. One
 * Save for the lot means a mistake in the loadout box throws away the nets you
 * just typed.
 *
 * WHAT YOU CAN PICK, YOU PICK. Nets come from the net list, tiles from the nine
 * the TAC//PAD has, the rank from the rank list. A typed id that matches
 * nothing is a permission that grants nothing, and nothing in game says so -
 * which is why this role's own arsenal is a document named after the role
 * rather than a class name somebody types.
 *
 * The loadout is the exception and always will be: it is a nested array copied
 * out of a config file. It gets a paste box and a plain-English summary of what
 * it resolves to, which is the check that the paste went in the right way round.
 * In game there is a CAPTURE button instead - see ghostD_pac_fnc_roleCapture.
 */

declare(strict_types=1);

require_once __DIR__ . '/../roles.php';
// ghostd_variant_summary and the slug helpers live with the ORBAT.
require_once __DIR__ . '/../orbat.php';
require_once __DIR__ . '/../inline_edit.php';

$unit = ghostd_config()['unit'];

$id  = trim((string) ($_GET['id'] ?? ($_POST['id'] ?? '')));
$new = ($id === '' || $id === '+');

// ONE SECTION ON SCREEN AT A TIME. A role is eight different subjects and all
// eight down one page is a page nobody reads to the bottom of - you scroll past
// six things you did not come for. The section is part of the address, so a
// save comes back to where it was made and a link can point at one.
$sec = (string) ($_GET['s'] ?? ($_POST['s'] ?? 'identity'));
if (!isset(GHOSTD_ROLE_SECTIONS[$sec])) {
    $sec = 'identity';
}
// $roleSections - the ones this role actually has - is worked out below, once
// the trait catalogue has been read; $sec is checked against it there.

$msg = null;
$err = null;

// ---- what the pickers offer ------------------------------------------------
$netIds = [];
try {
    foreach (ghostd_template_items('nets') as $nid => $n) {
        $netIds[(string) $nid] = (string) ($n['name'] ?? '');
    }
} catch (Throwable $e) {
    $netIds = [];
}

$rankIds = [];
try {
    $rd = ghostd_get($unit . '.ranks');
    foreach ((array) ($rd['items'] ?? []) as $rid => $r) {
        $rankIds[(string) $rid] = (string) (is_array($r) ? ($r['name'] ?? $rid) : $rid);
    }
} catch (Throwable $e) {
    $rankIds = [];
}

$skillIds = [];
try {
    $sd = ghostd_get($unit . '.skills');
    foreach ((array) ($sd['items'] ?? []) as $sid => $s) {
        $skillIds[(string) $sid] = (string) (is_array($s) ? ($s['name'] ?? $sid) : $sid);
    }
} catch (Throwable $e) {
    $skillIds = [];
}

$arsenals = ghostd_template_variants('arsenal');

// The names TAC//PAC's skills own. A role that sets one of these is a role with
// a setting the mod skips, and nothing in game says so - which is exactly the
// kind of thing an editor should refuse to let you do quietly.
$pacOwned = ghostd_pac_owned();

// ---- IS THERE A TRAITS PAGE AT ALL? ---------------------------------------
// A trait is either one of the engine's four or one this unit invented, and a
// skill can own either. When skills own all four and the unit has invented
// none, the page has nothing to tick - it drew two headings, "All four are set
// by skills", "None defined yet" and a Save button that saved nothing (user,
// 2026-09-09: "thidss why have this fucking page"). So it is not a tab then.
$traitsOffered = array_filter(
    array_keys(GHOSTD_ENGINE_TRAITS),
    static fn($t) => !in_array(strtolower((string) $t), $pacOwned, true)
);
$roleSections = GHOSTD_ROLE_SECTIONS;
if ($traitsOffered === []) {
    unset($roleSections['traits']);
}
if (!isset($roleSections[$sec])) {
    $sec = 'identity';
}

// ---- saving ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    $what = (string) ($_POST['what'] ?? '');

    /** name/value rows out of the parallel arrays a section posts. */
    $rows = static function (string $prefix, int $width): array {
        $out = [];
        foreach ((array) ($_POST[$prefix . '_name'] ?? []) as $i => $n) {
            $n = trim((string) $n);
            if ($n === '' || in_array((string) $i, (array) ($_POST[$prefix . '_remove'] ?? []), true)) {
                continue;
            }
            $row = [$n];
            for ($c = 1; $c < $width; $c++) {
                $v = trim((string) ($_POST[$prefix . '_v' . $c][$i] ?? ''));
                // A whole number stays a number: ace_medical_medicClass is 1,
                // and a "1" would be put on the man as a string.
                $row[] = ($v !== '' && preg_match('/^-?\d+$/', $v)) ? (int) $v : $v;
            }
            $out[] = $row;
        }
        return $out;
    };

    /** A textarea into trimmed, non-empty lines. */
    $lines = static function ($raw): array {
        return array_values(array_filter(array_map('trim',
            preg_split('/\r?\n/', (string) $raw) ?: []), static fn($x) => $x !== ''));
    };

    try {
        switch ($what) {

            case 'create':
                $wanted = trim((string) ($_POST['newid'] ?? ''));
                if (!ghostd_role_id_ok($wanted)) {
                    throw new RuntimeException('A role id is a config class name: letters, digits and '
                        . 'underscore, starting with a letter - "jfoBanshee", not "JFO Banshee".');
                }
                if (in_array($wanted, ghostd_role_ids(), true)) {
                    throw new RuntimeException('A role called "' . $wanted . '" already exists.');
                }
                ghostd_role_save($wanted, [
                    'name'    => trim((string) ($_POST['name'] ?? '')) ?: $wanted,
                    'slotTag' => $wanted,
                ]);
                header('Location: ?page=role&id=' . urlencode($wanted));
                exit;

            case 'identity':
                $to = trim((string) ($_POST['newid'] ?? $id));
                if ($to !== $id) {
                    $moved = ghostd_role_rename($id, $to);
                    ghostd_role_save($to, [
                        'name'        => trim((string) ($_POST['name'] ?? '')),
                        'description' => trim((string) ($_POST['description'] ?? '')),
                        'icon'        => trim((string) ($_POST['icon'] ?? '')),
                        'slotTag'     => trim((string) ($_POST['slotTag'] ?? '')) ?: $to,
                    ]);
                    header('Location: ?page=role&id=' . urlencode($to) . '&moved=' . $moved);
                    exit;
                }
                ghostd_role_save($id, [
                    'name'        => trim((string) ($_POST['name'] ?? '')),
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'icon'        => trim((string) ($_POST['icon'] ?? '')),
                    'slotTag'     => trim((string) ($_POST['slotTag'] ?? '')) ?: $id,
                ]);
                $msg = 'Identity saved.';
                break;

            case 'gates':
                ghostd_role_save($id, [
                    'minRank'        => trim((string) ($_POST['minRank'] ?? '')),
                    'requiredSkills' => array_values((array) ($_POST['requiredSkills'] ?? [])),
                    'defaultSkills'  => array_values((array) ($_POST['defaultSkills'] ?? [])),
                    'uids'           => $lines($_POST['uids'] ?? ''),
                ]);
                $msg = 'Who may take it, saved.';
                break;

            case 'nets':
                ghostd_role_save($id, ['nets' => $rows('net', 2)]);
                $msg = 'Nets saved.';
                break;

            case 'tiles':
                // Checkboxes, because the tiles are a fixed list of nine. The
                // stored shape is still [id, "true"] - the mod's, unchanged.
                $on = [];
                foreach (array_keys(GHOSTD_TILES) as $t) {
                    if (in_array($t, (array) ($_POST['tiles'] ?? []), true)) {
                        $on[] = [$t, 'true'];
                    }
                }
                ghostd_role_save($id, ['tiles' => $on]);
                $msg = count($on) . ' tiles saved.';
                break;

            // TICKED, NOT TYPED - and the custom flag comes from WHICH LIST the
            // name is on, not from anybody remembering. That flag is
            // setUnitTrait's third argument: wrong for a name the engine does
            // not have and the trait is thrown away without a word.
            case 'traits':
                $on   = (array) ($_POST['t_on'] ?? []);
                $nums = (array) ($_POST['t_num'] ?? []);
                // THE FOUR, AND ONLY THE FOUR. setUnitTrait's third argument
                // says the name is a custom one; none of these are, so it is
                // always false. A unit cannot invent a trait - what it invents
                // is a VARIABLE, saved on the next screen.
                // {name, value} - the pair a config file writes. No third
                // column: setUnitTrait's custom flag is only ever false here,
                // and setupPlayer defaults it.
                $out = [];
                foreach (array_keys(GHOSTD_ENGINE_TRAITS) as $t) {
                    if (in_array($t, $on, true)) {
                        $out[] = [$t, 'true'];
                    }
                }
                ghostd_role_save($id, ['traits' => $out]);
                $msg = count($out) . ' traits saved.';
                break;

            // TICKED, NOT TYPED - the same as the traits screen. The catalogue
            // says which names exist and what kind each is; the global flag on
            // an existing row is kept, because a variable deliberately set
            // local should not be "corrected" by somebody editing the list.
            case 'vars':
                $on   = (array) ($_POST['v_on'] ?? []);
                $nums = (array) ($_POST['v_num'] ?? []);
                $wasGlobal = [];
                foreach (ghostd_role($id)['customVariables'] as $row) {
                    $wasGlobal[(string) $row[0]] = (string) ($row[2] ?? 'true');
                }
                $out = [];
                foreach (ghostd_role_vars() as $vn => $meta) {
                    $g = $wasGlobal[$vn] ?? 'true';
                    if ($meta['kind'] === 'number') {
                        $v = trim((string) ($nums[$vn] ?? ''));
                        if ($v === '') {
                            continue;              // not set is not zero
                        }
                        $out[] = [$vn, is_numeric($v) ? $v + 0 : $v, $g];
                    } elseif (in_array($vn, $on, true)) {
                        $out[] = [$vn, 'true', $g];
                    }
                }
                ghostd_role_save($id, ['customVariables' => $out]);
                $msg = count($out) . ' custom variables saved.';
                break;

            case 'loadout':
                $l = ghostd_sqf_decode((string) ($_POST['loadout'] ?? ''));
                ghostd_role_save($id, ['defaultLoadout' => $l]);
                $msg = 'Default loadout saved - ' . count($l) . ' slots.';
                break;

            // The role's own arsenal document, edited in the boxes below the
            // gear lists - src/pages/_edit_inline.php - not on a template page.
            case 'inlineedit':
                $msg = ghostd_inline_save($_POST);
                break;

            case 'arsenal':
                ghostd_role_save($id, [
                    'arsenalWeapons'   => $lines($_POST['arsenalWeapons'] ?? ''),
                    'arsenalMagazines' => $lines($_POST['arsenalMagazines'] ?? ''),
                    'arsenalItems'     => $lines($_POST['arsenalItems'] ?? ''),
                    'arsenalBackpacks' => $lines($_POST['arsenalBackpacks'] ?? ''),
                ]);
                $msg = 'Arsenal saved.';
                break;

            case 'delete':
                $used = [];
                foreach ((array) (ghostd_get($unit . '.orbat')['groups'] ?? []) as $g) {
                    if (in_array($id, (array) ($g[1] ?? []), true)) {
                        $used[] = (string) ($g[0] ?? '');
                    }
                }
                if ($used !== [] && ($_POST['confirm'] ?? '') !== 'yes') {
                    throw new RuntimeException('That role fills slots in ' . implode(', ', $used)
                        . '. Tick the box to remove it anyway - those slots will not fill.');
                }
                ghostd_doc_delete(ghostd_role_doc_id($id));
                header('Location: ?page=orbat&s=roles');
                exit;

            default:
                throw new RuntimeException('Nothing said which section to save.');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// ---- the new-role form -----------------------------------------------------
if ($new) {
    ghostd_head('New role', 'orbat');
    if ($err !== null) { ghostd_flash('bad', $err); }
    ?>
    <p class="dim"><a href="?page=orbat&amp;s=roles">&larr; Roles</a></p>
    <h2>A new role</h2>
    <p class="note">The id is the <code>Dynamic_Roles</code> class name, and it is
    what a squad's slot list names. It cannot be changed casually later - a
    rename moves every slot that asks for it, which this page will do, but a
    mission file that names the old one will not follow.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
      <input type="hidden" name="what" value="create">
      <div class="fields">
        <label>Id <span class="dim">jfoBanshee, medicalC2 - no spaces</span>
          <input type="text" name="newid" required pattern="[A-Za-z][A-Za-z0-9_]*"></label>
        <label>Shown as <span class="dim">Joint Fires Observer</span>
          <input type="text" name="name"></label>
      </div>
      <div class="actions">
        <button type="submit">Create it</button>
        <a class="btnlink" href="?page=orbat&amp;s=roles">Cancel</a>
      </div>
    </form>
    <?php
    ghostd_foot();
    return;
}

// ---- the role itself -------------------------------------------------------
$r = ghostd_role($id);

ghostd_head($r['name'] !== '' ? $r['name'] : $id, 'orbat');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
if (isset($_GET['moved'])) {
    ghostd_flash('good', 'Renamed. ' . (int) $_GET['moved'] . ' squad slots moved with it.');
}

if (!$r['exists']) {
    ghostd_flash('bad', 'No role document called ' . h($id) . '. Saving any section below creates one.');
}

// Which squads ask for this role - the useful question about any role.
$usedBy = [];
try {
    foreach ((array) (ghostd_get($unit . '.orbat')['groups'] ?? []) as $g) {
        if (in_array($id, (array) ($g[1] ?? []), true)) {
            $usedBy[] = (string) ($g[0] ?? '');
        }
    }
} catch (Throwable $e) {
    $usedBy = [];
}

$csrf = ghostd_csrf_token();

/** The header of one section, and the hidden fields every one of them posts. */
$open = static function (string $what) use ($csrf, $id, $sec) {
    echo '<form method="post">'
       . '<input type="hidden" name="csrf" value="' . h($csrf) . '">'
       . '<input type="hidden" name="id" value="' . h($id) . '">'
       . '<input type="hidden" name="s" value="' . h($sec) . '">'
       . '<input type="hidden" name="what" value="' . h($what) . '">';
};
?>
<p class="dim"><a href="?page=orbat&amp;s=roles">&larr; Roles</a> &middot;
<code><?= h(ghostd_role_doc_id($id)) ?></code> &middot;
<?= $usedBy === [] ? 'used by nothing' : 'in ' . h(implode(', ', $usedBy)) ?></p>

<nav class="subrail">
  <?php foreach ($roleSections as $k => $label): ?>
    <a class="<?= $sec === $k ? 'on' : '' ?>"
       href="?page=role&amp;id=<?= urlencode($id) ?>&amp;s=<?= h($k) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ------------------------------------------------------------ identity -->
<?php if ($sec === 'identity'): ?>
<section class="tsection" id="identity">
  <h2>Identity</h2>
  <?php $open('identity'); ?>
    <div class="fields">
      <label>Id <span class="dim">the class name; changing it moves every squad slot that asks for it</span>
        <input type="text" name="newid" value="<?= h($id) ?>" required pattern="[A-Za-z][A-Za-z0-9_]*"></label>
      <label>Shown as
        <input type="text" name="name" value="<?= h($r['name']) ?>"></label>
      <label>Description <span class="dim">what the job is - read on the slot card</span>
        <textarea name="description" rows="3" class="short"><?= h($r['description']) ?></textarea></label>
      <label>Icon <span class="dim">a paa path; blank is fine</span>
        <input type="text" name="icon" value="<?= h($r['icon']) ?>"></label>
      <label>Slot tag <span class="dim">how the HUD labels the slot; defaults to the id</span>
        <input type="text" name="slotTag" value="<?= h($r['slotTag']) ?>"></label>
    </div>
    <div class="actions"><button type="submit">Save identity</button></div>
  </form>
</section>
<?php endif; ?>

<!-- --------------------------------------------------------------- gates -->
<?php if ($sec === 'gates'): ?>
<section class="tsection" id="gates">
  <h2>Who may take it</h2>
  <p class="dim">All three are optional. Leave them empty and anybody may slot in.</p>
  <?php $open('gates'); ?>
    <div class="fields">
      <label>Minimum rank
        <select name="minRank">
          <option value="">- no rank gate -</option>
          <?php foreach ($rankIds as $rid => $rname): ?>
            <option value="<?= h((string) $rid) ?>" <?= $r['minRank'] === (string) $rid ? 'selected' : '' ?>>
              <?= h($rname !== '' ? $rname . ' (' . $rid . ')' : (string) $rid) ?></option>
          <?php endforeach; ?>
          <?php if ($r['minRank'] !== '' && !isset($rankIds[$r['minRank']])): ?>
            <option value="<?= h($r['minRank']) ?>" selected><?= h($r['minRank']) ?> (no such rank)</option>
          <?php endif; ?>
        </select></label>
    </div>

    <h3>Skills required <span class="dim">he must hold all of them</span></h3>
    <?php if ($skillIds === []): ?>
      <p class="note readonly">No skill documents, so there is nothing to require.</p>
    <?php else: ?>
      <div class="checkgrid">
        <?php foreach ($skillIds as $sid => $sname): ?>
          <label class="inlinelabel">
            <input type="checkbox" name="requiredSkills[]" value="<?= h((string) $sid) ?>"
              <?= in_array((string) $sid, $r['requiredSkills'], true) ? 'checked' : '' ?>>
            <?= h($sname !== '' ? $sname : (string) $sid) ?></label>
        <?php endforeach; ?>
      </div>

      <h3>Skills it grants <span class="dim">given to whoever takes the slot</span></h3>
      <div class="checkgrid">
        <?php foreach ($skillIds as $sid => $sname): ?>
          <label class="inlinelabel">
            <input type="checkbox" name="defaultSkills[]" value="<?= h((string) $sid) ?>"
              <?= in_array((string) $sid, $r['defaultSkills'], true) ? 'checked' : '' ?>>
            <?= h($sname !== '' ? $sname : (string) $sid) ?></label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h3>Locked to <span class="dim">Steam ids, one per line</span></h3>
    <p class="dim">Anything here makes the slot theirs alone - an admin grant is
    the only other way in. Empty means open to everyone who passes the gates above.</p>
    <textarea name="uids" rows="3" class="short"><?= h(implode("\n", $r['uids'])) ?></textarea>

    <?php if ($r['uids'] !== []): ?>
      <p class="dim">Who they are:
        <?php foreach ($r['uids'] as $u): ?><?= steamlink($u) ?> <?php endforeach; ?>
      </p>
    <?php endif; ?>

    <div class="actions"><button type="submit">Save who may take it</button></div>
  </form>
</section>
<?php endif; ?>

<!-- ---------------------------------------------------------------- nets -->
<?php if ($sec === 'nets'): ?>
<section class="tsection" id="nets">
  <h2>Messaging nets</h2>
  <p class="dim">TAC//MSG. A net he is not on is a net he does not read - there is
  no "all nets". Sub-nets are the dotted ones; listing <code>C2</code> does not
  give him <code>C2.reports</code>.</p>
  <?php $open('nets'); ?>
    <table class="grid">
      <thead><tr><th>Net</th><th>On</th><th>Remove</th></tr></thead>
      <tbody>
      <?php
        $netRows = $r['nets'];
        $netRows[] = ['', 'true'];
      ?>
      <?php foreach ($netRows as $i => $row): ?>
        <?php $isNew = $i >= count($r['nets']); ?>
        <tr>
          <td>
            <select name="net_name[<?= $i ?>]">
              <option value=""><?= $isNew ? '- add a net -' : '- remove -' ?></option>
              <?php foreach ($netIds as $nid => $nname): ?>
                <option value="<?= h((string) $nid) ?>" <?= (string) $row[0] === (string) $nid ? 'selected' : '' ?>>
                  <?= h((string) $nid) ?><?= $nname !== '' ? ' - ' . h($nname) : '' ?></option>
              <?php endforeach; ?>
              <?php if ($row[0] !== '' && !isset($netIds[$row[0]])): ?>
                <option value="<?= h((string) $row[0]) ?>" selected><?= h((string) $row[0]) ?> (no such net)</option>
              <?php endif; ?>
            </select>
          </td>
          <td>
            <select name="net_v1[<?= $i ?>]">
              <option value="true" <?= (string) $row[1] !== 'false' ? 'selected' : '' ?>>yes</option>
              <option value="false" <?= (string) $row[1] === 'false' ? 'selected' : '' ?>>no</option>
            </select>
          </td>
          <td><?= $isNew ? '' : '<input type="checkbox" name="net_remove[]" value="' . $i . '">' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="actions"><button type="submit">Save nets</button></div>
  </form>
  <p class="dim"><a href="?page=configedit&amp;t=nets">Edit the net list</a> to add one that is not offered.</p>
</section>
<?php endif; ?>

<!-- --------------------------------------------------------------- tiles -->
<?php if ($sec === 'tiles'): ?>
<section class="tsection" id="tiles">
  <h2>TAC//PAD tiles</h2>
  <p class="dim">A tile not ticked is not drawn at all, and the app behind it
  cannot be reached. If <strong>no</strong> role in the unit ticks anything, the
  gate is off entirely and everybody sees every tile.</p>
  <?php
    $tileOn = [];
    foreach ($r['tiles'] as $row) {
        if (!in_array(strtolower((string) $row[1]), ['false', '0', 'no'], true)) {
            $tileOn[] = strtolower((string) $row[0]);
        }
    }
  ?>
  <?php $open('tiles'); ?>
    <div class="checkgrid">
      <?php foreach (GHOSTD_TILES as $t => $desc): ?>
        <label class="inlinelabel">
          <input type="checkbox" name="tiles[]" value="<?= h($t) ?>" <?= in_array($t, $tileOn, true) ? 'checked' : '' ?>>
          <strong><?= h(strtoupper($t)) ?></strong> <span class="dim"><?= h($desc) ?></span></label>
      <?php endforeach; ?>
    </div>
    <div class="actions"><button type="submit">Save tiles</button></div>
  </form>
</section>
<?php endif; ?>

<!-- -------------------------------------------------------------- traits -->
<?php if ($sec === 'traits'): ?>
<section class="tsection" id="traits">
  <h2>Traits <span class="dim">the four the game has</span></h2>
  <p class="note"><strong>A trait is one of these four and nothing else.</strong>
  They are <code>setUnitTrait</code>. Anything else this unit puts on a man is a
  <em>variable</em> - <code>setVariable</code> - on the next screen, whether it
  is one of ACE3's or one of your own. <em>Skills</em> are TAC//PAC's catalogue,
  under "Who may take it", and PAC applies those <strong>after</strong> the
  role, so a name a skill owns is skipped here whatever you tick.</p>

  <?php
    $has = [];
    foreach ($r['traits'] as $row) {
        $has[(string) $row[0]] = $row[1];
    }
    $isOn = static fn($t) => isset($has[$t])
        && !in_array(strtolower((string) $has[$t]), ['false', '0', 'no', ''], true);
  ?>

  <?php $open('traits'); ?>
    <div class="checkgrid">
      <?php foreach (GHOSTD_ENGINE_TRAITS as $t => $desc): ?>
        <?php // A name a skill sets is not offered: PAC applies it after the
              // role and the role's copy is skipped, so a box for it does
              // nothing. Where each is set is on Configs > Skills. ?>
        <?php if (in_array(strtolower($t), $pacOwned, true)) { continue; } ?>
        <label class="inlinelabel">
          <input type="checkbox" name="t_on[]" value="<?= h($t) ?>"
                 <?= $isOn($t) ? 'checked' : '' ?>>
          <span><strong><?= h($t) ?></strong>
            <span class="dim"><?= h($desc) ?></span></span>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="actions"><button type="submit">Save traits</button></div>
  </form>

  <?php
    // A trait the role carries that is not one of the four is one this screen
    // cannot show, so the next save would drop it silently. Say so.
    $stray = [];
    foreach ($r['traits'] as $row) {
        $t = (string) $row[0];
        if (!isset(GHOSTD_ENGINE_TRAITS[$t])) { $stray[] = $t; }
    }
  ?>
  <?php if ($stray !== []): ?>
    <p class="flash bad">This role carries <?= h(implode(', ', $stray)) ?> as a
    <em>trait</em>, and none of those are one of the four.
    <strong>Saving this page drops them.</strong> They are variables - add them
    under <a href="?page=records&amp;s=traits">Configs &rarr; Custom
    variables</a> and tick them on the next screen instead.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($sec === 'vars'): ?>
<section class="tsection" id="vars">
  <h2>Variables <span class="dim">setVariable</span></h2>
  <p class="note">Put on the man with <code>setVariable</code> when he slots in.
  <strong>Two groups:</strong> ACE3 spells its own, and this unit spells the
  rest. Neither is a trait - traits are the four on the previous screen. Add to
  your own under <a href="?page=records&amp;s=traits">Configs &rarr; Custom
  variables</a>, so every role ticks the same spelling.</p>

  <?php
    // A NAME A SKILL SETS IS NOT OFFERED. Filter here rather than inside the
    // loop: skipping rows underneath a heading that is already on screen is
    // how a group ends up as a title with nothing under it.
    $cat = array_filter(ghostd_role_vars(),
        static fn($m, $n) => !in_array(strtolower((string) $n), $pacOwned, true),
        ARRAY_FILTER_USE_BOTH);
    $has = [];
    foreach ($r['customVariables'] as $row) { $has[(string) $row[0]] = $row[1]; }
    $isOn = static fn($n) => isset($has[$n])
        && !in_array(strtolower((string) $has[$n]), ['false', '0', 'no', ''], true);
  ?>

  <?php if ($cat === []): ?>
    <p class="note readonly">Nothing to tick - every variable this unit has is
    set by a skill, or the list is empty. Add names under
    <a href="?page=records&amp;s=traits">Configs &rarr; Custom variables</a>.</p>
  <?php else: ?>
    <?php $open('vars'); ?>
      <?php foreach (['ace' => "ACE3's", 'unit' => "This unit's own"] as $grp => $grpLabel): ?>
      <?php $inGrp = array_filter($cat, static fn($m) => ($m['group'] ?? 'unit') === $grp); ?>
      <?php if ($inGrp === []) { continue; } ?>
      <h3><?= h($grpLabel) ?> <span class="dim"><?= count($inGrp) ?></span></h3>
      <div class="checkgrid">
        <?php foreach ($inGrp as $vn => $meta): ?>
          <label class="inlinelabel">
            <?php if ($meta['kind'] === 'number'): ?>
              <input type="number" step="1" name="v_num[<?= h($vn) ?>]"
                     value="<?= h(isset($has[$vn]) ? (string) $has[$vn] : '') ?>">
            <?php else: ?>
              <input type="checkbox" name="v_on[]" value="<?= h($vn) ?>"
                     <?= $isOn($vn) ? 'checked' : '' ?>>
            <?php endif; ?>
            <span><strong><?= h($meta['label']) ?></strong>
              <?php if ($meta['label'] !== $vn): ?><code><?= h($vn) ?></code><?php endif; ?>
              <span class="dim"><?= h($meta['help']) ?></span></span>
          </label>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
      <div class="actions"><button type="submit">Save variables</button></div>
    </form>
  <?php endif; ?>

  <?php
    $stray = [];
    foreach ($r['customVariables'] as $row) {
        $vn = (string) $row[0];
        if (!isset($cat[$vn])) { $stray[] = $vn; }
    }
  ?>
  <?php if ($stray !== []): ?>
    <p class="flash bad">This role sets variables that are not on the list:
    <?= h(implode(', ', $stray)) ?>. <strong>Saving this page drops them.</strong>
    Add them under <a href="?page=records&amp;s=traits">Configs &rarr; Custom
    variables</a> first if you want to keep them.</p>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($sec === 'loadout'): ?>
<section class="tsection" id="loadout">
  <h2>Default loadout</h2>
  <p class="dim">What he spawns in. This is the array a config file writes -
  copy it out of one, or paste one in. In game the PAC role editor has a
  <strong>CAPTURE</strong> button that takes it off whoever is wearing it, which
  is how you actually build one.</p>

  <?php $summary = ghostd_loadout_summary($r['defaultLoadout']); ?>
  <?php if ($summary === []): ?>
    <p class="note readonly">Nothing set - he spawns in whatever the mission gives him.</p>
  <?php else: ?>
    <table class="kv">
      <?php foreach ($summary as $s): ?>
        <tr><th><?= h($s[0]) ?></th><td><?= h($s[1]) ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php $open('loadout'); ?>
    <textarea name="loadout" class="codebox" rows="16" spellcheck="false"><?= h(ghostd_sqf_encode($r['defaultLoadout'])) ?></textarea>
    <div class="actions"><button type="submit">Save loadout</button></div>
  </form>
  <p class="dim">Braces or brackets, either is read. Nothing is written unless
  the whole thing parses, so a bad paste cannot destroy what is there.</p>
</section>
<?php endif; ?>

<!-- ------------------------------------------------------------- arsenal -->
<?php if ($sec === 'arsenal'): ?>
<section class="tsection" id="arsenal">
  <h2>Arsenal</h2>
  <p class="note">Four layers, narrowest last: the <a href="?page=configedit&amp;t=arsenal">common
  arsenal</a>, then his platoon's, then his squad's, then this role's. They
  <strong>add up</strong> - nothing here takes anything away.</p>

  <?php $open('arsenal'); ?>
    <?php
      // THE ROLE'S ARSENAL IS THE ROLE'S. It used to be a dropdown naming a
      // separate document - "Arsenal_Wraith", which on this unit did not exist
      // and had never been filled in (user, 2026-09-09). The document is
      // derived from the role's id now, the way a squad's and a platoon's are,
      // so there is nothing to point at.
      // $id, NOT $rid. $rid is the rank picker's loop variable further up this
      // file and still holds the last rank id here - every role's arsenal
      // resolved to role_COLONEL, one document shared by all of them, named
      // after a rank (2026-09-09).
      $rv = ghostd_role_variant($id);
      $rvHas = ghostd_variant_summary('arsenal', $rv);
    ?>
    <h3>This role's own gear <span class="dim">one classname per line</span></h3>
    <p class="dim">On top of everything above. The kit the job needs and nobody
    else gets - a laser designator, a spare 117F.</p>
    <div class="fieldbox">
      <label class="stacked">Weapons
        <textarea name="arsenalWeapons" rows="5" class="short"><?= h(implode("\n", $r['arsenalWeapons'])) ?></textarea></label>
      <label class="stacked">Magazines
        <textarea name="arsenalMagazines" rows="5" class="short"><?= h(implode("\n", $r['arsenalMagazines'])) ?></textarea></label>
      <label class="stacked">Items
        <textarea name="arsenalItems" rows="5" class="short"><?= h(implode("\n", $r['arsenalItems'])) ?></textarea></label>
      <label class="stacked">Backpacks
        <textarea name="arsenalBackpacks" rows="5" class="short"><?= h(implode("\n", $r['arsenalBackpacks'])) ?></textarea></label>
    </div>
    <div class="actions"><button type="submit">Save arsenal</button></div>
  </form>

  <?php // THE ROLE'S WHOLE ARSENAL, in the boxes, on this page. A separate form
        // because the gear lists above are their own save and HTML has no
        // nested forms - two buttons, two documents, one page. ?>
  <h3>This role's arsenal <span class="dim">the whole document, its own</span></h3>
  <p class="dim">A list only this role draws from, on top of the common one, its
  platoon's and its squad's. Empty until you put something in it.</p>
  <?php
    $ilKey = 'arsenal'; $ilVariant = $rv; $ilLabel = "this role's arsenal";
    $ilHidden = ['id' => $id, 's' => $sec];
    require __DIR__ . '/_edit_inline.php';
  ?>
</section>
<?php endif; ?>

<?php if ($sec === 'remove'): ?>
<section class="tsection" id="remove">
  <h2>Remove</h2>
  <form method="post" class="danger">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= h($id) ?>">
    <input type="hidden" name="what" value="delete">
    <?php if ($usedBy !== []): ?>
      <p class="flash bad">This role fills slots in <?= h(implode(', ', $usedBy)) ?>.
      Removing it leaves those slots unfillable.</p>
      <label class="inlinelabel"><input type="checkbox" name="confirm" value="yes"> I know</label>
    <?php endif; ?>
    <button type="submit" class="hot">Delete <?= h($id) ?></button>
  </form>
</section>
<?php endif; ?>
<?php
ghostd_foot();
