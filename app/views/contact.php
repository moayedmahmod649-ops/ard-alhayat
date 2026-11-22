<?php
$topic = old('topic') ?: (in_array(get('topic'), ['general', 'partner', 'services', 'invest'], true) ? get('topic') : 'general');
$mapUrl = setting('map_embed');
$mapOk = (bool)preg_match('#^https://www\.google\.com/maps/embed\?[A-Za-z0-9!%._~=&;:+,\-]+$#', $mapUrl);
echo page_hero(t('contact'), t('contact_title'), '/assets/img/contact.webp', [home_crumb(), [t('contact'), url('contact')]], t('cta_body'));
?>
<section class="sec paper-calm">
  <div class="wrap contact-grid">
    <div class="contact-cards">
<?php
$addr = LANG === 'ar' ? setting('address_ar') : setting('address_en'); $ph = setting('phone'); $em = setting('email'); $hrs = LANG === 'ar' ? setting('hours_ar') : setting('hours_en');
?>
<?php if ($addr || $ph || $em || $hrs): ?>
      <ul class="cinfo reveal">
<?php if ($addr): ?>        <li><?= icon('pin') ?><div><h2 class="h5"><?= e(t('address')) ?></h2><p><?= bidi($addr) ?></p></div></li>
<?php endif; if ($ph): ?>        <li><?= icon('phone') ?><div><h2 class="h5"><?= e(t('phone')) ?></h2><p><a dir="ltr" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ph)) ?>"><?= e($ph) ?></a></p></div></li>
<?php endif; if ($em): ?>        <li><?= icon('mail') ?><div><h2 class="h5"><?= e(t('email')) ?></h2><p><a dir="ltr" href="mailto:<?= e($em) ?>"><?= e($em) ?></a></p></div></li>
<?php endif; if ($hrs): ?>        <li><?= icon('clock') ?><div><h2 class="h5"><?= e(t('hours')) ?></h2><p><?= bidi($hrs) ?></p></div></li>
<?php endif ?>      </ul>
<?php else: ?>
      <figure class="contact-visual reveal"><?= pic('/assets/img/office.webp', '', 'contact-visual-img') ?><figcaption><strong><?= e(t('company_short')) ?></strong><span><?= e(t('cta_body')) ?></span></figcaption></figure>
<?php endif ?>
      <?php if ($soc = social_links()): ?><div class="reveal"><h2 class="h5"><?= e(t('follow')) ?></h2><?= $soc ?></div><?php endif ?>
<?php if ($mapOk): ?>
      <div class="map-box reveal" aria-label="<?= e(t('map')) ?>">
        <button type="button" class="map-load" data-map="<?= e($mapUrl) ?>" data-title="<?= e(t('map')) ?>"><?= icon('pin') ?><span><?= e(t('map_load')) ?></span><small><?= e(t('map_note')) ?></small></button>
      </div>
<?php endif ?>
    </div>
    <section class="form-card reveal" id="form" aria-labelledby="cf-h">
      <h2 id="cf-h" class="h3"><?= e(t('contact_title')) ?></h2>
      <?php if (get('sent')): ?><div class="alert alert-ok" role="status" tabindex="-1" data-focus><?= icon('check') ?><?= e(t('msg_ok')) ?></div><?php endif ?>
      <?= form_errors() ?>
      <form method="post" action="<?= e(url('contact')) ?>#form" class="form" novalidate>
        <input type="hidden" name="_ft" value="<?= e(form_token('contact')) ?>">
        <div class="hp" aria-hidden="true"><label>website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset class="topics"><legend><?= e(t('topic')) ?></legend>
<?php foreach (['general' => 'topic_general', 'partner' => 'topic_partner', 'services' => 'topic_services', 'invest' => 'topic_invest'] as $k => $lab): ?>
          <label class="topic"><input type="radio" name="topic" value="<?= $k ?>"<?= $topic === $k ? ' checked' : '' ?>><span><?= e(t($lab)) ?></span></label>
<?php endforeach ?>
        </fieldset>
        <div class="row2">
          <div class="field"><label for="c-name"><?= e(t('full_name')) ?> <span class="req">*</span></label><input id="c-name" name="name" required maxlength="120" autocomplete="name" value="<?= e(old('name')) ?>"></div>
          <div class="field"><label for="c-email"><?= e(t('email')) ?> <span class="req">*</span></label><input id="c-email" type="email" name="email" required maxlength="190" autocomplete="email" dir="ltr" value="<?= e(old('email')) ?>"></div>
        </div>
        <div class="row2">
          <div class="field"><label for="c-phone"><?= e(t('phone')) ?> <small>(<?= e(t('optional')) ?>)</small></label><input id="c-phone" type="tel" name="phone" maxlength="40" autocomplete="tel" dir="ltr" value="<?= e(old('phone')) ?>"></div>
          <div class="field"><label for="c-org"><?= e(t('organisation')) ?> <small>(<?= e(t('optional')) ?>)</small></label><input id="c-org" name="organisation" maxlength="160" autocomplete="organization" value="<?= e(old('organisation')) ?>"></div>
        </div>
        <div class="field"><label for="c-sub"><?= e(t('subject')) ?> <small>(<?= e(t('optional')) ?>)</small></label><input id="c-sub" name="subject" maxlength="200" value="<?= e(old('subject')) ?>"></div>
        <div class="field"><label for="c-msg"><?= e(t('message')) ?> <span class="req">*</span></label><textarea id="c-msg" name="message" rows="6" required maxlength="5000"><?= e(old('message')) ?></textarea></div>
        <button class="btn btn-gold btn-lg" type="submit"><?= e(t('send')) ?></button>
      </form>
    </section>
  </div>
</section>
