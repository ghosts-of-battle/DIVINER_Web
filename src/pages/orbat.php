<?php
/**
 * The ORBAT maker, in four sub-menus: Radio, Roles, Squads, Platoons.
 *
 * THAT ORDER IS THE ORDER YOU FILL THEM IN. Nets exist before a squad can be
 * put on one; roles exist before a squad can hold them; squads exist before a
 * platoon can list them. Working the other way round means typing names that
 * do not resolve yet.
 *
 * TWO DOCUMENTS, ONE PAGE. Platoons, squads and their roles live in
 * <unit>.orbat; the channel a squad sits on lives in <unit>.radio, because
 * that is where the mod reads it (srSquadChannel for ACRE, tfarNets for TFAR).
 * They are edited together because nobody thinks of them separately - a squad
 * without a channel is half a squad.
 *
 * EACH BLOCK SAVES ON ITS OWN, so a mistake costs one block and half-typed
 * work elsewhere is never discarded.
 */

declare(strict_types=1);

require_once __DIR__ . '/../templates.php';

$cfg  = ghostd_config();
$unit = $cfg['unit'];

const GHOSTD_ORBAT_TABS = [
    'radio'    => 'Radio',
    'roles'    => 'Roles',
    'squads'   => 'Squads',
    'platoons' => 'Platoons',
];

$tab = (string) ($_GET['s'] ?? ($_POST['s'] ?? 'radio'));
if (!isset(GHOSTD_ORBAT_TABS[$tab])) {
    $tab = 'radio';
}

// Versions, as every template has.
$variant = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
if ($variant !== '' && !ghostd_variant_ok($variant)) {
    $variant = '';
}
$docId   = $unit . '.orbat' . ($variant !== '' ? '.' . $variant : '');
$radioId = $unit . '.radio';

$variants = [];
try {
    $prefix = $unit . '.orbat.';
    foreach (ghostd_keys() as $k) {
        if (str_starts_with($k, $prefix)) { $variants[] = substr($k, strlen($prefix)); }
    }
    sort($variants);
} catch (Throwable $e) {
    $variants = [];
}

$msg = null;
$err = null;

/** A textarea into trimmed, non-empty lines. */
$lines = static function (string $s): array {
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $s) ?: []),
        static fn($x) => $x !== ''));
};

/** Read the orbat document, hand it to $fn to change, write it back. */
$editOrbat = static function (callable $fn) use ($docId, $variant) {
    $doc = ghostd_get($docId);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $fn($doc);
    $doc['section']   = 'orbat';
    $doc['id']        = $variant;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put($docId, $doc);
};

/** The same for the radio plan, which keeps {section, items}. */
$editRadio = static function (callable $fn) use ($radioId) {
    $doc = ghostd_get($radioId);
    $doc = is_array($doc) ? $doc : [];
    unset($doc['_id']);
    $items = is_array($doc['items'] ?? null) ? $doc['items'] : [];
    $fn($items);
    $doc['section']   = 'radio';
    $doc['items']     = $items;
    $doc['from']      = 'DIVINER_Web';
    $doc['updatedAt'] = gmdate('Y-m-d H:i:s');
    ghostd_put($radioId, $doc);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        require __DIR__ . '/orbat_save.php';
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

// ---- what everything below reads ------------------------------------------
$o = [];
$radio = [];
try {
    $o = ghostd_get($docId) ?? [];
    $rdoc = ghostd_get($radioId);
    $radio = is_array($rdoc['items'] ?? null) ? $rdoc['items'] : [];
} catch (Throwable $e) {
    $err = $err ?? $e->getMessage();
}

$platoons  = is_array($o['platoons'] ?? null) ? $o['platoons'] : [];
$groups    = is_array($o['groups'] ?? null) ? $o['groups'] : [];
$radioNets = is_array($o['radioNets'] ?? null) ? $o['radioNets'] : [];
$faction   = (string) ($o['faction'] ?? '');

$csrf = ghostd_csrf_token();

ghostd_head('ORBAT', 'orbat');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim"><code><?= h($docId) ?></code> &middot; squad channels in
<code><?= h($radioId) ?></code> &middot; replaces <code>config_groups.hpp</code></p>

<nav class="sections">
  <?php foreach (GHOSTD_ORBAT_TABS as $k => $label): ?>
    <a href="?page=orbat&amp;s=<?= h($k) ?><?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>"
       class="<?= $tab === $k ? 'on' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
  <span class="tabsep">version</span>
  <a href="?page=orbat&amp;s=<?= h($tab) ?>" class="<?= $variant === '' ? 'on' : '' ?>">Common</a>
  <?php foreach ($variants as $v): ?>
    <a href="?page=orbat&amp;s=<?= h($tab) ?>&amp;v=<?= urlencode($v) ?>"
       class="<?= $variant === $v ? 'on' : '' ?>"><?= h($v) ?></a>
  <?php endforeach; ?>
</nav>
<p class="dim">Fill them in that order: nets before squads can sit on one,
roles before a squad can hold them, squads before a platoon can list them.
Editing <strong><?= $variant === '' ? 'the common ORBAT' : h($variant) ?></strong>.</p>

<?php require __DIR__ . '/orbat_' . $tab . '.php'; ?>
<?php
ghostd_foot();
