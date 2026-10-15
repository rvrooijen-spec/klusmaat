<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Productie-adapter op $wpdb. NOG NIET GETEST tegen echte MySQL/WordPress (niet beschikbaar in de
 * bouwomgeving); de kern zelf is wel getest op SQLite. Eerst op staging draaien.
 */
final class WpdbDatabase implements Database
{
    private int $diepte = 0;

    public function __construct(private \wpdb $wpdb)
    {
    }

    public function execute(string $sql, array $params = []): int
    {
        return (int) $this->stil(function () use ($sql, $params) {
            $r = $this->wpdb->query($this->voorbereid($sql, $params));
            $this->controleer();
            return $r;
        });
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->wpdb->insert_id;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->stil(function () use ($sql, $params) {
            $r = $this->wpdb->get_results($this->voorbereid($sql, $params), ARRAY_A);
            $this->controleer();
            return $r ?? [];
        });
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        return $this->stil(function () use ($sql, $params) {
            $r = $this->wpdb->get_row($this->voorbereid($sql, $params), ARRAY_A);
            $this->controleer();
            return $r ?: null;
        });
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        return $this->stil(function () use ($sql, $params) {
            $r = $this->wpdb->get_var($this->voorbereid($sql, $params));
            $this->controleer();
            return $r;
        });
    }

    public function transactional(callable $werk): mixed
    {
        if ($this->diepte > 0) {
            return $werk();
        }
        $this->diepte = 1;
        $this->wpdb->query('START TRANSACTION');
        try {
            $uit = $werk();
            $this->wpdb->query('COMMIT');
            return $uit;
        } catch (\Throwable $e) {
            $this->wpdb->query('ROLLBACK');
            throw $e;
        } finally {
            $this->diepte = 0;
        }
    }

    public function tabel(string $naam): string
    {
        return $this->wpdb->prefix . 'klusmaat_' . $naam;
    }

    /** Zet databasefouten uit het scherm en geeft de vorige instelling terug na afloop. */
    private function stil(callable $werk): mixed
    {
        $vorige = $this->wpdb->suppress_errors(true);
        try {
            return $werk();
        } finally {
            $this->wpdb->suppress_errors($vorige);
        }
    }

    /**
     * Zet "?" om naar %d of %s en laat wpdb::prepare de waarden veilig invoegen. Een null-waarde wordt
     * een echte NULL: anders maakt prepare er een lege tekst van en weigert MySQL die in een getalkolom.
     */
    private function voorbereid(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }
        $waarden = [];
        $i = 0;
        $sql = preg_replace_callback('/\?/', static function () use (&$i, $params, &$waarden): string {
            if (!array_key_exists($i, $params)) {
                throw new DatabaseFout('Te weinig waarden voor de query.');
            }
            $v = $params[$i++];
            if ($v === null) {
                return 'NULL';
            }
            $waarden[] = is_bool($v) ? (int) $v : $v;
            return is_int($v) || is_bool($v) ? '%d' : '%s';
        }, $sql);
        if ($i !== count($params)) {
            throw new DatabaseFout('Te veel waarden voor de query.');
        }
        return $waarden === [] ? $sql : $this->wpdb->prepare($sql, ...$waarden);
    }

    private function controleer(): void
    {
        if ($this->wpdb->last_error === '') {
            return;
        }
        $fout = $this->wpdb->last_error;
        $duplicaat = stripos($fout, 'duplicate entry') !== false || stripos($fout, 'unique constraint') !== false;
        $this->wpdb->last_error = '';
        throw new DatabaseFout($fout, $duplicaat);
    }
}
