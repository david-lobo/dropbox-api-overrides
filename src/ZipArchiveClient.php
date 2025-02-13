<?php

namespace Davidlobo\DropboxApiOverrides;

use Spatie\Dropbox\Client;

class ZipArchiveClient extends Client
{
    /**
     * Upload file split in chunks. This allows uploading large files, since
     * Dropbox API v2 limits the content size to 150MB.
     *
     * The chunk size will affect directly the memory usage, so be careful.
     * Large chunks tends to speed up the upload, while smaller optimizes memory usage.
     *
     * @param  string|resource  $contents
     * @return array<mixed>
     */
    public function uploadChunked(string $path, mixed $contents, string $mode = 'add', ?int $chunkSize = null): array
    {
        if ($chunkSize === null || $chunkSize > $this->maxChunkSize) {
            $chunkSize = $this->maxChunkSize;
        }

        $uploadedSize = $chunkSize;
        $size = is_string($contents) ? strlen($contents) : fstat($contents)['size'];

        $stream = $this->getStream($contents);

        $cursor = $this->uploadChunk(self::UPLOAD_SESSION_START, $stream, $chunkSize, null);

        while ((! $stream->eof()) && $uploadedSize < $size) {
            $uploadedSize += $chunkSize;

            if (class_exists('\Illuminate\Support\Facades\Log')) {
                \Illuminate\Support\Facades\Log::debug('uploading chunk', [
                    'chunk_size' => $chunkSize,
                    'uploaded_size' => $uploadedSize,
                    'current_size' => $size,
                    'stream_of' => $stream->eof()
                ]);
            }

            $cursor = $this->uploadChunk(self::UPLOAD_SESSION_APPEND, $stream, $chunkSize, $cursor);
        }

        return $this->uploadSessionFinish('', $cursor, $path, $mode);
    }
}
