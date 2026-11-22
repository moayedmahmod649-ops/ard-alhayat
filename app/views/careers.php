<?php $jobs = jobs_open(); $job = null;
echo page_hero(t('careers'), t('open_jobs'), '/assets/img/careers.webp', [home_crumb(), [t('careers'), url('careers')]],
  LANG === 'ar' ? 'انضم إلى فريق أرض الحياة. اطّلع على الوظائف الشاغرة وقدّم طلبك مع سيرتك الذاتية.' : 'Join the Ard Al-Hayat team. Browse open positions and apply with your CV.'); ?>
<section class="sec paper-calm">
  <div class="wrap detail-grid">
    <div class="detail-main">
      <h2 class="h3 reveal"><?= e(t('open_jobs')) ?></h2>
<?php if ($jobs): ?>
      <ul class="jobs">
<?php foreach ($jobs as $j): ?>
        <li class="job reveal"><a href="<?= e(url('careers/' . $j['slug'])) ?>"><div><h3><?= bidi(L($j, 'title')) ?></h3>
          <p class="meta"><?= bidi(L($j, 'dept') ?: '—') ?> · <?php if (L($j, 'location')): ?><?= bidi(L($j, 'location')) ?> · <?php endif ?><?= e(job_type_label($j['jtype'])) ?></p>
          <?php if ($j['deadline']): ?><p class="meta"><?= e(t('deadline')) ?>: <time datetime="<?= e($j['deadline']) ?>"><?= e(fdate($j['deadline'])) ?></time></p><?php endif ?></div>
          <span class="btn btn-outline"><?= e(t('apply')) ?><?= icon('arrow', 'flip') ?></span></a></li>
<?php endforeach ?>
      </ul>
<?php else: ?><p class="empty"><?= e(t('no_jobs')) ?></p><?php endif ?>
    </div>
    <aside class="detail-side"><?php include __DIR__ . '/_apply.php' ?></aside>
  </div>
</section>
