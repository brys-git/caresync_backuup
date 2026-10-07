<?php
/**
 * Search box for list pages that have no filter form of their own.
 *
 * @var string $action       form target (the list page itself)
 * @var string $term         current search term (keeps it in the box)
 * @var string $placeholder  what can be searched
 * @var array  $keep         other GET params to carry along (name => value)
 */
$term        = (string) ($term ?? '');
$placeholder = (string) ($placeholder ?? 'Search');
$keep        = $keep ?? [];
$id          = 'search-' . substr(md5((string) $action), 0, 6);
?>
<form class="cs-filters mb-3" method="get" action="<?= esc($action) ?>" role="search">
    <?php foreach ($keep as $name => $value): ?>
        <?php if ($value !== '' && $value !== null): ?>
            <input type="hidden" name="<?= esc((string) $name, 'attr') ?>" value="<?= esc((string) $value, 'attr') ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <div class="cs-filters__field cs-filters__field--wide">
        <label class="form-label" for="<?= $id ?>">Search</label>
        <input class="form-control" id="<?= $id ?>" type="search" name="search" value="<?= esc($term, 'attr') ?>" placeholder="<?= esc($placeholder, 'attr') ?>" maxlength="100">
    </div>
    <div class="cs-filters__actions">
        <button class="btn btn-primary" type="submit"><i class="ti ti-search" aria-hidden="true"></i> Search</button>
        <?php if ($term !== ''): ?>
            <a class="btn btn-ghost" href="<?= esc($action . ($keep ? '?' . http_build_query(array_filter($keep, static fn ($v) => $v !== '' && $v !== null)) : '')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
