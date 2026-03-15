USE controle_funeraria;

CREATE TABLE IF NOT EXISTS themes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  cor_fundo_pagina VARCHAR(7) NOT NULL,
  cor_fundo_formulario VARCHAR(7) NOT NULL,
  cor_fontes_principais VARCHAR(7) NOT NULL,
  cor_bordas VARCHAR(7) NOT NULL,
  cor_botao_enviar VARCHAR(7) NOT NULL,
  cor_texto_botao_enviar VARCHAR(7) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS memorials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memorial_key VARCHAR(120) NOT NULL,
  nome_falecido VARCHAR(160) NOT NULL,
  foto_falecido VARCHAR(255) NULL,
  theme_id INT UNSIGNED NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_memorials_theme
    FOREIGN KEY (theme_id) REFERENCES themes(id)
    ON DELETE SET NULL,
  UNIQUE KEY uk_memorials_memorial_key (memorial_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
