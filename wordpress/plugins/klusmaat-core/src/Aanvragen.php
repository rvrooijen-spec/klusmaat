<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Klus-aanvragen. Twee regels staan hier centraal:
 * 1. De persoonlijke link van de klant bestaat alleen als hash in de database.
 * 2. Gegevens van de klant komen pas uit de database als de klusser de klus heeft opgepakt.
 */
final class Aanvragen
{
    /** Velden die elke goedgekeurde klusser mag zien. */
    private const OPENBAAR = 'id, klustype_id, plaats_id, omschrijving, gewenst_moment, status, max_klussers, opgepakt_aantal, aangemaakt_op';
    /** Velden die pas na het oppakken zichtbaar zijn. */
    private const CONTACT = 'naam, email, telefoon, adres, postcode';

    public function __construct(
        private Database $db,
        private Config $config,
        private Logboek $logboek,
        private ?Catalogus $catalogus = null,
    ) {
    }

    /**
     * Plaatst een klus. Geeft het id en de persoonlijke link-sleutel terug. Die sleutel
     * wordt nergens bewaard en is daarna niet meer op te vragen.
     *
     * @param array<string,mixed> $invoer
     * @return array{id:int, token:string}
     */
    public function plaats(array $invoer): array
    {
        $schoon = $this->valideer($invoer);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $id = $this->db->insert(
            'INSERT INTO ' . $this->db->tabel('aanvragen') . ' (token_hash, klustype_id, plaats_id, omschrijving, gewenst_moment, naam, email, telefoon, adres, postcode, status, max_klussers, opgepakt_aantal, aangemaakt_op) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)',
            [
                self::hashToken($token), $schoon['klustype_id'], $schoon['plaats_id'], $schoon['omschrijving'], $schoon['gewenst_moment'],
                $schoon['naam'], $schoon['email'], $schoon['telefoon'], $schoon['adres'], $schoon['postcode'],
                'open', $this->config->maxKlussersPerKlus, gmdate('Y-m-d H:i:s'),
            ]
        );
        $this->logboek->schrijf('klant', 0, 'klus_geplaatst', 'aanvraag', $id, ['plaats_id' => $schoon['plaats_id'], 'klustype_id' => $schoon['klustype_id']]);
        return ['id' => $id, 'token' => $token];
    }

    /**
     * Wat de klant ziet via zijn link. Null bij een onbekende link.
     *
     * @return array<string,mixed>|null
     */
    public function voorKlant(string $token): ?array
    {
        $rij = $this->db->fetchRow(
            'SELECT ' . self::OPENBAAR . ', ' . self::CONTACT . ', gesloten_op, geanonimiseerd_op FROM ' . $this->db->tabel('aanvragen') . ' WHERE token_hash = ?',
            [self::hashToken($token)]
        );
        if ($rij === null) {
            return null;
        }
        $rij['klussers'] = $this->db->fetchAll(
            'SELECT user_id, aangemaakt_op FROM ' . $this->db->tabel('ontgrendelingen') . ' WHERE aanvraag_id = ? ORDER BY id',
            [(int) $rij['id']]
        );
        return $rij;
    }

    /** De klant sluit zijn eigen klus. Geeft waar als de klus nu gesloten is. */
    public function sluitenMetLink(string $token): bool
    {
        $rij = $this->db->fetchRow('SELECT id FROM ' . $this->db->tabel('aanvragen') . ' WHERE token_hash = ?', [self::hashToken($token)]);
        if ($rij === null) {
            throw new KlusmaatFout(KlusmaatFout::NIET_GEVONDEN, 'Deze klus is niet gevonden.');
        }
        return $this->sluiten((int) $rij['id'], 'klant', 0);
    }

    public function sluitenDoorBeheerder(int $aanvraagId, int $beheerderId): bool
    {
        return $this->sluiten($aanvraagId, 'beheerder', $beheerderId);
    }

