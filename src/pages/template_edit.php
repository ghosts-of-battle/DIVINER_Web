<?php
/**
 * Build or edit one report template, and write it into <unit>.templates.
 *
 * WHY A FORM AND NOT THE JSON EDITOR. A template is the one document shape with
 * a contract you cannot see by looking at it: a field's key is generated from
 * its LINE's title, not its own label, so "Location" gives Location.A and
 * renaming that line silently renames the field every subject and anchor
 * points at. This form shows the generated keys as you type and refuses an
 * anchor that names a key no line produces - which is the mistake the JSON
 * editor cannot catch.
 *
 * The shape written is exactly what ghostD_messaging_fnc_registerTemplate
 * takes, because ghostD_pac_fnc_templatesApply hands it straight over:
 *   {id, title, short, lines: [[lineTitle, LABEL, [[prefix,hint,type,opts]]]],
 *    options: [[key, value]]}
 */

declare(strict_types=1);

$cfg   = ghostd_config();
$docId = $cfg['unit'] . '.templates';

$editing = trim((string) ($_GET['id'] ?? ''));
$doc     = ghostd_get($docId);
$items   = (is_array($doc['items'] ?? null)) ? $doc['items'] : [];

const FIELD_TYPES = ['text', 'textarea', 'number', 'choice', 'bool', 'grid', 'callsign'];

/** "key=value" lines -> the [[key, value]] pairs registerTemplate reads. */
function parse_opts(string $raw): array
{
    $out = [];
    foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if ($k === '') {
            continue;
        }
        if ($k === 'choices') {
            $out[] = [$k, array_values(array_filter(array_map('trim', explode(',', $v)), static fn($s) => $s !== ''))];
        } elseif (is_numeric($v)) {
            $out[] = [$k, str_contains($v, '.') ? (float) $v : (int) $v];
        } elseif ($v === 'true' || $v === 'false') {
            $out[] = [$k, $v === 'true'];
        } else {
            $out[] = [$k, $v];
        }
    }
    return $out;
}

/** The [[key, value]] pairs back into "key=value" lines for the form. */
function unparse_opts(array $pairs): string
{
    $out = [];
    foreach ($pairs as $p) {
        if (!is_array($p) || count($p) < 2) {
            continue;
        }
        $v = $p[1];
        if (is_array($v)) {
            $v = implode(', ', array_map('strval', $v));
        } elseif (is_bool($v)) {
            $v = $v ? 'true' : 'false';
        }
        $out[] = $p[0] . '=' . $v;
    }
    return implode("\n", $out);
}

/** A line title becomes its key the way registerTemplate does it. */
function line_key(string $title, int $i): string
{
    $k = preg_replace('/\s+/', '', $title);
    return ($k === '' || $k === null) ? ('L' . $i) : $k;
}

$msg = null;
$err = null;

// ---------------------------------------------------------------- delete ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    ghostd_csrf_check();
    $del = (string) ($_POST['id'] ?? '');
    if ($del !== '' && isset($items[$del])) {
        ghostd_unset_path($docId, 'items.' . $del);
        header('Location: ?page=config&t=deck');
        exit;
    }
    $err = 'No such template to delete.';
}

