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
require_once __DIR__ . '/../orbat.php';
// ghostd_doc_delete lives with the roles - it is the one document delete there is.
require_once __DIR__ . '/../roles.php';

$cfg  = ghostd_config();
$unit = $cfg['unit'];

const GHOSTD_ORBAT_TABS = [
    'versions' => 'Orders of battle',
    // COMMUNICATIONS, not "Radio": it holds the messaging nets as well, and a
    // net is what a man reads while a channel is what he keys up on.
    'radio'    => 'Communications',
    'roles'    => 'Roles',
    'squads'   => 'Squads',
    'platoons' => 'Platoons',
];

// COMMON FIRST, because it is what the order of battle IS - who this unit is
// and which side it fights on - before any of its parts. The four after it are
// in the order they have to be filled in.
$tab = (string) ($_GET['s'] ?? ($_POST['s'] ?? 'versions'));
// 'common' was a tab called "Details" - the faction and the side are on the
// order of battle they belong to now, and the custom traits are with the roles
// that assign them. An old link lands on the orders of battle.
// "one" is the editor for a SINGLE order of battle, opened from the list. It
// is not a tab of its own - the bar keeps Orders of battle lit while it is up.
if ($tab !== 'one' && !isset(GHOSTD_ORBAT_TABS[$tab])) {
    $tab = 'versions';
}

// A tab's own sub-tabs, drawn in the SAME bar as the tabs themselves. Two
// stacked bars read as two menus and invite the question which one you are in;
// there is only one menu here, so there is one bar.
const GHOSTD_ORBAT_SUBTABS = [
    // The messaging nets sit with the radio: a net is what a man READS and a
    // channel is what he TALKS on, and the two are set up together.
    'radio' => ['nets' => 'Messaging', 'acre' => 'ACRE', 'tfar' => 'TFAR'],
];
$subTabs = GHOSTD_ORBAT_SUBTABS[$tab] ?? [];
$sub = (string) ($_GET['r'] ?? ($_POST['r'] ?? ''));
if (!isset($subTabs[$sub])) {
    $sub = $subTabs === [] ? '' : (string) array_key_first($subTabs);
}

// Versions, as every template has.
$variant = trim((string) ($_GET['v'] ?? ($_POST['v'] ?? '')));
if ($variant !== '' && !ghostd_variant_ok($variant)) {
    $variant = '';
}
$docId   = $unit . '.orbat' . ($variant !== '' ? '.' . $variant : '');
$radioId = $unit . '.radio';

$variants = ghostd_orbat_variants();

$msg = null;
$err = null;

/** A textarea into trimmed, non-empty lines. */
$lines = static function (string $s): array {
    return array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $s) ?: []),
        static fn($x) => $x !== ''));
};

// One implementation, in src/orbat.php, shared with the squad and platoon
// pages - two editors that disagree about what a squad is would be two
// different squads.
$editOrbat = static fn(callable $fn) => ghostd_orbat_edit($variant, $fn);
$editRadio = static fn(callable $fn) => ghostd_radio_edit($fn);

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

<nav class="sections onebar">
  <?php foreach (GHOSTD_ORBAT_TABS as $k => $label): ?>
    <a href="?page=orbat&amp;s=<?= h($k) ?><?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>"
       class="<?= ($tab === $k || ($tab === 'one' && $k === 'versions')) ? 'on' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>


  <?php // The orders of battle are chosen on their own tab; this strip says
        // which one the tab in front of you is editing.
        if ($variants !== [] && !in_array($tab, ['versions', 'one'], true)): ?>
    <span class="tabsep">order of battle</span>
    <a href="?page=orbat&amp;s=<?= h($tab) ?>" class="<?= $variant === '' ? 'on' : '' ?>"><?= h($unit) ?>.orbat</a>
    <?php foreach ($variants as $v): ?>
      <a href="?page=orbat&amp;s=<?= h($tab) ?>&amp;v=<?= urlencode($v) ?>"
         class="<?= $variant === $v ? 'on' : '' ?>"><?= h($v) ?></a>
    <?php endforeach; ?>
  <?php endif; ?>
</nav>
<?php if ($subTabs !== []): ?>
  <nav class="subrail">
    <?php foreach ($subTabs as $k => $label): ?>
      <a class="<?= $sub === $k ? 'on' : '' ?>"
         href="?page=orbat&amp;s=<?= h($tab) ?>&amp;r=<?= h($k) ?><?= $variant !== '' ? '&amp;v=' . urlencode($variant) : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
<?php require __DIR__ . '/orbat_' . $tab . '.php'; ?>
<?php
ghostd_foot();
