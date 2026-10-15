<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/** De inhoud van elk mailtype. Geeft de opbouw terug die Sjabloon opmaakt. */
final class Berichten
{
    /** Vult {variabelen} in. Onbekende variabelen blijven leeg. */
    public static function vul(string $tekst, array $ctx): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', static fn (array $m): string => (string) ($ctx[$m[1]] ?? ''), $tekst);
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array{onderwerp:string, opbouw:array<string,mixed>}
     */
    public static function maak(string $type, array $ctx, string $afmeldUrl = ''): array
    {
        $i = Instellingen::type($type);
        $onderwerp = self::vul($i['onderwerp'], $ctx);
        $intro = self::vul($i['intro'], $ctx);
        $klus = [['Soort klus', (string) ($ctx['klus_soort'] ?? '')], ['Plaats', (string) ($ctx['plaats'] ?? '')], ['Wanneer', (string) ($ctx['wanneer'] ?? '')]];
        $m = ['kop' => $onderwerp, 'voorvertoning' => $intro, 'alinea' => [$intro]];

        switch ($type) {
            case 'klant_bevestiging':
                $m['blokken'] = [['kop' => 'Jouw klus', 'rijen' => $klus]];
                $m['knop'] = ['Bekijk mijn klus', (string) $ctx['link']];
                $m['slot'] = [
                    'Wat gebeurt er nu? Een klusser die je klus oppakt, belt je op het nummer dat je hebt ingevuld. Dat zijn er maximaal ' . ($ctx['max'] ?? 3) . '. Jij kiest met wie je verder gaat.',
                    'Bewaar deze mail. Met de knop hierboven zie je welke klussers je klus hebben opgepakt en sluit je de klus als hij klaar is. We bewaren je link nergens, dus we kunnen hem niet opnieuw sturen.',
                ];
                $m['regels'] = [['Je persoonlijke link', (string) $ctx['link']]];
                break;
            case 'klant_opgepakt':
                $m['blokken'] = [['kop' => 'Jouw klus', 'rijen' => array_merge($klus, [['Klusser', ($ctx['n'] ?? 1) . ' van maximaal ' . ($ctx['max'] ?? 3)]])]];
                $m['slot'] = [
                    'Wie het is, zie je met je persoonlijke link. Die staat in de mail die je kreeg toen je de werkbon invulde.',
                    'Is de klus geregeld? Sluit hem dan met diezelfde link, zodat er geen klussers meer bellen.',
                ];
                break;
            case 'klusser_ontvangen':
                $m['slot'] = ['Zodra je bent goedgekeurd, krijg je een mail met je eerste klussen.'];
                break;
            case 'klusser_goedgekeurd':
                $m['blokken'] = [['kop' => 'Zo werkt het', 'rijen' => [['Je credits', (string) ($ctx['saldo'] ?? 0)], ['Een klus oppakken kost', ($ctx['kosten'] ?? 1) . ' credit']]]];
                $m['knop'] = ['Naar mijn klussen', (string) $ctx['dashboard_url']];
                $m['slot'] = ['Pak alleen klussen op die je echt wilt doen. Bel de klant meteen na het oppakken.'];
                break;
            case 'klusser_afgekeurd':
                $m['slot'] = ['Heb je vragen of denk je dat dit niet klopt? Antwoord dan op deze mail.'];
                break;
            case 'klusser_geblokkeerd':
                $m['slot'] = ['Denk je dat dit niet klopt? Antwoord dan op deze mail, dan kijken we er samen naar.'];
                break;
            case 'klusser_opgepakt':
                $m['blokken'] = [['kop' => 'De klus', 'rijen' => array_merge($klus, [['Je credits', (string) ($ctx['saldo'] ?? 0)]])]];
                $m['knop'] = ['Bekijk de gegevens van de klant', (string) $ctx['dashboard_url']];
                $m['slot'] = ['Om de gegevens van de klant te beschermen, staan ze niet in deze mail maar alleen in je dashboard.'];
                break;
            case 'klusser_nieuwe_klus':
                $m['blokken'] = [['kop' => 'De klus', 'rijen' => array_merge($klus, [['Omschrijving', (string) ($ctx['omschrijving_kort'] ?? '')], ['Plekken vrij', (string) ($ctx['plekken'] ?? '')]])]];
                $m['knop'] = ['Bekijk en pak op', (string) $ctx['dashboard_url']];
                $m['slot'] = ['Maximaal ' . ($ctx['max'] ?? 3) . ' klussers kunnen een klus oppakken. Wie het eerst is, heeft de beste kans.'];
                break;
            case 'klusser_overzicht':
                $blok = [];
                foreach ((array) ($ctx['lijst'] ?? []) as $r) {
                    $blok[] = [$r['klus_soort'] . ' in ' . $r['plaats'], $r['omschrijving_kort'] . ' (' . $r['plekken'] . ' plek(ken) vrij)'];
                }
                $m['blokken'] = [['kop' => 'Nieuwe klussen', 'rijen' => $blok]];
                $m['knop'] = ['Naar mijn klussen', (string) $ctx['dashboard_url']];
                break;
            case 'klusser_credits_laag':
                $m['knop'] = ['Naar mijn klussen', (string) $ctx['dashboard_url']];
                $m['slot'] = ['Credits bijkopen kan binnenkort. Tot die tijd kun je antwoorden op deze mail voor extra credits.'];
                break;
            case 'beheer_nieuwe_klusser':
                $m['knop'] = ['Aanmeldingen bekijken', (string) $ctx['beheer_url']];
                break;
            case 'beheer_nieuwe_klus':
                $m['blokken'] = [['kop' => 'De klus', 'rijen' => $klus]];
                $m['knop'] = ['Klussen bekijken', (string) $ctx['beheer_url']];
                break;
            case 'beheer_controle':
                $m['blokken'] = [['kop' => 'Meldingen', 'rijen' => array_map(static fn ($t) => ['Melding', (string) $t], (array) ($ctx['meldingen'] ?? []))]];
                $m['knop'] = ['Naar het beheer', (string) $ctx['beheer_url']];
                break;
        }
        $voet = [];
        if ($afmeldUrl !== '') {
            $voet[] = 'Je krijgt deze mail omdat je klusser bent bij Klusmaat. <a href="' . esc_url($afmeldUrl) . '" style="color:#2547B5">Pas je meldingen aan of meld je af</a>.';
        }
        $m['voet'] = $voet;
        if ($afmeldUrl !== '') {
            $m['regels'] = array_merge($m['regels'] ?? [], [['Meldingen aanpassen of afmelden', $afmeldUrl]]);
        }
        return ['onderwerp' => $onderwerp, 'opbouw' => $m];
    }

