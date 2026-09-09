<?php
declare(strict_types=1);

$cfg = ghostd_config();
[$up, $why] = ghostd_ping();

ghostd_head('Dashboard', 'dashboard');

if (!$up) {
    ghostd_flash('bad', 'Database not answering: ' . $why);
    echo '<p class="dim">Check the Atlas Network Access allowlist includes this '
       . 'server\'s public IP, and that the connection string is right.</p>';
    ghostd_foot();
    return;
}

$sections = ghostd_sections();
$store    = $sections['store'] !== null ? ghostd_get($sections['store']) : null;

// The store document uses plain field names - players, sessions, windows,
// opords, log - not the gfa_pac_* keys the server's profileNamespace uses.
$arr = static fn($v) => is_array($v) ? $v : [];
$players  = $arr($store['players']  ?? null);
$sessions = $arr($store['sessions'] ?? null);
$opords   = $arr($store['opords']   ?? null);
?>
<div class="tiles">
  <div class="tile"><span class="n"><?= count($players) ?></span>on the roster</div>
  <div class="tile"><span class="n"><?= count($sessions) ?></span>sessions</div>
  <div class="tile"><span class="n"><?= count($sections['roles']) ?></span>roles</div>
  <div class="tile"><span class="n"><?= count($opords) + count($sections['opords']) ?></span>orders</div>
</div>

<h2>This unit</h2>
<table class="kv">
  <tr><th>Unit id</th><td><code><?= h($cfg['unit']) ?></code></td></tr>
  <tr><th>Database</th><td><code><?= h($cfg['database']) ?>.<?= h($cfg['collection']) ?></code></td></tr>
  <tr><th>Server id</th><td><?= cell($store['serverId'] ?? null) ?></td></tr>
  <tr><th>Schema</th><td><?= cell($store['schemaVersion'] ?? null) ?></td></tr>
  <tr><th>Store last written</th><td><?= h(when($store['exportedAt'] ?? null)) ?></td></tr>
  <tr><th>Store document</th><td>
    <?php if ($sections['store'] !== null): ?>
      <a href="?page=document&amp;id=<?= urlencode($sections['store']) ?>"><code><?= h($sections['store']) ?></code></a>
    <?php else: ?>
      <span class="dim">none yet - the game writes it on SAVE, or at mission end</span>
    <?php endif; ?>
  </td></tr>
</table>

<p class="note">The game server writes the store only when an admin presses SAVE
and at mission end. Editing it here while a mission is running will be
overwritten by that save - change the roster in game, or between sessions.</p>

<h2>Config documents</h2>
<p class="dim">One per config file. A document edited here is read at the next mission start.</p>
<ul class="cols">
<?php foreach ($sections['sections'] as $k): ?>
  <li><a href="?page=document&amp;id=<?= urlencode($k) ?>"><?= h($k) ?></a></li>
<?php endforeach; ?>
</ul>

<h2>Roles <span class="dim">(<?= count($sections['roles']) ?>)</span></h2>
<ul class="cols">
<?php foreach ($sections['roles'] as $k): ?>
  <li><a href="?page=document&amp;id=<?= urlencode($k) ?>"><?= h(substr($k, strlen($cfg['unit'] . '.role.'))) ?></a></li>
<?php endforeach; ?>
</ul>

<?php if ($sections['opords'] !== []): ?>
<h2>Orders</h2>
<ul class="cols">
<?php foreach ($sections['opords'] as $k): ?>
  <li><a href="?page=document&amp;id=<?= urlencode($k) ?>"><?= h(substr($k, strlen($cfg['unit'] . '.opord.'))) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if ($sections['other'] !== []): ?>
<h2>Other units in this database</h2>
<ul class="cols">
<?php foreach ($sections['other'] as $k): ?>
  <li><a href="?page=document&amp;id=<?= urlencode($k) ?>"><?= h($k) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php
ghostd_foot();
