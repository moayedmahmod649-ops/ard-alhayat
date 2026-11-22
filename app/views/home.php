<?php
$slides = slides();
$about = page_by_slug('about');
$acts = activities(null, true, 6);
$projs = projects(null, null, 3);
$stats = stats_list();
$news = latest_news(3);
$nots = notices(null, 4);
$albums = albums();
$partners = partners_list();
$galleryPics = [];
foreach ($albums as $al) foreach (gallery_items($al['gallery']) as $g) { $galleryPics[] = $g + ['album' => $al]; if (count($galleryPics) >= 5) break 2; }
?>
<section class="hero" aria-roledescription="carousel" aria-label="<?= e(t('company_short')) ?>" data-hero>
  <div class="hero-slides">
<?php foreach ($slides as $i => $s): ?>
    <div class="hero-slide<?= $i === 0 ? ' is-active' : '' ?>" role="group" aria-roledescription="slide" aria-label="<?= e(t('slide_n', ($i + 1) . ' / ' . count($slides))) ?>"<?= $i ? ' aria-hidden="true"' : '' ?>>
      <div class="hero-media"><?= pic($s['image'], '', 'hero-img', 'hero', $i === 0) ?></div>
      <div class="wrap hero-copy">
        
        <?= $i === 0 ? '<h1 class="hero-title">' : '<h2 class="hero-title">' ?><?= bidi(L($s, 'title')) ?><?= $i === 0 ? '</h1>' : '</h2>' ?>
        <?php if (L($s, 'sub') !== ''): ?><p class="hero-sub"><?= bidi(L($s, 'sub')) ?></p><?php endif ?>
        <div class="hero-ctas">
          <?php if ($s['link'] && L($s, 'cta')): ?><a class="btn btn-gold btn-lg ring" href="<?= e(menu_href(safe_url($s['link']) ?: '/')) ?>"<?= $i ? ' tabindex="-1"' : '' ?>><?= e(L($s, 'cta')) ?><?= icon('arrow', 'flip') ?></a><?php endif ?>
          <a class="btn btn-ghost-light btn-lg" href="<?= e(url('about')) ?>"<?= $i ? ' tabindex="-1"' : '' ?>><?= e(t('learn_more')) ?></a>
        </div>
      </div>
      <?= draft_tag($s['image']) ?>
    </div>
<?php endforeach ?>
  </div>
  <div class="hero-frame" aria-hidden="true"></div>
  <div class="hero-inset" aria-hidden="true">
    <img src="/assets/img/olive-t.webp" alt="" width="720" height="480" loading="lazy">
    <span class="inset-cap"><?= e(LANG === 'ar' ? 'الزيتون · القمح · الثروة الحيوانية' : 'Olives · Wheat · Livestock') ?></span>
  </div>
<?php if (count($slides) > 1): ?>
  <div class="wrap hero-ctrl">
    <div class="hero-count" aria-hidden="true"><span class="cur">01</span><span class="sep"></span><span class="tot"><?= sprintf('%02d', count($slides)) ?></span></div>
    <div class="hero-dots" role="group" aria-label="<?= e(t('gallery_short')) ?>">
<?php foreach ($slides as $i => $s): ?>
      <button type="button" class="dot<?= $i === 0 ? ' is-active' : '' ?>" data-go="<?= $i ?>" aria-label="<?= e(t('slide_n', $i + 1)) ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>><span class="bar"><i></i></span></button>
<?php endforeach ?>
    </div>
    <div class="hero-btns">
      <button type="button" class="round" data-prev aria-label="<?= e(t('prev')) ?>"><?= icon('chev', 'flip-r') ?></button>
      <button type="button" class="round" data-next aria-label="<?= e(t('next')) ?>"><?= icon('chev', 'flip') ?></button>
    </div>
  </div>
<?php endif ?>
  <?= furrows('hero-furrows draw') ?>
</section>

<section class="sec about-sec" aria-labelledby="about-h">
  <div class="wrap about-grid">
    <div class="about-copy reveal">
      
      <h2 id="about-h" class="h2"><?= bidi(LANG === 'ar' ? setting('home_about_title_ar', 'جذور في الأرض، ورؤية للمستقبل') : setting('home_about_title_en', 'Rooted in the land, looking to the future')) ?></h2>
      <p class="lead"><?= bidi((LANG === 'ar' ? setting('home_about_ar') : setting('home_about_en')) ?: excerpt(L($about, 'body'), 420)) ?></p>
      <ul class="pillars">
        <li><span class="idx" aria-hidden="true">01</span><div><h3><?= e(t('agri')) ?></h3><p><?= e(LANG === 'ar' ? 'تنمية زراعية مستدامة للأرض والمحاصيل.' : 'Sustainable development of land and crops.') ?></p></div></li>
        <li><span class="idx" aria-hidden="true">02</span><div><h3><?= e(t('livestock')) ?></h3><p><?= e(LANG === 'ar' ? 'إنتاج حيواني يقوم على الجودة والرعاية.' : 'Livestock production built on quality and care.') ?></p></div></li>
      </ul>
      <a class="btn btn-forest" href="<?= e(url('about')) ?>"><?= e(t('learn_more')) ?><?= icon('arrow', 'flip') ?></a>
    </div>
    <div class="about-visual reveal">
      <figure class="stack-a"><img src="/assets/img/road-t.webp" srcset="/assets/img/road-t.webp 720w, /assets/img/road.webp 1600w" sizes="(max-width: 900px) 90vw, 40vw" alt="" width="720" height="480" loading="lazy"></figure>
      <figure class="stack-b"><img src="/assets/img/sheep-t.webp" alt="" width="720" height="480" loading="lazy"></figure>
    </div>
  </div>
