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

// WHICH TAB. Every template type, in the order the registry lists them.
$tabs = [];
foreach (GHOSTD_TEMPLATES as $k => $t) {
    // The messaging nets are edited on the ORBAT page, beside the radio: a net
    // is what a role reads and what a platoon commands on, and two places to
    // edit one list is one too many. Everything else - the arsenals included -
    // is a tab here (2026-09-09: the arsenals had a page of their own, it went
    // away, and this skip was what made them unreachable).
    if ($k === 'nets') {
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

// ---- deleting one version ------------------------------------------------
$msg = null;
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['what'] ?? '') === 'delversion') {
    ghostd_csrf_check();
    require_once __DIR__ . '/../roles.php';        // ghostd_doc_delete
    try {
        $dk = (string) ($_POST['key'] ?? '');
        $dv = trim((string) ($_POST['v'] ?? ''));
        if (!isset(GHOSTD_TEMPLATES[$dk])) {
            throw new RuntimeException('There is no template type called "' . $dk . '".');
        }
        // THE DEFAULT IS NOT DELETABLE. It is what a mission gets when it names
        // no version, so removing it is removing the config, not a version of
        // it - empty the lists instead if that is what you want.
        if ($dv === '') {
            throw new RuntimeException('The default is not a version - it is what a mission '
                . 'gets when it names none. Empty its lists instead.');
        }
        // A platoon's, a squad's or a role's own arsenal is deleted from that
        // platoon, squad or role, where the name comes from. Here it would be
        // deleting a thing whose owner still expects it.
        foreach (['plt_' => 'platoon', 'sqd_' => 'squad', 'role_' => 'role'] as $pfx => $whose) {
            if (str_starts_with($dv, $pfx)) {
                throw new RuntimeException($dv . ' belongs to a ' . $whose . ' and is deleted on '
                    . 'that ' . $whose . ', not here.');
            }
        }
        $docId = ghostd_template_doc_id($dk, $dv);
        ghostd_doc_delete($docId);
        $msg = $docId . ' deleted. A copy went to the backup collection first.';
        $tab = $dk;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// ---- the System tab's saves ---------------------------------------------
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
                ghostd_system_delete('opord');
                $msg = 'The order is back to the shape it ships with.';
                break;

            case 'resetkinds':
                ghostd_system_delete('ticketKinds');
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

<form method="post" id="verdel">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="delversion">
  <input type="hidden" name="key" value="<?= h($tab) ?>">
</form>

<?php foreach (GHOSTD_TEMPLATES as $key => $t): ?>
  <?php // Only the messaging nets live elsewhere - on the ORBAT, beside the
        // radio. The arsenals were skipped here too, left over from when they
        // had a page of their own: the tab existed and drew nothing
        // (2026-09-09, twice - the tab bar was fixed and this was not).
        if ($tab !== $key || $key === 'nets') { continue; } ?>
  <?php
    // A plt_*, sqd_* or role_* version belongs to a platoon, a squad or a role
    // and is edited there - one document, one place to change it. This page
    // lists the common versions, and only those.
    $rows = array_merge([''], array_values(array_filter(
        ghostd_template_variants($key),
        static fn($v) => !str_starts_with($v, 'plt_') && !str_starts_with($v, 'sqd_')
                      && !str_starts_with($v, 'role_')
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
          <td class="rowacts">
            <a class="btnlink" href="?page=configedit&amp;t=<?= urlencode($key) ?><?= $v !== '' ? '&amp;v=' . urlencode($v) : '' ?>">Edit</a>
            <?php // NO DELETE ON THE DEFAULT, and none on a version that belongs
                  // to a platoon, a squad or a role - that one is deleted where
                  // its name comes from. Every other version is a document
                  // somebody made here and can unmake here, with the same
                  // are-you-sure as every other delete on this site. ?>
            <?php if ($v !== '' && !str_starts_with($v, 'plt_') && !str_starts_with($v, 'sqd_')
                      && !str_starts_with($v, 'role_')): ?>
              <button type="submit" form="verdel" name="v" value="<?= h($v) ?>" class="hot"
                      onclick="return confirm('Delete <?= h($t['label']) ?> version <?= h($v) ?>? <?= (int) ($n ?? 0) ?> entries go with it. A copy is kept in the backup collection.');">Delete</button>
            <?php endif; ?>
          </td>
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
