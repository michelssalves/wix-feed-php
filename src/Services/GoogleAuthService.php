<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client;
use PDO;
use RuntimeException;

class GoogleAuthService
{
    public function __construct(
        private readonly array $config,
        private readonly PDO $pdo
    ) {
    }

    public function authenticate(string $credential): array
    {
        $clientId = trim((string) ($this->config['google']['client_id'] ?? ''));

        if ($clientId === '') {
            throw new RuntimeException('Google Client ID nao configurado.');
        }

        $payload = $this->verifyToken($credential, $clientId);

        if (($payload['email_verified'] ?? false) !== true && ($payload['email_verified'] ?? '') !== 'true') {
            throw new RuntimeException('O email do Google nao foi verificado.');
        }

        $user = [
            'google_id' => (string) ($payload['sub'] ?? ''),
            'name' => trim((string) ($payload['name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'photo_url' => trim((string) ($payload['picture'] ?? '')),
        ];

        if ($user['google_id'] === '' || $user['name'] === '' || $user['email'] === '') {
            throw new RuntimeException('Resposta do Google incompleta.');
        }

        $user['id'] = $this->persistUser($user);
        return $user;
    }

    private function verifyToken(string $credential, string $clientId): array
    {
        if (class_exists(Client::class)) {
            $client = new Client(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($credential);

            if (is_array($payload)) {
                return $payload;
            }
        }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
        $response = $this->httpGet($url);

        if ($response === null) {
            throw new RuntimeException('Nao foi possivel validar o token Google.');
        }

        $payload = json_decode($response, true);

        if (!is_array($payload) || ($payload['aud'] ?? '') !== $clientId) {
            throw new RuntimeException('Token Google invalido para este aplicativo.');
        }

        return $payload;
    }

    private function httpGet(string $url): ?string
    {
        $response = @file_get_contents($url);
        if ($response !== false) {
            return $response;
        }

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $response = curl_exec($curl);
            $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
                return $response;
            }
        }

        return null;
    }

    private function persistUser(array $user): int
    {
        $select = $this->pdo->prepare('SELECT id FROM users WHERE google_id = :google_id LIMIT 1');
        $select->execute(['google_id' => $user['google_id']]);
        $existingId = $select->fetchColumn();

        if ($existingId) {
            $update = $this->pdo->prepare(
                'UPDATE users SET nome = :nome, email = :email, foto_url = :foto_url, atualizado_em = NOW() WHERE id = :id'
            );
            $update->execute([
                'nome' => $user['name'],
                'email' => $user['email'],
                'foto_url' => $user['photo_url'],
                'id' => $existingId,
            ]);

            return (int) $existingId;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO users (nome, email, google_id, foto_url, criado_em, atualizado_em)
             VALUES (:nome, :email, :google_id, :foto_url, NOW(), NOW())'
        );
        $insert->execute([
            'nome' => $user['name'],
            'email' => $user['email'],
            'google_id' => $user['google_id'],
            'foto_url' => $user['photo_url'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
