<?php $gal = gallery_items($al['gallery']); $vids = album_videos($al);
echo page_hero(L($al, 'title'), t('gallery'), $al['image'] ?: '/assets/img/palms.webp', [home_crumb(), [t('gallery'), url('gallery')], [L($al, 'title'), url('gallery/' . $al['slug'])]], L($al, 'desc')); ?>
<section class="sec">
  <div class="wrap">
    <?php if ($gal): ?><h2 class="h3"><?= e(t('photos')) ?></h2><?php include __DIR__ . '/_gallery_grid.php'; endif ?>
<?php if ($vids): ?>
    <h2 class="h3 mt"><?= e(t('videos')) ?></h2>
    <p class="muted small"><?= e(t('video_ext')) ?></p>
    <ul class="photo-grid videos" data-lightbox-group>
<?php foreach ($vids as $v): $cap = LANG === 'en' ? $v['cap_en'] : $v['cap_ar']; ?>
      <li class="reveal"><a href="<?= e($v['url']) ?>" data-lb="video" data-embed="<?= e($v['embed']) ?>" data-cap="<?= e($cap) ?>">
        <?php if ($v['thumb']): ?><img src="<?= e($v['thumb']) ?>" alt="<?= e($cap) ?>" width="480" height="360" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><span class="vid-ph"><?= icon('video') ?></span><?php endif ?>
        <span class="play"><?= icon('play') ?><span class="sr"><?= e(t('play_video')) ?></span></span></a><?php if ($cap): ?><span class="cap"><?= bidi($cap) ?></span><?php endif ?></li>
<?php endforeach ?>
    </ul>
<?php endif ?>
    <?php if (!$gal && !$vids): ?><p class="empty"><?= e(t('no_results')) ?></p><?php endif ?>
  </div>
</section>
