<?php
// backend/lib/AirtableClient.php
// ตัวเชื่อมต่อ Airtable REST API — ดึง records ทั้งหมดจาก table ที่กำหนด
// อ่าน credential จาก Environment Variables (AIRTABLE_API_KEY, AIRTABLE_BASE_ID, AIRTABLE_TABLE_ID)

class AirtableClient
{
    private string $apiKey;
    private string $baseId;
    private string $tableId;

    public function __construct(string $apiKey, string $baseId, string $tableId)
    {
        $this->apiKey  = $apiKey;
        $this->baseId  = $baseId;
        $this->tableId = $tableId;
    }

    /**
     * ดึง records ทั้งหมดจาก Airtable (วน pagination อัตโนมัติ เพราะ Airtable
     * จำกัดให้ดึงได้สูงสุดครั้งละ 100 แถวต่อ 1 request)
     *
     * คืนค่าเป็น array ของ ['id' => 'rec...', 'fields' => [...]]
     */
    public function fetchAllRecords(): array
    {
        $allRecords = [];
        $offset = null;

        do {
            $url = "https://api.airtable.com/v0/{$this->baseId}/{$this->tableId}?pageSize=100";
            if ($offset) {
                $url .= '&offset=' . urlencode($offset);
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $result    = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                throw new Exception('เชื่อมต่อ Airtable ไม่สำเร็จ: ' . $curlError);
            }

            $data = json_decode($result, true);

            if ($httpCode !== 200) {
                $msg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
                throw new Exception('Airtable API error: ' . $msg);
            }

            foreach ($data['records'] ?? [] as $record) {
                $allRecords[] = $record;
            }

            $offset = $data['offset'] ?? null;
        } while ($offset);

        return $allRecords;
    }
}