    /** Voorbeeldgegevens voor het voorbeeld en de testmail in het beheer. */
    public static function voorbeeld(string $type): array
    {
        $dash = home_url('/mijn-klussen/');
        return [
            'voornaam' => 'Jan', 'naam' => 'Jan de Vries', 'plaats' => 'Roosendaal', 'klus_soort' => 'Binnen schilderen', 'wanneer' => 'Binnen een week',
            'omschrijving_kort' => 'Woonkamer en gang schilderen, ongeveer 40 m2.', 'link' => home_url('/mijn-klus/?k=voorbeeld'), 'dashboard_url' => $dash,
            'beheer_url' => admin_url('admin.php?page=klusmaat-klussers'), 'saldo' => 5, 'kosten' => 1, 'max' => 3, 'n' => 1, 'plekken' => 2, 'aantal' => 3,
            'meldingen' => ['Voorbeeld: het saldo van klusser 3 klopt niet met de boekingen.'],
            'lijst' => [
                ['klus_soort' => 'Binnen schilderen', 'plaats' => 'Roosendaal', 'omschrijving_kort' => 'Woonkamer en gang schilderen.', 'plekken' => 3],
                ['klus_soort' => 'Lekkage', 'plaats' => 'Bergen op Zoom', 'omschrijving_kort' => 'Lekkage onder de wastafel.', 'plekken' => 2],
                ['klus_soort' => 'Gras en onderhoud', 'plaats' => 'Halsteren', 'omschrijving_kort' => 'Gras maaien, hagen snoeien.', 'plekken' => 3],
            ],
        ];
    }
}
