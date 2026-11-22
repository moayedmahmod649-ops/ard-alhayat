<?php $als = albums();
echo page_hero(t('gallery'), t('albums'), '/assets/img/palms.webp', [home_crumb(), [t('gallery'), url('gallery')]]); ?>
<section class="sec">
  <div class="wrap">
    <?php if ($als): ?>
    <ul class="albums">
<?php foreach ($als as $al): $n = count(gallery_items($al['gallery'])); $v = count(album_videos($al)); ?>
      <li class="album reveal"><a href="<?= e(url('gallery/' . $al['slug'])) ?>">
        <div class="album-stack"><?= pic($al['image'], '', 'card-img') ?></div>
        <p class="meta"><?= e(t('n_photos', $n)) ?><?php if ($v): ?> · <?= e(t('n_videos', $v)) ?><?php endif ?></p><h2 class="h4"><?= bidi(L($al, 'title')) ?></h2><?php if (L($al, 'desc')): ?><p><?= bidi(L($al, 'desc')) ?></p><?php endif ?></a></li>
<?php endforeach ?>
    </ul>
    <?php else: ?><p class="empty"><?= e(t('no_results')) ?></p><?php endif ?>
  </div>
</section>
