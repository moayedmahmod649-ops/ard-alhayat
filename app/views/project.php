<?php
$gal = gallery_items($p['gallery']);
$others = array_values(array_filter(projects(null, null, 4), fn($x) => $x['id'] !== $p['id']));
$map = safe_url($p['map_url'], false);
echo page_hero(L($p, 'title'), t('projects_kicker'), $p['image'] ?: '/assets/img/irrigation.webp', [home_crumb(), [t('projects'), url('projects')], [L($p, 'title'), url('projects/' . $p['slug'])]], L($p, 'summary'));
?>
<section class="sec">
  <div class="wrap detail-grid">
    <article class="detail-main reveal">
      <div class="prose"><?= L($p, 'body') ?></div>
      <?php if ($gal): ?><h2 class="h3 mt"><?= e(t('detail_gallery')) ?></h2><?php include __DIR__ . '/_gallery_grid.php'; endif ?>
    </article>
    <aside class="detail-side reveal">
      <div class="side-card facts">
        <dl>
          <div><dt><?= e(t('status')) ?></dt><dd><span class="chip status-<?= e($p['status']) ?>"><?= e($p['status'] === 'completed' ? t('completed') : t('ongoing')) ?></span></dd></div>
          <div><dt><?= e(t('sector')) ?></dt><dd><?= e(sector_label($p['sector'])) ?></dd></div>
          <?php if (L($p, 'location')): ?><div><dt><?= e(t('location')) ?></dt><dd><?= bidi(L($p, 'location') ) ?></dd></div><?php endif ?>
          <?php if ($p['year']): ?><div><dt><?= e(t('year')) ?></dt><dd><?= e($p['year']) ?></dd></div><?php endif ?>
        </dl>
        <?php if ($map): ?><a class="btn btn-outline full" href="<?= e($map) ?>" target="_blank" rel="noopener"><?= e(t('view_map')) ?></a><?php endif ?>
      </div>
<?php if ($others): ?>
      <div class="side-card"><h2 class="h4"><?= e(t('other_projects')) ?></h2><ul class="side-links"><?php foreach (array_slice($others, 0, 3) as $o): ?><li><a href="<?= e(url('projects/' . $o['slug'])) ?>"><?= icon('pin') ?><?= bidi(L($o, 'title')) ?></a></li><?php endforeach ?></ul></div>
<?php endif ?>
    </aside>
  </div>
</section>
<?php include __DIR__ . '/_cta.php' ?>
