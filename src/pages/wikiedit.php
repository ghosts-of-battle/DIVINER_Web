<?php
/**
 * Writing a wiki page: new, or the one named by ?p=.
 *
 * Admins only - index.php keeps this behind the gate and the admin list; a
 * password session may open it and will be refused on save, which is the
 * rule every write follows (ghostd_guard_write). The editor is the same Wysi
 * the home page uses, and the HTML is cleaned on save.
 */

declare(strict_types=1);

require_once __DIR__ . '/../wikipages.php';

$slug = trim((string) ($_GET['p'] ?? ($_POST['slug'] ?? '')));
$page = $slug !== '' ? ghostd_wiki_page($slug) : null;
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ghostd_csrf_check();
    try {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'delete') {
            if ($page === null || !ghostd_wiki_delete($page['slug'])) {
                throw new RuntimeException('Nothing was deleted.');
            }
            header('Location: ?page=wiki');
            exit;
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $new = strtolower(trim((string) ($_POST['slug'] ?? '')));
        if ($new === '' && $page === null) {
            $new = ghostd_wiki_slugify($title);     // a new page takes its address from its title
        }
        if ($page === null && ghostd_wiki_page($new) !== null) {
            throw new RuntimeException('There is already a page at that address. Open it to edit it, or pick another.');
        }
        $who = ghostd_identity();
        ghostd_wiki_save(
            $new,
            $title,
            (string) ($_POST['html'] ?? ''),
            !empty($_POST['public']),
            (int) ($_POST['order'] ?? 0),
            (string) ($who['name'] ?? ($who['steamid'] ?? ''))
        );
        header('Location: ?page=wiki&p=' . urlencode($new));
        exit;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}

$title = $page['title'] ?? (string) ($_POST['title'] ?? '');
$html  = $page['html'] ?? (string) ($_POST['html'] ?? '');
$public = $page !== null ? $page['public'] : (!isset($_POST['title']) || !empty($_POST['public']));
$order = $page['order'] ?? (int) ($_POST['order'] ?? 0);

ghostd_head($page !== null ? 'Edit: ' . $page['title'] : 'New wiki page', 'wiki');
if ($msg !== null) { ghostd_flash('good', $msg); }
if ($err !== null) { ghostd_flash('bad', $err); }
?>
<p class="dim"><a href="?page=wiki">&larr; Wiki</a>
  <?php if ($page !== null): ?> &middot; <a href="?page=wiki&amp;p=<?= urlencode($page['slug']) ?>">View</a>
  &middot; Mongo doc <code><?= h(ghostd_wiki_doc_id($page['slug'])) ?></code><?php endif; ?></p>

<link rel="stylesheet" href="<?= h(ghostd_asset('wysi.min.css')) ?>">
<form method="post" class="card fields">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="action" value="save">

  <label for="wk_title">Title</label>
  <input type="text" id="wk_title" name="title" maxlength="120" required value="<?= h($title) ?>">

  <label for="wk_slug">Address <span class="dim">?page=wiki&amp;p=<em>this</em>; lower-case, dash, underscore<?= $page === null ? ' - blank to take it from the title' : '' ?></span></label>
  <input type="text" id="wk_slug" name="slug" pattern="[a-z0-9][a-z0-9_-]{0,60}" value="<?= h($page['slug'] ?? $slug) ?>"
         <?= $page !== null ? 'readonly' : '' ?> placeholder="radio-plan">

  <label for="wk_order">Order <span class="dim">pages list lowest first, then by title</span></label>
  <input type="number" id="wk_order" name="order" value="<?= (int) $order ?>" step="1" class="short" style="max-width:8rem">

  <label class="inlinelabel">
    <input type="checkbox" name="public" <?= $public ? 'checked' : '' ?>>
    Public <span class="dim">readable without signing in, and offered to the website through the feed</span>
  </label>

  <label for="wk_html">Content</label>
  <textarea id="wk_html" name="html" rows="24" data-wysi="html"><?= h($html) ?></textarea>
  <p class="dim">Links and pictures take an address: paste one, or a public file's link from Media.</p>

  <div class="actions"><button type="submit">Save</button></div>
</form>

<?php if ($page !== null): ?>
<form method="post" class="danger confirm">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="slug" value="<?= h($page['slug']) ?>">
  <button type="submit" class="hot">Delete this page</button>
  <span class="dim">a copy goes to the backup collection first</span>
</form>
<?php endif; ?>

<script src="<?= h(ghostd_asset('wysi.min.js')) ?>"></script>
<script src="<?= h(ghostd_asset('wysi-site.js')) ?>"></script>
<?php
ghostd_foot();
