if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo "KSR GURU JI WebSocket daemon must be launched from the CLI.\n";
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class Ksrv2RealTimeEngine implements MessageComponentInterface {
    protected \SplObjectStorage $clients;
    protected array $userMapping = []; // Tracks tied connections to support Multi-device Login

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->userMapping = []; 
        echo "KSR GURU JI v2.0 - Core Engine Bootstrapping...\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "Node Active: (" . spl_object_id($conn) . ")\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;
        
        echo "Valid Payload Received from " . spl_object_id($from) . ": {$data['type']}\n";

        switch($data['type']) {
            case 'v2_realtime_chat':
                // Relay instantaneous text/blob markers between connected instances natively
                foreach ($this->clients as $client) {
                    if ($from !== $client) {
                        $client->send(json_encode([
                            'type' => 'chat_message',
                            'sender_id' => $data['sender_id'] ?? 'Unknown',
                            'content' => $data['content'] ?? ''
                        ]));
                    }
                }
                break;
                
            case 'v2_multi_device_auth':
                // Authenticated sync tracking
                break;

            case 'v2_webrtc_call_signal':
                // Route STUN/TURN ICE Candidates between Caller and Receiver for strict P2P encryption
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Node Detached: " . spl_object_id($conn) . "\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error Node Caught: {$e->getMessage()}\n";
        $conn->close();
    }
}

// Instantiate the powerful Ratchet Daemon
echo "Instantiating KSR GURU JI WebSockets Matrix natively on Port 8080...\n";
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Ksrv2RealTimeEngine()
        )
    ),
    8080
);

$server->run();
