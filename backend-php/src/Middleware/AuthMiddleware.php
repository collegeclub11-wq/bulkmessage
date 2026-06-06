<?php
namespace Middleware;

use Exception;

class AuthMiddleware {
    public static function validateJWT() {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';

        if (empty($authHeader) && isset($headers['authorization'])) {
            $authHeader = $headers['authorization'];
        }

        if (empty($authHeader)) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing authorization token']);
            exit;
        }

        $token = str_replace('Bearer ', '', $authHeader);
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token structure']);
            exit;
        }

        list($headerEncoded, $payloadEncoded, $signature) = $parts;

        // Verify signature
        $secret = JWT_SECRET;
        $validSignature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
        $validSignatureEncoded = self::base64UrlEncode($validSignature);

        if (!hash_equals($validSignatureEncoded, $signature)) {
            http_response_code(401);
            echo json_encode(['error' => 'Token signature validation failed']);
            exit;
        }

        $payload = json_decode(base64_decode(strtr($payloadEncoded, '-_', '+/')), true);
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            http_response_code(401);
            echo json_encode(['error' => 'Authorization token expired']);
            exit;
        }

        return $payload;
    }

    public static function generateJWT($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload['exp'] = time() + JWT_EXPIRY;
        $payload['iat'] = time();

        $headerEncoded = self::base64UrlEncode($header);
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $secret = JWT_SECRET;
        $signature = hash_hmac('sha256', "$headerEncoded.$payloadEncoded", $secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "$headerEncoded.$payloadEncoded.$signatureEncoded";
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
?>
