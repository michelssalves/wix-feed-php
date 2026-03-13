USE controle_funeraria;

ALTER TABLE memorials
  ADD COLUMN foto_falecido VARCHAR(255) NULL AFTER nome_falecido;
