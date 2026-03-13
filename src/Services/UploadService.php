<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class UploadService
{
    public function __construct(private readonly array $config)
    {
    }

    public function uploadImage(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no envio da imagem.');
        }

        $tmpName = $file['tmp_name'] ?? '';
        $originalName = $file['name'] ?? '';
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException('Arquivo de imagem invalido.');
        }

        if ($size > $this->config['max_size']) {
            throw new RuntimeException('A imagem deve ter no maximo 2 MB.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $this->config['allowed_extensions'], true)) {
            throw new RuntimeException('Extensao de imagem nao permitida.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo ? finfo_file($finfo, $tmpName) : false;
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!$mimeType || !in_array($mimeType, $this->config['allowed_mime_types'], true)) {
            throw new RuntimeException('Tipo MIME da imagem nao permitido.');
        }

        $uploadDirectory = rtrim($this->config['directory'], '\\/');
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Nao foi possivel criar a pasta de uploads.');
        }

        $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $extension);
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Nao foi possivel salvar a imagem enviada.');
        }

        return trim((string) $this->config['public_path'], '/') . '/' . $filename;
    }
}
