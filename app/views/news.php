<?php
$cats = news_categories();
$catSlug = get('cat'); $cat = null;
foreach ($cats as $c) if ($c['slug'] === $catSlug) $cat = $c;
$qs = mb_substr(get('q'), 0, 80); $tag = mb_substr(get('tag'), 0, 40);
$page = clamp_int(get('page', '1'), 1, 500, 1); $per = 9; $total = 0;
$list = news_list($cat ? (int)$cat['id'] : null, $qs, $tag, $page, $per, $total);
$pages = (int)ceil($total / $per);
$qBase = fn(array $o) => url('news') . (($q = http_build_query(array_filter(array_merge(['cat' => $cat['slug'] ?? '', 'q' => $qs, 'tag' => $tag], $o)))) ? '?' . $q : '');
echo page_hero(t('news'), t('news_kicker'), '/assets/img/harvest.webp', [home_crumb(), [t('news'), url('news')]]);
?>
<section class="sec">
  <div class="wrap">
    <div class="news-tools reveal">
      <nav class="filters" aria-label="<?= e(t('news')) ?>">
        <a href="<?= e($qBase(['cat' => ''])) ?>"<?= !$cat ? ' aria-current="page"' : '' ?>><?= e(t('all')) ?></a>
        <?php foreach ($cats as $c): ?><a href="<?= e($qBase(['cat' => $c['slug'], 'page' => ''])) ?>"<?= $cat && $cat['id'] === $c['id'] ? ' aria-current="page"' : '' ?>><?= bidi(L($c, 'name')) ?></a><?php endforeach ?>
      </nav>
      <form class="search" role="search" method="get" action="<?= e(url('news')) ?>">
        <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat['slug']) ?>"><?php endif ?>
        <label class="sr" for="nq"><?= e(t('search_news')) ?></label>
        <input id="nq" type="search" name="q" value="<?= e($qs) ?>" placeholder="<?= e(t('search_news')) ?>" maxlength="80">
        <button class="btn btn-forest" type="submit"><?= icon('search') ?><span class="sr"><?= e(t('search')) ?></span></button>
      </form>
    </div>
    <?php if ($qs !== '' || $tag !== ''): ?><p class="results-for" role="status"><?= e(t('results_for', $qs !== '' ? $qs : '#' . $tag)) ?> — <?= (int)$total ?></p><?php endif ?>
    <?php if ($list): ?><div class="grid-3"><?php foreach ($list as $n) echo card_news($n) ?></div><?php else: ?><p class="empty"><?= e(t('no_results')) ?></p><?php endif ?>
    <?= pagination($page, $pages, fn($i) => $qBase(['page' => $i > 1 ? $i : ''])) ?>
  </div>
</section>
