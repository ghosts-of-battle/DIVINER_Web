<?php
declare(strict_types=1);

// One query for the whole listing - see ghostd_index().
$index = ghostd_index();

$q = trim((string) ($_GET['q'] ?? ''));
if ($q !== '') {
    $index = array_filter(
        $index,
        static fn($k) => stripos($k, $q) !== false,
        ARRAY_FILTER_USE_KEY
    );
}

ghostd_head('Documents', 'documents');
?>
<form method="get" class="inline">
  <input type="hidden" name="page" value="documents">
  <input type="text" name="q" value="<?= h($q) ?>" placeholder="filter by key">
  <button type="submit">Filter</button>
  <?php if ($q !== ''): ?><a href="?page=documents">clear</a><?php endif; ?>
</form>

<p class="dim"><?= count($index) ?> document<?= count($index) === 1 ? '' : 's' ?>.</p>

<table class="grid">
  <thead><tr><th>Key</th><th>Section</th><th>Updated</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($index as $k => $row): ?>
    <tr>
      <td><code><?= h($k) ?></code></td>
      <td><?= cell($row['section']) ?></td>
      <td class="dim"><?= h(when($row['updatedAt'] ?? $row['exportedAt'] ?? null)) ?></td>
      <td><a href="?page=document&amp;id=<?= urlencode($k) ?>">open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
ghostd_foot();
