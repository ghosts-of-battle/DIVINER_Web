<?php
/**
 * Layout and the small helpers every page uses.
 */

declare(strict_types=1);

require_once __DIR__ . '/wiki.php';
require_once __DIR__ . '/branding.php';

// The colour schemes are the unit's own - <unit>.schemes, the same document the
// TAC//PAD is painted from in game. See src/schemes.php.
require_once __DIR__ . '/schemes.php';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A value from a Mongo document, printed for a table cell. */
/**
 * A Steam id as a link to that account's Steam profile.
 *
 * WHY IT IS WORTH LINKING. The id is the key to everything here - the roster,
 * the admin list, a role's whitelist - and it is a seventeen digit number
 * nobody can recognise. One click to see whose it is turns it from an opaque
 * key into a person.
 *
 * Opens in a new tab and carries rel="noopener noreferrer": these pages are
 * behind a login and must not hand a third-party page a handle on them.
 *
 * A value that is not a Steam id is returned as plain text, not a broken link.
 */
function steamlink(?string $uid, ?string $label = null): string
{
    $uid = trim((string) $uid);
    if ($uid === '') {
        return '<span class="dim">-</span>';
    }
    if (!preg_match('/^\d{5,20}$/', $uid)) {
        return h($uid);
    }
    return '<a class="steamid" href="https://steamcommunity.com/profiles/' . h($uid) . '"'
         . ' target="_blank" rel="noopener noreferrer"'
         . ' title="Open this Steam profile in a new tab">' . h($label ?? $uid) . '</a>';
}

function cell($v): string
{
    if ($v === null) {
        return '<span class="dim">-</span>';
    }
    if (is_bool($v)) {
        return $v ? 'yes' : 'no';
    }
    if (is_array($v)) {
        if ($v === []) {
            return '<span class="dim">-</span>';
        }
        $flat = array_filter($v, static fn($x) => is_scalar($x));
        if (count($flat) === count($v)) {
            return h(implode(', ', array_map('strval', $v)));
        }
        return '<span class="dim">' . count($v) . ' items</span>';
    }
    return h((string) $v);
}

/**
 * Which screen is being drawn - set by ghostd_head, read by ghostd_foot so the
 * wiki panel knows which page to pull without every caller passing it twice.
 */
function ghostd_active(?string $set = null): string
{
    static $active = '';
    if ($set !== null) {
        $active = $set;
    }
    return $active;
}

/**
 * Whether the page has already begun. An error thrown mid-render must not
 * start a second document inside the first - which is what a nested <html>
 * in the middle of a form looks like on screen.
 */
function ghostd_head_sent(?bool $set = null): bool
{
    static $sent = false;
    if ($set !== null) {
        $sent = $set;
    }
    return $sent;
}

