<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class MemorialService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function exists(string $memorialKey): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM memorials WHERE memorial_key = :memorial_key LIMIT 1');
        $statement->execute(['memorial_key' => $memorialKey]);
        return (bool) $statement->fetchColumn();
    }

    public function create(string $deceasedName, ?string $photoPath = null): array
    {
        do {
            $memorialKey = $this->generateKey();
        } while ($this->exists($memorialKey));

        $statement = $this->pdo->prepare(
            'INSERT INTO memorials (memorial_key, nome_falecido, foto_falecido, criado_em)
             VALUES (:memorial_key, :nome_falecido, :foto_falecido, NOW())'
        );
        $statement->execute([
            'memorial_key' => $memorialKey,
            'nome_falecido' => $deceasedName,
            'foto_falecido' => $photoPath,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'memorial_key' => $memorialKey,
            'nome_falecido' => $deceasedName,
            'foto_falecido' => $photoPath,
        ];
    }

    public function latest(int $limit = 20, int $offset = 0): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, memorial_key, nome_falecido, foto_falecido, criado_em
             FROM memorials
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM memorials')->fetchColumn();
    }

    private function generateKey(): string
    {
        return (string) random_int(100000, 99999999);
    }
}
