<?php
/**
 * The common arsenal templates. That is all this page is.
 *
 * A platoon's arsenal is edited on the platoon, a squad's on the squad, and a
 * role's in the role - each belongs to the thing it is for, and listing them
 * here as well was three places to edit one document.
 *
 * So: the default and the named common versions. plt_* and sqd_* are skipped
 * for that reason and no other.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';
// ghostd_variant_summary lives with the ORBAT helpers.
require_once __DIR__ . '/../orbat.php';
require_once __DIR__ . '/../roles.php';

$unit = ghostd_config()['unit'];

// A ROLE'S ARSENAL IS EDITED IN THE ROLE, a platoon's on the platoon and a
// squad's on the squad. Not a guess about whether one is used - each is simply
// owned by something that has its own page for it.
$owned = [];
foreach (ghostd_role_ids() as $rid) {
    $ga = ghostd_role($rid)['groupArsenal'];
    if ($ga !== '') {
        $owned[$ga] = true;
    }
}

$versions = array_values(array_filter(
    ghostd_template_variants('arsenal'),
    static fn($v) => !str_starts_with($v, 'plt_')
        && !str_starts_with($v, 'sqd_')
        && !isset($owned[$v])
));

$holds = static function (string $v): string {
    $s = ghostd_variant_summary('arsenal', $v);
    return $s === '' ? '<span class="dim">nothing yet</span>' : h($s);
};

ghostd_head('Arsenals', 'arsenal');
?>
<p class="note">The arsenals everyone draws from. A mission runs one of them -
whichever its <code>currentArsenal</code> names, or the default.</p>
<p class="dim">A platoon's, a squad's and a role's own gear stack on top of it
and are edited on the platoon, the squad and the role - not here.</p>

<p class="note"><strong>One classname per line.</strong> Quotes, commas and
semicolons are stripped when you save, so a block pasted straight out of a
<code>.hpp</code> works as it is.</p>

<p class="note"><strong>A camo set is a whole arsenal.</strong> A named version
replaces the default rather than adding to it, so each carries the weapons,
optics and magazines too - only the uniforms, vests, headgear and backpacks
should differ between them.</p>

<table class="grid">
  <thead><tr><th>Arsenal</th><th>Holds</th><th>Mongo doc</th><th></th></tr></thead>
  <tbody>
    <tr>
      <td><strong>Default</strong>
          <span class="dim">used when a mission names none</span></td>
      <td><?= $holds('') ?></td>
      <td class="dim"><code><?= h($unit) ?>.arsenal</code></td>
      <td><a class="btnlink" href="?page=configedit&amp;t=arsenal">Edit</a></td>
    </tr>
    <?php foreach ($versions as $v): ?>
      <tr>
        <td><code><?= h($v) ?></code></td>
        <td><?= $holds($v) ?></td>
        <td class="dim"><code><?= h($unit) ?>.arsenal.<?= h($v) ?></code></td>
        <td><a class="btnlink" href="?page=configedit&amp;t=arsenal&amp;v=<?= urlencode($v) ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Add one</h2>
<form method="get" class="inline">
  <input type="hidden" name="page" value="configedit">
  <input type="hidden" name="t" value="arsenal">
  <label for="v">Name</label>
  <input type="text" id="v" name="v" required pattern="[A-Za-z][A-Za-z0-9_]*"
         placeholder="Ghost_Winter">
  <button type="submit">Open it</button>
  <span class="dim">Letters, digits and underscore. Opening it is how it is made.</span>
</form>
<?php
ghostd_foot();
