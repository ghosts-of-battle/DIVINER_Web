<?php
/**
 * The ORBAT maker: platoons, squads and radio nets.
 *
 * THIS IS WHAT config_groups.hpp WAS. Dynamic_Groups is read through
 * fnc_orbat, which already prefers the <unit>.orbat document - so a unit that
 * fills this in needs no config_groups.hpp at all.
 *
 * THE SHAPE IS POSITIONAL, and that is the mod's, not a choice made here:
 *   groups     [squad name, [role ids], condition]
 *   platoons   [id, display name, callsign, net, [squad names]]
 *   radioNets  [id, display name, [squad names]]
 * A squad exists because a platoon lists it and a group defines its roles; the
 * two halves are edited on one page so they cannot drift apart.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';

$cfg   = ghostd_config();
$docId = $cfg['unit'] . '.orbat';

$msg = null;
$err = null;

/** Split a textarea into trimmed non-empty lines. */
$lines = static function (string $s): array {
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $s) ?: []),
        static fn($x) => $x !== ''));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $doc = ghostd_get($docId);
        $doc = is_array($doc) ? $doc : [];
        unset($doc['_id']);

        // ---- platoons -----------------------------------------------------
        $platoons = [];
        foreach ((array) ($_POST['p_id'] ?? []) as $i => $pid) {
            $pid = trim((string) $pid);
            if ($pid === '' || in_array((string) $i, (array) ($_POST['p_remove'] ?? []), true)) {
                continue;
            }
            $platoons[] = [
                $pid,
                trim((string) ($_POST['p_name'][$i] ?? '')),
                trim((string) ($_POST['p_callsign'][$i] ?? '')),
                trim((string) ($_POST['p_net'][$i] ?? '')),
                $lines((string) ($_POST['p_squads'][$i] ?? '')),
            ];
        }

        // ---- groups (a squad and the roles it holds) -----------------------
        $groups = [];
        foreach ((array) ($_POST['g_name'] ?? []) as $i => $gname) {
            $gname = trim((string) $gname);
            if ($gname === '' || in_array((string) $i, (array) ($_POST['g_remove'] ?? []), true)) {
                continue;
            }
            $cond = trim((string) ($_POST['g_cond'][$i] ?? ''));
            $groups[] = [
                $gname,
                $lines((string) ($_POST['g_roles'][$i] ?? '')),
                $cond === '' ? 'true' : $cond,
            ];
        }

        // ---- radio nets ----------------------------------------------------
        $nets = [];
        foreach ((array) ($_POST['n_id'] ?? []) as $i => $nid) {
            $nid = trim((string) $nid);
            if ($nid === '' || in_array((string) $i, (array) ($_POST['n_remove'] ?? []), true)) {
                continue;
            }
            $nets[] = [
                $nid,
                trim((string) ($_POST['n_name'][$i] ?? '')),
                $lines((string) ($_POST['n_squads'][$i] ?? '')),
            ];
        }

        if ($platoons === [] && $groups === []) {
            throw new RuntimeException('That would leave no platoons and no squads. The mission reads this at boot - an empty ORBAT is a mission nobody can slot into.');
        }

        $doc['section']   = 'orbat';
        $doc['faction']   = trim((string) ($_POST['faction'] ?? ''));
        $doc['platoons']  = $platoons;
        $doc['groups']    = $groups;
        $doc['radioNets'] = $nets;
        $doc['from']      = 'DIVINER_Web';
        $doc['updatedAt'] = gmdate('Y-m-d H:i:s');

        ghostd_put($docId, $doc);
        $msg = count($platoons) . ' platoons, ' . count($groups) . ' squads and '
             . count($nets) . ' radio nets saved. Read at the next mission start.';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$o = [];
try {
    $o = ghostd_get($docId) ?? [];
} catch (Throwable $e) {
    $err = $err ?? $e->getMessage();
}
$faction   = (string) ($o['faction'] ?? '');
$platoons  = is_array($o['platoons'] ?? null) ? $o['platoons'] : [];
$groups    = is_array($o['groups'] ?? null) ? $o['groups'] : [];
$radioNets = is_array($o['radioNets'] ?? null) ? $o['radioNets'] : [];

// Every squad a platoon claims, so a squad with no roles can be pointed out.
$claimed = [];
foreach ($platoons as $p) {
    foreach ((array) ($p[4] ?? []) as $sq) { $claimed[(string) $sq] = true; }
}
$defined = [];
foreach ($groups as $g) { $defined[(string) ($g[0] ?? '')] = true; }

ghostd_head('ORBAT', 'orbat');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

$orphanSquads = array_diff(array_keys($claimed), array_keys($defined));
$unclaimed    = array_diff(array_keys($defined), array_keys($claimed));
?>
<p class="dim">document <code><?= h($docId) ?></code> &middot; replaces
<code>config_groups.hpp</code> (Dynamic_Groups)</p>

