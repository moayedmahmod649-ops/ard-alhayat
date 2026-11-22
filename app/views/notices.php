<?php
$kind = in_array(get('type'), ['announcement', 'tender', 'job'], true) ? get('type') : null;
$list = notices($kind);
echo page_hero(t('notices'), t('notices_kicker'), '/assets/img/office.webp', [home_crumb(), [t('notices'), url('notices')]],
  LANG === 'ar' ? 'إعلانات رسمية وفرص عمل ومناقصات، تُخفى تلقائيًا بعد انتهاء موعدها.' : 'Official announcements, job opportunities and tenders — hidden automatically after their closing date.');
?>
<section class="sec paper-calm">
  <div class="wrap narrow">
    <nav class="filters reveal" aria-label="<?= e(t('notices')) ?>">
      <a href="<?= e(url('notices')) ?>"<?= !$kind ? ' aria-current="page"' : '' ?>><?= e(t('all')) ?></a>
      <a href="<?= e(url('notices?type=announcement')) ?>"<?= $kind === 'announcement' ? ' aria-current="page"' : '' ?>><?= e(t('announcement')) ?></a>
      <a href="<?= e(url('notices?type=tender')) ?>"<?= $kind === 'tender' ? ' aria-current="page"' : '' ?>><?= e(t('tender')) ?></a>
      <a href="<?= e(url('notices?type=job')) ?>"<?= $kind === 'job' ? ' aria-current="page"' : '' ?>><?= e(t('job_notice')) ?></a>
    </nav>
    <?php if ($list): ?><div class="notice-list"><?php foreach ($list as $n) echo notice_row($n) ?></div><?php else: ?><p class="empty"><?= e(t('no_notices')) ?></p><?php endif ?>
  </div>
</section>
