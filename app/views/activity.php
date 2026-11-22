<?php
$others = array_values(array_filter(activities($a['sector']), fn($x) => $x['id'] !== $a['id']));
$gal = gallery_items($a['gallery']);
echo page_hero(L($a, 'title'), $a['sector'] === 'livestock' ? t('livestock') : t('agri'), $a['image'] ?: '/assets/img/hay.webp',
  [home_crumb(), [t('activities'), url('activities')], [L($a, 'title'), url('activities/' . $a['slug'])]], L($a, 'summary'));
?>
<section class="sec">
  <div class="wrap detail-grid">
    <article class="detail-main reveal">
      <div class="prose"><?= L($a, 'body') ?></div>
      <?php if ($gal): ?><h2 class="h3 mt"><?= e(t('detail_gallery')) ?></h2><?php include __DIR__ . '/_gallery_grid.php'; endif ?>
    </article>
    <aside class="detail-side reveal">
      <div class="side-card"><dl><dt><?= e(t('sector')) ?></dt><dd><?= e(sector_label($a['sector'])) ?></dd></dl>
        <a class="btn btn-gold full" href="<?= e(url('contact?topic=services')) ?>"><?= e(t('topic_services')) ?></a></div>
<?php if ($others): ?>
      <div class="side-card"><h2 class="h4"><?= e(t('other_acts')) ?></h2><ul class="side-links"><?php foreach ($others as $o): ?><li><a href="<?= e(url('activities/' . $o['slug'])) ?>"><?= bidi(L($o, 'title')) ?></a></li><?php endforeach ?></ul></div>
<?php endif ?>
    </aside>
  </div>
</section>
<?php include __DIR__ . '/_cta.php' ?>
