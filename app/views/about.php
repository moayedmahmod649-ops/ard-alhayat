<?php
$vision = page_by_slug('vision'); $mission = page_by_slug('mission');
$values = values_list(); $tree = org_tree(); $team = team_list(); $partners = partners_list();
function org_branch(array $tree, int $pid, int $depth = 0): string {
    if (empty($tree[$pid]) || $depth > 5) return '';
    $h = '<ul' . ($depth === 0 ? ' class="org-tree"' : '') . '>';
    foreach ($tree[$pid] as $u) $h .= '<li><span class="org-node d' . min($depth, 3) . '">' . bidi(L($u, 'name')) . '</span>' . org_branch($tree, (int)$u['id'], $depth + 1) . '</li>';
    return $h . '</ul>';
}
echo page_hero(t('about'), t('company_short'), '/assets/img/road.webp', [home_crumb(), [t('about'), url('about')]], t('company'));
?>
<section class="sec" aria-labelledby="intro-h">
  <div class="wrap about-grid">
    <div class="reveal">
      
      <h2 id="intro-h" class="h2"><?= bidi(L($pg, 'title')) ?></h2>
      <div class="prose"><?= L($pg, 'body') ?></div>
    </div>
    <div class="about-visual reveal">
      <figure class="stack-a"><img src="/assets/img/harvest-t.webp" srcset="/assets/img/harvest-t.webp 720w, /assets/img/harvest.webp 1600w" sizes="(max-width: 900px) 90vw, 40vw" alt="" width="720" height="480" loading="lazy"></figure>
      <figure class="stack-b"><img src="/assets/img/dairy-t.webp" alt="" width="720" height="480" loading="lazy"></figure>
    </div>
  </div>
</section>
<section class="sec dark-sec vm-sec" aria-label="<?= e(t('vision') . ' / ' . t('mission')) ?>">
  <?= furrows('bg-furrows') ?>
  <div class="wrap vm-grid">
<?php foreach ([[$vision, 'eye', t('vision')], [$mission, 'target', t('mission')]] as [$row, $ic, $lab]): if (!$row) continue; ?>
    <article class="vm-card reveal"><h2 class="h3 light"><?= e($lab) ?></h2><div class="prose light"><?= L($row, 'body') ?></div></article>
<?php endforeach ?>
  </div>
</section>
<?php if ($values): ?>
<section class="sec" aria-labelledby="val-h">
  <div class="wrap">
    <div class="sec-head center reveal"><div><h2 id="val-h" class="h2"><?= e(LANG === 'ar' ? 'القيم التي نعمل بها' : 'The values we work by') ?></h2></div></div>
    <ul class="values"><?php foreach ($values as $vi => $v): ?><li class="value reveal"><span class="idx" aria-hidden="true"><?= sprintf('%02d', $vi + 1) ?></span><h3><?= bidi(L($v, 'title')) ?></h3><p><?= bidi(L($v, 'body')) ?></p></li><?php endforeach ?></ul>
  </div>
</section>
<?php endif ?>
<?php if (!empty($tree[0])): ?>
<section class="sec paper-2" aria-labelledby="org-h">
  <div class="wrap">
    <div class="sec-head center reveal"><div><h2 id="org-h" class="h2"><?= e(t('org')) ?></h2></div></div>
    <div class="org-wrap reveal"><?= org_branch($tree, 0) ?></div>
  </div>
</section>
<?php endif ?>
<?php if ($team): ?>
<section class="sec" aria-labelledby="team-h">
  <div class="wrap">
    <div class="sec-head center reveal"><div><h2 id="team-h" class="h2"><?= e(t('team')) ?></h2></div></div>
    <ul class="team">
<?php foreach ($team as $m): ?>
      <li class="member reveal"><div class="member-ph"><?php if ($m['image']): ?><img src="<?= e(img_variant($m['image'], 't')) ?>" alt="<?= e(L($m, 'name')) ?>" width="400" height="400" loading="lazy"><?php else: ?><span></span><?php endif ?></div>
        <h3><?= bidi(L($m, 'name')) ?></h3><p class="role"><?= bidi(L($m, 'role')) ?></p><?php if (L($m, 'bio')): ?><p><?= bidi(L($m, 'bio')) ?></p><?php endif ?></li>
<?php endforeach ?>
    </ul>
  </div>
</section>
<?php endif ?>
<?php if ($partners): ?>
<section class="sec partners-sec paper-2" aria-labelledby="par-h"><div class="wrap">
  <div class="sec-head center reveal"><div><h2 id="par-h" class="h2"><?= e(t('partners')) ?></h2><p class="sec-lede"><?= e(t('partners_sub')) ?></p></div></div>
  <?php include __DIR__ . '/_partners.php' ?>
</div></section>
<?php endif ?>
<?php include __DIR__ . '/_cta.php' ?>
