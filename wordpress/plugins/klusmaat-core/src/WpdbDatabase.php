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
        $this->wpdb->suppress_errors(true);
        $r = $this->wpdb->query($this->voorbereid($sql, $params));
        $this->controleer($r);
        return (int) $r;
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->execute($sql, $params);
        return (int) $this->wpdb->insert_id;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $this->wpdb->suppress_errors(true);
        $r = $this->wpdb->get_results($this->voorbereid($sql, $params), ARRAY_A);
        $this->controleer($r);
        return $r ?? [];
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        $this->wpdb->suppress_errors(true);
        $r = $this->wpdb->get_row($this->voorbereid($sql, $params), ARRAY_A);
        $this->controleer($r);
        return $r ?: null;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $this->wpdb->suppress_errors(true);
        $r = $this->wpdb->get_var($this->voorbereid($sql, $params));
        $this->controleer($r);
        return $r;
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

    /** Zet "?" om naar %d/%s via wpdb::prepare, op basis van het type van de waarde. */
    private function voorbereid(string $sql, array $params): string
    {
        if ($params === []) {
            return $sql;
        }
        $i = 0;
        $sql = preg_replace_callback('/\?/', static function () use (&$i, $params): string {
            $v = $params[$i++] ?? null;
            return is_int($v) ? '%d' : '%s';
        }, $sql);
        return $this->wpdb->prepare($sql, ...array_map(static fn($v) => is_bool($v) ? (int) $v : $v, array_values($params)));
    }

    private function controleer(mixed $resultaat): void
    {
        if ($this->wpdb->last_error === '') {
            return;
        }
        $fout = $this->wpdb->last_error;
        $duplicaat = stripos($fout, 'duplicate entry') !== false;
        $this->wpdb->last_error = '';
        throw new DatabaseFout($fout, $duplicaat);
    }
}