// ------------------------------------------------------------------ save ---
$form = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    ghostd_csrf_check();

    $id    = strtolower(trim((string) ($_POST['tid'] ?? '')));
    $orig  = (string) ($_POST['orig'] ?? '');
    $title = trim((string) ($_POST['title'] ?? ''));
    $short = trim((string) ($_POST['short'] ?? ''));

    // Rebuild the lines from whatever indices the browser sent; blanks dropped.
    $lines = [];
    foreach ((array) ($_POST['line'] ?? []) as $ln) {
        $lt = trim((string) ($ln['key'] ?? ''));
        $ll = trim((string) ($ln['label'] ?? ''));
        $fields = [];
        foreach ((array) ($ln['field'] ?? []) as $f) {
            $hint = trim((string) ($f['hint'] ?? ''));
            $type = (string) ($f['type'] ?? 'text');
            $pfx  = (string) ($f['prefix'] ?? '');
            if ($hint === '' && $pfx === '') {
                continue;                       // an untouched blank slot
            }
            if (!in_array($type, FIELD_TYPES, true)) {
                $type = 'text';
            }
            $fields[] = [$pfx, $hint, $type, parse_opts((string) ($f['opts'] ?? ''))];
        }
        if ($lt === '' && $ll === '' && $fields === []) {
            continue;
        }
        $lines[] = [$lt, $ll, $fields];
    }

    // The keys this template will actually produce.
    $keys = [];
    foreach ($lines as $i => $l) {
        $lk = line_key((string) $l[0], $i);
        foreach ($l[2] as $fi => $_) {
            $keys[] = $lk . '.' . chr(65 + $fi);
        }
    }

    $opts = [];
    $kind = (string) ($_POST['kind'] ?? 'root');
    $opts[] = ['kind', in_array($kind, ['root', 'reply', 'both'], true) ? $kind : 'root'];
    $opts[] = ['priority', ($_POST['priority'] ?? 'normal') === 'high' ? 'high' : 'normal'];
    if (trim((string) ($_POST['subject'] ?? '')) !== '') {
        $opts[] = ['subject', trim((string) $_POST['subject'])];
    }
    $anchor = trim((string) ($_POST['anchor'] ?? ''));
    if ($anchor !== '') {
        $opts[] = ['anchor', $anchor];
    }
    $trans = trim((string) ($_POST['transitionsTo'] ?? ''));
    if ($trans !== '') {
        $opts[] = ['transitionsTo', $trans];
    }
    $senderMustBe = trim((string) ($_POST['senderMustBe'] ?? ''));
    if ($senderMustBe !== '' && $senderMustBe !== 'any') {
        $opts[] = ['senderMustBe', $senderMustBe];
    }
    $reply = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['replyableWith'] ?? ''))), static fn($s) => $s !== ''));
    if ($reply !== []) {
        $opts[] = ['replyableWith', $reply];
    }

    // Keep the form filled in if anything below refuses it.
    $form = [
        'id' => $id, 'orig' => $orig, 'title' => $title, 'short' => $short,
        'lines' => $lines, 'opts' => $opts,
        'kind' => $kind, 'priority' => (string) ($_POST['priority'] ?? 'normal'),
        'subject' => (string) ($_POST['subject'] ?? ''), 'anchor' => $anchor,
        'transitionsTo' => $trans, 'senderMustBe' => $senderMustBe,
        'replyableWith' => (string) ($_POST['replyableWith'] ?? ''),
    ];

    if ($id === '' || !preg_match('/^[a-z0-9_]+$/', $id)) {
        $err = 'The id must be lower case letters, digits or underscore, with no spaces - it is how every reply and reference names this card.';
    } elseif ($title === '') {
        $err = 'A title is required.';
    } elseif ($lines === []) {
        $err = 'A template needs at least one line with a field.';
    } elseif ($orig === '' && isset($items[$id])) {
        $err = "A template with id '" . $id . "' already exists. Open it to edit, or pick another id.";
    } elseif ($anchor !== '' && !in_array($anchor, $keys, true)) {
        $err = 'The anchor "' . $anchor . '" is not a key this template produces. Keys are: ' . implode(', ', $keys);
    } else {
        $tpl = [
            'id'      => $id,
            'title'   => $title,
            'short'   => $short !== '' ? $short : $title,
            'lines'   => $lines,
            'options' => $opts,
        ];
        // A renamed id moves the entry rather than leaving both behind.
        if ($orig !== '' && $orig !== $id) {
            ghostd_unset_path($docId, 'items.' . $orig);
        }
        ghostd_set_path($docId, 'items.' . $id, $tpl);
        header('Location: ?page=config&t=deck');
        exit;
    }
}

// --------------------------------------------------------------- prefill ---
if ($form === null) {
    if ($editing !== '' && isset($items[$editing])) {
        $t = $items[$editing];
        $o = [];
        foreach ((array) ($t['options'] ?? []) as $p) {
            if (is_array($p) && count($p) >= 2) {
                $o[(string) $p[0]] = $p[1];
            }
        }
        $form = [
            'id' => (string) ($t['id'] ?? $editing), 'orig' => $editing,
            'title' => (string) ($t['title'] ?? ''), 'short' => (string) ($t['short'] ?? ''),
            'lines' => (array) ($t['lines'] ?? []),
            'kind' => (string) ($o['kind'] ?? 'root'),
            'priority' => (string) ($o['priority'] ?? 'normal'),
            'subject' => (string) ($o['subject'] ?? ''),
            'anchor' => (string) ($o['anchor'] ?? ''),
            'transitionsTo' => (string) ($o['transitionsTo'] ?? ''),
            'senderMustBe' => (string) ($o['senderMustBe'] ?? ''),
            'replyableWith' => is_array($o['replyableWith'] ?? null) ? implode(', ', $o['replyableWith']) : '',
        ];
    } else {
        $form = [
            'id' => '', 'orig' => '', 'title' => '', 'short' => '',
            'lines' => [['', '', [['', '', 'text', []]]]],
            'kind' => 'root', 'priority' => 'normal', 'subject' => '',
            'anchor' => '', 'transitionsTo' => '', 'senderMustBe' => '', 'replyableWith' => '',
        ];
    }
}

