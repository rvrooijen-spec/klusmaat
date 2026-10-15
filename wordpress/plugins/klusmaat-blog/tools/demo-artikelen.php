<?php
/**
 * Demo-artikelen voor een testsite. Draai met: php wpcli.php 'require "<pad>/demo-artikelen.php"; km_demo("<map met stap-1.png ...>");'
 * Niet voor productie.
 */
function km_demo(string $map): void
{
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $term = get_term_by('slug', 'binnen-schilderen', 'klusmaat_klustype');
    $planken = get_term_by('slug', 'planken-en-tv-ophangen', 'klusmaat_klustype');
    $kraan = get_term_by('slug', 'kraan-en-toilet', 'klusmaat_klustype');
    $alts = [
        1 => 'Vloer en meubels afgedekt met folie',
        2 => 'Een plamuurmes strijkt plamuur in een gat in de muur',
        3 => 'Gele afplaktape langs de plint en rond het stopcontact',
        4 => 'Een kwast schildert een brede rand in de hoek van de muur',
        5 => 'Een verfroller maakt een W-beweging over de muur',
        6 => 'Afplaktape wordt schuin van de muur getrokken',
    ];
    $ids = [];
    foreach ($alts as $n => $alt) {
        $tmp = wp_tempnam("stap-$n.png");
        copy("$map/stap-$n.png", $tmp);
        $id = media_handle_sideload(['name' => "muur-schilderen-stap-$n.png", 'tmp_name' => $tmp], 0, null, ['post_title' => "Muur schilderen, stap $n"]);
        $ids[$n] = is_wp_error($id) ? 0 : (int) $id;
    }
    $maak = function (string $titel, string $slug, string $uitleg, string $tekst, array $g, ?WP_Term $t): int {
        $id = wp_insert_post(['post_type' => 'klusmaat_artikel', 'post_status' => 'publish', 'post_title' => $titel, 'post_name' => $slug, 'post_excerpt' => $uitleg, 'post_content' => $tekst]);
        $g = \Klusmaat\Blog\Artikelen::schoon($g);
        update_post_meta($id, '_km_artikel', $g);
        update_post_meta($id, '_km_soort', $g['soort']);
        if ($t) {
            wp_set_object_terms($id, [$t->term_id], 'klusmaat_klustype');
        }
        return (int) $id;
    };
    $stap = static fn (int $n, string $titel, string $tekst) => ['titel' => $titel, 'tekst' => $tekst, 'afbeelding' => $ids[$n] ?? 0, 'alt' => $alts[$n] ?? ''];
    $maak('Een kamer schilderen', 'een-kamer-schilderen', 'Een woonkamer of slaapkamer strak schilderen in een dag of twee, zonder verfspatten op je vloer.',
        '<p>Schilderen is de klus die je het makkelijkst zelf doet. Het meeste werk zit in de voorbereiding: wie goed afdekt en afplakt, hoeft achteraf nauwelijks bij te werken.</p><p>Reken voor een kamer van ongeveer 4 bij 4 meter op een halve dag voorbereiden en een halve tot hele dag schilderen, plus droogtijd tussen de lagen.</p>',
        ['soort' => 'stappenplan', 'moeilijkheid' => 1, 'minuten' => 480, 'kosten' => 'Ongeveer 40 tot 90 euro aan materiaal',
         'gereedschap' => "Verfroller met steel\nKwast van 5 cm\nVerfbak met rooster\nPlamuurmes\nSchuurblok\nTrap of opstapje", 'materiaal' => "Muurverf, 2,5 liter per 12 m2 per laag\nAfdekfolie\nAfplaktape voor binnen\nWandplamuur\nSchuurpapier korrel 120",
         'stappen' => [
            $stap(1, 'Ruim de kamer leeg en dek af', 'Haal alles weg wat kan. Dek de vloer en de meubels die blijven af met folie. Plak de folie vast met tape, zodat er niets kan opwaaien.'),
            $stap(2, 'Vul gaten en schuur de muur', 'Vul gaten van schroeven en spijkers met plamuur. Laat het drogen en schuur het glad. Stofzuig daarna de muur, anders blijft er stof in je verf zitten.'),
            $stap(3, 'Plak af', 'Plak tape langs de plint, de kozijnen en rond stopcontacten. Druk de rand stevig aan met je duim, dan loopt er geen verf onder.'),
            $stap(4, 'Schilder eerst de randen', 'Schilder met de kwast een strook van ongeveer 5 cm langs hoeken, plafond en tape. Dat doe je altijd vóór de roller, anders zie je de rand later terug.'),
            $stap(5, 'Rol de muur', 'Rol de verf in een grote W en strijk daarna zonder druk uit naar boven en beneden. Werk in banen van ongeveer een meter breed en blijf in de natte verf.'),
            $stap(6, 'Laat drogen en trek de tape eraf', 'Trek de tape schuin weg terwijl de verf nog licht nat is, dan krijg je een strakke rand. Laat de eerste laag minstens 4 uur drogen en schilder dan een tweede laag.'),
         ],
         'tips' => "Meng verf uit meerdere bussen in één emmer. Dan zie je geen kleurverschil.\nSchilder niet in direct zonlicht, de verf droogt dan te snel en wordt streperig.\nWas je kwast en roller niet in de gootsteen, maar spoel ze uit in een emmer.",
         'klusser_als' => "Je muren veel scheuren of vocht hebben\nJe plafond of trapgat wilt laten doen\nJe weinig tijd hebt en het liever in één dag klaar hebt"], $term);
    $maak('Planken ophangen: zo blijven ze zitten', 'planken-ophangen', 'Vijf tips voor een plank die recht hangt en niet naar beneden komt.',
        '<p>Een plank ophangen lijkt simpel, maar de meeste planken vallen omdat de plug niet bij de muur past. Dit moet je weten voordat je boort.</p><p>Controleer eerst wat voor muur je hebt. Klop erop: klinkt het hol, dan is het gipsplaat of een lichte wand. Klinkt het vol, dan is het steen of beton.</p>',
        ['soort' => 'tip', 'tips' => "Gebruik bij gipsplaat een speciale plug (hollewandplug) en geen gewone plug.\nZet een kruis met potlood op de plek van het gat voordat je gaat boren.\nBoor eerst een klein gat en kijk of je niets raakt. Gebruik een leidingzoeker als je twijfelt.\nControleer met een waterpas voordat je de laatste schroef aandraait.\nZwaar spul zoals boeken hoort in de stijl van de muur, niet alleen op een plug.",
         'klusser_als' => "Je een zware plank wilt ophangen, boven 15 kilo\nJe niet zeker weet waar leidingen lopen"], $planken);
    $maak('Een druppelende kraan repareren', 'druppelende-kraan-repareren', 'Een kraan die druppelt kost je een nieuw leertje of een nieuwe dop. Meestal ben je er in een half uur klaar mee.',
        '<p>De meeste druppelende kranen hebben geen nieuwe kraan nodig. Meestal is het rubber binnenin versleten. Dat vervang je zelf.</p>',
        ['soort' => 'stappenplan', 'moeilijkheid' => 2, 'minuten' => 45, 'kosten' => 'Ongeveer 5 tot 15 euro',
         'gereedschap' => "Verstelbare sleutel\nPlatte schroevendraaier\nHanddoek", 'materiaal' => "Nieuw kraanleertje of keramische kop (neem de oude mee naar de winkel)",
         'stappen' => [
            ['titel' => 'Draai de water af', 'tekst' => 'Draai de afsluiter onder de kraan dicht. Open daarna de kraan om de druk eruit te laten lopen. Leg een handdoek in de wastafel.', 'afbeelding' => 0, 'alt' => ''],
            ['titel' => 'Haal de kraan uit elkaar', 'tekst' => 'Haal het dopje van de kraan, draai de schroef los en til de kop eraf met de sleutel.', 'afbeelding' => 0, 'alt' => ''],
            ['titel' => 'Vervang het leertje', 'tekst' => 'Haal het oude rubber eruit en leg het nieuwe op dezelfde plek. Zet alles in omgekeerde volgorde terug.', 'afbeelding' => 0, 'alt' => ''],
            ['titel' => 'Draai de water weer aan', 'tekst' => 'Open de afsluiter langzaam en controleer of de kraan nog druppelt.', 'afbeelding' => 0, 'alt' => ''],
         ],
         'tips' => "Maak een foto voordat je iets losdraait, dan weet je hoe het terug moet.\nLet op dat je de afvoer afdekt, kleine onderdelen verdwijnen er snel in.",
         'klusser_als' => "Je de afsluiter niet kunt dichtdraaien\nEr water uit de muur of onder de kraan lekt"], $kraan);
}
