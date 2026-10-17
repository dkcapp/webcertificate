<?php
// backend/lib/CasClient.php
// ส่งคำขออนุมัติไประบบ CAS ของ DKC และถามสถานะคำขอ
// ตัวแปร: OAUTH_BASE_URI, OAUTH_CLIENT_ID, OAUTH_CLIENT_SECRET (ใช้ชุดเดียวกับล็อกอินได้)
//         ถ้า IT ออกชุดแยกสำหรับ CAS ให้ตั้ง CAS_CLIENT_ID / CAS_CLIENT_SECRET
//         ปรับ URL ได้ด้วย CAS_REQUEST_URL / CAS_REQ_STATUS_URL

class CasClient
{
    private string $requestUrl;
    private string $statusUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct(string $requestUrl, string $statusUrl, string $clientId, string $clientSecret)
    {
        $this->requestUrl   = $requestUrl;
        $this->statusUrl    = $statusUrl;
        $this->clientId     = $clientId;
        $this->clientSecret = $clientSecret;
    }

    public static function fromEnv(): self
    {
        $base = rtrim(getenv('OAUTH_BASE_URI') ?: '', '/');
        $id   = getenv('CAS_CLIENT_ID') ?: (getenv('OAUTH_CLIENT_ID') ?: '');
        $sec  = getenv('CAS_CLIENT_SECRET') ?: (getenv('OAUTH_CLIENT_SECRET') ?: '');
        if ($base === '' || $id === '' || $sec === '') {
            throw new Exception('ยังไม่ได้ตั้งค่า CAS (OAUTH_BASE_URI + client id/secret)');
        }
        return new self(
            getenv('CAS_REQUEST_URL') ?: $base . '/api/cas/request',
            getenv('CAS_REQ_STATUS_URL') ?: $base . '/api/cas/reqstat',
            $id,
            $sec
        );
    }

    /** ตอบ success เมื่อ success === true หรือ status === 'success' และมี data */
    public static function isOk($r): bool
    {
        return is_array($r)
            && (($r['success'] ?? null) === true || ($r['status'] ?? null) === 'success')
            && !empty($r['data']) && is_array($r['data']);
    }

    /** ส่งคำขอ คืน array ตอบกลับของ CAS (null ถ้าไม่ใช่ JSON) */
    public function sendRequest(string $ref, int $approveType, string $subject, string $message, string $requesterAd, string $callbackUrl): ?array
    {
        return $this->post($this->requestUrl, [
            'client_id'        => $this->clientId,
            'client_secret'    => $this->clientSecret,
            'ExternalRefID'    => $ref,
            'ApproveType'      => $approveType,
            'MsgSubject'       => $subject,
            'MsgForHead'       => $message,
            'Requester_ADUser' => $requesterAd,
            'CallbackURL'      => $callbackUrl,
        ]);
    }

    /** ถามสถานะจริง คืน data ที่มี Status หรือ null ถ้าถามไม่สำเร็จ */
    public function fetchStatus(string $ref): ?array
    {
        try {
            $r = $this->post($this->statusUrl, ['client_id' => $this->clientId, 'ExternalRefID' => $ref]);
        } catch (Throwable $e) {
            error_log('[cas] fetchStatus failed: ' . $e->getMessage());
            return null;
        }
        if (!is_array($r)) return null;
        $d = (isset($r['data']) && is_array($r['data'])) ? $r['data'] : $r;
        return !empty($d['Status']) ? $d : null;
    }

    private function post(string $url, array $payload): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $out = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) throw new Exception('CAS: เชื่อมต่อไม่ได้ (' . $err . ')');
        $json = json_decode($out, true);
        return is_array($json) ? $json : null;
    }
}
