<?php
declare(strict_types=1);

namespace Klusmaat\Mail;

/** Bouwt de HTML- en tekstversie van een mail in de huisstijl. Alleen opmaak, geen logica. */
final class Sjabloon
{
    private const INKT = '#161B26';
    private const PAPIER = '#FCFCF9';
    private const BETON = '#E3E6DF';
    private const GEEL = '#F4E8A0';
    private const POTLOOD = '#4B5260';
    private const BALPEN = '#2547B5';
    private const FONT = "Arial, Helvetica, sans-serif";

    /**
     * @param array{kop:string, voorvertoning?:string, alinea?:list<string>, blokken?:list<array{kop:string, rijen:list<array{0:string,1:string}>}>, knop?:array{0:string,1:string}|null, slot?:list<string>, voet?:list<string>, regels?:list<array{0:string,1:string}>} $m
     * @return array{html:string, tekst:string}
     */
    public static function bouw(array $m, string $voettekst): array
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $alinea = static function (string $t) use ($e): string {
            return '<p style="margin:0 0 16px;font:17px/1.55 ' . self::FONT . ';color:' . self::INKT . '">' . nl2br($e($t)) . '</p>';
        };
        $h = '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . $e($m['kop']) . '</title></head>';
        $h .= '<body style="margin:0;padding:0;background:' . self::BETON . '">';
        $h .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:' . self::BETON . '">' . $e($m['voorvertoning'] ?? '') . '&#8199;&#847;&#8199;&#847;</div>';
        $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . self::BETON . '"><tr><td align="center" style="padding:24px 12px">';
        $h .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">';
        $h .= '<tr><td style="padding:0 0 14px 2px;font:900 26px ' . self::FONT . ';letter-spacing:-0.5px;color:' . self::INKT . '">Klusmaat</td></tr>';
        $h .= '<tr><td style="background:' . self::PAPIER . ';padding:28px;border-top:3px solid ' . self::INKT . '">';
        $h .= '<h1 style="margin:0 0 16px;font:900 26px/1.15 ' . self::FONT . ';letter-spacing:-0.5px;color:' . self::INKT . '">' . $e($m['kop']) . '</h1>';
        foreach ($m['alinea'] ?? [] as $a) {
            $h .= $alinea($a);
        }
        foreach ($m['blokken'] ?? [] as $b) {
            $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;background:' . self::GEEL . '"><tr><td style="padding:16px 18px">';
            $h .= '<div style="font:700 15px ' . self::FONT . ';color:' . self::INKT . ';border-bottom:2px solid ' . self::INKT . ';padding-bottom:8px;margin-bottom:8px">' . $e($b['kop']) . '</div>';
            foreach ($b['rijen'] as [$l, $w]) {
                $h .= '<div style="font:16px/1.5 ' . self::FONT . ';color:' . self::INKT . ';padding:3px 0"><span style="color:#3A3F4A">' . $e($l) . ':</span> <strong>' . $e($w) . '</strong></div>';
            }
            $h .= '</td></tr></table>';
        }
        if (!empty($m['knop'])) {
            [$label, $url] = $m['knop'];
            $h .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 20px"><tr><td style="background:' . self::INKT . '"><a href="' . $e($url) . '" style="display:inline-block;padding:15px 24px;font:700 17px ' . self::FONT . ';color:' . self::PAPIER . ';text-decoration:none">' . $e($label) . '</a></td></tr></table>';
        }
        foreach ($m['slot'] ?? [] as $a) {
            $h .= $alinea($a);
        }
        $h .= '</td></tr>';
        $h .= '<tr><td style="padding:16px 4px 0;font:13px/1.5 ' . self::FONT . ';color:' . self::POTLOOD . '">';
        foreach ($m['voet'] ?? [] as $v) {
            $h .= '<p style="margin:0 0 8px">' . $v . '</p>'; // opgemaakte voetregels (bevatten een link) komen uit eigen code
        }
        $h .= '<p style="margin:0">' . $e($voettekst) . '</p></td></tr></table></td></tr></table></body></html>';

        $t = $m['kop'] . "\n" . str_repeat('=', min(60, max(8, strlen($m['kop'])))) . "\n\n";
        foreach ($m['alinea'] ?? [] as $a) {
            $t .= $a . "\n\n";
        }
        foreach ($m['blokken'] ?? [] as $b) {
            $t .= $b['kop'] . "\n";
            foreach ($b['rijen'] as [$l, $w]) {
                $t .= '- ' . $l . ': ' . $w . "\n";
            }
            $t .= "\n";
        }
        if (!empty($m['knop'])) {
            $t .= $m['knop'][0] . ":\n" . $m['knop'][1] . "\n\n";
        }
        foreach ($m['slot'] ?? [] as $a) {
            $t .= $a . "\n\n";
        }
        foreach ($m['regels'] ?? [] as [$l, $u]) {
            $t .= $l . ': ' . $u . "\n";
        }
        $t .= "\n-- \n" . $voettekst . "\n";
        return ['html' => $h, 'tekst' => $t];
    }
}
