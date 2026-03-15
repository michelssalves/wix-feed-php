USE controle_funeraria;

CREATE TABLE IF NOT EXISTS themes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  cor_fundo_pagina VARCHAR(7) NOT NULL,
  cor_fundo_formulario VARCHAR(7) NOT NULL,
  cor_fontes_principais VARCHAR(7) NOT NULL,
  cor_bordas VARCHAR(7) NOT NULL,
  cor_botao_enviar VARCHAR(7) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE memorials
  ADD COLUMN theme_id INT UNSIGNED NULL AFTER foto_falecido,
  ADD CONSTRAINT fk_memorials_theme
    FOREIGN KEY (theme_id) REFERENCES themes(id)
    ON DELETE SET NULL;
