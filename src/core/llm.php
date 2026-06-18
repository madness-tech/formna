<?php

/**
 * Generic OpenAI-compatible LLM Client
 *
 * Supports any provider using the /v1/chat/completions format:
 * OpenRouter, OpenAI, Ollama, or custom endpoints.
 *
 * Pure PHP curl — no Composer dependency.
 */

/** Hard ceiling — no request may exceed this regardless of config */
const LLM_MAX_TIMEOUT = 120;

/** Default timeout when none is specified */
const LLM_DEFAULT_TIMEOUT = 30;

/** Default max_tokens when none is specified */
const LLM_DEFAULT_MAX_TOKENS = 1024;

/**
 * Send a chat completion request to any OpenAI-compatible API.
 *
 * @param string $base_url   API base URL (no trailing slash)
 * @param string $api_key    Bearer token (empty string for Ollama / unauthenticated)
 * @param string $model      Model identifier (e.g. 'anthropic/claude-sonnet-4')
 * @param array<int, array{role: string, content: string}>  $messages   Chat messages [{role, content}, ...]
 * @param array{max_tokens?: int, timeout?: int, headers?: list<string>, token_param?: string}  $options    Extra options:
 *                           - max_tokens   (int)    : max response tokens
 *                           - timeout      (int)    : seconds, capped at LLM_MAX_TIMEOUT
 *                           - headers      (array)  : extra HTTP headers ['Header: value', ...]
 *                           - token_param  (string) : payload key for token limit
 *                             ('max_tokens'|'max_completion_tokens'), default 'max_tokens'
 * @return array{success: bool, content?: string, tokens_used?: int, model?: string, error?: string}
 */
function llm_chat(string $base_url, string $api_key, string $model, array $messages, array $options = []): array
{
    // ── Validate inputs ──────────────────────────────────────────────────
    $base_url = rtrim($base_url, '/');
    if ($base_url === '') {
        return ['success' => false, 'error' => 'LLM base URL is not configured.'];
    }
    if ($model === '') {
        return ['success' => false, 'error' => 'LLM model is not configured.'];
    }
    if (empty($messages)) {
        return ['success' => false, 'error' => 'No messages provided.'];
    }

    $max_tokens = max(1, min((int)($options['max_tokens'] ?? LLM_DEFAULT_MAX_TOKENS), 16384));
    $timeout    = max(5, min((int)($options['timeout'] ?? LLM_DEFAULT_TIMEOUT), LLM_MAX_TIMEOUT));

    // ── Build request ────────────────────────────────────────────────────
    $url = $base_url . '/chat/completions';

    // Use max_completion_tokens for providers that require the newer parameter
    // (e.g. OpenAI GPT-5.x / o-series). Falls back to max_tokens for others.
    $token_param = $options['token_param'] ?? 'max_tokens';

    $payload = json_encode([
        'model'        => $model,
        'messages'     => $messages,
        $token_param   => $max_tokens,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return ['success' => false, 'error' => 'Failed to encode request payload.'];
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($api_key !== '') {
        $headers[] = 'Authorization: Bearer ' . $api_key;
    }

    // Merge any extra headers (e.g. OpenRouter HTTP-Referer, X-Title)
    foreach ($options['headers'] ?? [] as $h) {
        $headers[] = $h;
    }

    // ── Execute curl ─────────────────────────────────────────────────────
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        // TLS verification — always on
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // Do NOT follow redirects — API endpoints should not redirect.
        // curl silently strips the Authorization header on redirects,
        // which causes "missing auth header" errors from the provider.
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $response_body = curl_exec($ch);
    $http_code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect_url  = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
    $curl_errno    = curl_errno($ch);
    $curl_error    = curl_error($ch);

    // ── Handle curl-level errors ─────────────────────────────────────────
    if ($curl_errno !== 0) {
        $friendly = match ($curl_errno) {
            CURLE_OPERATION_TIMEDOUT
                => "LLM request timed out after {$timeout}s.",
            CURLE_COULDNT_RESOLVE_HOST
                => 'Could not resolve LLM host. Check the base URL.',
            CURLE_COULDNT_CONNECT
                => 'Could not connect to LLM endpoint. Check the base URL and network.',
            CURLE_SSL_CONNECT_ERROR, CURLE_SSL_CERTPROBLEM, CURLE_SSL_CIPHER
                => 'TLS/SSL error connecting to LLM endpoint.',
            default
                => "Connection error: {$curl_error}",
        };
        return ['success' => false, 'error' => $friendly];
    }

    // ── Check for redirects before parsing body ──────────────────────────
    // We intentionally do not follow redirects because curl silently strips
    // the Authorization header, which causes auth errors at the destination.
    if (in_array($http_code, [301, 302, 307, 308], true)) {
        $hint = $redirect_url
            ? " The endpoint wants to redirect to: {$redirect_url}"
            : ' Check the Base URL for typos (e.g. http instead of https).';
        return [
            'success' => false,
            'error'   => "LLM endpoint returned a redirect (HTTP {$http_code}).{$hint}",
        ];
    }

    // ── Parse response ───────────────────────────────────────────────────
    $data = json_decode($response_body, true);

    if (!is_array($data)) {
        return [
            'success' => false,
            'error'   => "Invalid JSON response from LLM (HTTP {$http_code}).",
        ];
    }

    // API-level error (OpenAI/OpenRouter error object)
    if (isset($data['error'])) {
        $api_error = is_array($data['error'])
            ? ($data['error']['message'] ?? 'Unknown API error')
            : (string) $data['error'];

        return [
            'success' => false,
            'error'   => "LLM API error (HTTP {$http_code}): {$api_error}",
        ];
    }

    // HTTP error without structured error body
    if ($http_code < 200 || $http_code >= 300) {
        return [
            'success' => false,
            'error'   => "LLM returned HTTP {$http_code}.",
        ];
    }

    // ── Extract content ──────────────────────────────────────────────────
    $content = $data['choices'][0]['message']['content'] ?? null;
    if ($content === null || $content === '') {
        $finish = $data['choices'][0]['finish_reason'] ?? 'unknown';
        $hint = match ($finish) {
            'length'         => ' The model hit the token limit before producing output (try a higher Max Response Tokens value, or a non-reasoning model).',
            'content_filter' => ' The response was blocked by the provider\'s content filter.',
            default          => '',
        };
        return [
            'success' => false,
            'error'   => "LLM returned an empty response (finish_reason: {$finish}).{$hint}",
        ];
    }

    // Token usage (may vary between providers)
    $usage       = $data['usage'] ?? [];
    $tokens_used = ($usage['total_tokens'] ?? null) !== null
        ? (int) $usage['total_tokens']
        : null;

    // Actual model used (OpenRouter may route to a fallback)
    $model_used = $data['model'] ?? $model;

    return [
        'success'     => true,
        'content'     => $content,
        'tokens_used' => $tokens_used,
        'model'       => $model_used,
    ];
}
