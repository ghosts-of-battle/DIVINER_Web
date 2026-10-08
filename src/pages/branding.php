<?php
/**
 * Branding lives on the Web settings page now (2026-09-11, "move branding in
 * to web settings") - see _branding.php. The old address keeps working: a
 * bookmark or a link in the wiki lands where the forms went.
 */

declare(strict_types=1);

header('Location: ?page=websettings');
exit;
