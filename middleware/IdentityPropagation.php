<?php
/**
 * Identity Propagation Middleware
 * Based on AWS sample-agent-interoperability pattern
 * Propagates user identity across all provider calls (Groq, OpenRouter, Bedrock, Gemini)
 */

class IdentityPropagation {
    private $jwtSecret;
    private $tokenExpiration = 3600; // 1 hour

    public function __construct() {
        $this->jwtSecret = getenv('JWT_SECRET') ?: 'default-secret-change-in-production';
    }

    /**
     * Generate JWT token with user identity
     */
    public function generateToken($userId, $email, $teams = []) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'email' => $email,
            'teams' => $teams,
            'iat' => time(),
            'exp' => time() + $this->tokenExpiration
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $this->jwtSecret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Validate and decode JWT token
     */
    public function validateToken($token) {
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return null;
            }

            list($header, $payload, $signature) = $tokenParts;

            // Verify signature
            $expectedSignature = hash_hmac('sha256', $header . "." . $payload, $this->jwtSecret, true);
            $expectedBase64Signature = $this->base64UrlEncode($expectedSignature);

            if ($signature !== $expectedBase64Signature) {
                return null;
            }

            // Decode payload
            $decodedPayload = json_decode($this->base64UrlDecode($payload), true);

            // Check expiration
            if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
                return null;
            }

            return $decodedPayload;

        } catch (Exception $e) {
            error_log("Token validation failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract identity from Authorization header
     */
    public function extractIdentityFromHeader($authHeader) {
        if (!$authHeader) {
            return null;
        }

        // Remove "Bearer " prefix
        $token = str_replace('Bearer ', '', $authHeader);

        return $this->validateToken($token);
    }

    /**
     * Inject identity headers for provider calls
     * Returns array of headers to include in API requests
     */
    public function getProviderHeaders($userId, $email, $provider) {
        $headers = [
            'X-User-Id' => $userId,
            'X-User-Email' => $email,
            'X-Request-Timestamp' => (string)time()
        ];

        // Provider-specific headers
        switch ($provider) {
            case 'groq':
                $headers['X-Custom-User-ID'] = $userId;
                break;
            case 'openrouter':
                $headers['HTTP-Referer'] = getenv('APP_URL') ?: 'http://localhost:8081';
                $headers['X-Title'] = 'AI Billing Hub';
                break;
            case 'bedrock':
                // AWS uses IAM roles, but we can include metadata
                $headers['X-Amz-Meta-User-Id'] = $userId;
                break;
            case 'gemini':
                $headers['X-Goog-User-Id'] = $userId;
                break;
        }

        return $headers;
    }

    /**
     * Middleware for API requests
     * Checks for valid JWT and extracts user identity
     */
    public function middleware() {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        if (!$authHeader) {
            http_response_code(401);
            echo json_encode(['error' => 'Missing authorization header']);
            exit;
        }

        $identity = $this->extractIdentityFromHeader($authHeader);

        if (!$identity) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        // Store identity in request context
        $_SERVER['USER_IDENTITY'] = $identity;

        return $identity;
    }

    /**
     * Get current user identity from request context
     */
    public function getCurrentIdentity() {
        return $_SERVER['USER_IDENTITY'] ?? null;
    }

    /**
     * Helper: Base64 URL encode
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Helper: Base64 URL decode
     */
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
