<?php 
namespace KSeFClient;

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/ksef_api.php";

class TokenManager {
    private $conn;
    private $subject_id;
    private $mode;

    public function __construct($nip, KsefMode $mode) {
        $this->mode = $mode;
        $this->conn = Database::getConnection();

        $this->subject_id = $this->get_subject_id_by_nip($nip);
        if (!$this->subject_id) {
            throw new \Exception("Subject not found for NIP: " . htmlspecialchars($nip));
        }
    }

    private function get_subject_id_by_nip($nip) {
        $stmt = $this->conn->prepare("SELECT id FROM subjects WHERE nip = ?");
        $stmt->bind_param("i", $nip);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return (int) $row['id'];
        }
        return null;
    }

    public function save_access_and_refresh($access_token, $access_token_expires_at, $refresh_token, $refresh_token_expires_at) {
        $accessDt = $this->parseExpiry($access_token_expires_at);
        $refreshDt = $this->parseExpiry($refresh_token_expires_at);

        $accessForDb = $accessDt ? $accessDt->format('Y-m-d H:i:s') : $access_token_expires_at;
        $refreshForDb = $refreshDt ? $refreshDt->format('Y-m-d H:i:s') : $refresh_token_expires_at;

        $stmt = $this->conn->prepare("
            INSERT INTO tokens (
                subject_id,
                access_token,
                access_token_expires_at,
                refresh_token,
                refresh_token_expires_at
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                access_token_expires_at = VALUES(access_token_expires_at),
                refresh_token_expires_at = VALUES(refresh_token_expires_at)
        ");

        $stmt->bind_param(
            "issss",
            $this->subject_id,
            $access_token,
            $accessForDb,
            $refresh_token,
            $refreshForDb
        );

        if (!$stmt->execute()) {
            throw new \Exception("Error saving tokens: " . $stmt->error);
        }

        return true;
    }

    private function parseExpiry($value): ?\DateTimeImmutable {
        if (!$value) return null;

        if (is_numeric($value)) {
            $ts = (int)$value;
            if (strlen((string)$value) > 10) { // milliseconds
                $ts = (int) round($ts / 1000);
            }
            try {
                return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone('UTC'));
            } catch (\Throwable $e) {
                return null;
            }
        }

        try {
            $dt = \DateTimeImmutable::createFromFormat(\DateTime::ATOM, $value);
            if ($dt !== false) return $dt->setTimezone(new \DateTimeZone('UTC'));
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function save_access(string $access_token, string $access_token_expires_at, string $refresh_token): bool {
        $expiresDt = $this->parseExpiry($access_token_expires_at);
        $expiresForDb = $expiresDt ? $expiresDt->format('Y-m-d H:i:s') : $access_token_expires_at;

        $stmt = $this->conn->prepare("
            UPDATE tokens
            SET 
                access_token = ?,
                access_token_expires_at = ?
            WHERE refresh_token = ?
            AND refresh_token_expires_at > NOW()
        ");

        $stmt->bind_param("sss", $access_token, $expiresForDb, $refresh_token);

        if (!$stmt->execute()) {
            throw new \Exception("Error updating access token: " . $stmt->error);
        }

        return $stmt->affected_rows > 0;
    }

    private function delete_expired_tokens() {
        $stmt = $this->conn->prepare("DELETE FROM tokens WHERE subject_id = ? AND refresh_token_expires_at < NOW()");

        $stmt->bind_param("i", $this->subject_id);

        if (!$stmt->execute()) {
            throw new \Exception("Error deleting expired tokens: " . $stmt->error);
        }

        return true;
    }

    public function get_access_token() {
        $this->delete_expired_tokens();

        $stmt = $this->conn->prepare("SELECT * FROM tokens WHERE subject_id = ? AND refresh_token_expires_at > NOW() ORDER BY access_token_expires_at DESC LIMIT 1");
        $stmt->bind_param("i", $this->subject_id);

        if (!$stmt->execute()) {
            throw new \Exception("Error reading tokens: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $token = $result->fetch_assoc();

        if (!$token)
            return null;
        
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expires = $this->parseExpiry($token['access_token_expires_at']);

        if (!$expires) {
            throw new \Exception("Cannot parse access_token_expires_at: " . var_export($token['access_token_expires_at'], true));
        }

        if ($now >= $expires) {
            $ksef_api = new KsefApi($this->mode);
            $json = $ksef_api->refresh_access_token($token['refresh_token']);

            $access_token              = $json['accessToken']['token'] ?? null;
            $access_token_expires_at   = $json['accessToken']['validUntil'] ?? null;

            if (!$access_token || !$access_token_expires_at) {
                throw new \Exception("Refresh response missing token or expiry");
            }

            if(!$this->save_access($access_token, $access_token_expires_at, $token['refresh_token']))
                return null;

            return [
                "access_token" => $access_token,
                "access_token_expires_at" => $access_token_expires_at
            ];
        }

        return [
            "access_token" => $token['access_token'],
            "access_token_expires_at" => $token['access_token_expires_at']
        ];
    }

    function delete_subject_tokens() {
        $stmt = $this->conn->prepare("DELETE FROM tokens WHERE subject_id = ?");

        $stmt->bind_param("i", $this->subject_id);

        if (!$stmt->execute()) {
            throw new \Exception("Error deleting tokens: " . $stmt->error);
        }

        return true;
    }
}