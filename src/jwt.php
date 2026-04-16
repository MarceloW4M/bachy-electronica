<?php

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_sign(array $payload): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $body = $payload + ['iat' => time()];

    $segments = [
        base64url_encode(json_encode($header, JSON_UNESCAPED_UNICODE)),
        base64url_encode(json_encode($body, JSON_UNESCAPED_UNICODE)),
    ];

    $signature = hash_hmac('sha256', implode('.', $segments), JWT_SECRET, true);
    $segments[] = base64url_encode($signature);

    return implode('.', $segments);
}

function jwt_verify(string $token): ?array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$head, $body, $signature] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $head . '.' . $body, JWT_SECRET, true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode(base64url_decode($body), true);
    return is_array($payload) ? $payload : null;
}
