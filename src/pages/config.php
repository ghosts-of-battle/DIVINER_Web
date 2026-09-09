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
// THE REPORT DECK AND THE COLOUR SCHEMES LIVE HERE TOO (user, 2026-09-09:
// "Report deck can go under templates", "Colour schemes ... move under
// templates/system"). Neither is a versioned template, so neither goes through
// configedit - they are drawn by their own partial below.
$tabs['deck']   = 'Report deck';
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
            // The operation order is edited a section at a time - see
            // src/pages/opord_section.php, which owns that save.
            // The request kinds are edited one at a time - see
            // src/pages/ticket_kind.php, which owns that save.
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
<p class="dim">A mission names the versions it runs in its own
<code>CfgGFA_PAC &gt; settings</code> - <code>currentArsenal</code>,
<code>currentWelcome</code> and the rest. Default is what it gets when it names
none. One set per unit: every mission naming <code><?= h($unit) ?></code> reads
these.</p>

<p class="dim">A platoon's or a squad's own version of any of these is edited on
that platoon or that squad, not here.</p>

<?php endif; ?>

<nav class="sections onebar">
  <?php foreach ($tabs as $k => $label): ?>
    <a href="?page=config&amp;t=<?= urlencode($k) ?>"
       class="<?= $tab === $k ? 'on' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'deck'): ?>
  <?php require __DIR__ . '/deck_list.php'; ?>
<?php elseif ($tab === 'system'): ?>
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
