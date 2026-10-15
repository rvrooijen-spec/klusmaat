<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/**
 * Alle mailtypes. Onderwerp en introductietekst zijn door de beheerder aan te passen.
 * Variabelen tussen accolades worden ingevuld: {voornaam}, {plaats}, {klus_soort}, {aantal}, enzovoort.
 */
final class Typen
{
    public const KLANT = 'klant';
    public const KLUSSER = 'klusser';
    public const BEHEER = 'beheer';

    /** @return array<string,array{doelgroep:string,naam:string,uitleg:string,aan:bool,onderwerp:string,intro:string,variabelen:string}> */
    public static function alle(): array
    {
        return [
            'klant_bevestiging' => [
                'doelgroep' => self::KLANT, 'naam' => 'Werkbon ontvangen', 'aan' => true,
                'uitleg' => 'Direct na het plaatsen. Bevat de persoonlijke link; dit is de enige mail waarin die staat, want de link wordt nergens bewaard.',
                'onderwerp' => 'Je werkbon staat klaar. Bewaar deze mail.',
                'intro' => 'Bedankt, {voornaam}. Je klus staat op de werkbon. Goedgekeurde klussers uit {plaats} kunnen hem nu oppakken.',
                'variabelen' => '{voornaam} {plaats} {klus_soort}',
            ],
            'klant_opgepakt' => [
                'doelgroep' => self::KLANT, 'naam' => 'Klusser heeft je klus opgepakt', 'aan' => true,
                'uitleg' => 'Bij elke klusser die de klus oppakt. Zonder naam of gegevens van de klusser: die staan achter de persoonlijke link.',
                'onderwerp' => 'Een klusser heeft je klus opgepakt',
                'intro' => 'Goed nieuws, {voornaam}. Een klusser uit de buurt heeft je klus ({klus_soort} in {plaats}) opgepakt en belt je op het nummer dat je hebt ingevuld.',
                'variabelen' => '{voornaam} {plaats} {klus_soort} {n} {max}',
            ],
            'klusser_ontvangen' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Aanmelding ontvangen', 'aan' => true,
                'uitleg' => 'Direct na het aanmelden als klusser.',
                'onderwerp' => 'We hebben je aanmelding ontvangen',
                'intro' => 'Welkom bij Klusmaat, {voornaam}. We bekijken je aanmelding zo snel mogelijk. Je hoort van ons zodra je bent goedgekeurd.',
                'variabelen' => '{voornaam}',
            ],
            'klusser_goedgekeurd' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Klusser goedgekeurd', 'aan' => true,
                'uitleg' => 'Zodra de beheerder een klusser goedkeurt.',
                'onderwerp' => 'Je bent goedgekeurd. Dit zijn je eerste klussen.',
                'intro' => 'Je bent goedgekeurd, {voornaam}. Je ziet nu de klussen in jouw regio en je kunt ze oppakken.',
                'variabelen' => '{voornaam} {saldo} {kosten}',
            ],
            'klusser_afgekeurd' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Klusser afgekeurd', 'aan' => true,
                'uitleg' => 'Als de beheerder een aanmelding afkeurt. Houd de tekst vriendelijk en kort.',
                'onderwerp' => 'Over je aanmelding bij Klusmaat',
                'intro' => 'Bedankt voor je aanmelding, {voornaam}. We kunnen je op dit moment helaas niet toelaten als klusser.',
                'variabelen' => '{voornaam}',
            ],
            'klusser_geblokkeerd' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Klusser geblokkeerd', 'aan' => true,
                'uitleg' => 'Als de beheerder een klusser blokkeert.',
                'onderwerp' => 'Je account bij Klusmaat is geblokkeerd',
                'intro' => 'Je account bij Klusmaat is geblokkeerd, {voornaam}. Je kunt geen klussen meer oppakken.',
                'variabelen' => '{voornaam}',
            ],
            'klusser_opgepakt' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Klus opgepakt (bevestiging)', 'aan' => true,
                'uitleg' => 'Bevestiging voor de klusser. Bevat bewust geen gegevens van de klant: die staan alleen in het dashboard.',
                'onderwerp' => 'Je hebt een klus opgepakt: bel de klant',
                'intro' => 'Je hebt een klus opgepakt, {voornaam}. De gegevens van de klant staan in je dashboard. Bel snel: wie het eerst belt, krijgt de klus meestal.',
                'variabelen' => '{voornaam} {plaats} {klus_soort} {saldo}',
            ],
            'klusser_nieuwe_klus' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Nieuwe klus in jouw regio', 'aan' => true,
                'uitleg' => 'Melding aan klussers met die plaats en soort klus. De klusser kiest zelf: direct, dagelijks overzicht of geen.',
                'onderwerp' => 'Nieuwe klus in {plaats}: {klus_soort}',
                'intro' => 'Er is een nieuwe klus in jouw regio, {voornaam}.',
                'variabelen' => '{voornaam} {plaats} {klus_soort} {plekken}',
            ],
            'klusser_overzicht' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Dagelijks overzicht nieuwe klussen', 'aan' => true,
                'uitleg' => 'Eén mail per dag met alle nieuwe klussen, voor klussers die geen directe meldingen willen.',
                'onderwerp' => '{aantal} nieuwe klussen in jouw regio',
                'intro' => 'Dit zijn de nieuwe klussen sinds gisteren, {voornaam}.',
                'variabelen' => '{voornaam} {aantal}',
            ],
            'klusser_credits_laag' => [
                'doelgroep' => self::KLUSSER, 'naam' => 'Weinig credits over', 'aan' => true,
                'uitleg' => 'Als het saldo na het oppakken van een klus op of onder de drempel komt.',
                'onderwerp' => 'Je hebt nog {saldo} credit(s) over',
                'intro' => 'Je saldo is laag, {voornaam}: je hebt nog {saldo} credit(s). Met 0 credits kun je geen klussen meer oppakken.',
                'variabelen' => '{voornaam} {saldo}',
            ],
            'beheer_nieuwe_klusser' => [
                'doelgroep' => self::BEHEER, 'naam' => 'Nieuwe klusser wacht op goedkeuring', 'aan' => true,
                'uitleg' => 'Naar het beheeradres, zodat aanmeldingen niet blijven liggen.',
                'onderwerp' => 'Nieuwe klusser wacht op goedkeuring: {naam}',
                'intro' => '{naam} heeft zich aangemeld als klusser en wacht op goedkeuring.',
                'variabelen' => '{naam}',
            ],
            'beheer_nieuwe_klus' => [
                'doelgroep' => self::BEHEER, 'naam' => 'Nieuwe klus geplaatst', 'aan' => false,
                'uitleg' => 'Elke nieuwe werkbon naar het beheeradres. Uit bij het starten; zet aan als je mee wilt kijken. Zonder gegevens van de klant.',
                'onderwerp' => 'Nieuwe klus: {klus_soort} in {plaats}',
                'intro' => 'Er is een nieuwe klus geplaatst: {klus_soort} in {plaats}.',
                'variabelen' => '{plaats} {klus_soort}',
            ],
            'beheer_controle' => [
                'doelgroep' => self::BEHEER, 'naam' => 'Gegevenscontrole vond iets', 'aan' => true,
                'uitleg' => 'Als de dagelijkse controle van credits en logboek een afwijking vindt. Zet deze altijd aan.',
                'onderwerp' => 'Klusmaat: de gegevenscontrole vond iets',
                'intro' => 'De dagelijkse controle van Klusmaat vond een afwijking.',
                'variabelen' => '',
            ],
        ];
    }

    public static function doelgroepNaam(string $d): string
    {
        return ['klant' => 'Klant', 'klusser' => 'Klusser', 'beheer' => 'Beheer'][$d] ?? $d;
    }
}
