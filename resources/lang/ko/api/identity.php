<?php

return [
    'oauth' => [
        'discord_not_configured' => '이 서버에 Discord OAuth가 구성되어 있지 않습니다.',
        // curated at 2026-09-23T13:18:48+07:00 — ko curation wave: register normalized to 해요체
        'token_exchange_failed' => 'Discord 인증에 실패했습니다. 다시 시도하세요.',
        // curated at 2026-09-23T13:18:48+07:00 — ko curation wave: register normalized to 해요체
        'token_refresh_failed' => 'Discord 세션이 만료되었습니다. 연결된 계정에서 Discord를 다시 연결하세요.',
        // curated at 2026-09-23T11:22:14+07:00 — ko curation wave
        'connections_failed' => '연결된 Discord 계정을 불러올 수 없습니다.',
        // curated at 2026-09-23T13:18:48+07:00 — ko curation wave: register normalized to 해요체
        'discord_not_connected' => '먼저 연결된 계정에서 Discord를 연결하세요.',
        'discord_user_missing' => 'Discord에서 사용자 ID를 반환하지 않았습니다.',
        'authorize_url' => 'Discord 인증 URL을 발급했습니다.',
        'spa_url_missing' => '이 서버에 SPA 출처가 구성되어 있지 않습니다.',
        'return_path_invalid' => '허용되지 않는 OAuth 반환 경로입니다.',
    ],
    'connections' => [
        'show' => '연결된 계정을 불러왔습니다.',
        'synced' => '연결된 계정을 갱신했습니다.',
        'disconnected' => '계정 연결을 해제했습니다.',
        'discord_disconnected' => 'Discord 연결을 해제했습니다.',
    ],
];