$isEdit = $form['orig'] !== '';

// Keys this template currently produces, for the anchor datalist.
$keyList = [];
foreach ($form['lines'] as $i => $l) {
    $lk = line_key((string) ($l[0] ?? ''), $i);
    foreach ((array) ($l[2] ?? []) as $fi => $_) {
        $keyList[] = $lk . '.' . chr(65 + (int) $fi);
    }
}

ghostd_head($isEdit ? 'Edit template' : 'New template', 'config');
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="note">
  A field's key comes from its <strong>line's</strong> title, not the field's own
  label: a line called <code>Location</code> whose first field is a grid gives
  <code>Location.A</code>. Rename a line and you rename the field that
  <code>subject</code> and <code>anchor</code> point at. Keys are shown against
  each line as you go.
</p>

<form method="post" id="tplform">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="orig" value="<?= h($form['orig']) ?>">

  <h2>The card</h2>
  <div class="fields">
    <label>Id <span class="dim">lower case, no spaces - how replies name it</span>
      <input type="text" name="tid" value="<?= h($form['id']) ?>" required pattern="[a-z0-9_]+"></label>
    <label>Title <span class="dim">shown to the player</span>
      <input type="text" name="title" value="<?= h($form['title']) ?>" required></label>
    <label>Short <span class="dim">used in subjects and notifications</span>
      <input type="text" name="short" value="<?= h($form['short']) ?>"></label>
  </div>

  <h2>Behaviour</h2>
  <div class="fields">
    <label>Kind
      <select name="kind">
        <?php foreach (['root' => 'root - opens a thread', 'reply' => 'reply - answers one', 'both' => 'both'] as $k => $lab): ?>
          <option value="<?= h($k) ?>" <?= $form['kind'] === $k ? 'selected' : '' ?>><?= h($lab) ?></option>
        <?php endforeach; ?>
      </select></label>

    <label>Priority
      <select name="priority">
        <option value="normal" <?= $form['priority'] !== 'high' ? 'selected' : '' ?>>normal</option>
        <option value="high" <?= $form['priority'] === 'high' ? 'selected' : '' ?>>high - FLASH</option>
      </select></label>

    <label>Subject <span class="dim">e.g. <code>TASKING {Unit.A} - {Task.A}</code></span>
      <input type="text" name="subject" value="<?= h($form['subject']) ?>"></label>

    <label>Anchor <span class="dim">the field key the thread pins to on the map</span>
      <input type="text" name="anchor" list="keylist" value="<?= h($form['anchor']) ?>">
      <datalist id="keylist">
        <?php foreach ($keyList as $k): ?><option value="<?= h($k) ?>"><?php endforeach; ?>
      </datalist></label>

    <label>Transitions to
      <span class="dim">a state (ISSUED, CLOSED...) makes the thread a <strong>task</strong>; leave blank for a plain message</span>
      <input type="text" name="transitionsTo" value="<?= h($form['transitionsTo']) ?>"></label>

    <label>Sender must be <span class="dim">any, leader, issuer, assignee</span>
      <input type="text" name="senderMustBe" value="<?= h($form['senderMustBe']) ?>"></label>

    <label>Repliable with <span class="dim">comma separated ids, e.g. roger, wilco, freetext, close</span>
      <input type="text" name="replyableWith" value="<?= h($form['replyableWith']) ?>"></label>
  </div>

  <h2>Lines</h2>
  <div id="lines">
  <?php foreach ($form['lines'] as $i => $l): ?>
    <?php
      $lt = (string) ($l[0] ?? '');
      $ll = (string) ($l[1] ?? '');
      $lf = (array) ($l[2] ?? []);
      if ($lf === []) { $lf = [['', '', 'text', []]]; }
    ?>
    <fieldset class="line" data-line="<?= (int) $i ?>">
      <legend>Line <?= (int) $i + 1 ?> <span class="dim key">key: <code><?= h(line_key($lt, $i)) ?></code></span></legend>
      <div class="fields">
        <label>Line title <span class="dim">makes the key</span>
          <input type="text" name="line[<?= (int) $i ?>][key]" value="<?= h($lt) ?>"></label>
        <label>Line label <span class="dim">drawn on the card</span>
          <input type="text" name="line[<?= (int) $i ?>][label]" value="<?= h($ll) ?>"></label>
      </div>
      <div class="linefields">
      <?php foreach ($lf as $fi => $f): ?>
        <div class="fieldbox">
          <span class="pill dimpill"><?= h(line_key($lt, $i) . '.' . chr(65 + (int) $fi)) ?></span>
          <input type="text" name="line[<?= (int) $i ?>][field][<?= (int) $fi ?>][prefix]"
                 value="<?= h((string) ($f[0] ?? '')) ?>" placeholder="prefix">
          <input type="text" name="line[<?= (int) $i ?>][field][<?= (int) $fi ?>][hint]"
                 value="<?= h((string) ($f[1] ?? '')) ?>" placeholder="hint shown to the player">
          <select name="line[<?= (int) $i ?>][field][<?= (int) $fi ?>][type]">
            <?php foreach (FIELD_TYPES as $t): ?>
              <option value="<?= h($t) ?>" <?= ((string) ($f[2] ?? 'text')) === $t ? 'selected' : '' ?>><?= h($t) ?></option>
            <?php endforeach; ?>
          </select>
          <textarea name="line[<?= (int) $i ?>][field][<?= (int) $fi ?>][opts]" rows="2"
                    placeholder="one per line: min=0 / max=999 / choices=GREEN,AMBER,RED / source=mapClick / autoFill=ownCallsign / required=true"><?= h(unparse_opts((array) ($f[3] ?? []))) ?></textarea>
        </div>
      <?php endforeach; ?>
      </div>
      <button type="button" class="addfield">+ field</button>
    </fieldset>
  <?php endforeach; ?>
  </div>

  <button type="button" id="addline">+ line</button>

  <div class="actions">
    <button type="submit"><?= $isEdit ? 'Save changes' : 'Create template' ?></button>
    <a href="?page=config&amp;t=deck">Cancel</a>
  </div>
