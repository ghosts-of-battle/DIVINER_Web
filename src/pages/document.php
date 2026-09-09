<?php
/**
 * One document, as JSON, editable.
 *
 * WHY RAW JSON AND NOT A FORM PER SECTION. The documents are "one per config
 * file with real BSON fields" and their shapes differ - ranks are a map, orbat
 * is four lists, a role is one object. A form per shape would be nine forms
 * that go stale the moment the mod adds a section. JSON is the shape the mod
 * already agreed to, and the purpose-built pages (Roster, Report deck) cover
 * the two things people edit often.
 *
 * SAVING IS A REPLACE, and the previous version is copied into the backup
 * collection first - see ghostd_put().
 */

declare(strict_types=1);

$id = (string) ($_GET['id'] ?? '');
if ($id === '') {
    header('Location: ?page=documents');
    exit;
}

$cfg     = ghostd_config();
$isStore = ($id === $cfg['unit']);

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    $raw = (string) ($_POST['json'] ?? '');
    $new = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $err = 'Not valid JSON: ' . json_last_error_msg() . ' - nothing was written.';
    } elseif (!is_array($new)) {
        $err = 'The document must be a JSON object - nothing was written.';
    } elseif ($isStore && ($_POST['confirm'] ?? '') !== 'yes') {
        $err = 'That is the store document. Tick the confirmation box to write it.';
    } else {
        $res = ghostd_put($id, $new);
        $msg = 'Saved. Matched ' . $res['matched'] . ', modified ' . $res['modified'] . '. '
             . 'The previous version is in the backup collection.';
    }
}

$doc = ghostd_get($id);

ghostd_head('Mongo doc', 'documents');
echo '<p class="dim"><code>' . h($id) . '</code></p>';

if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }

if ($doc === null) {
    ghostd_flash('bad', 'No such document.');
    ghostd_foot();
    return;
}

/**
 * BSON values that JSON cannot carry, turned into the strings the mod itself
 * writes. A date shown as {"$date":...} is a date nobody can edit by hand.
 */
$plain = static function ($v) use (&$plain) {
    if ($v instanceof MongoDB\BSON\UTCDateTime) {
        return $v->toDateTime()->format('Y-m-d H:i:s');
    }
    if ($v instanceof MongoDB\BSON\ObjectId) {
        return (string) $v;
    }
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $x) {
            $out[$k] = $plain($x);
        }
        return $out;
    }
    return $v;
};

$view = $plain($doc);
unset($view['_id']);
$json = json_encode($view, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<?php if ($isStore): ?>
<p class="flash bad">
  This is the <strong>store</strong> - the roster, sessions and orders. The game
  server rewrites it whole when an admin presses SAVE and at mission end, so an
  edit made during a live mission will be lost. Prefer the
  <a href="?page=roster">Roster</a> page, which changes one field at a time.
</p>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <textarea name="json" spellcheck="false" rows="30" data-editor="json"><?= h($json) ?></textarea>
  <?php if ($isStore): ?>
    <label class="confirm">
      <input type="checkbox" name="confirm" value="yes">
      I understand this replaces the store document.
    </label>
  <?php endif; ?>
  <div class="actions">
    <button type="submit">Save</button>
    <a href="?page=documents">Back to documents</a>
  </div>
</form>

<p class="dim">Saving replaces the document. Keys you delete here are removed;
the previous version is copied to
<code><?= h($cfg['backup_collection'] !== '' ? $cfg['backup_collection'] : 'nowhere - backups are off') ?></code> first.
Dates are shown and saved as the plain strings the mod writes.</p>
<?php
ghostd_foot();
