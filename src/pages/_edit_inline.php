<?php
/**
 * The config editor, drawn INSIDE the page that owns the document.
 *
 * Set $ilKey (a GHOSTD_TEMPLATES key) and $ilVariant (the derived document
 * name - sqd_*, plt_*, role_*) and require this. It draws the same boxes the
 * template page draws and posts back to the page it is on, so a squad's
 * arsenal is edited on the squad and nothing navigates away.
 *
 * The page must carry the post through to ghostd_inline_save() - see
 * src/inline_edit.php - which recognises it by `what=inlineedit`.
 *
 * $ilHidden is an array of extra hidden fields the owning page needs back
 * (which squad, which section), since the form posts to that page's own URL.
 */

declare(strict_types=1);

$ilT     = GHOSTD_TEMPLATES[$ilKey];
$ilDoc   = ghostd_template_doc_id($ilKey, $ilVariant);
$ilKnown = ghostd_classnames($ilT['classKind'] ?? 'all');
$ilHidden = $ilHidden ?? [];
// WHOSE ARSENAL THIS IS. The template's own label is "Common arsenal", which
// is a lie on a squad page - the owner passes its own word.
$ilLabel = $ilLabel ?? strtolower($ilT['label']);
?>
<form method="post" class="inlineedit">
  <input type="hidden" name="csrf" value="<?= h(ghostd_csrf_token()) ?>">
  <input type="hidden" name="what" value="inlineedit">
  <input type="hidden" name="ilk" value="<?= h($ilKey) ?>">
  <input type="hidden" name="ilv" value="<?= h($ilVariant) ?>">
  <?php foreach ($ilHidden as $hk => $hv): ?>
    <input type="hidden" name="<?= h((string) $hk) ?>" value="<?= h((string) $hv) ?>">
  <?php endforeach; ?>

  <?php if ($ilKnown !== []): ?>
    <?php // ONE DATALIST PER PAGE, not per box - the same 3000 options repeated
          // per textarea is a page nobody can load. ?>
    <datalist id="classlist-<?= h($ilKey) ?>">
      <?php foreach (array_slice($ilKnown, 0, 3000) as $ilC): ?>
        <option value="<?= h($ilC) ?>"></option>
      <?php endforeach; ?>
    </datalist>
  <?php endif; ?>

<?php if ($ilT['shape'] === 'lists'): ?>
  <?php
    $ilLists = ghostd_template_lists($ilKey, $ilVariant);
    // A DOCUMENT THAT DOES NOT EXIST YET STILL GETS ITS BOXES. Otherwise the
    // first thing you see on a new squad is an editor with nothing to edit and
    // no way to start one.
    if ($ilLists === []) {
        foreach ((array) ($ilT['lists'] ?? []) as $ilName) {
            $ilLists[$ilName] = [];
        }
    }
  ?>
  <p class="dim"><strong>One classname per line.</strong> Quotes and commas are
  stripped on save, so a block pasted straight out of a <code>.hpp</code> works
  as it is.<?= $ilKnown === [] ? ' No classname export yet - these are plain boxes until the admin console\'s EXPORT CLASSES has been run.' : '' ?></p>

  <?php foreach ($ilLists as $ilName => $ilVals): ?>
    <fieldset class="line">
      <legend><?= h((string) $ilName) ?>
        <span class="key"><?= count($ilVals) ?></span>
        <?php if (str_starts_with((string) $ilName, 'items')): ?>
          <span class="key">merged as items</span>
        <?php endif; ?>
      </legend>
      <textarea name="list[<?= h((string) $ilName) ?>]"
                rows="<?= max(3, min(14, count($ilVals) + 2)) ?>"
                class="short" spellcheck="false"
                placeholder="one classname per line"><?= h(implode("\n", $ilVals)) ?></textarea>
    </fieldset>
  <?php endforeach; ?>

  <?php if (!empty($ilT['openNames'])): ?>
    <fieldset class="line">
      <legend>Add a list</legend>
      <p class="dim">Every list in the document is merged. A name beginning
      <code>items</code> is merged as items; anything else is merged by its own
      kind, so the name has to match what it holds.</p>
      <div class="fieldbox">
        <input type="text" name="newname[0]" placeholder="list name, e.g. items_explosives">
        <textarea name="newlist[0]" rows="3" class="short" placeholder="one classname per line"></textarea>
      </div>
    </fieldset>
  <?php endif; ?>

