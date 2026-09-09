<?php
/**
 * The unit's records: a card per set, opened on its own page.
 *
 * These are the seven the PAC could edit and the site could not.
 */

declare(strict_types=1);

require_once __DIR__ . '/../records.php';

ghostd_head('Configs', 'records');
?>
<p class="dim">One set per unit - no versions, no mission picks between them.
The PAC's admin pages edit the same documents.</p>

<?php foreach (GHOSTD_RECORDS as $sec => $meta): ?>
<?php $items = ghostd_record_items((string) $sec); ?>
<details class="card">
  <summary>
    <strong><?= h($meta['label']) ?></strong>
    <code><?= h(ghostd_record_doc_id((string) $sec)) ?></code>
    <span class="pill dimpill"><?= count($items) ?></span>
    <a class="edit" href="?page=record&amp;s=<?= urlencode((string) $sec) ?>">edit</a>
  </summary>

  <p class="dim"><?= $meta['blurb'] ?></p>

  <table class="grid">
    <thead><tr><th style="width:26%">Id</th><th style="width:74%">Name</th></tr></thead>
    <tbody>
    <?php foreach ($items as $id => $it): ?>
      <tr>
        <td><code><?= h((string) $id) ?></code></td>
        <td><?= h((string) ($it['name'] ?? '')) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
      <tr><td colspan="2" class="dim">nothing yet</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</details>
<?php endforeach; ?>
<?php
ghostd_foot();