function ghostd_head(string $title, string $active = ''): void
{
    ghostd_active($active);
    ghostd_head_sent(true);

    $nav = ghostd_is_admin() || !ghostd_is_member()
        ? [
            'dashboard'    => 'Dashboard',
            'roster'       => 'Roster',
            'applications' => 'Applications',
            'tickets'      => 'PAC actions',
            'opords'       => 'Orders',
            'records'      => 'Configs',
            'config'       => 'Templates',
            'orbat'        => 'ORBAT',
            'media'        => 'Media',
            'documents'    => 'Mongo docs',
            'backup'       => 'Backup',
            'branding'     => 'Branding',
            // An admin is a member too - they have a record like anybody else,
            // and dropping this left them no way to reach their own details.
            'me'           => 'My details',
        ]
        : ['me' => 'My details', 'tickets' => 'PAC requests', 'media' => 'Media', 'apply' => 'Apply'];

    // Only ask who is signed in when a session is already running. This
    // function has begun sending HTML by the time the bar is drawn, and
    // starting a session then would try to set a cookie after the headers
    // have gone - a warning on every "Not configured" page.
    $who = session_status() === PHP_SESSION_ACTIVE ? ghostd_identity() : null;

    $default = ghostd_default_theme();
    ?><!doctype html>
<html lang="en" data-theme="<?= h($default) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> - <?= h(ghostd_unit_name()) ?></title>
<link rel="stylesheet" href="style.css">
<?= ghostd_schemes_css() ?><?= ghostd_branding_css() ?>
<script>
/* Applied before first paint, or the page flashes the default scheme first. */
(function () {
  try {
    var t = localStorage.getItem('ghostd_theme');
    if (t) { document.documentElement.setAttribute('data-theme', t); }
    if (localStorage.getItem('ghostd_wiki') === 'off') {
      document.documentElement.classList.add('nowiki');
    }
  } catch (e) {}
})();
</script>
</head>
<body class="p-<?= $active !== '' ? h($active) : 'gate' ?><?= ($active === '' && ghostd_brand_asset('background') !== null) ? ' hasbg' : '' ?>">
<?php if ($active === '' && ghostd_brand_asset('background') !== null): ?>
<style>
  body.hasbg {
    background-image: url("<?= ghostd_asset_url('background') ?>");
    background-size: <?= h((string) ghostd_branding()['loginFit']) ?>;
  }
  body.hasbg::before { opacity: <?= (float) ghostd_branding()['loginDim'] / 100 ?>; }
</style>
<?php endif; ?>
<header class="bar">
  <div class="brand"><?= ghostd_brand_html() ?></div>
  <?php if ($who !== null): ?>
  <nav>
    <?php foreach ($nav as $k => $label): ?>
      <a href="?page=<?= h($k) ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <span class="dim who" title="<?= h($who['steamid'] ?? 'password login') ?>">
      <?= h($who['name'] ?? ($who['steamid'] ?? 'signed in')) ?>
    </span>
    <a href="?page=logout" class="right">Log out</a>
  </nav>
  <?php endif; ?>
  <button type="button" id="wikishow" class="wikishow" title="Show the wiki panel">wiki</button>
  <label class="themepick" title="Color scheme">
    <span class="vh">Color scheme</span>
    <select id="themepick">
      <?php foreach (ghostd_themes() as $k => $label): ?>
        <option value="<?= h($k) ?>"><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</header>
<div class="shell">
<main>
<h1><?= h($title) ?></h1>
<?php if ($who !== null && !ghostd_can_edit()): ?>
  <p class="note readonly"><strong>Read-only.</strong> You signed in with the
  shared password, which can look but not change. Sign in through Steam - the
  unit's admin list decides who may edit.
  <a href="?page=logout">Switch</a></p>
<?php endif; ?>
<?php
}

function ghostd_foot(): void
{
    $active = ghostd_active();
    ?>
</main>
<?= ghostd_wiki_panel($active) ?>
</div>
<footer class="dim"><?= h(ghostd_unit_name()) ?></footer>
<script>
(function () {
  var html = document.documentElement;

  var pick = document.getElementById('themepick');
  if (pick) {
    try { pick.value = localStorage.getItem('ghostd_theme') || html.getAttribute('data-theme'); } catch (e) {}
    pick.addEventListener('change', function () {
      html.setAttribute('data-theme', pick.value);
      try { localStorage.setItem('ghostd_theme', pick.value); } catch (e) {}
    });
  }

  /* The wiki panel is a preference, not a page state: hiding it must survive
     the next click, so it is remembered rather than re-shown every load. */
  var t = document.getElementById('wikitoggle');
  if (t) {
    t.addEventListener('click', function () {
      var off = html.classList.toggle('nowiki');
      try { localStorage.setItem('ghostd_wiki', off ? 'off' : 'on'); } catch (e) {}
    });
  }
  var show = document.getElementById('wikishow');
  if (show) {
    show.addEventListener('click', function () {
      html.classList.remove('nowiki');
      try { localStorage.setItem('ghostd_wiki', 'on'); } catch (e) {}
    });
  }
})();
</script>
<script src="editor.js"></script>
</body>
</html><?php
}

function ghostd_flash(string $kind, string $msg): void
{
    echo '<p class="flash ' . h($kind) . '">' . h($msg) . '</p>';
}

/**
 * A timestamp for display.
 *
 * The documents carry both shapes: the mod writes plain strings
 * ("2026-09-08 04:26:21"), while anything written by a driver - this site
 * included - can land as a BSON date. Casting a UTCDateTime to string gives
 * milliseconds since the epoch, which is not a date anybody can read.
 */
function when($v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    if ($v instanceof MongoDB\BSON\UTCDateTime) {
        return $v->toDateTime()->format('Y-m-d H:i:s');
    }
    if ($v instanceof DateTimeInterface) {
        return $v->format('Y-m-d H:i:s');
    }
    return (string) $v;
}
