USE controle_funeraria;

ALTER TABLE posts
  ADD COLUMN memorial_key VARCHAR(120) NOT NULL DEFAULT 'geral' AFTER id,
  ADD KEY idx_posts_memorial_key (memorial_key);
