<?php
/**
 * Config templates: one TAB per type, its versions listed under it.
 *
 * ONE TAB PER TYPE because that is how somebody looks for one - "the arsenal,
 * the Banshee one" - not by scrolling past eleven other kinds of config to
 * reach it. Twelve sections stacked down one page was a page nobody read to the
 * bottom of. The common version is listed first and always offered, even before
 * anything has been saved to it: opening it is how it gets created.
 *
 * NOTHING HERE PICKS WHICH VERSION IS USED. A mission names the ones it runs
 * in its own CfgGFA_PAC settings; this page is where the versions are written,
 * not where one is chosen. Two places to decide is one too many.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';

/** How much is in one version of one template, or null if it cannot be read. */
function ghostd_count_one(string $key, array $t, string $variant): ?int
{
    try {
        switch ($t['shape']) {
            case 'welcome':
                $wt = ghostd_welcome($variant)['text'];
                return $wt === '' ? 0 : substr_count($wt, "\n") + 1;
            case 'lists':
                return array_sum(array_map('count', ghostd_template_lists($key, $variant)));
            case 'code':
                $c = ghostd_template_code($key, $variant);
                return $c === '' ? 0 : substr_count($c, "\n") + 1;
            default:
                return count(ghostd_template_items($key, $variant));
        }
    } catch (Throwable $e) {
        return null;
    }
}

$unit = ghostd_config()['unit'];

// WHICH TAB. Every template type, then the ORBAT, then the inventory.
$tabs = [];
foreach (GHOSTD_TEMPLATES as $k => $t) {
    // The messaging nets are edited on the ORBAT page, beside the radio - a net
    // is what a role reads and what a platoon commands on. Two places to edit
    // one list is one too many.
    // Both of these are edited on the ORBAT page: the messaging nets beside
    // the radio, the custom traits on its Details tab. Two places to edit one
    // list is one too many.
    // Three live elsewhere: the messaging nets beside the radio on the ORBAT
    // page, the custom traits on its Details tab, and the arsenals on a page of
    // their own - there are more of those than of anything else and they are
    // what people edit most.
    if ($k === 'nets' || $k === 'traits' || $k === 'arsenal') {
        continue;
    }
    $tabs[$k] = $t['label'];
}
// THE FORMS THE SYSTEM ITSELF IS MADE OF - the shape of an operation order and
// the kinds of PAC request. Not config a mission ships, which is why they are
// their own tab rather than mixed in with the arsenal and the motorpool.
$tabs['system'] = 'System';

// THE ORBAT IS NOT A TAB HERE. It is a template type like the rest, but it has
// a whole page of its own with its own versions tab, and two places to switch
// order of battle is one too many.

$tab = (string) ($_GET['t'] ?? '');
if (!isset($tabs[$tab])) {
    $tab = (string) array_key_first($tabs);
}

// WHICH VERSION IS IN USE. A unit keeps several common arsenals - a bare-bones
// one and a set named for the camo an operation is in - and until this was
// wired the camo sets were documents nobody read.
// ---- the System tab's saves ---------------------------------------------
$msg = null;
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['t'] ?? '') === 'system') {
    ghostd_csrf_check();
    require_once __DIR__ . '/../system.php';
    try {
        switch ((string) ($_POST['what'] ?? '')) {
            case 'opord':
                $helpWas = [];
                foreach (ghostd_opord_rows() as $w) {
                    $helpWas[$w['section'] . '.' . $w['field']] = $w['help'];
                }
                $rows = [];
                foreach ((array) ($_POST['o_field'] ?? []) as $i => $fid) {
                    $fid = trim((string) $fid);
                    $sec = trim((string) ($_POST['o_section'][$i] ?? ''));
                    if ($fid === '' || $sec === ''
                        || in_array((string) $i, (array) ($_POST['o_remove'] ?? []), true)) {
                        continue;
                    }
                    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $fid)
                        || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $sec)) {
                        throw new RuntimeException('"' . $sec . '.' . $fid . '" is not usable - a '
                            . 'section and a field are letters, digits and underscore, starting with '
                            . 'a letter. They are what a report template points at.');
                    }
                    $rows[] = [
                        'section'      => $sec,
                        'sectionTitle' => ucfirst($sec),
                        'sectionHint'  => '',
                        'field'        => $fid,
                        'label'        => trim((string) ($_POST['o_label'][$i] ?? '')) ?: $fid,
                        'kind'         => (string) ($_POST['o_kind'][$i] ?? 'x'),
                        // The help text a field carries is kept as it was -
                        // the editor stopped showing it rather than losing it.
                        'help'         => (string) ($helpWas[$sec . '.' . $fid] ?? ''),
                    ];
                }
                if ($rows === []) {
                    throw new RuntimeException('An order with no fields is not an order. Leave at least one.');
                }
                ghostd_opord_rows_save($rows);
                $msg = count($rows) . ' fields saved. Orders already written keep what is in them.';
                break;

            case 'ticketkinds':
                $items = [];
                foreach ((array) ($_POST['k_id'] ?? []) as $i => $kid) {
                    $kid = trim((string) $kid);
                    if ($kid === '' || in_array((string) $i, (array) ($_POST['k_remove'] ?? []), true)) {
                        continue;
                    }
                    if (!preg_match('/^[a-z][a-z0-9_]*$/', $kid)) {
                        throw new RuntimeException('"' . $kid . '" is not a kind id - lower case '
                            . 'letters, digits and underscore. It is what every ticket carries.');
                    }
                    $items[$kid] = [
                        'label' => trim((string) ($_POST['k_label'][$i] ?? '')) ?: $kid,
                        'hint'  => trim((string) ($_POST['k_hint'][$i] ?? '')),
                    ];
                }
                ghostd_ticket_kinds_save($items);
                $msg = count($items) . ' request kinds saved.';
                break;

            case 'resetopord':
                ghostd_system_reset('opord');
                $msg = 'The order is back to the shape it ships with.';
                break;

            case 'resetkinds':
                ghostd_system_reset('ticketKinds');
                $msg = 'The request kinds are back to the ones it ships with.';
                break;

            default:
                throw new RuntimeException('Nothing said what to save.');
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

