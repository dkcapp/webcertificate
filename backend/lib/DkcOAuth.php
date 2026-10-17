<?php
// backend/lib/DkcOAuth.php
// ล็อกอินผ่าน DKC OAuth (oauth.dhammakaya.network) แบบ authorization-code
// ตัวแปรที่ใช้: OAUTH_BASE_URI, OAUTH_CLIENT_ID, OAUTH_CLIENT_SECRET, OAUTH_REDIRECT_URI

class DkcOAuth
{
    private string $base;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(string $base, string $clientId, string $clientSecret, string $redirectUri)
    {
        $this->base         = rtrim($base, '/');
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri  = $redirectUri;
    }

    public static function fromEnv(): self
    {
        $base = getenv('OAUTH_BASE_URI') ?: '';
        $id   = getenv('OAUTH_CLIENT_ID') ?: '';
        $sec  = getenv('OAUTH_CLIENT_SECRET') ?: '';
        $uri  = getenv('OAUTH_REDIRECT_URI') ?: '';
        if ($base === '' || $id === '' || $sec === '' || $uri === '') {
            throw new Exception('ยังไม่ได้ตั้งค่า OAUTH_BASE_URI / OAUTH_CLIENT_ID / OAUTH_CLIENT_SECRET / OAUTH_REDIRECT_URI');
        }
        return new self($base, $id, $sec, $uri);
    }

    public function authorizeUrl(string $state): string
    {
        return $this->base . '/oauth/authorize?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'state'         => $state,
        ]);
    }

    /** แลก code เป็น access_token */
    public function exchangeCode(string $code): string
    {
        $res = $this->request('POST', $this->base . '/oauth/token', [], http_build_query([
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'code'          => $code,
        ]), 'application/x-www-form-urlencoded');
        $token = $res['access_token'] ?? '';
        if (!is_string($token) || $token === '') {
            throw new Exception('DKC OAuth: ไม่ได้รับ access_token');
        }
        return $token;
    }

    /** ดึงโปรไฟล์ผู้ใช้ (ต้องมี username = บัญชี AD) */
    public function fetchUser(string $accessToken): array
    {
        $u = $this->request('GET', $this->base . '/api/user', ['Authorization: Bearer ' . $accessToken], null, null);
        if (empty($u['username']) || !is_string($u['username'])) {
            throw new Exception('DKC OAuth: โปรไฟล์ผู้ใช้ไม่มี username');
        }
        return $u;
    }

    private function request(string $method, string $url, array $headers, ?string $body, ?string $contentType): array
    {
        $ch = curl_init($url);
        $h = array_merge(['Accept: application/json'], $headers);
        if ($contentType) $h[] = 'Content-Type: ' . $contentType;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => $h,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        }
        $out    = curl_exec($ch);
        $err    = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($out === false) throw new Exception('DKC OAuth: เชื่อมต่อไม่ได้ (' . $err . ')');
        $json = json_decode($out, true);
        if ($status < 200 || $status >= 300 || !is_array($json)) {
            throw new Exception("DKC OAuth: ตอบกลับผิดปกติ (HTTP $status)");
        }
        return $json;
    }
}
