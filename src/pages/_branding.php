<?php
/**
 * Branding, as a section of the Web settings page: the unit's name, tagline,
 * colours, logo sizes, the login page's look, and the three pictures.
 *
 * It was a page of its own, Branding in the bar. The user moved it in with
 * the home page (2026-09-11: "move branding in to web settings") because the
 * two are one job - what the site looks like - and one page is where an
 * admin looks for that. Two functions, because a page handles its posts
 * before it draws its head: websettings.php calls ghostd_branding_post()
 * from its POST switch and ghostd_branding_forms() where the section goes.
 * ?page=branding still answers, by sending the old link there.
 *
 * Everything is stored in the <unit>.web document, so it follows the
 * database rather than this server; the pictures live in <unit>.web.assets.
 */

declare(strict_types=1);

require_once __DIR__ . '/../branding.php';

const GHOSTD_LOGO_MAX = 524288;      // 512 KB - a logo, not a photograph
const GHOSTD_BG_MAX   = 2097152;     // 2 MB

/** Read, merge, put - so a missing document is created rather than ignored. */
function ghostd_branding_merge_put(string $id, array $fields): void
{
    $now = ghostd_get($id);
    $doc = is_array($now) ? $now : [];
    unset($doc['_id']);
    foreach ($fields as $k => $v) {
        if ($v === null) {
            unset($doc[$k]);
        } else {
            $doc[$k] = $v;
        }
    }
    $doc['section'] = 'web';
    ghostd_put($id, $doc);
}

/**
 * One posted branding form - identity, upload or remove - stored. Returns
 * the message to show; throws with the reason when it cannot.
 */
function ghostd_branding_post(string $what): string
{
    $cfg     = ghostd_config();
    $docId   = $cfg['unit'] . '.web';
    $assetId = $cfg['unit'] . '.web.assets';

    if ($what === 'identity') {
        $colours = [];
        foreach (GHOSTD_BRAND_COLOURS as $token => $_label) {
            $v = trim((string) ($_POST['c_' . $token] ?? ''));
            $use = isset($_POST['use_' . $token]);
            if ($use && ghostd_is_hex($v)) {
                $colours[$token] = strtolower($v);
            }
        }
        ghostd_branding_merge_put($docId, [
            'logoLoginScale' => max(20, min(400, (int) ($_POST['logoLoginScale'] ?? 100))),
            'logoBarScale'   => max(20, min(400, (int) ($_POST['logoBarScale'] ?? 100))),
            'unitName'     => trim((string) ($_POST['unitName'] ?? '')),
            'tagline'      => trim((string) ($_POST['tagline'] ?? '')),
            'colours'      => $colours,
            'hidePassword' => isset($_POST['hidePassword']),
            'loginFit'     => (($_POST['loginFit'] ?? 'cover') === 'contain') ? 'contain' : 'cover',
            'loginDim'     => max(0, min(90, (int) ($_POST['loginDim'] ?? 55))),
        ]);
        return 'Saved. The name, colors and login page are updated for everyone.';
    }

    if ($what === 'upload') {
        $which = (string) ($_POST['which'] ?? '');
        if (!in_array($which, GHOSTD_ASSET_SLOTS, true)) {
            throw new RuntimeException('Unknown picture.');
        }
        $f = $_FILES['file'] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Nothing arrived. If the file is large, PHP\'s own upload_max_filesize ('
                . ini_get('upload_max_filesize') . ') and post_max_size (' . ini_get('post_max_size')
                . ') are the limits to raise.'
            );
        }
        $max = $which === 'background' ? GHOSTD_BG_MAX : GHOSTD_LOGO_MAX;
        if ((int) $f['size'] > $max) {
            throw new RuntimeException('That file is ' . round($f['size'] / 1024) . ' KB; the limit is '
                . round($max / 1024) . ' KB. Resize it first - this is a web page, not an archive.');
        }
        // The browser's word for the type is not evidence; ask the file.
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        if (!isset(GHOSTD_ASSET_TYPES[$mime])) {
            throw new RuntimeException('That is a ' . $mime . '. Use PNG, JPEG, GIF, WebP or SVG.');
        }
        $bytes = file_get_contents($f['tmp_name']);
        if ($bytes === false) {
            throw new RuntimeException('The upload could not be read.');
        }
        ghostd_branding_merge_put($assetId, [$which => ['mime' => $mime, 'data' => base64_encode($bytes)]]);
        // Stamped so browsers holding the old picture fetch the new one.
        ghostd_branding_merge_put($docId, ['assetsAt' => gmdate('YmdHis')]);
        return ucfirst($which) . ' uploaded (' . round(strlen($bytes) / 1024) . ' KB).';
    }

    if ($what === 'remove') {
        $which = (string) ($_POST['which'] ?? '');
        if (!in_array($which, GHOSTD_ASSET_SLOTS, true)) {
            throw new RuntimeException('Unknown picture.');
        }
        ghostd_unset_path($assetId, $which);
        ghostd_branding_merge_put($docId, ['assetsAt' => gmdate('YmdHis')]);
        return ucfirst($which) . ' removed.';
    }

    throw new RuntimeException('Nothing said what to do.');
}

