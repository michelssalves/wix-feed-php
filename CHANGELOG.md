# Changelog

Este arquivo consolida a evolucao do projeto de forma legivel, ja que os primeiros commits ficaram com mensagens muito curtas.

## [Unreleased]

### Changed
- Espaco reservado para as proximas entregas, correcoes e ajustes visuais do projeto.
- Reestruturado o formulario de criacao de memorial em secoes mais claras, com melhor hierarquia visual, agrupamento de cores, preview mais integrado e CTA final mais evidente.
- Ajustado o fuso horario da conexao MySQL para respeitar a timezone configurada na aplicacao, evitando gravacao em UTC quando o painel da hospedagem estiver diferente.
- Refinada a tela de gerenciamento de temas com abas mais fortes, cards mais organizados, preview melhor integrado e acoes de edicao com visual de painel administrativo premium.
- Adicionado suporte a cor do texto do botao nos temas visuais, com aplicacao real no preview e no mural renderizado.

### Added
- Edicao inline de comentarios para o autor logado com Google.
- Exclusao de comentarios para o autor logado com Google.
- Endpoints dedicados para atualizar e excluir comentarios com validacao de propriedade.
- Edicao e exclusao da propria postagem para o autor logado com Google.
- Opcao para excluir memorial criado na tela de listagem, removendo tambem posts e comentarios vinculados.
- Estrutura de temas visuais reutilizaveis para memoriais.
- Selecao de tema existente ou criacao de novo tema no cadastro do memorial.
- Edicao posterior dos temas cadastrados.
- Aplicacao automatica do tema do memorial no mural publico.
- Abas para alternar entre memoriais cadastrados e temas cadastrados.
- Preview visual ao vivo para o novo tema antes de salvar.
- Edicao de memorial diretamente pela listagem.
- Mini preview de cores na listagem de temas.

## [0.3.0] - 2026-03-14

### Memorials
- Criada a tela [`gerar-memorial.php`](/D:/Projetos/wix-feed-php/gerar-memorial.php) para gerar `memorial_key` unica.
- Adicionada a tabela `memorials` para registrar memoriais validos antes de permitir posts.
- Implementada validacao para aceitar post e comentario apenas quando a `memorial_key` existir.
- Adicionado upload opcional de foto da pessoa no cadastro do memorial.
- Incluida listagem paginada de memoriais gerados.
- Incluido botao `Copy` para copiar a URL do memorial diretamente da listagem.

## [0.2.0] - 2026-03-13

### Memorials
- Mural ajustado para funcionar por `memorial_key` na URL, por exemplo `/?memorial_key=123456`.
- Feed filtrado por memorial e ordenado do mais recente para o mais antigo.
- Comentarios vinculados ao contexto do memorial.

## [0.1.0] - 2026-03-12

### Feed e mural
- Modal para ampliar imagem do post em tela cheia.
- Ajustes visuais do feed para um estilo mais proximo de rede social.

### Editor e postagem
- Editor rico simplificado com opcoes de negrito, italico, lista e atalho para simbolos/emojis adequados ao contexto memorial.
- Regra ajustada para aceitar postagem com texto ou imagem, sem exigir os dois.
- Upload de imagem validando extensao, MIME type e limite de 2 MB.
- Botao principal e textos do formulario refinados para o contexto de homenagem e condolencia.

### Login e identificacao
- Login manual com nome livre.
- Login com Google preenchendo nome e foto automaticamente.
- Tratamento de avatar com fallback para iniciais quando a foto nao carregar.
- Ajustes de sessao para Wamp/local e preparacao para embed em iframe.

### Deploy e infraestrutura
- Ajustado deploy para raiz do dominio na Hostinger.
- Estrutura duplicada de assets mantida para suportar ambiente local (`public/`) e producao na raiz.
- SQLs separados para criacao total e alteracoes incrementais.
- Projeto inicializado com Git e branch `main`.

## Observacao

A partir deste ponto, a recomendacao e:
- manter este changelog atualizado em cada entrega relevante
- usar mensagens de commit mais descritivas, por exemplo `Add memorial photo upload` ou `Format memorial dates in pt-BR`
