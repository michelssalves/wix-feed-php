<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class FeedService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createPost(string $memorialKey, ?int $userId, string $authorName, ?string $authorPhoto, string $text, ?string $image): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO posts (memorial_key, user_id, nome_autor, foto_autor, texto, imagem, criado_em)
             VALUES (:memorial_key, :user_id, :nome_autor, :foto_autor, :texto, :imagem, NOW())'
        );
        $statement->execute([
            'memorial_key' => $memorialKey,
            'user_id' => $userId,
            'nome_autor' => $authorName,
            'foto_autor' => $authorPhoto,
            'texto' => $text,
            'imagem' => $image,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updatePostText(int $postId, int $userId, string $text): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE posts
             SET texto = :texto
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => $postId,
            'user_id' => $userId,
            'texto' => $text,
        ]);

        return $statement->rowCount() > 0;
    }

    public function deletePost(int $postId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM posts
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => $postId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function postExistsForUser(int $postId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM posts
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $postId,
            'user_id' => $userId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function createComment(int $postId, ?int $userId, string $authorName, ?string $authorPhoto, string $text): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO comments (post_id, user_id, nome_autor, foto_autor, texto, criado_em)
             VALUES (:post_id, :user_id, :nome_autor, :foto_autor, :texto, NOW())'
        );
        $statement->execute([
            'post_id' => $postId,
            'user_id' => $userId,
            'nome_autor' => $authorName,
            'foto_autor' => $authorPhoto,
            'texto' => $text,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateComment(int $commentId, int $userId, string $text): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE comments
             SET texto = :texto
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => $commentId,
            'user_id' => $userId,
            'texto' => $text,
        ]);

        return $statement->rowCount() > 0;
    }

    public function deleteComment(int $commentId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM comments
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'id' => $commentId,
            'user_id' => $userId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function commentExistsForUser(int $commentId, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1
             FROM comments
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $statement->execute([
            'id' => $commentId,
            'user_id' => $userId,
        ]);

        return (bool) $statement->fetchColumn();
    }

    public function postExists(int $postId, string $memorialKey = ''): bool
    {
        if ($memorialKey !== '') {
            $statement = $this->pdo->prepare('SELECT 1 FROM posts WHERE id = :id AND memorial_key = :memorial_key LIMIT 1');
            $statement->execute([
                'id' => $postId,
                'memorial_key' => $memorialKey,
            ]);
            return (bool) $statement->fetchColumn();
        }

        $statement = $this->pdo->prepare('SELECT 1 FROM posts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $postId]);
        return (bool) $statement->fetchColumn();
    }

    public function feed(string $memorialKey = ''): array
    {
        if ($memorialKey !== '') {
            $statement = $this->pdo->prepare(
                'SELECT id, memorial_key, user_id, nome_autor, foto_autor, texto, imagem, criado_em
                 FROM posts
                 WHERE memorial_key = :memorial_key
                 ORDER BY criado_em DESC, id DESC'
            );
            $statement->execute(['memorial_key' => $memorialKey]);
            $posts = $statement->fetchAll();
        } else {
            $posts = $this->pdo->query(
                'SELECT id, memorial_key, user_id, nome_autor, foto_autor, texto, imagem, criado_em
                 FROM posts
                 ORDER BY criado_em DESC, id DESC'
            )->fetchAll();
        }

        if (!$posts) {
            return [];
        }

        $postIds = array_column($posts, 'id');
        $placeholders = implode(',', array_fill(0, count($postIds), '?'));
        $commentsStatement = $this->pdo->prepare(
            "SELECT id, post_id, user_id, nome_autor, foto_autor, texto, criado_em
             FROM comments
             WHERE post_id IN ($placeholders)
             ORDER BY criado_em ASC, id ASC"
        );
        $commentsStatement->execute($postIds);
        $comments = $commentsStatement->fetchAll();

        $commentsByPost = [];
        foreach ($comments as $comment) {
            $commentsByPost[(int) $comment['post_id']][] = $comment;
        }

        foreach ($posts as &$post) {
            $post['comments'] = $commentsByPost[(int) $post['id']] ?? [];
        }

        return $posts;
    }
}