ghostd_head('Config templates', 'config');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<?php if ($tab !== 'system'): ?>
<p class="note"><strong>The mission decides which version it runs.</strong>
The <em>default</em> is simply the one a mission gets when it names none - it is
not a version to choose between, and the only thing with a default worth setting
deliberately is the <a href="?page=orbat&amp;s=versions">order of battle</a>.
Each type can hold as many versions as you like; a mission names the ones it
wants in its own <code>CfgGFA_PAC &gt; settings</code> -
<code>currentArsenal</code>, <code>currentMotorpool</code>,
<code>currentWelcome</code> and the rest. Nothing on this page picks one, because
two places to decide is one too many. Unnamed means the common version.</p>

<p class="dim">A platoon's or a squad's own version of any of these is edited on
that platoon or that squad, not here.</p>

<p class="note">What a mission used to ship in its <code>config\</code> folder,
kept in the database instead. <strong>One set per unit</strong> - every mission
naming <code><?= h($unit) ?></code> reads these, so the framework missions and
Roomba share them.</p>
<?php endif; ?>

<nav class="sections onebar">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="?page=config&amp;t=<?= urlencode($k) ?>"
       class="<?= $tab === $k ? 'on' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'system'): ?>
  <?php require __DIR__ . '/config_system.php'; ?>
<?php endif; ?>

<?php foreach (GHOSTD_TEMPLATES as $key => $t): ?>
  <?php if ($tab !== $key || in_array($key, ['nets', 'traits', 'arsenal'], true)) { continue; } ?>
  <?php
    // A plt_* or sqd_* version belongs to a platoon or a squad and is edited
    // there. This page lists the common templates, and only those.
    $rows = array_merge([''], array_values(array_filter(
        ghostd_template_variants($key),
        static fn($v) => !str_starts_with($v, 'plt_') && !str_starts_with($v, 'sqd_')
    )));
  ?>
  <section class="tsection">
    <h2><?= h($t['label']) ?></h2>
    <p class="dim">
      replaces <code><?= h($t['replaces']) ?></code> &middot;
      <?= count($rows) ?> version<?= count($rows) === 1 ? '' : 's' ?>
    </p>
    <table class="grid">
      <thead>
        <tr>
          <th>Version</th><th>Mongo doc</th>
          <th><?= $t['shape'] === 'code' ? 'Lines' : 'Entries' ?></th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $v): ?>
        <?php $n = ghostd_count_one($key, $t, $v); ?>
        <tr>
          <td><?= $v === ''
                ? '<strong>Default</strong> <span class="dim">used when a mission names no version</span>'
                : '<code>' . h($v) . '</code>' ?></td>
          <td class="dim"><code><?= h($unit . '.' . $t['doc'] . ($v !== '' ? '.' . $v : '')) ?></code></td>
          <td><?= $n === null ? '<span class="dim">unreadable</span>' : (int) $n ?></td>
          <td><a href="?page=configedit&amp;t=<?= urlencode($key) ?><?= $v !== '' ? '&amp;v=' . urlencode($v) : '' ?>">edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="dim"><a href="?page=configedit&amp;t=<?= urlencode($key) ?>">Open
    <?= h(strtolower($t['label'])) ?></a> to add a version.</p>

  </section>
<?php endforeach; ?>



<?php
ghostd_foot();
