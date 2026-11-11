<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Secure file upload handler with MIME/extension whitelisting and safe storage paths.
 */
final class FileUploadService
{
    private const ALLOWED_EXTENSIONS = [
        'pdf', 'png', 'jpg', 'jpeg', 'webp', 'gif',
        'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt',
    ];

    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'application/octet-stream', // fallback for some xlsx/docx MIME detection
    ];

    public function store(array $file, string $subDir = 'documents'): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error code: ' . ($file['error'] ?? 'unknown'));
        }

        $maxBytes = (int) config('max_upload_bytes', 10485760);
        if (($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException('File exceeds maximum allowed size of 10 MB.');
        }

        $originalName = basename((string) ($file['name'] ?? 'file'));
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException("File extension '.{$ext}' is not permitted.");
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : 'application/octet-stream';
        if ($finfo) {
            finfo_close($finfo);
        }

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException("Detected MIME type '{$mime}' is not permitted.");
        }

        $safeSub = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir) ?: 'documents';
        $targetDir = rtrim((string) config('upload_path'), '/') . '/' . $safeSub;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }

        $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $targetDir . '/' . $storedName;

        $moved = is_uploaded_file($tmpPath) ? move_uploaded_file($tmpPath, $destPath) : copy($tmpPath, $destPath);
        if (!$moved) {
            throw new RuntimeException('Failed to store uploaded file.');
        }

        return [
            'relative_path' => $safeSub . '/' . $storedName,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'size' => (int) ($file['size'] ?? filesize($destPath)),
        ];
    }

    public function resolveSafePath(string $relativePath): ?string
    {
        $base = realpath((string) config('upload_path'));
        if ($base === false) {
            return null;
        }
        $clean = ltrim(str_replace('..', '', $relativePath), '/');
        $full = realpath($base . '/' . $clean);
        if ($full === false || !str_starts_with($full, $base) || !is_file($full)) {
            return null;
        }
        return $full;
    }
}
