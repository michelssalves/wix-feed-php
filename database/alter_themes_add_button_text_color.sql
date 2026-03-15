USE controle_funeraria;

ALTER TABLE themes
  ADD COLUMN cor_texto_botao_enviar VARCHAR(7) NOT NULL DEFAULT '#231C09' AFTER cor_botao_enviar;
