<?php
/**
 * The unit's front door: the public page a visitor lands on before signing in.
 *
 * It wears the sign-in page's clothes - the same background, dim and logo
 * from Branding - because it is the same place; sign-in is one click further
 * in. What it says, how wide and tall it is and which of the layouts it
 * takes all come from the Web settings page; the layout is a class on the
 * card and style.css does the rest. Sign in and Apply are the way in and sit
 * where a visitor who has read the page is looking (user, 2026-09-11: "make
 * the sign in and the apply buttons on the bottom"). index.php only sends
 * anybody here when Web settings has switched it on, or an admin is
 * previewing it.
 */

declare(strict_types=1);

require_once __DIR__ . '/../home.php';
require_once __DIR__ . '/../branding.php';
require_once __DIR__ . '/../steam.php';

$home    = ghostd_home();
$brand   = ghostd_branding();
$tagline = trim((string) $brand['tagline']);
$logo    = ghostd_login_logo();

ghostd_head('Home', 'home');
?>
<?php
// Where the card sits: the gate centres its main; left and right push it to
// that side with a little air, and the width rule below is the same one.
$margin = ['left' => '0 auto 0 4%', 'right' => '0 4% 0 auto', 'center' => '0 auto'][$home['align']] ?? '0 auto';
?>
<style>
  .p-home main { width: <?= (int) $home['width'] ?>%; margin: <?= h($margin) ?>; }
  <?php if ((int) $home['height'] > 0): ?>
  .home { min-height: <?= (int) $home['height'] ?>vh; }
  <?php endif; ?>
</style>
<div class="gate home l-<?= h($home['layout']) ?>">
  <div class="gatemark">
    <?php if ($logo !== null): ?>
      <img class="gatelogo" src="<?= h(ghostd_asset_url($logo)) ?>" alt="<?= h(ghostd_unit_name()) ?>">
    <?php endif; ?>
    <div class="gatetext">
      <h2 class="gatename"><?= h(ghostd_unit_name()) ?></h2>
      <?php if ($tagline !== ''): ?>
        <p class="gatetag"><?= h($tagline) ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="homewords">
  <?php foreach (GHOSTD_HOME_BLOCKS as $key => $label): ?>
    <?php if ($home['blocks'][$key] === '') { continue; } ?>
    <section class="homeblock">
      <?php if (trim($home['headings'][$key]) !== ''): ?>
        <h2><?= h($home['headings'][$key]) ?></h2>
      <?php endif; ?>
      <div class="homebody"><?= $home['blocks'][$key] ?></div>
    </section>
  <?php endforeach; ?>
  </div>

  <div class="homeactions">
    <a class="btnlink" href="?page=login">Sign in</a>
    <?php if (ghostd_steam_enabled()): ?>
      <a class="btnlink alt" href="?page=steam">Apply to join</a>
    <?php endif; ?>
  </div>

  <?php if (!$home['enabled']): ?>
    <p class="gatehelp">Preview - the home page is switched off, so only admins see this.</p>
  <?php endif; ?>
</div>
<?php
ghostd_foot();