<?php elseif ($ilT['shape'] === 'items'): ?>
  <?php
    $ilItems = [];
    try {
        $ilItems = ghostd_template_items($ilKey, $ilVariant);
    } catch (Throwable $e) {
        $ilItems = [];
    }
    $ilBlocks = array_filter($ilT['fields'], static fn($m) => $m['kind'] === 'block');
    $ilSpan   = 2 + count($ilT['fields']) - count($ilBlocks);
  ?>
  <table class="grid">
    <thead>
      <tr>
        <th>Id</th>
        <?php foreach ($ilT['fields'] as $ilF => $ilM): ?>
          <?php if ($ilM['kind'] === 'block') { continue; } ?>
          <th><?= h($ilM['label']) ?></th>
        <?php endforeach; ?>
        <th>Remove</th>
      </tr>
    </thead>
    <tbody>
    <?php $ilRow = 0; foreach ($ilItems as $ilId => $ilIt): ?>
      <tr>
        <td><input type="text" name="id[<?= $ilRow ?>]" value="<?= h((string) $ilId) ?>"></td>
        <?php foreach ($ilT['fields'] as $ilF => $ilM): ?>
          <?php if ($ilM['kind'] === 'block') { continue; } ?>
          <td>
            <?php if ($ilM['kind'] === 'list'): ?>
              <textarea name="<?= h($ilF) ?>[<?= $ilRow ?>]" rows="<?= max(6, min(20, count((array) ($ilIt[$ilF] ?? [])) + 2)) ?>" class="short"
                        spellcheck="false"><?= h(implode("\n", (array) ($ilIt[$ilF] ?? []))) ?></textarea>
            <?php else: ?>
              <input type="text" name="<?= h($ilF) ?>[<?= $ilRow ?>]"
                     value="<?= h((string) ($ilIt[$ilF] ?? '')) ?>">
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td><input type="checkbox" name="remove[]" value="<?= $ilRow ?>"></td>
      </tr>
    <?php $ilRow++; endforeach; ?>

    <?php // TWO SPARE ROWS, so adding a category is typing in one - there is no
          // "add" button to press first. ?>
    <?php for ($ilI = 0; $ilI < 2; $ilI++): $ilR = $ilRow + $ilI; ?>
      <tr>
        <td><input type="text" name="id[<?= $ilR ?>]" placeholder="<?= h($ilI === 0 ? 'new id' : '') ?>"></td>
        <?php foreach ($ilT['fields'] as $ilF => $ilM): ?>
          <?php if ($ilM['kind'] === 'block') { continue; } ?>
          <td>
            <?php if ($ilM['kind'] === 'list'): ?>
              <textarea name="<?= h($ilF) ?>[<?= $ilR ?>]" rows="6" class="short"
                        spellcheck="false" placeholder="one per line"></textarea>
            <?php else: ?>
              <input type="text" name="<?= h($ilF) ?>[<?= $ilR ?>]" placeholder="<?= h($ilM['label']) ?>">
            <?php endif; ?>
          </td>
        <?php endforeach; ?>
        <td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <p class="dim"><strong>Id:</strong> <?= h($ilT['idHelp']) ?>
  Order is the order of the rows. Clearing every field on a row removes it.</p>
<?php endif; ?>

  <div class="actions">
    <button type="submit">Save <?= h($ilLabel) ?></button>
    <span class="dim"><code><?= h($ilDoc) ?></code></span>
  </div>
</form>
