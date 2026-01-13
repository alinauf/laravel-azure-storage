<?php

namespace AliNauf\AzureStorage\Support;

class MimeTypeGuesser
{
    /**
     * Common MIME type mappings.
     *
     * @var array<string, string>
     */
    protected static array $mimeTypes = [
        // Images
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',

        // Videos
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo',
        'wmv' => 'video/x-ms-wmv',
        'flv' => 'video/x-flv',
        'mkv' => 'video/x-matroska',
        'm4v' => 'video/x-m4v',
        '3gp' => 'video/3gpp',

        // Audio
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'wma' => 'audio/x-ms-wma',

        // Documents
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',

        // Text
        'txt' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'css' => 'text/css',
        'csv' => 'text/csv',
        'xml' => 'application/xml',
        'json' => 'application/json',
        'js' => 'application/javascript',
        'md' => 'text/markdown',

        // Archives
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar',
        'tar' => 'application/x-tar',
        'gz' => 'application/gzip',
        '7z' => 'application/x-7z-compressed',

        // Fonts
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'eot' => 'application/vnd.ms-fontobject',
    ];

    /**
     * Guess the MIME type based on file extension.
     */
    public static function guess(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::$mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Get the extension for a MIME type.
     */
    public static function getExtension(string $mimeType): ?string
    {
        $flipped = array_flip(self::$mimeTypes);

        return $flipped[$mimeType] ?? null;
    }
}
