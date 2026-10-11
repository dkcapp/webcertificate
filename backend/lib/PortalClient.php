<?php
// backend/lib/PortalClient.php
// เรียก DKC OAuth Portal (Cloudflare Worker) แบบ server-to-server เพื่อดึงรายการคำขออนุมัติ
// ใช้ PORTAL_BASE_URL และ PORTAL_ADMIN_API_KEY (ค่าเดียวกับ ADMIN_API_KEY ของ Worker)
// คีย์อยู่ฝั่ง server เท่านั้น เบราว์เซอร์ไม่เห็น

class PortalClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey  = $apiKey;
    }

    public static function fromEnv(): self
    {
        $base = getenv('PORTAL_BASE_URL') ?: '';
        $key  = getenv('PORTAL_ADMIN_API_KEY') ?: '';
        if ($base === '' || $key === '') {
            throw new Exception('ยังไม่ได้ตั้งค่า PORTAL_BASE_URL / PORTAL_ADMIN_API_KEY');
        }
        return new self($base, $key);
    }

    /** ดึงรายการคำขอ: page, per_page, status (approved|rejected|pending), q */
    public function listRequests(array $params): array
    {
        $query = [];
        foreach (['page', 'per_page', 'status', 'q'] as $k) {
            if (isset($params[$k]) && $params[$k] !== '') {
                $query[$k] = $params[$k];
            }
        }
        $url = $this->baseUrl . '/api/admin/requests' . ($query ? '?' . http_build_query($query) : '');

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ]);
        $body   = curl_exec($ch);
        $err    = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('เชื่อมต่อ Portal ไม่ได้: ' . $err);
        }
        if ($status === 401 || $status === 403) {
            throw new Exception('Portal ปฏิเสธคีย์ (ตรวจ PORTAL_ADMIN_API_KEY ให้ตรงกับ ADMIN_API_KEY)');
        }
        if ($status === 503) {
            throw new Exception('Portal ยังไม่ได้ตั้งค่า ADMIN_API_KEY');
        }
        $json = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($json)) {
            throw new Exception("Portal ตอบกลับผิดปกติ (HTTP $status)");
        }
        return $json;
    }
}
