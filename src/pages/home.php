<?php
/**
 * The unit's front door: the public page a visitor lands on before signing in.
 *
 * It wears the sign-in page's clothes - the same background, dim and logo
 * from Branding - because it is the same place; sign-in is one click further
 * in. What it says comes from the Web settings page, block by block, and
 * index.php only sends anybody here when that page has switched it on (or an
 * admin is previewing it).
 */

declare(strict_types=1);

require_once __DIR__ . '/../home.php';
require_once __DIR__ . '/../branding.php';
require_once __DIR__ . '/../steam.php';

$home    = ghostd_home();
$brand   = ghostd_branding();
$tagline = trim((string) $brand['tagline']);
$logo    = ghostd_login_logo();

ghostd_head('Home');
?>
<div class="home">
  <div class="gate homehead">
    <div class="gatemark">
      <?php if ($logo !== null): ?>
        <img class="gatelogo" src="<?= h(ghostd_asset_url($logo)) ?>" alt="<?= h(ghostd_unit_name()) ?>">
      <?php endif; ?>
      <h2 class="gatename"><?= h(ghostd_unit_name()) ?></h2>
      <?php if ($tagline !== ''): ?>
        <p class="gatetag"><?= h($tagline) ?></p>
      <?php endif; ?>
    </div>
    <div class="homeactions">
      <a class="btnlink" href="?page=login">Sign in</a>
      <?php if (ghostd_steam_enabled()): ?>
        <a class="applybtn" href="?page=steam">Apply to join</a>
      <?php endif; ?>
    </div>
    <?php if (!$home['enabled']): ?>
      <p class="gatehelp">Preview - the home page is switched off, so only admins see this.</p>
    <?php endif; ?>
  </div>

  <?php foreach (GHOSTD_HOME_BLOCKS as $key => $label): ?>
    <?php if ($home['blocks'][$key] === '') { continue; } ?>
    <section class="gate homeblock">
      <?php if (trim($home['headings'][$key]) !== ''): ?>
        <h2><?= h($home['headings'][$key]) ?></h2>
      <?php endif; ?>
      <div class="homebody"><?= $home['blocks'][$key] ?></div>
    </section>
  <?php endforeach; ?>
</div>
<?php
ghostd_foot();
