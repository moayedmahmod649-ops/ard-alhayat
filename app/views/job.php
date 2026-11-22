<?php $job = $j;
echo page_hero(L($j, 'title'), t('careers'), '/assets/img/careers.webp', [home_crumb(), [t('careers'), url('careers')], [L($j, 'title'), url('careers/' . $j['slug'])]]); ?>
<section class="sec paper-calm">
  <div class="wrap detail-grid">
    <article class="detail-main calm reveal">
      <dl class="job-facts">
        <div><dt><?= e(t('department')) ?></dt><dd><?= bidi(L($j, 'dept') ?: '—') ?></dd></div>
        <?php if (L($j, 'location')): ?><div><dt><?= e(t('location')) ?></dt><dd><?= bidi(L($j, 'location') ) ?></dd></div><?php endif ?>
        <div><dt><?= e(t('job_type')) ?></dt><dd><?= e(job_type_label($j['jtype'])) ?></dd></div>
        <?php if ($j['deadline']): ?><div><dt><?= e(t('deadline')) ?></dt><dd><time datetime="<?= e($j['deadline']) ?>"><?= e(fdate($j['deadline'])) ?></time></dd></div><?php endif ?>
      </dl>
      <div class="prose"><?= L($j, 'body') ?></div>
      <?= share_links(base_url() . url('careers/' . $j['slug']), L($j, 'title')) ?>
    </article>
    <aside class="detail-side"><?php include __DIR__ . '/_apply.php' ?></aside>
  </div>
</section>
