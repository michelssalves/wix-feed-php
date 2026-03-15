CREATE DATABASE IF NOT EXISTS controle_funeraria
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE controle_funeraria;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  google_id VARCHAR(191) NOT NULL,
  foto_url VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_users_email (email),
  UNIQUE KEY uk_users_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memorial_key VARCHAR(120) NOT NULL,
  user_id INT UNSIGNED NULL,
  nome_autor VARCHAR(120) NOT NULL,
  foto_autor VARCHAR(255) NULL,
  texto TEXT NOT NULL,
  imagem VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_posts_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  KEY idx_posts_memorial_key (memorial_key),
  KEY idx_posts_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  nome_autor VARCHAR(120) NOT NULL,
  foto_autor VARCHAR(255) NULL,
  texto TEXT NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_post
    FOREIGN KEY (post_id) REFERENCES posts(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_comments_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  KEY idx_comments_post (post_id),
  KEY idx_comments_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
