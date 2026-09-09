<?php
/**
 * Layout and the small helpers every page uses.
 */

declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A value from a Mongo document, printed for a table cell. */
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

function ghostd_head(string $title, string $active = ''): void
{
    $nav = [
        'dashboard' => 'Dashboard',
        'roster'    => 'Roster',
        'templates' => 'Report deck',
        'documents' => 'Documents',
    ];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> - DIVINER PAC</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="bar">
  <div class="brand">TAC//PAC <span class="dim">manager</span></div>
  <nav>
    <?php foreach ($nav as $k => $label): ?>
      <a href="?page=<?= h($k) ?>" class="<?= $active === $k ? 'on' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <a href="?page=logout" class="right">Log out</a>
  </nav>
</header>
<main>
<h1><?= h($title) ?></h1>
<?php
}

function ghostd_foot(): void
{
    ?>
</main>
<footer class="dim">
  DIVINER_Web - reads and writes the same MongoDB documents the game server
  does. Every write is copied to the backup collection first.
</footer>
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
