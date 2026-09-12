<?php
/**
 * Reforger config generator.
 *
 * The ORBAT is edited here; Reforger reads it as .conf resources loaded at world
 * init, which cannot be built from the database at runtime. So this turns the
 * documents into the files the Reforger mod ships.
 *
 * OFF BY DEFAULT, and the switch is on this page. A unit that does not play
 * Reforger turns it on once or never sees it again.
 *
 * NOTHING HERE TOUCHES A RUNNING SERVER. It produces text; an admin downloads
 * it, drops it into the addon, commits, builds, republishes. That is the whole
 * loop, and it is deliberate - a live ORBAT swap under a running mission is not
 * something the engine can do.
 */

declare(strict_types=1);

require_once __DIR__ . '/../reforger.php';

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $what = (string) ($_POST['what'] ?? 'settings');

        if ($what === 'settings') {
            $skip = [];
            foreach ((array) ($_POST['skip'] ?? []) as $id) {
                $skip[] = (string) $id;
            }
            ghostd_rf_settings_save([
                'enabled'   => isset($_POST['enabled']),
                'factions'  => isset($_POST['factions']),
                'radio'     => isset($_POST['radio']),
                'callsigns' => isset($_POST['callsigns']),
                'prefix'    => (string) ($_POST['prefix'] ?? ''),
                'skip'      => $skip,
            ]);
            $msg = 'Saved.';
        } elseif ($what === 'download') {
            $gen = ghostd_rf_generate();

            if (!$gen['files']) {
                $err = 'Nothing was generated. ' . implode(' ', $gen['notes']);
            } else {
                // THE LEDGER IS SAVED ONLY ON A REAL DOWNLOAD. A preview must
                // not mint GUIDs that the download then disagrees with.
                ghostd_rf_ledger_save($gen['ledger']);
                ghostd_rf_send_zip($gen['files']);   // exits
            }
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$cfg    = ghostd_rf_settings();
$orbat  = ghostd_rf_orbat();
$ranks  = ghostd_rf_ranks();
$preview = $cfg['enabled'] ? ghostd_rf_generate() : ['files' => [], 'notes' => []];

ghostd_head('Reforger', 'reforger');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="note">Turns <code><?= h(ghostd_rf_doc_id('orbat')) ?></code>,
<code><?= h(ghostd_rf_doc_id('ranks')) ?></code> and
<code><?= h(ghostd_rf_doc_id('radio')) ?></code> into the
<code>.conf</code> files an Arma Reforger mod ships. Download, drop into the
addon, commit, build, republish - <strong>a change is not live</strong>.
Who holds which rank, promotions, awards and attendance stay live; the ladder
and the order of battle move at the speed of a mod release.</p>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="settings">

  <p><label><input type="checkbox" name="enabled" value="1"<?= $cfg['enabled'] ? ' checked' : '' ?>>
     <strong>Enable the Reforger config generator</strong></label><br>
     <span class="dim">Off by default. Nothing else on this page applies while it is off.</span></p>

  <p><label>Faction key prefix
     <input type="text" name="prefix" value="<?= h($cfg['prefix']) ?>" size="16">
     </label><br>
     <span class="dim">A platoon called <code>Able</code> becomes the faction key
     <code><?= h(ghostd_rf_key($cfg['prefix'] . '_Able')) ?></code>.</span></p>

  <p><strong>What to write</strong><br>
     <label><input type="checkbox" name="factions" value="1"<?= $cfg['factions'] ? ' checked' : '' ?>> Factions - one per platoon, with its squads, rank ladder and nets</label><br>
     <label><input type="checkbox" name="radio" value="1"<?= $cfg['radio'] ? ' checked' : '' ?>> Radio plan - <code>Configs/freqs/freqs.conf</code></label><br>
     <label><input type="checkbox" name="callsigns" value="1"<?= $cfg['callsigns'] ? ' checked' : '' ?>> Callsigns</label><br>
     <span class="dim">A unit may want generated frequencies while keeping
     hand-written factions.</span></p>

<?php if ($orbat['platoons']): ?>
  <p><strong>Skip these platoons</strong><br>
     <span class="dim">A platoon that is Arma-3-only, or hand-maintained.
     Anything not ticked is written - opting out is the deliberate act, so a new
     platoon is never silently left out.</span><br>
<?php foreach ($orbat['platoons'] as $p):
        $id = (string) ($p[0] ?? '');
        if ($id === '') { continue; }
        $on = in_array($id, $cfg['skip'], true); ?>
     <label><input type="checkbox" name="skip[]" value="<?= h($id) ?>"<?= $on ? ' checked' : '' ?>>
       <?= h((string) ($p[1] ?? $id)) ?> <span class="dim"><?= h($id) ?></span></label><br>
<?php endforeach; ?>
  </p>
<?php endif; ?>

  <p><button type="submit">Save</button></p>
</form>

<?php if ($cfg['enabled']): ?>
<h2>What it will write</h2>

<?php foreach ($preview['notes'] as $n): ?>
<p class="note"><?= h($n) ?></p>
<?php endforeach; ?>

<?php if ($preview['files']): ?>
<table>
  <tr><th>File</th><th>Size</th></tr>
<?php foreach ($preview['files'] as $path => $body): ?>
  <tr><td><code><?= h($path) ?></code></td>
      <td class="dim"><?= h((string) strlen($body)) ?> bytes</td></tr>
<?php endforeach; ?>
</table>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="download">
  <p><button type="submit">Download</button>
     <span class="dim">Downloading is what fixes the GUIDs. Until then this is
     only a preview.</span></p>
</form>

<h2>Preview</h2>
<?php foreach ($preview['files'] as $path => $body):
        if (substr($path, -5) === '.meta') { continue; } ?>
<h3><code><?= h($path) ?></code></h3>
<pre><?= h($body) ?></pre>
<?php endforeach; ?>
<?php endif; ?>

<h2>Why a change is not live</h2>
<p class="note">Factions, group presets, rank ladders and radio plans are
<code>.conf</code> resources <strong>read at world init</strong>. Reforger cannot
create a faction or add a squad from a database at boot, so the order of battle
has to arrive as files in the mod. Everything PAC owns - who holds which rank,
promotions, awards, attendance, status, notes - stays live and needs none of
this.</p>

<p class="note"><strong>The GUIDs are the part that matters.</strong> Every
resource carries a 16-hex id and other files reference it by that id. Renaming a
squad keeps its GUID, because the ledger in
<code><?= h(ghostd_rf_doc_id('guids')) ?></code> remembers it - otherwise every
reference to that squad would break the next time you generated.</p>
<?php endif; ?>

<?php ghostd_foot(); ?>
