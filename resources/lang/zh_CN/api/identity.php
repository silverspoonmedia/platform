<?php

return [
    'oauth' => [
        'discord_not_configured' => '此服务器未配置 Discord OAuth。',
        'token_exchange_failed' => 'Discord 授权失败，请重试。',
        'token_refresh_failed' => 'Discord 会话已过期，请从“已连接账户”重新连接 Discord。',
        'connections_failed' => '无法加载已关联的 Discord 账户。',
        'discord_not_connected' => '请先从“已连接账户”连接 Discord。',
        'discord_user_missing' => 'Discord 未返回用户 ID。',
        'authorize_url' => 'Discord 授权 URL 已签发。',
        'spa_url_missing' => '此服务器未配置 SPA 来源。',
        'return_path_invalid' => '不允许使用此 OAuth 返回路径。',
    ],
    'connections' => [
        // curated at 2026-09-22T12:19:25+07:00 — zh_CN curation wave
        'show' => '已加载已连接账户。',
        'synced' => '关联账户已更新。',
        'disconnected' => '账户已断开连接。',
        'discord_disconnected' => 'Discord 已断开连接。',
    ],
];
