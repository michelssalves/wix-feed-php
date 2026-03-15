<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class ThemeService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query(
            'SELECT id, nome, cor_fundo_pagina, cor_fundo_formulario, cor_fontes_principais, cor_bordas, cor_botao_enviar, cor_texto_botao_enviar, criado_em, atualizado_em
             FROM themes
             ORDER BY nome ASC, id DESC'
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, nome, cor_fundo_pagina, cor_fundo_formulario, cor_fontes_principais, cor_bordas, cor_botao_enviar, cor_texto_botao_enviar, criado_em, atualizado_em
             FROM themes
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $theme = $statement->fetch();

        return $theme ?: null;
    }

    public function create(array $data): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO themes (nome, cor_fundo_pagina, cor_fundo_formulario, cor_fontes_principais, cor_bordas, cor_botao_enviar, cor_texto_botao_enviar, criado_em, atualizado_em)
             VALUES (:nome, :cor_fundo_pagina, :cor_fundo_formulario, :cor_fontes_principais, :cor_bordas, :cor_botao_enviar, :cor_texto_botao_enviar, NOW(), NOW())'
        );
        $statement->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE themes
             SET nome = :nome,
                 cor_fundo_pagina = :cor_fundo_pagina,
                 cor_fundo_formulario = :cor_fundo_formulario,
                 cor_fontes_principais = :cor_fontes_principais,
                 cor_bordas = :cor_bordas,
                 cor_botao_enviar = :cor_botao_enviar,
                 cor_texto_botao_enviar = :cor_texto_botao_enviar,
                 atualizado_em = NOW()
             WHERE id = :id'
        );
        $statement->execute($data + ['id' => $id]);

        return $statement->rowCount() > 0;
    }
}
