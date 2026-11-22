<?php echo page_hero(t('activities'), t('company_short'), '/assets/img/hay.webp', [home_crumb(), [t('activities'), url('activities')]],
  LANG === 'ar' ? 'صفحات تفصيلية لأنشطة الشركة الزراعية وأنشطة الإنتاج الحيواني.' : 'Detailed pages for the company\'s agricultural and livestock activities.'); ?>
<?php foreach (['agri' => ['wheat', t('agri')], 'livestock' => ['cow', t('livestock')]] as $sec => [$ic, $label]): $list = activities($sec); if (!$list) continue; ?>
<section class="sec<?= $sec === 'livestock' ? ' paper-2' : '' ?>" aria-labelledby="h-<?= $sec ?>">
  <div class="wrap">
    <div class="sec-head reveal"><div><h2 id="h-<?= $sec ?>" class="h2"><?= e($label) ?></h2></div></div>
    <div class="acts-list"><?php foreach ($list as $k => $a) echo card_activity($a, $k + 1) ?></div>
  </div>
</section>
<?php endforeach ?>
<?php include __DIR__ . '/_cta.php' ?>
