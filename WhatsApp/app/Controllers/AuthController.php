<?php
namespace App\Controllers;

use PDO;

class AuthController {

    private ?PDO $db = null;

    public function __construct() {
        $this->db = \Config\Database::getInstance()->getConnection();
    }

    /**
     * Executes the Registration Payload received from /api/auth/register.php
     * Generates universally unique KSR ID.
     */
    public function registerUser(array $requestPayload): string {
        $mobile = $requestPayload['mobile'] ?? null;
        $name = $requestPayload['name'] ?? null;
        
        if (!$mobile || !$name) {
            return $this->sendResponse(400, 'error', 'Missing required fields for Registration.');
        }

        // 1. Check if Mobile is already registered securely
        $stmt = $this->db->prepare("SELECT id FROM users WHERE mobile_number = ?");
        $stmt->execute([$mobile]);
        if ($stmt->fetch()) {
            return $this->sendResponse(409, 'error', 'Mobile number already registered.');
        }

        // 2. Generate Unique KSR ID Ecosystem Public Identifier
        $cleanName = preg_replace("/[^a-zA-Z0-9]/", "", strtolower($name));
        $secureSuffix = substr(bin2hex(random_bytes(3)), 0, 4); 
        $ksrId = "@ksr_" . $cleanName . $secureSuffix; 

        try {
            $this->db->beginTransaction();

            // 3. Register Base User Identity
            $insertUser = $this->db->prepare("INSERT INTO users (ksr_id, mobile_number) VALUES (?, ?)");
            $insertUser->execute([$ksrId, $mobile]);
            $userId = $this->db->lastInsertId();

            // 4. Attach Public Profile logic
            $insertProfile = $this->db->prepare("INSERT INTO user_profiles (user_id, display_name) VALUES (?, ?)");
            $insertProfile->execute([$userId, $name]);
            
            // 5. Assign DEFAULT 'USER' Role
            $insertRole = $this->db->prepare("INSERT INTO user_roles (user_id, role) VALUES (?, 'USER')");
            $insertRole->execute([$userId]);
            
            $this->db->commit();
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Database Registry Error: " . $e->getMessage());
            return $this->sendResponse(500, 'error', 'Critical Backend Fault generating account.');
        }

        // 6. Hook into Native PHP Session logic to bind the Auth
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['ksr_id'] = $ksrId;
        $_SESSION['display_name'] = $name;
        $_SESSION['role'] = 'USER';

        // 7. Fire response payload back to public/register.php Javascript client
        return $this->sendResponse(201, 'success', 'User successfully registered in KSR GURU JI v1.0.', [
            'user_id' => $userId,
            'ksr_id' => $ksrId,
            'display_name' => $name,
            'status' => 'active_session'
        ]);
    }

    /**
     * Executes physical MySQL login validation mapping via Mobile or KSR ID
     */
    public function loginUser(array $requestPayload): string {
        $identifier = $requestPayload['identifier'] ?? null;
        $pin = $requestPayload['pin'] ?? null; // Simulated PIN logic
        
        if (!$identifier) {
            return $this->sendResponse(400, 'error', 'Missing authentication identifier.');
        }

        try {
            // Intelligent query binding: Determine if they entered an @ksr ID or a raw Mobile Number
            $isKsrId = strpos($identifier, '@') === 0;
            $query = $isKsrId ? 
                "SELECT u.id, u.ksr_id, p.display_name, r.role FROM users u JOIN user_profiles p ON u.id = p.user_id JOIN user_roles r ON u.id = r.user_id WHERE u.ksr_id = ?" :
                "SELECT u.id, u.ksr_id, p.display_name, r.role FROM users u JOIN user_profiles p ON u.id = p.user_id JOIN user_roles r ON u.id = r.user_id WHERE u.mobile_number = ?";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();

            if (!$user) {
                return $this->sendResponse(401, 'error', 'Account not found or invalid credentials.');
            }

            // Hook Session Native Bind
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['ksr_id'] = $user['ksr_id'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['role'] = $user['role'];

            return $this->sendResponse(200, 'success', 'Login successful.', [
                'user_id' => $user['id'],
                'ksr_id' => $user['ksr_id'], 'display_name' => $user['display_name']
            ]);

        } catch (\Exception $e) {
            error_log("Login Query Error: " . $e->getMessage());
            return $this->sendResponse(500, 'error', 'Database validation failure.');
        }
    }

    /**
     * Standardized JSON API Responder
     */
    private function sendResponse(int $statusCode, string $status, string $message, array $data = []): string {
        http_response_code($statusCode);
        return json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}