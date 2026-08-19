<?php

/*
 * Server-side verification helper for Google reCAPTCHA v3 (or any other
 * token/secret + siteverify-shaped provider using the same request/response
 * contract). Framework-level utility, not app-specific - the caller always
 * supplies its own site/secret keys (see erwebConfig.php in the erweb app
 * for the config-driven accessor pattern).
 *
 * Every error path fails closed (returns success = false) rather than
 * throwing or silently passing - this guards a security-relevant decision,
 * so an unconfigured secret, a missing token, or a network failure must
 * never be mistaken for a passed check by a caller that forgets to inspect
 * the 'error' key.
 */

class recaptchaClass {
    const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const DEFAULT_SCORE_THRESHOLD = 0.5;
    const TIMEOUT_SECONDS = 5;

    // Calls Google's siteverify endpoint. Returns
    // ['success' => bool, 'score' => ?float, 'action' => ?string, 'error' => ?string].
    // 'error' is non-null only on a failure that short-circuited before (or
    // instead of) a real response from the provider.
    static function verify(?string $token, ?string $secretKey, ?string $remoteIp = null): array {
        $failed = function (string $error): array {
            return ['success' => false, 'score' => null, 'action' => null, 'error' => $error];
        };

        if ($token === null || trim($token) === '') {
            return $failed('missing_token');
        }

        if ($secretKey === null || trim($secretKey) === '' || str_starts_with($secretKey, 'TODO')) {
            return $failed('not_configured');
        }

        $postFields = [
            'secret' => $secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== null && $remoteIp !== '') {
            $postFields['remoteip'] = $remoteIp;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::VERIFY_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return $failed('request_failed: ' . $curlError);
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return $failed('invalid_response');
        }

        return [
            'success' => isset($decoded['success']) && $decoded['success'] === true,
            'score' => isset($decoded['score']) ? (float) $decoded['score'] : null,
            'action' => $decoded['action'] ?? null,
            'error' => null,
        ];
    }

    // Convenience wrapper applying a score threshold and, optionally, an
    // expected action name on top of verify(). Callers that need the raw
    // score/action/error detail should call verify() directly instead.
    static function isHuman(
        ?string $token,
        ?string $secretKey,
        float $minScore = self::DEFAULT_SCORE_THRESHOLD,
        ?string $expectedAction = null,
        ?string $remoteIp = null
    ): bool {
        $result = self::verify($token, $secretKey, $remoteIp);

        if (!$result['success']) {
            return false;
        }

        if ($result['score'] !== null && $result['score'] < $minScore) {
            return false;
        }

        if ($expectedAction !== null && $result['action'] !== null && $result['action'] !== $expectedAction) {
            return false;
        }

        return true;
    }

}   /* end class definition */
