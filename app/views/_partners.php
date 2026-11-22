<div class="logo-row" data-marquee>
  <ul class="logo-strip">
<?php foreach ($partners as $p): $nm = L($p, 'name'); $u = safe_url($p['url'], false); ?>
    <li class="logo-tile"><?= $u ? '<a href="' . e($u) . '" target="_blank" rel="noopener" aria-label="' . e($nm) . '">' : '<div>' ?>
      <?php if ($p['image']): ?><img src="<?= e($p['image']) ?>" alt="<?= e($nm) ?>" width="160" height="100" loading="lazy"><?php else: ?><span class="logo-ph"><span><?= bidi($nm) ?></span></span><?php endif ?>
      <?= $u ? '</a>' : '</div>' ?></li>
<?php endforeach ?>
  </ul>
</div>
