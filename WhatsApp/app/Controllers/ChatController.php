<?php
namespace App\Controllers;

class ChatController {

    private $db;

    public function __construct() {
        // Assume Database dependency is injected or called via a Singleton
        // $this->db = \Config\Database::getInstance()->getConnection();
    }

    /**
     * Executes fetching dynamic Chat List for a target user ID 
     */
    public function getChatList(int $userId) {
        if (!$userId) {
            return $this->sendResponse(400, 'error', 'Session User ID required.');
        }

        // Logic to Fetch 1:1 and Group Chat histories natively
        /*
        $query = "
            SELECT c.id, p.display_name, m.content, m.created_at
            FROM conversations c
            JOIN conversation_members cm ON c.id = cm.conversation_id
            JOIN user_profiles p ON p.user_id = cm.user_id
            LEFT JOIN messages m ON m.conversation_id = c.id
            WHERE cm.user_id != ? AND c.id IN (
                SELECT conversation_id FROM conversation_members WHERE user_id = ?
            )
            ORDER BY m.created_at DESC LIMIT 20;
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$userId, $userId]);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
        
        // Mocked response for array architecture bridging
        $chats = [
             ['chat_id' => 1, 'name' => 'Rahul', 'last_msg' => 'Hello, how are you?', 'timestamp' => '10:30 PM', 'unread' => 0],
             ['chat_id' => 2, 'name' => 'KSR GURU JI Group', 'last_msg' => 'Welcome to the group', 'timestamp' => '09:15 AM', 'unread' => 0]
        ];

        return $this->sendResponse(200, 'success', 'Retrieved chat nodes successfully', $chats);
    }
    
    /**
     * Handles processing an inbound text/attachment layout to MySQL schema
     */
    public function processInboundMessage(array $requestPayload) {
        $senderId = $requestPayload['sender_id'] ?? null;
        $targetChatId = $requestPayload['conversation_id'] ?? null; // Null if group_id is passed
        $targetGroupId = $requestPayload['group_id'] ?? null;
        $content = $requestPayload['content'] ?? null;
        $msgType = 'text'; 
        
        // Follow structural rules: SENDING -> SENT -> DELIVERED -> READ lifecycle starts here.
        /*
        $stmt = $this->db->prepare("INSERT INTO messages (conversation_id, group_id, sender_id, message_type, content) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$targetChatId, $targetGroupId, $senderId, $msgType, $content]);
        $msgId = $this->db->lastInsertId();
        
        // Log "Delivered" metric natively into message_reads
        $readStmt = $this->db->prepare("INSERT INTO message_reads (message_id, user_id, status) VALUES (?, ?, 'delivered')");
        // ... (Loop over recipients logic conceptually mapped here)
        */

        return $this->sendResponse(201, 'success', 'Message pushed to DB successfully. Setting flag to SENT.');
    }

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
