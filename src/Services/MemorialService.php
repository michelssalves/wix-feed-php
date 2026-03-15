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

    public function create(string $deceasedName, ?string $photoPath = null, ?int $themeId = null): array
    {
        do {
            $memorialKey = $this->generateKey();
        } while ($this->exists($memorialKey));

        $statement = $this->pdo->prepare(
            'INSERT INTO memorials (memorial_key, nome_falecido, foto_falecido, theme_id, criado_em)
             VALUES (:memorial_key, :nome_falecido, :foto_falecido, :theme_id, NOW())'
        );
        $statement->execute([
            'memorial_key' => $memorialKey,
            'nome_falecido' => $deceasedName,
            'foto_falecido' => $photoPath,
            'theme_id' => $themeId,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'memorial_key' => $memorialKey,
            'nome_falecido' => $deceasedName,
            'foto_falecido' => $photoPath,
            'theme_id' => $themeId,
        ];
    }

    public function latest(int $limit = 20, int $offset = 0): array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.id, m.memorial_key, m.nome_falecido, m.foto_falecido, m.theme_id, m.criado_em, t.nome AS theme_nome
             FROM memorials m
             LEFT JOIN themes t ON t.id = m.theme_id
             ORDER BY m.id DESC
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

    public function findByKey(string $memorialKey): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT m.id, m.memorial_key, m.nome_falecido, m.foto_falecido, m.theme_id, m.criado_em,
                    t.nome AS theme_nome,
                    t.cor_fundo_pagina,
                    t.cor_fundo_formulario,
                    t.cor_fontes_principais,
                    t.cor_bordas,
                    t.cor_botao_enviar,
                    t.cor_texto_botao_enviar
             FROM memorials m
             LEFT JOIN themes t ON t.id = m.theme_id
             WHERE m.memorial_key = :memorial_key
             LIMIT 1'
        );
        $statement->execute(['memorial_key' => $memorialKey]);
        $memorial = $statement->fetch();

        return $memorial ?: null;
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, memorial_key, nome_falecido, foto_falecido, theme_id, criado_em
             FROM memorials
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $memorial = $statement->fetch();

        return $memorial ?: null;
    }

    public function updateById(int $id, string $deceasedName, ?string $photoPath, ?int $themeId): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE memorials
             SET nome_falecido = :nome_falecido,
                 foto_falecido = :foto_falecido,
                 theme_id = :theme_id
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'nome_falecido' => $deceasedName,
            'foto_falecido' => $photoPath ?? $existing['foto_falecido'],
            'theme_id' => $themeId,
        ]);

        return true;
    }

    public function deleteById(int $id): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT memorial_key
             FROM memorials
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $memorialKey = $statement->fetchColumn();

        if (!$memorialKey) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            $deletePosts = $this->pdo->prepare('DELETE FROM posts WHERE memorial_key = :memorial_key');
            $deletePosts->execute(['memorial_key' => $memorialKey]);

            $deleteMemorial = $this->pdo->prepare('DELETE FROM memorials WHERE id = :id');
            $deleteMemorial->execute(['id' => $id]);

            $this->pdo->commit();
            return $deleteMemorial->rowCount() > 0;
        } catch (\Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $throwable;
        }
    }

    private function generateKey(): string
    {
        return (string) random_int(100000, 99999999);
    }
}
