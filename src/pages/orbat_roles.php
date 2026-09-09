<?php
/**
 * Roles: what a squad's slots are made of.
 *
 * ONE DOCUMENT PER ROLE - <unit>.role.<id> - which is the mod's arrangement,
 * not a choice here: a new role is a new document, so adding one never
 * rewrites the others. This page lists them and says which squads use each,
 * because the useful question is "is this role used anywhere" and no other
 * page can answer it.
 *
 * The role's own fields are edited on the document page; duplicating that
 * editor here would be a second place for the same thing to drift.
 */

declare(strict_types=1);

$prefix = $unit . '.role.';
$roles = [];
try {
    foreach (ghostd_keys() as $k) {
        if (str_starts_with($k, $prefix)) { $roles[] = substr($k, strlen($prefix)); }
    }
    sort($roles);
} catch (Throwable $e) {
    $roles = [];
}

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
<p class="dim">One document each. A squad's slot list on the Squads tab names
these by id.</p>

<?php if ($missing !== []): ?>
  <p class="flash bad">Squads ask for roles that have no document:
  <?= h(implode(', ', $missing)) ?>. Those slots will not fill.</p>
<?php endif; ?>

<?php if ($roles === []): ?>
  <p class="dim">No role documents yet.</p>
<?php else: ?>
<table class="grid">
  <thead><tr><th>Role id</th><th>Used by</th><th>Mongo doc</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($roles as $r): ?>
    <tr>
      <td><code><?= h($r) ?></code></td>
      <td><?= isset($usedBy[$r]) ? h(implode(', ', $usedBy[$r])) : '<span class="dim">nothing - unused</span>' ?></td>
      <td class="dim"><code><?= h($prefix . $r) ?></code></td>
      <td><a href="?page=document&amp;id=<?= urlencode($prefix . $r) ?>">edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<p class="note">A role is created in game - the admin console's STRUCTURE
editor, ROLES - or by adding a <code><?= h($prefix) ?>&lt;id&gt;</code>
document. This page is for seeing what exists and what uses it.</p>
