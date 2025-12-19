<?php
// api/jwt_helper.php

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64UrlDecode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}

function generate_jwt($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $base64UrlHeader = base64UrlEncode($header);
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);
    return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
}

function auth_disabled_user() {
    $flag = getenv('AUTH_DISABLED');
    if ($flag === false && defined('AUTH_DISABLED')) {
        $flag = AUTH_DISABLED;
    }
    if ($flag === false && defined('DEV_MODE')) {
        $flag = DEV_MODE ? 'true' : 'false';
    }

    $isDisabled = filter_var($flag, FILTER_VALIDATE_BOOLEAN);
    if (!$isDisabled) {
        return null;
    }

    $role = getenv('AUTH_DISABLED_ROLE') ?: (defined('AUTH_DISABLED_ROLE') ? AUTH_DISABLED_ROLE : 'ADMIN');
    $username = getenv('AUTH_DISABLED_USER') ?: (defined('AUTH_DISABLED_USER') ? AUTH_DISABLED_USER : 'Sistema Público');
    $id = getenv('AUTH_DISABLED_USER_ID') ?: (defined('AUTH_DISABLED_USER_ID') ? AUTH_DISABLED_USER_ID : 0);

    return [
        'id' => (int)$id,
        'username' => $username,
        'role' => $role
    ];
}

function verify_jwt($token, $secret) {
    if ($overrideUser = auth_disabled_user()) {
        return $overrideUser;
    }

    if (!$token) {
        return false;
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;

    $header = $parts[0];
    $payload = $parts[1];
    $signature_provided = $parts[2];

    $signature_generated = hash_hmac('sha256', $header . "." . $payload, $secret, true);
    $base64UrlSignature = base64UrlEncode($signature_generated);

    if ($base64UrlSignature === $signature_provided) {
        return json_decode(base64UrlDecode($payload), true);
    }
    return false;
}

function get_bearer_token() {
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}
?>
