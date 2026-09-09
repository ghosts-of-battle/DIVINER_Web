<?php
/**
 * The config templates a unit has, and the ones still living in mission files.
 *
 * The list is honest about what is not built yet: the point of moving config
 * into the database is that a Mongo mission ships no config folder, and that is
 * only true once every one of these has a template.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';

/**
 * What a mission still ships as a file, with nothing in the database yet.
 * Kept here rather than in the registry because these have no document and no
 * editor - they are a to-do list, not a template.
 */
const GHOSTD_TEMPLATES_TODO = [
    'sounds'            => ['label' => 'Sounds', 'file' => 'description.ext', 'note' => 'NOT a template: CfgSounds names audio packed inside the mission PBO, so the path only resolves from there. Inline it in description.ext.'],
];

$counts = ghostd_template_counts();

ghostd_head('Config templates', 'config');
?>
<p class="note">What a mission used to ship in its <code>config\</code> folder,
kept in the database instead. <strong>One set per unit</strong> - every mission
naming <code><?= h(ghostd_config()['unit']) ?></code> reads these, so the
framework missions and Roomba share one list.</p>

<h2>Templates</h2>
<table class="grid">
  <thead><tr><th>Template</th><th>Replaces</th><th>Document</th><th>Items</th><th></th></tr></thead>
  <tbody>
  <?php foreach (GHOSTD_TEMPLATES as $key => $t): ?>
    <tr>
      <td><a href="?page=configedit&amp;t=<?= urlencode($key) ?>"><?= h($t['label']) ?></a></td>
      <td><code><?= h($t['replaces']) ?></code></td>
      <td><code><?= h(ghostd_config()['unit'] . '.' . $t['doc']) ?></code></td>
      <td><?= $counts[$key] === null ? '<span class="dim">unreadable</span>' : (int) $counts[$key] ?></td>
      <td><a href="?page=configedit&amp;t=<?= urlencode($key) ?>">edit</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Already in the database, edited elsewhere</h2>
<p class="dim">These moved out of mission files already and have their own
pages: ranks, skills, awards, statuses, promotion and training (the game's
STRUCTURE editor), roles, the report deck
(<a href="?page=templates">Report deck</a>), TAC//PAD colour schemes, the radio
plan, the ORBAT, and operation orders (<a href="?page=opords">Orders</a>).</p>

<h2>Handled by the ORBAT</h2>
<p class="dim"><code>config_groups.hpp</code> (Dynamic_Groups - platoons,
squads, callsigns) is read through <code>fnc_orbat</code>, which already takes
the <code>&lt;unit&gt;.orbat</code> document. It is edited on the
<a href="?page=orbat">ORBAT</a> page - platoons, squads, roles and radio nets
together.</p>

<h2>Still a file</h2>
<p class="dim">No document and no editor yet. Until each of these has one, a
Mongo mission still needs a <code>config\</code> folder.</p>
<table class="grid">
  <thead><tr><th>Config</th><th>File</th><th>What it needs</th></tr></thead>
  <tbody>
  <?php foreach (GHOSTD_TEMPLATES_TODO as $key => $t): ?>
    <tr>
      <td><?= h($t['label']) ?></td>
      <td><code><?= h($t['file']) ?></code></td>
      <td class="dim"><?= h($t['note']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
ghostd_foot();