/** The branding section: name, colours, logo sizes and the login page in one form; the pictures in three. */
function ghostd_branding_forms(): void
{
    $cfg     = ghostd_config();
    $docId   = $cfg['unit'] . '.web';
    $assetId = $cfg['unit'] . '.web.assets';

    // What is stored, not what was posted.
    $b = GHOSTD_BRAND_DEFAULTS;
    try {
        $doc = ghostd_get($docId);
        if (is_array($doc)) {
            foreach (GHOSTD_BRAND_DEFAULTS as $k => $d) {
                if (array_key_exists($k, $doc)) { $b[$k] = $doc[$k]; }
            }
        }
    } catch (Throwable $e) {
        ghostd_flash('bad', 'The branding document could not be read: ' . $e->getMessage());
    }
    $colours = is_array($b['colours'] ?? null) ? $b['colours'] : [];

    $fallback = [
        'accent' => '#93cf72', 'ground' => '#0b0e11', 'panel' => '#12161b',
        'ink' => '#e3e9ef', 'line' => '#242b33', 'hot' => '#e4574a',
    ];
    ?>
<h2 class="sectionhead">Branding</h2>
<p class="note">The unit's name, colours and pictures, on every page and on the
sign-in card. Stored in the <code><?= h($docId) ?></code> document; the pictures
in <code><?= h($assetId) ?></code>.</p>

<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="identity">

  <h2>Name</h2>
  <label for="unitName">Unit name <span class="dim">shown in the bar, the tab title and the login card</span></label>
  <input type="text" id="unitName" name="unitName" maxlength="60"
         placeholder="Ghost TAC//PAC" value="<?= h((string) $b['unitName']) ?>">

  <label for="tagline">Tagline <span class="dim">optional, under the name on the login page</span></label>
  <input type="text" id="tagline" name="tagline" maxlength="120"
         placeholder="Personnel and operations" value="<?= h((string) $b['tagline']) ?>">

  <h2>Colors</h2>
  <p class="dim">Tick a color to use it. Anything left unticked follows the
  scheme the visitor picked. The shades either side of a color - panel edges,
  the text on a button - are worked out from it.</p>
  <?php foreach (GHOSTD_BRAND_COLOURS as $token => $label): ?>
    <div class="fieldrow colourrow">
      <label class="inlinelabel">
        <input type="checkbox" name="use_<?= h($token) ?>" <?= isset($colours[$token]) ? 'checked' : '' ?>>
        <?= h($label) ?>
      </label>
      <input type="color" name="c_<?= h($token) ?>"
             value="<?= h((string) ($colours[$token] ?? $fallback[$token])) ?>">
      <code><?= h($token) ?></code>
    </div>
  <?php endforeach; ?>

  <h2>Logo size</h2>
  <p class="dim">Two scales, because the two places are not alike: the login
  card is the only thing on its page, while the bar sits above every table.
  100% is 96px tall on the login card and 26px in the bar. The home page has
  its own scale, above.</p>

  <label for="logoLoginScale">Login logo <span class="dim">centred, above the unit name</span></label>
  <div class="scalerow">
    <input type="range" id="logoLoginScale" name="logoLoginScale" min="20" max="400" step="5"
           value="<?= (int) $b['logoLoginScale'] ?>" oninput="this.nextElementSibling.textContent = this.value + '%'">
    <output><?= (int) $b['logoLoginScale'] ?>%</output>
  </div>

  <label for="logoBarScale">Bar logo <span class="dim">every other page</span></label>
  <div class="scalerow">
    <input type="range" id="logoBarScale" name="logoBarScale" min="20" max="400" step="5"
           value="<?= (int) $b['logoBarScale'] ?>" oninput="this.nextElementSibling.textContent = this.value + '%'">
    <output><?= (int) $b['logoBarScale'] ?>%</output>
  </div>

  <h2>Login page</h2>
  <label class="inlinelabel">
    <input type="checkbox" name="hidePassword" <?= !empty($b['hidePassword']) ? 'checked' : '' ?>>
    Hide the admin login entirely
  </label>
  <p class="dim">With this on, the Admin login button does not appear and the
  password cannot be used - Steam becomes the only way in. Leave it off until
  you have signed in with Steam at least once, or you can lock yourself out.</p>

  <label for="loginFit">Background fit</label>
  <select id="loginFit" name="loginFit">
    <option value="cover" <?= $b['loginFit'] === 'cover' ? 'selected' : '' ?>>Cover - fill the window, crop the edges</option>
    <option value="contain" <?= $b['loginFit'] === 'contain' ? 'selected' : '' ?>>Contain - show all of it</option>
  </select>

  <label for="loginDim">Darken the background <span class="dim">so the card stays readable</span></label>
  <input type="range" id="loginDim" name="loginDim" min="0" max="90" step="5"
         value="<?= (int) $b['loginDim'] ?>">

  <div class="actions"><button type="submit">Save branding</button></div>
</form>

<h2>Pictures</h2>
<div class="tiles">
  <?php foreach ([
      ['logo', 'Bar logo', 'The small mark beside the navigation, on every page. Reads at 26px, so a compact badge works better than a wordmark. 512 KB.'],
      ['loginLogo', 'Login logo', 'The big mark on the login card and the home page. Falls back to the bar logo if you leave this empty. 512 KB.'],
      ['background', 'Login background', 'Behind the login card and the home page. 2 MB - and it is fetched before anyone has signed in, so smaller is kinder.'],
  ] as $a): ?>
    <?php [$which, $label, $hint] = $a; $have = ghostd_brand_asset($which) !== null; ?>
    <div class="card">
      <h3><?= h($label) ?></h3>
      <p class="dim"><?= h($hint) ?></p>
      <?php if ($have): ?>
        <p><img src="<?= h(ghostd_asset_url($which)) ?>" alt="" class="brandprev"></p>
      <?php else: ?>
        <p class="dim">None uploaded.</p>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
        <input type="hidden" name="what" value="upload">
        <input type="hidden" name="which" value="<?= h($which) ?>">
        <input type="file" name="file" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required>
        <button type="submit">Upload</button>
      </form>
      <?php if ($have): ?>
      <form method="post" class="danger">
        <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
        <input type="hidden" name="what" value="remove">
        <input type="hidden" name="which" value="<?= h($which) ?>">
        <button type="submit" class="hot">Remove</button>
      </form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
    <?php
}
