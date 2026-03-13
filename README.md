# Wix Feed PHP

Aplicacao simples em PHP puro + MySQL para rodar sozinha ou dentro de um iframe no Wix, com postagens em feed, comentarios por post, nome manual ou login com Google e upload de imagem opcional com limite de 2 MB.

## Estrutura

```text
wix-feed-php/
├── api/
├── database/
├── public/
│   ├── assets/
│   └── uploads/
├── src/
│   ├── Config/
│   ├── Services/
│   └── Support/
├── composer.json
└── README.md
```

## Configuracao local

1. Copie `src/Config/config.example.php` para `src/Config/config.php`.
2. Ajuste `app_url`, MySQL e `google.client_id`.
3. Execute [`database/schema.sql`](D:\Projetos\wix-feed-php\database\schema.sql).
4. Opcional, mas recomendado para validar token Google com a biblioteca oficial:

```bash
composer install
```

5. Garanta permissao de escrita em `public/uploads`.
6. Em Wamp, publique a pasta `public` em `C:\wamp64\www\wix-feed-php` ou crie um alias/junction para ela.
7. Se usar o servidor embutido do PHP em vez do Wamp:

```bash
php -S localhost:8000 -t public
```

## Google Login

1. Crie um OAuth Client Web no Google Cloud.
2. Cadastre a origem do app, por exemplo `http://localhost:8000`.
3. Cole o Client ID em `src/Config/config.php`.

## Wix e iframe

- Use HTTPS em producao.
- Defina `session.secure => true` em producao.
- Em iframe, o navegador pode bloquear cookies third-party. Se isso acontecer, o login Google pode falhar, mas o modo manual continua funcionando.
- Se o container do iframe permitir, use `allow="identity-credentials-get; clipboard-write"`.

## Endpoints

- `GET /api/feed.php`
- `POST /api/google-login.php`
- `POST /api/logout.php`
- `POST /api/create-post.php`
- `POST /api/create-comment.php`

## Validacoes

- nome obrigatorio quando nao houver login Google
- postagem obrigatoria
- comentario obrigatorio
- upload somente imagem
- validacao por extensao e MIME type
- limite de 2 MB por imagem
- feed do mais recente para o mais antigo
