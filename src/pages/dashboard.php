<?php
/**
 * The dashboard: what state the unit is actually in.
 *
 * NOT A LIST OF DOCUMENTS. It used to be three columns of Mongo doc names
 * linking to a JSON editor - which told you what existed and nothing about
 * whether any of it was right. The Templates and ORBAT pages own the documents
 * now; this page answers the questions you open it for: is the database up, is
 * anything unfinished, what needs attention, and when did the game last write.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';
require_once __DIR__ . '/../tickets.php';
require_once __DIR__ . '/../applications.php';

$cfg  = ghostd_config();
$unit = $cfg['unit'];

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

$arr = static fn($v) => is_array($v) ? $v : [];
$players  = $arr($store['players']  ?? null);
$sessions = $arr($store['sessions'] ?? null);
$windows  = $arr($store['windows']  ?? null);
$log      = $arr($store['log']      ?? null);

// ---- what needs attention -------------------------------------------------
$attention = [];

// Applications waiting.
$appsWaiting = 0;
try {
    foreach (ghostd_applications() as $a) {
        if (($a['status'] ?? 'new') === 'new') { $appsWaiting++; }
    }
} catch (Throwable $e) {
    $appsWaiting = null;
}
if ($appsWaiting) {
    $attention[] = [$appsWaiting . ' application' . ($appsWaiting === 1 ? '' : 's') . ' waiting',
                    '?page=applications', 'Nobody has decided them yet.'];
}

// PAC actions still open.
$openTickets = 0;
try {
    foreach (ghostd_tickets() as $t) {
        if (($t['status'] ?? 'open') === 'open') { $openTickets++; }
    }
} catch (Throwable $e) {
    $openTickets = null;
}
if ($openTickets) {
    $attention[] = [$openTickets . ' PAC action' . ($openTickets === 1 ? '' : 's') . ' open',
                    '?page=tickets', 'Leave, awards, requests and problems nobody has answered.'];
}

// Operators the Discord bot cannot match.
$unlinked = 0;
foreach ($players as $p) {
    if (trim((string) ($p['discordId'] ?? '')) === '') { $unlinked++; }
}
if ($unlinked) {
    $attention[] = [$unlinked . ' of ' . count($players) . ' operators have no Discord id',
                    '?page=roster', 'The bot cannot match them to a record.'];
}

// The ORBAT's two halves disagreeing - a squad nobody can slot into.
$orbat = [];
try {
    $orbat = ghostd_get($unit . '.orbat') ?? [];
} catch (Throwable $e) {
    $orbat = [];
}
$claimed = [];
foreach ((array) ($orbat['platoons'] ?? []) as $p) {
    foreach ((array) ($p[4] ?? []) as $sq) { $claimed[(string) $sq] = true; }
}
$defined = [];
foreach ((array) ($orbat['groups'] ?? []) as $g) { $defined[(string) ($g[0] ?? '')] = true; }
$orphans = array_diff(array_keys($claimed), array_keys($defined));
if ($orphans !== []) {
    $attention[] = [count($orphans) . ' squad' . (count($orphans) === 1 ? '' : 's') . ' with no roles',
                    '?page=orbat&s=squads', implode(', ', $orphans) . ' - a platoon lists them but nobody can slot in.'];
}

// Roles a squad asks for that have no document.
$roleIds = array_map(static fn($k) => substr($k, strlen($unit . '.role.')), $sections['roles']);
$wanted = [];
foreach ((array) ($orbat['groups'] ?? []) as $g) {
    foreach ((array) ($g[1] ?? []) as $r) { $wanted[(string) $r] = true; }
}
$missingRoles = array_diff(array_keys($wanted), $roleIds);
if ($missingRoles !== []) {
    $attention[] = [count($missingRoles) . ' role' . (count($missingRoles) === 1 ? '' : 's') . ' with no document',
                    '?page=orbat&s=roles', implode(', ', array_slice($missingRoles, 0, 6)) . ' - those slots will not fill.'];
}

// The class export the arsenal editors want.
$classCount = count(ghostd_classnames());
if ($classCount === 0) {
    $attention[] = ['No classname export', '?page=config',
                    'The arsenal and motorpool editors are plain text boxes until the game runs EXPORT CLASSES.'];
}

// ---- the last thing the game did ------------------------------------------
$lastLog = [];
foreach (array_slice(array_reverse($log), 0, 8) as $l) {
    if (is_array($l)) { $lastLog[] = $l; }
}
?>
<div class="tiles">
  <div class="tile"><span class="n"><?= count($players) ?></span>on the roster</div>
  <div class="tile"><span class="n"><?= count($sessions) ?></span>sessions recorded</div>
  <div class="tile"><span class="n"><?= count($windows) ?></span>operation windows</div>
  <div class="tile"><span class="n"><?= count($sections['roles']) ?></span>roles</div>
  <div class="tile"><span class="n"><?= count($sections['opords']) ?></span>orders</div>
  <div class="tile"><span class="n"><?= $openTickets === null ? '?' : $openTickets ?></span>PAC actions open</div>
</div>

<?php if ($attention !== []): ?>
  <h2>Needs attention <span class="dim"><?= count($attention) ?></span></h2>
  <table class="grid">
    <tbody>
    <?php foreach ($attention as $a): ?>
      <tr>
        <td><strong><?= h($a[0]) ?></strong></td>
        <td class="dim"><?= h($a[2]) ?></td>
        <td><a class="btnlink" href="<?= h($a[1]) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php else: ?>
  <p class="flash good">Nothing waiting - no open applications, no open PAC
  actions, and the ORBAT's squads and roles all resolve.</p>
<?php endif; ?>

<?php
// ---- WHO IS DUE A PROMOTION (user, 2026-09-09) ----------------------------
// Admins only, like the rest of the personnel side. The points are the same
// sum the game does - see src/promotion.php - so this and the operator file
// cannot disagree.
if (ghostd_is_admin() && $store !== null):
    require_once __DIR__ . '/../promotion.php';
    $auto = ghostd_auto_promote();
    $due  = [];
    foreach (ghostd_promotion_table((array) $store) as $uid => $r) {
        if ($r['next'] !== '' && $r['needed'] === 0) {
            $due[$uid] = $r;
        }
    }
    uasort($due, static fn($a, $b) => $b['points'] <=> $a['points']);
    $rankNames = [];
    foreach (ghostd_record_items('ranks') as $rid => $rk) {
        $rankNames[(string) $rid] = (string) ($rk['name'] ?? $rid);
    }
?>
  <h2>Promotable <span class="dim"><?= count($due) ?></span></h2>
  <p class="dim">Automatic promotion is
    <strong><?= $auto ? 'on' : 'off' ?></strong> -
    <a href="?page=record&amp;s=promotion">change it</a>. Points required to
    hold a rank are on the <a href="?page=record&amp;s=ranks">ranks</a> page.</p>

  <?php if ($due === []): ?>
    <p class="dim">Nobody is over the line.</p>
  <?php else: ?>
    <table class="grid">
      <thead><tr><th style="width:34%">Who</th><th style="width:22%">Rank</th>
          <th style="width:14%">Points</th><th style="width:30%">Due</th></tr></thead>
      <tbody>
      <?php foreach ($due as $uid => $r): ?>
        <tr>
          <td><a href="?page=player&amp;uid=<?= urlencode((string) $uid) ?>"><?= h($r['name']) ?></a></td>
          <td class="dim"><?= h($rankNames[$r['rankId']] ?? $r['rankId']) ?></td>
          <td><?= (int) $r['points'] ?></td>
          <td><strong><?= h($rankNames[$r['next']] ?? $r['next']) ?></strong>
              <span class="dim">at <?= (int) $r['nextAt'] ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
<?php endif; ?>

<h2>The unit</h2>
<table class="kv">
  <tr><th>Unit id</th><td><code><?= h($unit) ?></code></td></tr>
  <tr><th>Database</th><td><code><?= h($cfg['database']) ?>.<?= h($cfg['collection']) ?></code>
      <span class="pill">answering</span></td></tr>
  <tr><th>Server id</th><td><?= cell($store['serverId'] ?? null) ?></td></tr>
  <tr><th>Store schema</th><td><?= cell($store['schemaVersion'] ?? null) ?></td></tr>
  <tr><th>Game last wrote the store</th>
      <td><?= $store === null
            ? '<span class="dim">never - the game writes it on SAVE, or at mission end</span>'
            : h(when($store['exportedAt'] ?? null)) ?></td></tr>
  <tr><th>Classnames exported</th>
      <td><?= $classCount === 0
            ? '<span class="dim">none - run EXPORT CLASSES in the admin console</span>'
            : (int) $classCount ?></td></tr>
  <tr><th>Mongo docs</th><td><?= count(ghostd_keys()) ?> in total</td></tr>
</table>

<?php if ($lastLog !== [] && ghostd_is_admin()): ?>
  <h2>Last admin actions</h2>
  <table class="grid">
    <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
    <tbody>
    <?php foreach ($lastLog as $l): ?>
      <?php
        // POSITIONAL, not keyed: [id, when, byUid, byName, type, targetUid,
        // target, detail] - the same row the in-game log reads. Reading it by
        // string key gave a table of empty rows.
        $when   = (string) ($l[1] ?? '');
        $who    = (string) ($l[3] ?? '');
        $type   = (string) ($l[4] ?? '');
        $target = (string) ($l[6] ?? '');
        $detail = (string) ($l[7] ?? '');
      ?>
      <tr>
        <td class="dim"><?= h($when) ?></td>
        <td><?= h($who) ?></td>
        <td><?= h(trim($type . ' ' . $target . ' ' . $detail)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php
ghostd_foot();
