<?php
/**
 * Title: Zelf doen
 * Slug: klusmaat/zelf-doen
 * Categories: klusmaat
 */
if (!shortcode_exists('klusmaat_artikelen')) {
    return;
}
?>
<!-- wp:group {"className":"km-sectie km-sectie-papier"} -->
<div class="wp-block-group km-sectie km-sectie-papier"><!-- wp:group {"className":"km-breed"} -->
<div class="wp-block-group km-breed"><!-- wp:shortcode -->
[klusmaat_artikelen aantal="3" compact="1"]
<!-- /wp:shortcode --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