<p class="note">Platoons hold squads; squads hold roles. A squad only appears in
game if a platoon lists it <em>and</em> a squad entry gives it roles - which is
why both are on this page.</p>

<?php if ($orphanSquads !== []): ?>
  <p class="flash bad">Listed by a platoon but given no roles:
  <?= h(implode(', ', $orphanSquads)) ?>. Nobody can slot into these.</p>
<?php endif; ?>
<?php if ($unclaimed !== []): ?>
  <p class="note readonly">Has roles but no platoon lists it:
  <?= h(implode(', ', $unclaimed)) ?>. These will not show in the group menu.</p>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">

  <label for="faction">Faction name</label>
  <input type="text" id="faction" name="faction" value="<?= h($faction) ?>" placeholder="Framework">

  <h2>Platoons <span class="dim"><?= count($platoons) ?></span></h2>
  <?php $rows = $platoons; $rows[] = ['', '', '', '', []]; ?>
  <?php foreach ($rows as $i => $p): ?>
    <fieldset class="line">
      <legend><?= $i < count($platoons) ? h((string) ($p[1] ?? $p[0])) : '<span class="key">new</span>' ?></legend>
      <div class="fieldbox">
        <input type="text" name="p_id[<?= $i ?>]" value="<?= h((string) ($p[0] ?? '')) ?>" placeholder="id (C2, Plt1)">
        <input type="text" name="p_name[<?= $i ?>]" value="<?= h((string) ($p[1] ?? '')) ?>" placeholder="1ST PLT INF">
        <input type="text" name="p_callsign[<?= $i ?>]" value="<?= h((string) ($p[2] ?? '')) ?>" placeholder="callsign">
        <input type="text" name="p_net[<?= $i ?>]" value="<?= h((string) ($p[3] ?? '')) ?>" placeholder="net" list="netlist">
        <?php if ($i < count($platoons)): ?>
          <label class="inlinelabel"><input type="checkbox" name="p_remove[]" value="<?= $i ?>"> remove</label>
        <?php endif; ?>
      </div>
      <textarea name="p_squads[<?= $i ?>]" rows="3" class="short"
                placeholder="one squad name per line"><?= h(implode("\n", (array) ($p[4] ?? []))) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <h2>Squads and their roles <span class="dim"><?= count($groups) ?></span></h2>
  <?php $rows = $groups; $rows[] = ['', [], 'true']; ?>
  <?php foreach ($rows as $i => $g): ?>
    <fieldset class="line">
      <legend><?= $i < count($groups) ? h((string) ($g[0] ?? '')) : '<span class="key">new</span>' ?></legend>
      <div class="fieldbox">
        <input type="text" name="g_name[<?= $i ?>]" value="<?= h((string) ($g[0] ?? '')) ?>" placeholder="GHOST 6">
        <input type="text" name="g_cond[<?= $i ?>]" value="<?= h((string) ($g[2] ?? 'true')) ?>" placeholder="condition, or true">
        <?php if ($i < count($groups)): ?>
          <label class="inlinelabel"><input type="checkbox" name="g_remove[]" value="<?= $i ?>"> remove</label>
        <?php endif; ?>
      </div>
      <textarea name="g_roles[<?= $i ?>]" rows="4" class="short"
                placeholder="one role id per line - the order is the slot order"><?= h(implode("\n", (array) ($g[1] ?? []))) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <h2>Radio nets <span class="dim"><?= count($radioNets) ?></span></h2>
  <p class="dim">Which squads share a net. The net ids come from the
  <a href="?page=configedit&amp;t=nets">radio nets template</a>.</p>
  <?php $rows = $radioNets; $rows[] = ['', '', []]; ?>
  <?php foreach ($rows as $i => $n): ?>
    <fieldset class="line">
      <legend><?= $i < count($radioNets) ? h((string) ($n[1] ?? $n[0])) : '<span class="key">new</span>' ?></legend>
      <div class="fieldbox">
        <input type="text" name="n_id[<?= $i ?>]" value="<?= h((string) ($n[0] ?? '')) ?>" placeholder="Ground1">
        <input type="text" name="n_name[<?= $i ?>]" value="<?= h((string) ($n[1] ?? '')) ?>" placeholder="GROUND 1">
        <?php if ($i < count($radioNets)): ?>
          <label class="inlinelabel"><input type="checkbox" name="n_remove[]" value="<?= $i ?>"> remove</label>
        <?php endif; ?>
      </div>
      <textarea name="n_squads[<?= $i ?>]" rows="2" class="short"
                placeholder="one squad name per line"><?= h(implode("\n", (array) ($n[2] ?? []))) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <datalist id="netlist">
    <?php foreach (ghostd_template_items('nets') as $nid => $n): ?>
      <option value="<?= h((string) $nid) ?>"></option>
    <?php endforeach; ?>
  </datalist>

  <div class="actions"><button type="submit">Save ORBAT</button></div>
</form>
<?php
ghostd_foot();
