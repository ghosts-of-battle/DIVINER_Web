<?php
/**
 * Config templates: a section per type, its versions listed under it.
 *
 * ONE SECTION PER TYPE because that is how somebody looks for one - "the
 * arsenal, the Banshee one" - not by hunting through a flat list of documents.
 * The common version is listed first and always offered, even before anything
 * has been saved to it: opening it is how it gets created.
 *
 * The page is also the honest inventory - what has a template, what is edited
 * elsewhere, and what is deliberately not a template at all.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';

/** Configs that are not templates, and why. */
const GHOSTD_TEMPLATES_TODO = [
    'sounds' => [
        'label' => 'Sounds',
        'file'  => 'description.ext',
        'note'  => 'CfgSounds names audio packed inside the mission PBO, so the path only resolves from there. Inline it in description.ext.',
    ],
];

/** How much is in one version of one template, or null if it cannot be read. */
function ghostd_count_one(string $key, array $t, string $variant): ?int
{
    try {
        switch ($t['shape']) {
            case 'welcome':
                return count(ghostd_welcome($variant)['lines']);
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

// The ORBAT is a template type too - it just has its own page rather than the
// shared editor, so it is listed the same way and linked elsewhere.
$orbatCounts = [];
try {
    $prefix = $unit . '.orbat.';
    $ov = [];
    foreach (ghostd_keys() as $k) {
        if (str_starts_with($k, $prefix)) {
            $ov[] = substr($k, strlen($prefix));
        }
    }
    sort($ov);
    foreach (array_merge([''], $ov) as $v) {
        $d = ghostd_get($unit . '.orbat' . ($v !== '' ? '.' . $v : ''));
        $orbatCounts[$v] = is_array($d)
            ? count($d['platoons'] ?? []) . ' platoons, ' . count($d['groups'] ?? []) . ' squads'
            : 'nothing saved yet';
    }
} catch (Throwable $e) {
    $orbatCounts = ['' => 'unreadable'];
}

ghostd_head('Config templates', 'config');
?>
<p class="note">What a mission used to ship in its <code>config\</code> folder,
kept in the database instead. <strong>One set per unit</strong> - every mission
naming <code><?= h($unit) ?></code> reads these, so the framework missions and
Roomba share them. Each type holds as many versions as you like; a mission uses
the common one unless something names another.</p>

<?php foreach (GHOSTD_TEMPLATES as $key => $t): ?>
  <?php $rows = array_merge([''], ghostd_template_variants($key)); ?>
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
          <td><?= $v === '' ? '<strong>Common</strong>' : '<code>' . h($v) . '</code>' ?></td>
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

<section class="tsection">
  <h2>ORBAT</h2>
  <p class="dim">replaces <code>config_groups.hpp</code> (Dynamic_Groups)
  &middot; <?= count($orbatCounts) ?> version<?= count($orbatCounts) === 1 ? '' : 's' ?></p>
  <table class="grid">
    <thead><tr><th>Version</th><th>Mongo doc</th><th>Holds</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($orbatCounts as $v => $desc): ?>
      <tr>
        <td><?= $v === '' ? '<strong>Common</strong>' : '<code>' . h((string) $v) . '</code>' ?></td>
        <td class="dim"><code><?= h($unit . '.orbat' . ($v !== '' ? '.' . (string) $v : '')) ?></code></td>
        <td><?= h((string) $desc) ?></td>
        <td><a href="?page=orbat<?= $v !== '' ? '&amp;v=' . urlencode((string) $v) : '' ?>">edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="dim">A mission uses the version its <code>currentOrbat</code>
  setting names, or the common one.</p>
</section>

<section class="tsection">
  <h2>Edited elsewhere</h2>
  <p class="dim">Already in the database before this system existed, each with
  its own page: ranks, skills, awards, statuses, promotion and training (the
  game's STRUCTURE editor), roles, the report deck
  (<a href="?page=templates">Report deck</a>), TAC//PAD colour schemes, the
  radio plan, and operation orders (<a href="?page=opords">Orders</a>).</p>
</section>

<section class="tsection">
  <h2>Not a template</h2>
  <table class="grid">
    <thead><tr><th>Config</th><th>Where it lives</th><th>Why</th></tr></thead>
    <tbody>
    <?php foreach (GHOSTD_TEMPLATES_TODO as $x): ?>
      <tr>
        <td><?= h($x['label']) ?></td>
        <td><code><?= h($x['file']) ?></code></td>
        <td class="dim"><?= h($x['note']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php
ghostd_foot();
