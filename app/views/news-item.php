<?php
$abs = base_url() . url('news/' . $n['slug']);
$tags = array_values(array_filter(array_map('trim', explode(',', $n['tags']))));
$rel = related_news($n);
$cat = $n['cat_ar'] ? (LANG === 'en' && $n['cat_en'] ? $n['cat_en'] : $n['cat_ar']) : '';
?>
<article class="article">
  <header class="art-head">
    <div class="art-media"><?= pic($n['image'], '', 'phero-img', 'hero', true) ?></div><div class="phero-shade"></div><div class="grain"></div>
    <div class="wrap art-head-in">
      <p class="art-cat"><?= $cat ? bidi($cat) : e(t('news_short')) ?><?= $n['status'] === 'archived' ? ' · ' . e(t('archived')) : '' ?></p>
      <h1 class="phero-title"><?= bidi(L($n, 'title')) ?></h1>
      <p class="art-meta"><time datetime="<?= e(substr($n['publish_at'], 0, 10)) ?>"><?= e(fdate($n['publish_at'])) ?></time></p>
      <?= crumbs([home_crumb(), [t('news'), url('news')], [L($n, 'title'), url('news/' . $n['slug'])]]) ?>
    </div>
    <?= draft_tag($n['image']) ?><?= furrows('phero-furrows') ?>
  </header>
  <div class="wrap art-body">
    <div class="art-main reveal">
      <?php if (L($n, 'excerpt')): ?><p class="lead"><?= bidi(L($n, 'excerpt')) ?></p><?php endif ?>
      <div class="prose"><?= L($n, 'body') ?></div>
      <?php if ($tags): ?><p class="tags"><span><?= e(t('tags')) ?>:</span> <?php foreach ($tags as $tg): ?><a href="<?= e(url('news?tag=' . rawurlencode($tg))) ?>">#<?= bidi($tg) ?></a> <?php endforeach ?></p><?php endif ?>
      <?= share_links($abs, L($n, 'title')) ?>
    </div>
<?php if ($rel): ?>
    <aside class="art-side reveal" aria-labelledby="rel-h"><h2 id="rel-h" class="h4"><?= e(t('related')) ?></h2>
      <ul class="side-news"><?php foreach ($rel as $r): ?><li><a href="<?= e(url('news/' . $r['slug'])) ?>"><img src="<?= e(img_variant($r['image'], 't')) ?>" alt="" width="120" height="80" loading="lazy"><span><strong><?= bidi(L($r, 'title')) ?></strong><time datetime="<?= e(substr($r['publish_at'], 0, 10)) ?>"><?= e(fdate($r['publish_at'])) ?></time></span></a></li><?php endforeach ?></ul>
    </aside>
<?php endif ?>
  </div>
</article>
