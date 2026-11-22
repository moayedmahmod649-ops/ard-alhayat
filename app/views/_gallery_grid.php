<?php /** @var array $gal */ if ($gal): ?>
<ul class="photo-grid" data-lightbox-group>
<?php foreach ($gal as $g): $cap = LANG === 'en' ? (($g['en'] ?? '') ?: ($g['ar'] ?? '')) : ($g['ar'] ?? ''); ?>
  <li class="reveal"><a href="<?= e($g['src']) ?>" data-lb="image" data-cap="<?= e($cap) ?>"><img src="<?= e(img_variant($g['src'], 't')) ?>" alt="<?= e($cap) ?>" width="600" height="450" loading="lazy"><span class="zoom"><?= icon('search') ?></span></a><?php if ($cap): ?><span class="cap"><?= bidi($cap) ?></span><?php endif ?></li>
<?php endforeach ?>
</ul>
<?php endif ?>
