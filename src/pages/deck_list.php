<?php
/**
 * The report deck, a tab of the Templates page.
 *
 * Included by config.php; the page's head, tab bar and foot are its.
 *
 * On a mission whose config lives in the database this document IS the deck:
 * there is no config_messaging.hpp, and ghostD_pac_fnc_templatesApply registers
 * what is here. On a mission that still ships GHOSTFR_Templates, the file wins
 * and this is ignored.
 */

declare(strict_types=1);

$cfg = ghostd_config();
$id  = $cfg['unit'] . '.templates';
try {
    $doc = ghostd_get($id);
} catch (Throwable $e) {
    ghostd_flash('bad', $e->getMessage());
    return;
}

ghostd_head('Report deck', 'templates');

if ($doc === null) {
    ghostd_flash('bad', 'No ' . $id . ' document. This unit\'s deck lives in the mission config instead.');
    ghostd_foot();
    return;
}

$items = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];
ksort($items);
?>
<p class="dim">
  <?= count($items) ?> templates in <code><?= h($id) ?></code>.
  <a href="?page=document&amp;id=<?= urlencode($id) ?>">Edit the raw document</a>.
</p>

<p class="actions"><a href="?page=template_edit" class="btnlink">+ New template</a></p>

<?php foreach ($items as $tid => $t): ?>
<?php
    $opts = [];
    foreach ((array) ($t['options'] ?? []) as $o) {
        if (is_array($o) && count($o) >= 2) {
            $opts[(string) $o[0]] = $o[1];
        }
    }
    $isTask = ($opts['transitionsTo'] ?? '') !== '';
?>
<details class="card">
  <summary>
    <strong><?= h((string) ($t['title'] ?? $tid)) ?></strong>
    <code><?= h((string) $tid) ?></code>
    <?php if ($isTask): ?><span class="pill">task</span><?php else: ?><span class="pill dimpill">message</span><?php endif; ?>
    <?php if (($opts['priority'] ?? '') === 'high'): ?><span class="pill hot">flash</span><?php endif; ?>
    <a class="edit" href="?page=template_edit&amp;id=<?= urlencode((string) $tid) ?>">edit</a>
  </summary>

  <table class="kv">
    <tr><th>Short</th><td><?= cell($t['short'] ?? null) ?></td></tr>
    <?php foreach ($opts as $k => $v): ?>
      <tr><th><?= h((string) $k) ?></th><td><?= cell($v) ?></td></tr>
    <?php endforeach; ?>
  </table>

  <h3>Lines</h3>
  <table class="grid">
    <thead><tr><th>Key</th><th>Label</th><th>Fields</th></tr></thead>
    <tbody>
    <?php foreach ((array) ($t['lines'] ?? []) as $i => $line): ?>
      <?php
        $lineKey   = (string) ($line[0] ?? ('L' . $i));
        $lineLabel = (string) ($line[1] ?? '');
        $fields    = (array) ($line[2] ?? []);
      ?>
      <tr>
        <td><code><?= h($lineKey !== '' ? $lineKey : 'L' . $i) ?></code></td>
        <td><?= h($lineLabel) ?></td>
        <td>
          <?php foreach ($fields as $fi => $f): ?>
            <?php
              $letter = chr(65 + (int) $fi);
              $prefix = (string) ($f[0] ?? '');
              $hint   = (string) ($f[1] ?? '');
              $type   = (string) ($f[2] ?? 'text');
            ?>
            <div class="fieldrow">
              <code><?= h(($lineKey !== '' ? $lineKey : 'L' . $i) . '.' . $letter) ?></code>
              <span class="pill dimpill"><?= h($type) ?></span>
              <?php if ($prefix !== ''): ?><strong><?= h($prefix) ?></strong><?php endif; ?>
              <span class="dim"><?= h($hint) ?></span>
            </div>
          <?php endforeach; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php endforeach; ?>