    /**
     * Wat een goedgekeurde klusser van één klus mag zien. Zonder oppakken: alleen de
     * openbare velden. Na oppakken ook de gegevens van de klant.
     *
     * @return array<string,mixed>
     */
    public function voorKlusser(int $aanvraagId, int $userId): array
    {
        $this->eisGoedgekeurd($userId);
        $rij = $this->db->fetchRow('SELECT ' . self::OPENBAAR . ' FROM ' . $this->db->tabel('aanvragen') . ' WHERE id = ?', [$aanvraagId]);
        if ($rij === null) {
            throw new KlusmaatFout(KlusmaatFout::NIET_GEVONDEN, 'Deze klus is niet gevonden.');
        }
        $rij = $this->metPlekken($rij);
        $rij['opgepakt'] = $this->isOpgepakt($aanvraagId, $userId);
        if ($rij['opgepakt']) {
            $rij += $this->db->fetchRow('SELECT ' . self::CONTACT . ' FROM ' . $this->db->tabel('aanvragen') . ' WHERE id = ?', [$aanvraagId]) ?? [];
        }
        return $rij;
    }

    /**
     * Open klussen die voor deze klusser interessant zijn: in zijn plaatsen en vakgebieden en met
     * een vrije plek. Alleen openbare velden, nooit gegevens van de klant.
     *
     * @param list<int> $plaatsIds
     * @param list<int> $klustypeIds
     * @return list<array<string,mixed>>
     */
    public function lijstVoorKlusser(int $userId, array $plaatsIds, array $klustypeIds): array
    {
        $this->eisGoedgekeurd($userId);
        if ($plaatsIds === [] || $klustypeIds === []) {
            return [];
        }
        $plaatsen = implode(',', array_fill(0, count($plaatsIds), '?'));
        $typen = implode(',', array_fill(0, count($klustypeIds), '?'));
        $rijen = $this->db->fetchAll(
            'SELECT ' . self::OPENBAAR . ' FROM ' . $this->db->tabel('aanvragen')
            . " WHERE status = 'open' AND opgepakt_aantal < max_klussers AND plaats_id IN ({$plaatsen}) AND klustype_id IN ({$typen})"
            . ' AND NOT EXISTS (SELECT 1 FROM ' . $this->db->tabel('ontgrendelingen') . ' o WHERE o.aanvraag_id = ' . $this->db->tabel('aanvragen') . '.id AND o.user_id = ?)'
            . ' ORDER BY id DESC',
            [...array_map('intval', $plaatsIds), ...array_map('intval', $klustypeIds), $userId]
        );
        return array_map(fn (array $r): array => $this->metPlekken($r), $rijen);
    }

    /**
     * De klussen die deze klusser heeft opgepakt, met gegevens van de klant.
     *
     * @return list<array<string,mixed>>
     */
    public function mijnKlussen(int $userId): array
    {
        $this->eisGoedgekeurd($userId);
        $a = $this->db->tabel('aanvragen');
        $o = $this->db->tabel('ontgrendelingen');
        $cols = implode(', ', array_map(fn (string $c): string => "a.{$c}", array_map('trim', explode(',', self::OPENBAAR . ',' . self::CONTACT))));
        $rijen = $this->db->fetchAll("SELECT {$cols}, o.aangemaakt_op AS opgepakt_op FROM {$o} o JOIN {$a} a ON a.id = o.aanvraag_id WHERE o.user_id = ? ORDER BY o.id DESC", [$userId]);
        return array_map(fn (array $r): array => $this->metPlekken($r), $rijen);
    }

    /**
     * Alles van een klus, voor de beheerder. De aanroeper moet zelf controleren dat de gebruiker
     * beheerder is.
     *
     * @return array<string,mixed>|null
     */
    public function volledig(int $aanvraagId): ?array
    {
        return $this->db->fetchRow('SELECT ' . self::OPENBAAR . ', ' . self::CONTACT . ' FROM ' . $this->db->tabel('aanvragen') . ' WHERE id = ?', [$aanvraagId]);
    }

    /**
     * Overzicht voor het beheerscherm. De aanroeper moet zelf controleren dat de gebruiker beheerder is.
     *
     * @return array{rijen:list<array<string,mixed>>, totaal:int}
     */
    public function overzichtBeheer(?string $status, int $limiet, int $start): array
    {
        $a = $this->db->tabel('aanvragen');
        $waar = $status !== null ? ' WHERE status = ?' : '';
        $params = $status !== null ? [$status] : [];
        $totaal = (int) $this->db->fetchValue("SELECT COUNT(*) FROM {$a}{$waar}", $params);
        $rijen = $this->db->fetchAll(
            'SELECT ' . self::OPENBAAR . ', ' . self::CONTACT . ", gesloten_op, geanonimiseerd_op FROM {$a}{$waar} ORDER BY id DESC LIMIT " . max(1, $limiet) . ' OFFSET ' . max(0, $start),
            $params
        );
        return ['rijen' => $rijen, 'totaal' => $totaal];
    }

