<?php
$files = notice_files($n); $d = days_left($n['end_date']);
echo page_hero(L($n, 'title'), notice_kind_label($n['kind']), '/assets/img/office.webp', [home_crumb(), [t('notices'), url('notices')], [L($n, 'title'), url('notices/' . $n['slug'])]]);
?>
<section class="sec paper-calm">
  <div class="wrap detail-grid">
    <article class="detail-main calm reveal">
      <div class="prose"><?= L($n, 'body') ?></div>
<?php if ($files): ?>
      <h2 class="h3 mt"><?= e(t('attachments')) ?></h2>
      <ul class="files"><?php foreach ($files as $f): ?><li><a class="file" href="/files/<?= e($f['token']) ?>" download><?= icon('file') ?><span><strong dir="auto"><?= e($f['orig_name']) ?></strong><small><?= e(strtoupper(pathinfo($f['orig_name'], PATHINFO_EXTENSION))) ?> · <?= e(human_bytes((int)$f['bytes'])) ?></small></span><?= icon('download') ?><span class="sr"><?= e(t('download')) ?></span></a></li><?php endforeach ?></ul>
<?php endif ?>
      <?= share_links(base_url() . url('notices/' . $n['slug']), L($n, 'title')) ?>
    </article>
    <aside class="detail-side reveal">
      <div class="side-card facts">
        <dl>
          <div><dt><?= e(t('status')) ?></dt><dd><?= e(notice_kind_label($n['kind'])) ?></dd></div>
          <?php if ($n['ref_no']): ?><div><dt><?= e(t('ref_no')) ?></dt><dd><bdi><?= e($n['ref_no']) ?></bdi></dd></div><?php endif ?>
          <?php if ($n['start_date']): ?><div><dt><?= e(t('starts')) ?></dt><dd><time datetime="<?= e($n['start_date']) ?>"><?= e(fdate($n['start_date'])) ?></time></dd></div><?php endif ?>
          <?php if ($n['end_date']): ?><div><dt><?= e(t('ends')) ?></dt><dd><time datetime="<?= e($n['end_date']) ?>"><?= e(fdate($n['end_date'])) ?></time><?php if ($d !== null && $d <= 7): ?><span class="soon"><?= e(t('closing_soon')) ?> · <?= e(t('days_left', max(0, $d))) ?></span><?php endif ?></dd></div><?php endif ?>
        </dl>
        <?php if ($n['kind'] === 'job'): ?><a class="btn btn-gold full" href="<?= e(url('careers')) ?>"><?= e(t('careers')) ?></a><?php endif ?>
      </div>
    </aside>
  </div>
</section>
