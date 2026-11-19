<?php

namespace App\Support\Storage;

use App\Support\Presentation\DatePresenter;

/**
 * Shared JSON shape for direct-upload session responses (init + status).
 *
 * Controllers may inject extra keys (e.g. folder_id) via {@see $extra}.
 */
trait SerializesDirectUploadSession
{
    /**
     * @param  array{
     *     upload: object{id: mixed, path: string, expires_at?: mixed, folder_id?: mixed},
     *     strategy: string,
     *     chunk_size: int,
     *     part_count: int,
     *     parts: list<array<string, mixed>>
     * }  $result
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function serializeUploadSession(
        array $result,
        array $extra = [],
    ): array {
        $upload = $result['upload'];
        [$expiresIso, $expiresFormatted, $expiresHuman] = DatePresenter::pack($upload->expires_at ?? null);

        return array_merge([
            'upload_id' => $upload->id,
            'strategy' => $result['strategy'],
            'path' => $upload->path,
            'chunk_size' => $result['chunk_size'],
            'part_count' => $result['part_count'],
            'parts' => $result['parts'],
            'expires_at' => $expiresIso,
            'expires_at_formatted' => $expiresFormatted,
            'expires_at_human' => $expiresHuman,
        ], $extra);
    }
}