    /**
     * Wist de gegevens van de klant bij gesloten klussen die al lang dicht zijn. De klus zelf (soort,
     * plaats, omschrijving) blijft staan voor de cijfers. Geeft het aantal geanonimiseerde klussen terug.
     */
    public function anonimiseerOudeKlussen(int $dagen): int
    {
        if ($dagen < 1) {
            throw new KlusmaatFout(KlusmaatFout::ONGELDIG, 'Kies minstens 1 dag.');
        }
        $grens = gmdate('Y-m-d H:i:s', time() - $dagen * 86400);
        $nu = gmdate('Y-m-d H:i:s');
        return (int) $this->db->transactional(function () use ($grens, $nu, $dagen): int {
            $aantal = $this->db->execute(
                'UPDATE ' . $this->db->tabel('aanvragen') . " SET naam = '', email = '', telefoon = '', adres = '', postcode = '', omschrijving = '', geanonimiseerd_op = ? WHERE status = 'gesloten' AND geanonimiseerd_op IS NULL AND gesloten_op IS NOT NULL AND gesloten_op < ?",
                [$nu, $grens]
            );
            if ($aantal > 0) {
                $this->logboek->schrijf('systeem', 0, 'klussen_geanonimiseerd', 'aanvraag', 0, ['aantal' => $aantal, 'dagen' => $dagen]);
            }
            return $aantal;
        });
    }

    /**
     * Wist de gegevens van de klant bij alle klussen met dit e-mailadres (verzoek tot verwijdering).
     * Geeft het aantal klussen terug. Open klussen worden eerst gesloten.
     */
    public function wisGegevensVanEmail(string $email, int $beheerderId): int
    {
        $email = trim($email);
        if ($email === '') {
            throw new KlusmaatFout(KlusmaatFout::ONGELDIG, 'Vul een e-mailadres in.');
        }
        return (int) $this->db->transactional(function () use ($email, $beheerderId): int {
            $nu = gmdate('Y-m-d H:i:s');
            $aantal = $this->db->execute(
                'UPDATE ' . $this->db->tabel('aanvragen') . " SET naam = '', email = '', telefoon = '', adres = '', postcode = '', omschrijving = '', status = 'gesloten', gesloten_op = COALESCE(gesloten_op, ?), geanonimiseerd_op = ? WHERE email = ? AND geanonimiseerd_op IS NULL",
                [$nu, $nu, $email]
            );
            $this->logboek->schrijf('beheerder', $beheerderId, 'gegevens_gewist_op_verzoek', 'aanvraag', 0, ['aantal' => $aantal]);
            return $aantal;
        });
    }

    /**
     * Alle gegevens die bij dit e-mailadres horen, voor een verzoek tot inzage.
     *
     * @return list<array<string,mixed>>
     */
    public function gegevensVanEmail(string $email): array
    {
        return $this->db->fetchAll(
            'SELECT id, klustype_id, plaats_id, omschrijving, gewenst_moment, naam, email, telefoon, adres, postcode, status, aangemaakt_op, gesloten_op FROM ' . $this->db->tabel('aanvragen') . ' WHERE email = ? ORDER BY id',
            [trim($email)]
        );
    }

    /** Staan er telefoonnummers, e-mailadressen of lange cijferreeksen in de tekst? */
    private function bevatContactgegevens(string $tekst): bool
    {
        if (preg_match('/\S+@\S+/u', $tekst) === 1) {
            return true;
        }
        return preg_match('/(?:\d[\s\-.]?){9,}/', $tekst) === 1;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function sluiten(int $aanvraagId, string $actorType, int $actorId): bool
    {
        return (bool) $this->db->transactional(function () use ($aanvraagId, $actorType, $actorId): bool {
            $geraakt = $this->db->execute('UPDATE ' . $this->db->tabel('aanvragen') . " SET status = 'gesloten', gesloten_op = ? WHERE id = ? AND status = 'open'", [gmdate('Y-m-d H:i:s'), $aanvraagId]);
            if ($geraakt === 1) {
                $this->logboek->schrijf($actorType, $actorId, 'klus_gesloten', 'aanvraag', $aanvraagId);
            }
            return $geraakt === 1;
        });
    }

    private function eisGoedgekeurd(int $userId): void
    {
        $status = $this->db->fetchValue('SELECT status FROM ' . $this->db->tabel('klussers') . ' WHERE user_id = ?', [$userId]);
        if ($status !== 'goedgekeurd') {
            throw new KlusmaatFout(KlusmaatFout::NIET_GOEDGEKEURD, 'Je account is nog niet goedgekeurd.');
        }
    }

    private function isOpgepakt(int $aanvraagId, int $userId): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM ' . $this->db->tabel('ontgrendelingen') . ' WHERE aanvraag_id = ? AND user_id = ?', [$aanvraagId, $userId]) > 0;
    }

