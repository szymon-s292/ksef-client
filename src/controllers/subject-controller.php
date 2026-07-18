<?php
require_once __DIR__ . '/../utils/db.php';

class SubjectController {
    private mysqli $db;

    public function __construct() {
        global $conn;
        $this->db = $conn;
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT s.* FROM subjects s WHERE s.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public function getByNip(string $nip): ?array {
        $stmt = $this->db->prepare("SELECT s.* FROM subjects s WHERE s.nip = ?");
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc() ?: null;
    }

    public function create($name, $nip, $address, $phone, $email) {
        $stmt1 = $this->db->prepare("INSERT INTO subjects (name, nip, address, phone, email) VALUES (?, ?, ?, ?, ?)");

        $name = $name ?: "";
        $nip = $nip ?: "";
        $address = $address ?: "";
        $phone = $phone ?: "";
        $email = $email ?: "";

        $stmt1->bind_param(
            "ssssssssss",
            $name,
            $nip,
            $address,
            $phone,
            $email,
        );

        $stmt1->execute();
        $subject_id = $this->db->insert_id;

        return $subject_id;
    }

    public function update($id, $name, $nip, $address, $phone, $email): bool {
        $stmt = $this->db->prepare("
            UPDATE subjects s 
            SET name = ?, nip = ?, regon = ?, address = ?, phone = ?, email = ?
            WHERE s.id = ?"
        );

        $name = $name ?: "";
        $nip = $nip ?: "";
        $address = $address ?: "";
        $phone = $phone ?: "";
        $email = $email ?: "";

        $stmt->bind_param(
            "ssssss",
            $name,
            $nip,
            $address,
            $phone,
            $email,
            $id,
        );

        return $stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("
            DELETE FROM subjects s
            WHERE s.id = ?"
        );
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