</section>

<?php if ($acts): ?>
<section class="sec dark-sec services-sec" aria-labelledby="srv-h">
  <?= furrows('bg-furrows') ?>
  <div class="wrap">
    <div class="sec-head reveal">
      <div><h2 id="srv-h" class="h2 light"><?= e(t('services_title')) ?></h2></div>
      <a class="text-link" href="<?= e(url('activities')) ?>"><?= e(t('view_all')) ?><?= icon('arrow', 'flip') ?></a>
    </div>
    <div class="grid-3 acts-feature"><?php foreach ($acts as $k => $a) echo card_activity($a, $k + 1) ?></div>
  </div>
</section>
<?php endif ?>

<?php if ($stats): ?>
<section class="sec stats-sec" aria-labelledby="stats-h">
  <div class="stats-bg" aria-hidden="true"><img src="/assets/img/hero-pivot-t.webp" alt="" width="720" height="480" loading="lazy"></div>
  <div class="wrap">
    <h2 id="stats-h" class="h3 light center"><?= e(t('stats_kicker')) ?></h2>
    <ul class="stats">
<?php foreach ($stats as $s): ?>
      <li class="stat reveal"><p class="stat-num"><span class="count" data-count="<?= e(preg_replace('/[^0-9]/', '', $s['value'])) ?>"><?= e($s['value']) ?></span><span class="suf"><?= e(L($s, 'suffix')) ?></span></p><p class="stat-label"><?= bidi(L($s, 'label')) ?></p></li>
<?php endforeach ?>
    </ul>
  </div>
</section>
<?php endif ?>

<?php if ($projs): ?>
<section class="sec projects-sec" aria-labelledby="proj-h">
  <div class="wrap">
    <div class="sec-head reveal">
      <div><h2 id="proj-h" class="h2"><?= e(t('projects_title')) ?></h2></div>
      <a class="text-link" href="<?= e(url('projects')) ?>"><?= e(t('view_all')) ?><?= icon('arrow', 'flip') ?></a>
    </div>
    <div class="proj-layout"><?php foreach ($projs as $i => $p) echo card_project($p, $i === 0) ?></div>
  </div>
</section>
<?php endif ?>

<section class="sec news-sec paper-2" aria-labelledby="news-h">
  <div class="wrap news-notices<?= $nots ? '' : ' solo' ?>">
    <div class="news-col">
      <div class="sec-head reveal">
        <div><h2 id="news-h" class="h2"><?= e(t('news')) ?></h2></div>
        <a class="text-link" href="<?= e(url('news')) ?>"><?= e(t('view_all')) ?><?= icon('arrow', 'flip') ?></a>
      </div>
      <div class="grid-3 news-grid"><?php foreach ($news as $n) echo card_news($n) ?></div>
    </div>
<?php if ($nots): ?>
    <aside class="notices-col reveal" aria-labelledby="not-h">
      <div class="notices-box">
        <h2 id="not-h" class="h3"><?= e(t('notices_kicker')) ?></h2>
        <?php foreach ($nots as $n) echo notice_row($n) ?>
        <a class="text-link" href="<?= e(url('notices')) ?>"><?= e(t('view_all')) ?><?= icon('arrow', 'flip') ?></a>
      </div>
    </aside>
<?php endif ?>
  </div>
</section>

<?php if ($galleryPics): ?>
<section class="sec gallery-sec" aria-labelledby="gal-h">
  <div class="wrap">
    <div class="sec-head reveal">
      <div><h2 id="gal-h" class="h2"><?= e(t('gallery')) ?></h2></div>
      <a class="text-link" href="<?= e(url('gallery')) ?>"><?= e(t('view_all')) ?><?= icon('arrow', 'flip') ?></a>
    </div>
    <ul class="mosaic" data-lightbox-group>
<?php foreach ($galleryPics as $i => $g): $cap = LANG === 'en' ? ($g['en'] ?? '') ?: ($g['ar'] ?? '') : ($g['ar'] ?? ''); ?>
      <li class="m<?= $i ?> reveal"><a href="<?= e($g['src']) ?>" data-lb="image" data-cap="<?= e($cap) ?>"><img src="<?= e(img_variant($g['src'], 't')) ?>" alt="<?= e($cap) ?>" width="600" height="450" loading="lazy"></a></li>
<?php endforeach ?>
    </ul>
  </div>
</section>
<?php endif ?>

<?php if ($partners): ?>
<section class="sec partners-sec" aria-labelledby="par-h">
  <div class="wrap">
    <div class="sec-head center reveal"><div><h2 id="par-h" class="h2"><?= e(t('partners')) ?></h2><p class="sec-lede"><?= e(t('partners_sub')) ?></p></div></div>
    <?php include __DIR__ . '/_partners.php' ?>
  </div>
</section>
<?php endif ?>

<?php include __DIR__ . '/_cta.php' ?>
