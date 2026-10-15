<?php
/**
 * Title: Hero met werkbon
 * Slug: klusmaat/hero
 * Categories: klusmaat
 */
?>
<!-- wp:group {"className":"km-hero"} -->
<div class="wp-block-group km-hero"><!-- wp:group {"className":"km-hero-tekst"} -->
<div class="wp-block-group km-hero-tekst"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Zet de klus op papier.</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"km-inleiding"} -->
<p class="km-inleiding">Vul de werkbon in. Goedgekeurde klussers uit Roosendaal, Bergen op Zoom en omgeving pakken hem op. Maximaal drie.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"className":"km-hulp"} -->
<p class="km-hulp">Gratis voor jou. Geen account nodig.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="km-stapel"><div class="km-bon">
  <div class="km-bon-kop"><h2>Werkbon</h2><span>Doorslag voor jou en de klusser</span></div>
  <div><label class="km-label" for="km-plaats">Plaats van de klus</label>
    <select id="km-plaats"><option>Roosendaal</option><option>Bergen op Zoom</option><option>Halsteren</option><option>Wouw</option></select></div>
  <div class="km-rijen"><h3 class="km-rijen-kop">Wat moet er gebeuren?</h3>
    <a class="km-rij" href="/werkbon/?klus=schilderwerk"><span class="km-vakje"></span><span class="km-rij-tekst"><span class="km-rij-naam">Schilderwerk</span><span class="km-rij-hulp">Binnen, buiten, behang en plamuren</span></span></a>
    <a class="km-rij" href="/werkbon/?klus=loodgieterswerk"><span class="km-vakje"></span><span class="km-rij-tekst"><span class="km-rij-naam">Loodgieterswerk</span><span class="km-rij-hulp">Lekkage, kraan, afvoer en sanitair</span></span></a>
    <a class="km-rij" href="/werkbon/?klus=tuinwerk"><span class="km-vakje"></span><span class="km-rij-tekst"><span class="km-rij-naam">Tuinwerk</span><span class="km-rij-hulp">Snoeien, bestraten, gras en hagen</span></span></a>
    <a class="km-rij" href="/werkbon/?klus=elektra"><span class="km-vakje"></span><span class="km-rij-tekst"><span class="km-rij-naam">Elektra</span><span class="km-rij-hulp">Stopcontacten, lampen en groepenkast</span></span></a>
    <a class="km-rij" href="/werkbon/?klus=montage"><span class="km-vakje"></span><span class="km-rij-tekst"><span class="km-rij-naam">Montage</span><span class="km-rij-hulp">Meubels, kasten en keukens in elkaar</span></span></a>
    <a class="km-rij-balk" href="/werkbon/"><span>Een andere klus kiezen</span><span aria-hidden="true">Alle klussen</span></a>
  </div>
</div></div>
<!-- /wp:html --></div>
<!-- /wp:group -->
