<section class="cta-band" aria-labelledby="cta-h">
  <div class="cta-media"><img src="/assets/img/hay.webp" alt="" width="1600" height="900" loading="lazy"></div>
  <div class="cta-shade"></div><div class="grain"></div>
  <div class="wrap cta-in reveal">
    <img class="cta-emblem" src="/assets/img/brand-mark-light.webp" alt="" width="160" height="160" loading="lazy">
    <h2 id="cta-h" class="h2 light"><?= e(t('cta_title')) ?></h2>
    <p><?= e(t('cta_body')) ?></p>
    <div class="cta-btns">
      <a class="btn btn-gold btn-lg ring" href="<?= e(url('contact?topic=partner')) ?>"><?= e(t('partner_req')) ?></a>
      <a class="btn btn-ghost-light btn-lg" href="<?= e(url('contact')) ?>"><?= e(t('contact')) ?></a>
    </div>
  </div>
</section>
