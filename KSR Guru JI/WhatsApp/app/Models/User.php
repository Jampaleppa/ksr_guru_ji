<?php
namespace App\Models;

class User {
    
    private $db;
    
    public function __construct() {
        // Pseudo DB injection for modeling
        // $this->db = \Config\Database::getInstance()->getConnection();
    }
    
    /**
     * Locate user exclusively by their unique @username
     * @param string $ksrId
     * @return array|null
     */
    public function findByKsrId(string $ksrId): ?array {
        /*
        $stmt = $this->db->prepare("
            SELECT u.id, u.ksr_id, p.display_name, p.avatar_url, p.is_online, r.role
            FROM users u
            JOIN user_profiles p ON u.id = p.user_id
            JOIN user_roles r ON u.id = r.user_id
            WHERE u.ksr_id = ? AND u.status_state = 'active'
            LIMIT 1
        ");
        $stmt->execute([$ksrId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        */
        
        // Mock payload representing successful fetch
        return [
            'id' => 8421,
            'ksr_id' => $ksrId,
            'display_name' => 'Mapped User',
            'avatar_url' => null,
            'is_online' => true,
            'role' => 'USER'
        ];
    }
    
    /**
     * Safely locates User ID connected to a Raw Mobile Number
     * Kept isolated to respect privacy rules
     * @param string $mobileNumber
     * @return int|null
     */
    public function findIdByMobile(string $mobileNumber): ?int {
        /*
        $stmt = $this->db->prepare("SELECT id FROM users WHERE mobile_number = ? LIMIT 1");
        $stmt->execute([$mobileNumber]);
        return $stmt->fetchColumn() ?: null;
        */
        return 8421;
    }
    
    /**
     * Toggles Online/Offline presence mechanism
     * @param int $userId
     * @param bool $isOnline
     * @return bool
     */
    public function updatePresence(int $userId, bool $isOnline): bool {
        /*
        $stmt = $this->db->prepare("
            UPDATE user_profiles 
            SET is_online = ?, last_seen = NOW() 
            WHERE user_id = ?
        ");
        return $stmt->execute([(int)$isOnline, $userId]);
        */
        return true;
    }
    
    /**
     * Update the user's mutable About / Bio Text
     * @param int $userId 
     * @param string $aboutText
     * @return bool
     */
    public function updateAbout(int $userId, string $aboutText): bool {
        /*
        $stmt = $this->db->prepare("UPDATE user_profiles SET about_text = ? WHERE user_id = ?");
        return $stmt->execute([$aboutText, $userId]);
        */
        return true;
    }

}