</form>

<?php if ($isEdit): ?>
<form method="post" class="danger" onsubmit="return confirm('Delete this template? The document is backed up first.');">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="id" value="<?= h($form['orig']) ?>">
  <button type="submit" class="hot">Delete template</button>
</form>
<?php endif; ?>

<script>
// Indices only have to be unique - PHP reindexes with array_values on the way
// in - so new rows count up from whatever is already on the page.
(function () {
  var lines = document.getElementById('lines');
  var nextLine = lines.querySelectorAll('fieldset.line').length;

  function fieldHTML(li, fi) {
    var n = 'line[' + li + '][field][' + fi + ']';
    var types = <?= json_encode(FIELD_TYPES) ?>;
    var opts = types.map(function (t) { return '<option value="' + t + '">' + t + '</option>'; }).join('');
    return '<div class="fieldbox">' +
      '<span class="pill dimpill">new</span>' +
      '<input type="text" name="' + n + '[prefix]" placeholder="prefix">' +
      '<input type="text" name="' + n + '[hint]" placeholder="hint shown to the player">' +
      '<select name="' + n + '[type]">' + opts + '</select>' +
      '<textarea name="' + n + '[opts]" rows="2" placeholder="one per line: min=0 / choices=A,B / source=mapClick"></textarea>' +
      '</div>';
  }

  lines.addEventListener('click', function (e) {
    if (!e.target.classList.contains('addfield')) return;
    var fs = e.target.closest('fieldset.line');
    var li = fs.getAttribute('data-line');
    var box = fs.querySelector('.linefields');
    box.insertAdjacentHTML('beforeend', fieldHTML(li, box.children.length + 100));
  });

  document.getElementById('addline').addEventListener('click', function () {
    var i = nextLine++;
    var html = '<fieldset class="line" data-line="' + i + '">' +
      '<legend>Line ' + (i + 1) + ' <span class="dim key">key: from the title</span></legend>' +
      '<div class="fields">' +
      '<label>Line title <input type="text" name="line[' + i + '][key]"></label>' +
      '<label>Line label <input type="text" name="line[' + i + '][label]"></label>' +
      '</div><div class="linefields">' + fieldHTML(i, 0) + '</div>' +
      '<button type="button" class="addfield">+ field</button></fieldset>';
    lines.insertAdjacentHTML('beforeend', html);
  });
})();
</script>
<?php
ghostd_foot();
