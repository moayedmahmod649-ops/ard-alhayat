<?php echo page_hero(L($pg, 'title'), t('company_short'), '/assets/img/office.webp', [home_crumb(), [L($pg, 'title'), url('p/' . $pg['slug'])]]); ?>
<section class="sec paper-calm"><div class="wrap narrow"><article class="detail-main calm prose reveal"><?= L($pg, 'body') ?></article></div></section>
