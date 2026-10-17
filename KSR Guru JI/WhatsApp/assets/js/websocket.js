class KSRSocket {
    constructor(url='ws://localhost:8080') {
        this.socket = new WebSocket(url);
        this.socket.onmessage = this.handleMessage.bind(this);
    }
    handleMessage(e) { console.log('Socket incoming:', e.data); }
    send(type, payload) { this.socket.send(JSON.stringify({type, payload})); }
}

