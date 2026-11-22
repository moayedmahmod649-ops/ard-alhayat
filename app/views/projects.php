<?php
$st = in_array(get('status'), ['completed', 'ongoing'], true) ? get('status') : null;
$list = projects($st);
echo page_hero(t('projects'), t('projects_kicker'), '/assets/img/irrigation.webp', [home_crumb(), [t('projects'), url('projects')]],
  LANG === 'ar' ? 'المشاريع المنفّذة والجارية مع الصور والتفاصيل والمواقع.' : 'Completed and ongoing projects with photos, details and locations.');
?>
<section class="sec">
  <div class="wrap">
    <nav class="filters reveal" aria-label="<?= e(t('status')) ?>">
      <a href="<?= e(url('projects')) ?>"<?= !$st ? ' aria-current="page"' : '' ?>><?= e(t('all')) ?></a>
      <a href="<?= e(url('projects?status=ongoing')) ?>"<?= $st === 'ongoing' ? ' aria-current="page"' : '' ?>><?= e(t('ongoing')) ?></a>
      <a href="<?= e(url('projects?status=completed')) ?>"<?= $st === 'completed' ? ' aria-current="page"' : '' ?>><?= e(t('completed')) ?></a>
    </nav>
    <?php if ($list): ?><div class="grid-3"><?php foreach ($list as $p) echo card_project($p) ?></div><?php elseif (get('status') !== ''): ?><p class="empty"><?= e(t('no_results')) ?></p><?php else: ?><div class="empty empty-rich"><p><?= e(t('projects_empty')) ?></p><a class="btn btn-forest" href="<?= e(url('activities')) ?>"><?= e(t('activities')) ?><?= icon('arrow', 'flip') ?></a></div><?php endif ?>
  </div>
</section>
<?php include __DIR__ . '/_cta.php' ?>
