<?php
/**
 * Editing a config document WHERE IT BELONGS, not on the template page.
 *
 * A squad's arsenal, a platoon's motorpool and a role's arsenal are documents
 * named after the thing that owns them - sqd_<SQUAD>, plt_<PLATOON>,
 * role_<CLASS>. They used to be a link that threw you at ?page=configedit, and
 * a link is not an editor (user, 2026-09-09: "motor needs to have the windows
 * not a add or like to a fucking templa how many times do you need told this").
 *
 * So the boxes are on the page. This is the same editor configedit.php draws,
 * split out so both draw it from one place - change the shape of an arsenal
 * once and the squad page, the platoon page, the role page and the template
 * page all follow.
 *
 * The renderer is src/pages/_edit_inline.php; this is the save half.
 */

declare(strict_types=1);

require_once __DIR__ . '/templates.php';

/**
 * Write back what _edit_inline.php posted. Returns the message to flash.
 *
 * The owning page recognises the post by `what=inlineedit` and hands $_POST
 * straight over; the document is named in `ilk` (which template) and `ilv`
 * (which version), both written by the renderer, so a page needs no per-type
 * code of its own.
 */
function ghostd_inline_save(array $post): string
{
    $key     = (string) ($post['ilk'] ?? '');
    $variant = trim((string) ($post['ilv'] ?? ''));

    if (!isset(GHOSTD_TEMPLATES[$key])) {
        throw new RuntimeException('There is no config called "' . $key . '".');
    }
    $t = GHOSTD_TEMPLATES[$key];

    // A DERIVED NAME ONLY. This editor is embedded on the page that owns the
    // document, and the only documents a page owns are the ones named after
    // it. Refusing anything else means a hand-edited form cannot reach past
    // its own squad and overwrite the common arsenal.
    if (!str_starts_with($variant, 'sqd_') && !str_starts_with($variant, 'plt_')
        && !str_starts_with($variant, 'role_')) {
        throw new RuntimeException('"' . $variant . '" is not a squad\'s, platoon\'s or role\'s '
            . 'own document. The common versions are edited under Templates.');
    }

    switch ($t['shape']) {
        // Named lists of classnames - the arsenal.
        case 'lists':
            $lists = [];
            foreach ((array) ($post['list'] ?? []) as $name => $raw) {
                $lists[(string) $name] = preg_split('/\r?\n/', (string) $raw) ?: [];
            }
            // Lists the unit invented, name and contents side by side.
            $newNames = (array) ($post['newname'] ?? []);
            $newVals  = (array) ($post['newlist'] ?? []);
            foreach ($newNames as $i => $nm) {
                $nm = trim((string) $nm);
                if ($nm === '') {
                    continue;
                }
                $lists[$nm] = preg_split('/\r?\n/', (string) ($newVals[$i] ?? '')) ?: [];
            }
            ghostd_variant_save($key, $variant, $lists);
            $total = array_sum(array_map(
                static fn($l) => count(array_filter(array_map('trim', $l))), $lists));
            return $total . ' entries saved to ' . ghostd_template_doc_id($key, $variant)
                . '. Read at the next mission start.';

        // A keyed table with fields - the motorpool's categories.
        case 'items':
            $ids    = (array) ($post['id'] ?? []);
            $remove = (array) ($post['remove'] ?? []);
            $items  = [];
            $order  = 0;
            foreach ($ids as $row => $rawId) {
                $id = trim((string) $rawId);
                if ($id === '' || in_array((string) $row, $remove, true)) {
                    continue;
                }
                if (!preg_match($t['idPattern'], $id)) {
                    throw new RuntimeException('"' . $id . '" is not a usable id. ' . $t['idHelp']);
                }
                if (isset($items[$id])) {
                    throw new RuntimeException('Two rows share the id "' . $id . '".');
                }
                $rec   = ['id' => $id, 'order' => $order += 10];
                $blank = true;
                foreach ($t['fields'] as $f => $meta) {
                    $raw = (string) ($post[$f][$row] ?? '');
                    if ($meta['kind'] === 'list') {
                        $rec[$f] = array_values(array_filter(array_map('trim',
                            preg_split('/\r?\n/', $raw) ?: []), static fn($x) => $x !== ''));
                        if ($rec[$f] !== []) { $blank = false; }
                    } else {
                        $rec[$f] = trim($raw);
                        if ($rec[$f] !== '') { $blank = false; }
                    }
                }
                // An id with nothing against it is a row somebody started and
                // abandoned, not a category with no vehicles in it.
                if ($blank) {
                    continue;
                }
                $items[$id] = $rec;
            }
            ghostd_template_save($key, $items, $variant);
            return count($items) . ' ' . strtolower($t['label']) . ' entries saved to '
                . ghostd_template_doc_id($key, $variant) . '.';
    }

    throw new RuntimeException('A ' . $t['label'] . ' cannot be edited in place yet.');
}
