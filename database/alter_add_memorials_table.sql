USE controle_funeraria;

CREATE TABLE IF NOT EXISTS memorials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  memorial_key VARCHAR(120) NOT NULL,
  nome_falecido VARCHAR(160) NOT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_memorials_memorial_key (memorial_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
