<?php
/**
 * Roles: the list. One card each, opened on its own page.
 *
 * THE SAME ARRANGEMENT AS THE REPORT DECK, because it is the same problem: two
 * dozen things with eight parts apiece. A card says what the role is and what
 * uses it - which is the question you open this page with - and Edit opens the
 * one you want with room for its sections.
 *
 * ONE DOCUMENT PER ROLE, <unit>.role.<id>, which is the mod's arrangement: a
 * new role is a new document, so adding one never rewrites the others.
 */

declare(strict_types=1);

require_once __DIR__ . '/../roles.php';

$roles = ghostd_role_ids();

// Which squads ask for each role, and which roles nothing asks for.
$usedBy = [];
foreach ($groups as $g) {
    foreach ((array) ($g[1] ?? []) as $r) {
        $usedBy[(string) $r][] = (string) ($g[0] ?? '');
    }
}
$missing = array_diff(array_keys($usedBy), $roles);
?>
<h2>Roles <span class="dim"><?= count($roles) ?></span></h2>
<p class="dim">A squad's slot list on the Squads tab names these by id.</p>

<?php if ($missing !== []): ?>
  <p class="flash bad">Squads ask for roles that have no document:
  <?= h(implode(', ', $missing)) ?>. Those slots will not fill.
  <?php foreach ($missing as $m): ?>
    <a class="btnlink" href="?page=role&amp;id=<?= urlencode($m) ?>">Create <?= h($m) ?></a>
  <?php endforeach; ?>
  </p>
<?php endif; ?>

<p class="actions"><a class="btnlink" href="?page=role&amp;id=%2B">+ New role</a></p>

<?php if ($roles === []): ?>
  <p class="dim">No role documents yet.</p>
<?php endif; ?>

<?php foreach ($roles as $rid): ?>
  <?php
    $r = ghostd_role($rid);
    $tiles = [];
    foreach ($r['tiles'] as $row) {
        if (!in_array(strtolower((string) $row[1]), ['false', '0', 'no'], true)) {
            $tiles[] = strtoupper((string) $row[0]);
        }
    }
    $nets = array_map(static fn($row) => (string) $row[0], $r['nets']);
    $used = $usedBy[$rid] ?? [];
    $loadout = ghostd_loadout_summary($r['defaultLoadout']);
  ?>
  <details class="card">
    <summary>
      <strong><?= h($r['name'] !== '' ? $r['name'] : $rid) ?></strong>
      <code><?= h($rid) ?></code>
      <?php if ($used === []): ?>
        <span class="pill dimpill">unused</span>
      <?php else: ?>
        <span class="pill"><?= h(implode(', ', $used)) ?></span>
      <?php endif; ?>
      <?php if ($r['minRank'] !== ''): ?><span class="pill dimpill"><?= h($r['minRank']) ?>+</span><?php endif; ?>
      <?php if ($r['uids'] !== []): ?><span class="pill hot">locked</span><?php endif; ?>
      <a class="edit" href="?page=role&amp;id=<?= urlencode($rid) ?>">edit</a>
    </summary>

    <table class="kv">
      <tr><th>What it does</th><td><?= $r['description'] !== '' ? h($r['description']) : '<span class="dim">not said</span>' ?></td></tr>
      <tr><th>Nets</th><td><?= $nets === [] ? '<span class="dim">none - reads nothing</span>' : h(implode(', ', $nets)) ?></td></tr>
      <tr><th>Tiles</th><td><?= $tiles === [] ? '<span class="dim">none</span>' : h(implode(', ', $tiles)) ?></td></tr>
      <tr><th>Traits</th><td><?= $r['traits'] === [] ? '<span class="dim">none</span>' : (int) count($r['traits']) . ' set' ?></td></tr>
      <tr><th>Custom variables</th><td><?= $r['customVariables'] === [] ? '<span class="dim">none</span>' : (int) count($r['customVariables']) . ' set' ?></td></tr>
      <tr><th>Loadout</th><td><?= $loadout === []
            ? '<span class="dim">none - spawns in whatever the mission gives him</span>'
            : h(implode(' / ', array_map(static fn($s) => $s[1], array_slice($loadout, 0, 3)))) ?></td></tr>
      <tr><th>Group arsenal</th><td><?= $r['groupArsenal'] !== '' ? '<code>' . h($r['groupArsenal']) . '</code>' : '<span class="dim">none</span>' ?></td></tr>
      <tr><th>Mongo doc</th><td class="dim"><code><?= h(ghostd_role_doc_id($rid)) ?></code></td></tr>
    </table>
  </details>
<?php endforeach; ?>

<p class="note">A role can also be created in game - the admin console's
STRUCTURE editor, ROLES - and the two write the same document.</p>
