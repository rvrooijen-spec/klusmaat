<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/** Database-laag op PDO. Gebruikt in de tests (SQLite); werkt ook met MySQL. */
final class PdoDatabase implements Database
{
    private int $diepte = 0;

    public function __construct(private \PDO $pdo, private string $voorvoegsel = '')
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    }

    public function tabel(string $naam): string
    {
        return $this->voorvoegsel . 'klusmaat_' . $naam;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->run($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    public function fetchRow(string $sql, array $params = []): ?array
    {
        $rij = $this->run($sql, $params)->fetch();
        return $rij === false ? null : $rij;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $waarde = $this->run($sql, $params)->fetchColumn();
        return $waarde === false ? null : $waarde;
    }

    public function transactional(callable $werk): mixed
    {
        if ($this->diepte > 0) {
            return $werk();
        }
        $this->diepte = 1;
        $this->pdo->beginTransaction();
        try {
            $resultaat = $werk();
            $this->pdo->commit();
            return $resultaat;
        } catch (\Throwable $fout) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $fout;
        } finally {
            $this->diepte = 0;
        }
    }

    private function run(string $sql, array $params): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach (array_values($params) as $i => $waarde) {
                $type = match (true) {
                    is_int($waarde) => \PDO::PARAM_INT,
                    is_null($waarde) => \PDO::PARAM_NULL,
                    is_bool($waarde) => \PDO::PARAM_INT,
                    default => \PDO::PARAM_STR,
                };
                $stmt->bindValue($i + 1, is_bool($waarde) ? (int) $waarde : $waarde, $type);
            }
            $stmt->execute();
            return $stmt;
        } catch (\PDOException $e) {
            $duplicaat = ($e->errorInfo[0] ?? '') === '23000'
                && (($e->errorInfo[1] ?? 0) === 1062 || str_contains($e->getMessage(), 'UNIQUE'));
            throw new DatabaseFout($e->getMessage(), $duplicaat, $e);
        }
    }
}
