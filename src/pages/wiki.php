<?php
/**
 * The unit's wiki, read: the list of pages, or one page.
 *
 * Public pages open with no sign-in - index.php sends this page round the
 * gate - and a member's session opens the rest. Editing is the wikiedit page,
 * reached from the links an admin sees here. The help panel in the corner is
 * src/wiki.php and has nothing to do with this.
 */

declare(strict_types=1);

require_once __DIR__ . '/../wikipages.php';
require_once __DIR__ . '/../auth.php';

$signedIn = ghostd_logged_in();
$canEdit  = $signedIn && ghostd_can_edit();
$slug     = trim((string) ($_GET['p'] ?? ''));

// ---- one page --------------------------------------------------------------
if ($slug !== '') {
    $page = ghostd_wiki_page($slug);
    if ($page === null) {
        ghostd_head('Wiki', 'wiki');
        ghostd_flash('bad', 'There is no page at that address.');
        echo '<p><a href="?page=wiki">&larr; Wiki</a></p>';
        ghostd_foot();
        return;
    }
    if (!$page['public'] && !$signedIn) {
        ghostd_head('Wiki', 'wiki');
        echo '<p class="note">That page is for members. <a href="?page=login">Sign in</a> to read it.</p>';
        echo '<p><a href="?page=wiki">&larr; Wiki</a></p>';
        ghostd_foot();
        return;
    }
    ghostd_head($page['title'], 'wiki');
    ?>
    <p class="dim wk-crumbs"><a href="?page=wiki">&larr; Wiki</a>
      <?php if (!$page['public']): ?> &middot; <span class="pill dimpill">members only</span><?php endif; ?>
      <?php if ($canEdit): ?> &middot; <a href="?page=wikiedit&amp;p=<?= urlencode($page['slug']) ?>">Edit</a><?php endif; ?>
    </p>
    <article class="wk-body"><?= $page['html'] ?></article>
    <?php if ($page['updatedAt'] !== ''): ?>
      <p class="dim wk-foot">Updated <?= h($page['updatedAt']) ?><?= $page['updatedBy'] !== '' ? ' by ' . h($page['updatedBy']) : '' ?></p>
    <?php endif; ?>
    <?php
    ghostd_foot();
    return;
}

// ---- the list --------------------------------------------------------------
$pages = ghostd_wiki_index(!$signedIn);

ghostd_head('Wiki', 'wiki');
?>
<?php if ($canEdit): ?>
<p><a href="?page=wikiedit" class="btnlink">New page</a></p>
<?php endif; ?>

<?php if ($pages === []): ?>
  <p class="dim"><?= $signedIn ? 'No pages yet.' : 'No public pages yet.' ?></p>
<?php else: ?>
<ul class="wk-list">
  <?php foreach ($pages as $p): ?>
    <li><a href="?page=wiki&amp;p=<?= urlencode($p['slug']) ?>"><?= h($p['title']) ?></a>
      <?php if ($signedIn && !$p['public']): ?><span class="pill dimpill">members only</span><?php endif; ?>
      <?php if ($p['updatedAt'] !== ''): ?><span class="dim"><?= h(substr($p['updatedAt'], 0, 10)) ?></span><?php endif; ?>
      <?php if ($canEdit): ?><a class="dim" href="?page=wikiedit&amp;p=<?= urlencode($p['slug']) ?>">edit</a><?php endif; ?>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>

<?php if (!$signedIn): ?>
<p class="note"><a href="?page=login">Sign in</a> to see the members' pages.</p>
<?php endif; ?>
<?php
ghostd_foot();