    /** @param array<string,mixed> $rij */
    private function metPlekken(array $rij): array
    {
        $rij['plekken_vrij'] = max(0, (int) $rij['max_klussers'] - (int) $rij['opgepakt_aantal']);
        return $rij;
    }

    /**
     * @param array<string,mixed> $invoer
     * @return array<string,mixed>
     */
    private function valideer(array $invoer): array
    {
        $fouten = [];
        $tekst = fn (string $k): string => trim((string) ($invoer[$k] ?? ''));
        $lengte = fn (string $s): int => mb_strlen($s);

        $klustype = (int) ($invoer['klustype_id'] ?? 0);
        $plaats = (int) ($invoer['plaats_id'] ?? 0);
        if ($klustype < 1 || ($this->catalogus !== null && !$this->catalogus->klustypeBestaat($klustype))) {
            $fouten['klustype_id'] = 'Kies welke soort klus het is.';
        }
        if ($plaats < 1 || ($this->catalogus !== null && !$this->catalogus->plaatsBestaat($plaats))) {
            $fouten['plaats_id'] = 'Kies de plaats waar de klus gedaan moet worden.';
        }

        $omschrijving = $tekst('omschrijving');
        if ($lengte($omschrijving) < 10) {
            $fouten['omschrijving'] = 'Vertel wat er moet gebeuren, in minstens 10 tekens.';
        } elseif ($lengte($omschrijving) > 2000) {
            $fouten['omschrijving'] = 'Houd de omschrijving onder de 2000 tekens.';
        } elseif ($this->bevatContactgegevens($omschrijving)) {
            $fouten['omschrijving'] = 'Zet hier geen telefoonnummer of e-mailadres in. Die vul je op het volgende blad in; klussers zien ze pas als ze je klus oppakken.';
        }

        $moment = $tekst('gewenst_moment');
        if (!in_array($moment, Config::MOMENTEN, true)) {
            $fouten['gewenst_moment'] = 'Kies wanneer de klus klaar moet zijn.';
        }

        $naam = $tekst('naam');
        if ($lengte($naam) < 2 || $lengte($naam) > 120) {
            $fouten['naam'] = 'Vul je naam in.';
        }

        $email = $tekst('email');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $lengte($email) > 190) {
            $fouten['email'] = 'Vul een geldig e-mailadres in.';
        }

        $telefoon = $tekst('telefoon');
        $cijfers = preg_replace('/\D/', '', $telefoon) ?? '';
        if (preg_match('/^[0-9+\-\s()]{8,20}$/', $telefoon) !== 1 || strlen($cijfers) < 9 || strlen($cijfers) > 13) {
            $fouten['telefoon'] = 'Vul een geldig telefoonnummer in.';
        }

        $adres = $tekst('adres');
        if ($lengte($adres) < 3 || $lengte($adres) > 190) {
            $fouten['adres'] = 'Vul je straat en huisnummer in.';
        }

        $postcode = strtoupper(preg_replace('/\s+/', '', $tekst('postcode')) ?? '');
        if (preg_match('/^[1-9][0-9]{3}[A-Z]{2}$/', $postcode) !== 1) {
            $fouten['postcode'] = 'Vul een geldige postcode in, bijvoorbeeld 4701 AB.';
        } else {
            $postcode = substr($postcode, 0, 4) . ' ' . substr($postcode, 4);
        }

        if ($fouten !== []) {
            throw new KlusmaatFout(KlusmaatFout::ONGELDIG, 'Controleer de gemarkeerde velden.', $fouten);
        }
        return [
            'klustype_id' => $klustype, 'plaats_id' => $plaats, 'omschrijving' => $omschrijving, 'gewenst_moment' => $moment,
            'naam' => $naam, 'email' => $email, 'telefoon' => $telefoon, 'adres' => $adres, 'postcode' => $postcode,
        ];
    }
}
