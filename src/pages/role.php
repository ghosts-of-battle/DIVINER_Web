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
 * the TAC//PAD has, the rank from the rank list, the group arsenal from the
 * arsenals that exist. A typed id that matches nothing is a permission that
 * grants nothing, and nothing in game says so.
 *
 * The loadout is the exception and always will be: it is a nested array copied
 * out of a config file. It gets a paste box and a plain-English summary of what
 * it resolves to, which is the check that the paste went in the right way round.
 * In game there is a CAPTURE button instead - see ghostD_pac_fnc_roleCapture.
 */

declare(strict_types=1);

require_once __DIR__ . '/../roles.php';

$unit = ghostd_config()['unit'];

$id  = trim((string) ($_GET['id'] ?? ($_POST['id'] ?? '')));
$new = ($id === '' || $id === '+');

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

            case 'traits':
                ghostd_role_save($id, ['traits' => $rows('trait', 2)]);
                $msg = 'Traits saved.';
                break;

            case 'vars':
                ghostd_role_save($id, ['customVariables' => $rows('var', 3)]);
                $msg = 'Custom variables saved.';
                break;

            case 'loadout':
                $l = ghostd_sqf_decode((string) ($_POST['loadout'] ?? ''));
                ghostd_role_save($id, ['defaultLoadout' => $l]);
                $msg = 'Default loadout saved - ' . count($l) . ' slots.';
                break;

            case 'arsenal':
                ghostd_role_save($id, [
                    'groupArsenal'     => trim((string) ($_POST['groupArsenal'] ?? '')),
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
$open = static function (string $what) use ($csrf, $id) {
    echo '<form method="post">'
       . '<input type="hidden" name="csrf" value="' . h($csrf) . '">'
       . '<input type="hidden" name="id" value="' . h($id) . '">'
       . '<input type="hidden" name="what" value="' . h($what) . '">';
};
?>
<p class="dim"><a href="?page=orbat&amp;s=roles">&larr; Roles</a> &middot;
<code><?= h(ghostd_role_doc_id($id)) ?></code> &middot;
<?= $usedBy === [] ? 'used by nothing' : 'in ' . h(implode(', ', $usedBy)) ?></p>

<nav class="sections onebar">
  <?php foreach (GHOSTD_ROLE_SECTIONS as $k => $label): ?>
    <a href="#<?= h($k) ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<!-- ------------------------------------------------------------ identity -->
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

<!-- --------------------------------------------------------------- gates -->
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

    <div class="actions"><button type="submit">Save who may take it</button></div>
  </form>
</section>

<!-- ---------------------------------------------------------------- nets -->
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
            <select name="net_name[<?= $i ?>]" style="min-width:14rem">
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

<!-- --------------------------------------------------------------- tiles -->
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

<!-- -------------------------------------------------------------- traits -->
<section class="tsection" id="traits">
  <h2>Traits</h2>
  <p class="dim">Engine traits - <code>UAVHacker</code>, <code>audibleCoef</code>.
  Anything TAC//PAC's skills own (medic, engineer, EOD, leader ...) is applied by
  PAC afterwards and is ignored here, so set those as skills above.</p>
  <?php $open('traits'); ?>
    <table class="grid">
      <thead><tr><th>Trait</th><th>Value</th><th>Remove</th></tr></thead>
      <tbody>
      <?php $traitRows = $r['traits']; $traitRows[] = ['', 'true']; ?>
      <?php foreach ($traitRows as $i => $row): ?>
        <?php $isNew = $i >= count($r['traits']); ?>
        <tr>
          <td><input type="text" name="trait_name[<?= $i ?>]" value="<?= h((string) $row[0]) ?>"
                     placeholder="<?= $isNew ? 'UAVHacker' : '' ?>" style="min-width:14rem"></td>
          <td><input type="text" name="trait_v1[<?= $i ?>]" value="<?= h((string) $row[1]) ?>"
                     placeholder="true" style="min-width:8rem"></td>
          <td><?= $isNew ? '' : '<input type="checkbox" name="trait_remove[]" value="' . $i . '">' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="actions"><button type="submit">Save traits</button></div>
  </form>
</section>

<!-- ---------------------------------------------------------------- vars -->
<section class="tsection" id="vars">
  <h2>Custom variables</h2>
  <p class="dim">Put on the man with <code>setVariable</code> when he slots in -
  <code>draWhitelisted</code>, <code>isISR</code>,
  <code>ace_medical_medicClass</code>. <strong>Global</strong> means every
  machine sees it; leave it on unless you know it is local.</p>
  <?php $open('vars'); ?>
    <table class="grid">
      <thead><tr><th>Variable</th><th>Value</th><th>Global</th><th>Remove</th></tr></thead>
      <tbody>
      <?php $varRows = $r['customVariables']; $varRows[] = ['', 'true', 'true']; ?>
      <?php foreach ($varRows as $i => $row): ?>
        <?php $isNew = $i >= count($r['customVariables']); ?>
        <tr>
          <td><input type="text" name="var_name[<?= $i ?>]" value="<?= h((string) $row[0]) ?>"
                     placeholder="<?= $isNew ? 'draWhitelisted' : '' ?>" style="min-width:16rem"></td>
          <td><input type="text" name="var_v1[<?= $i ?>]" value="<?= h((string) $row[1]) ?>"
                     placeholder="true" style="min-width:8rem"></td>
          <td>
            <select name="var_v2[<?= $i ?>]">
              <option value="true" <?= (string) ($row[2] ?? 'true') !== 'false' ? 'selected' : '' ?>>yes</option>
              <option value="false" <?= (string) ($row[2] ?? 'true') === 'false' ? 'selected' : '' ?>>no</option>
            </select>
          </td>
          <td><?= $isNew ? '' : '<input type="checkbox" name="var_remove[]" value="' . $i . '">' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="actions"><button type="submit">Save custom variables</button></div>
  </form>
</section>

<!-- ------------------------------------------------------------- loadout -->
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

<!-- ------------------------------------------------------------- arsenal -->
<section class="tsection" id="arsenal">
  <h2>Arsenal</h2>
  <p class="note">Four layers, narrowest last: the <a href="?page=configedit&amp;t=arsenal">common
  arsenal</a>, then his platoon's, then his squad's, then this role's. They
  <strong>add up</strong> - nothing here takes anything away.</p>

  <?php $open('arsenal'); ?>
    <div class="fields">
      <label>Group arsenal <span class="dim">a named arsenal version this role also draws from</span>
        <select name="groupArsenal">
          <option value="">- none -</option>
          <?php foreach ($arsenals as $a): ?>
            <option value="<?= h($a) ?>" <?= $r['groupArsenal'] === $a ? 'selected' : '' ?>><?= h($a) ?></option>
          <?php endforeach; ?>
          <?php if ($r['groupArsenal'] !== '' && !in_array($r['groupArsenal'], $arsenals, true)): ?>
            <option value="<?= h($r['groupArsenal']) ?>" selected><?= h($r['groupArsenal']) ?> (no such arsenal)</option>
          <?php endif; ?>
        </select></label>
    </div>
    <?php if ($r['groupArsenal'] !== ''): ?>
      <p class="dim"><a href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($r['groupArsenal']) ?>">Edit
      <?= h($r['groupArsenal']) ?></a></p>
    <?php endif; ?>

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
</section>

<section class="tsection">
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
<?php
ghostd_foot();
