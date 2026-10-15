<?php
/**
 * Title: Blog op de homepage
 * Slug: klusmaat/zelf-doen
 * Categories: klusmaat
 */
// Alleen tonen als de child-plugin Klusmaat Blog actief is.
if (!shortcode_exists('klusmaat_artikelen')) {
    return;
}
?>
<!-- wp:group {"className":"km-sectie km-sectie-papier"} -->
<div class="wp-block-group km-sectie km-sectie-papier"><!-- wp:group {"className":"km-breed km-blogblok"} -->
<div class="wp-block-group km-breed km-blogblok"><!-- wp:html -->
<?php echo do_shortcode('[klusmaat_artikelen aantal="3" compact="1" soort="stappenplan" kop="Eerst zelf proberen?"]'); ?>
<?php echo do_shortcode('[klusmaat_artikelen aantal="3" compact="1" soort="tip" kop="Tips van klussers"]'); ?>
<!-- /wp:html --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
