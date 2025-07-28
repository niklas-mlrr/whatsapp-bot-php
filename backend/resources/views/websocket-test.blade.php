<!DOCTYPE html>
<html>
<head>
    <title>WebSocket Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .status { padding: 10px; margin: 10px 0; border-radius: 4px; font-weight: bold; }
        .connected { background-color: #d4edda; color: #155724; }
        .connecting { background-color: #fff3cd; color: #856404; }
        .disconnected { background-color: #f8d7da; color: #721c24; }
        .error { background-color: #f8d7da; color: #721c24; }
        #messages { 
            margin: 20px 0; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            min-height: 100px; 
            max-height: 400px; 
            overflow-y: auto; 
            background-color: #f9f9f9;
        }
        .message { 
            padding: 8px 12px; 
            margin: 5px 0; 
            border-bottom: 1px solid #eee; 
            background-color: white;
            border-radius: 3px;
        }
        .message:last-child {
            border-bottom: none;
        }
        button { 
            padding: 10px 15px; 
            background-color: #4CAF50; 
            color: white; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        button:hover { 
            background-color: #45a049; 
            opacity: 0.9;
        }
        button:disabled { 
            background-color: #cccccc; 
            cursor: not-allowed;
        }
        .button-group {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .secondary {
            background-color: #6c757d;
        }
        .secondary:hover {
            background-color: #5a6268;
        }
        .timestamp {
            font-size: 0.8em;
            color: #6c757d;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>WebSocket Test</h1>

    <script>
        // Use the app key passed from the server
        const appKey = '{{ $appKey }}';
        
        // Initialize Pusher client
        const pusher = new Pusher(appKey, {
            cluster: 'mt1',
            wsHost: window.location.hostname,
            wsPort: 8080,
            forceTLS: false,
            encrypted: false,
            disableStats: true,
            enabledTransports: ['ws'],
            authEndpoint: '/broadcasting/auth',
        });

        // Check connection state
        pusher.connection.bind('connected', function() {
            document.getElementById('status').innerHTML = 'Connected to WebSocket server';
        });

        pusher.connection.bind('disconnected', function() {
            document.getElementById('status').innerHTML = 'Disconnected from WebSocket server';
        });

        pusher.connection.bind('error', function(err) {
            document.getElementById('status').innerHTML = 'Connection error: ' + JSON.stringify(err);
        });

        // Subscribe to a private channel
        const channel = pusher.subscribe('private-test-channel');
        function addMessage(message, type = 'info') {
            const messageElement = document.createElement('div');
            messageElement.className = `message ${type}`;
            
            const timestamp = new Date().toLocaleTimeString();
            messageElement.innerHTML = `
                <span class="timestamp">[${timestamp}]</span>
                <span class="message-text">${message}</span>
            `;
            
            messagesContainer.prepend(messageElement);
        }

        // Connect to WebSocket
        function connectWebSocket() {
            updateStatus('connecting', 'Connecting to WebSocket server...');
            
            if (socket) {
                socket.close();
            }
            
            const wsUrl = `${config.wsProtocol}://${config.wsHost}:${config.wsPort}/app/${config.appKey}?protocol=7&client=js&version=7&flash=false`;
            
            try {
                socket = new WebSocket(wsUrl);
                
                socket.onopen = function() {
                    addMessage('WebSocket connection established', 'success');
                    updateStatus('connected', 'Connected to WebSocket server');
                    isConnected = true;
                };
                
                socket.onmessage = function(event) {
                    try {
                        const data = JSON.parse(event.data);
                        console.log('Received message:', data);
                        
                        switch (data.event) {
                            case 'pusher:connection_established':
                                const socketId = JSON.parse(data.data).socket_id;
                                addMessage(`Connection established with socket ID: ${socketId}`, 'success');
                                authenticate(socketId);
                                break;
                                
                            case 'pusher_internal:subscription_succeeded':
                                addMessage(`Subscribed to channel: ${data.channel}`, 'success');
                                break;
                                
                            case 'test.event':
                                addMessage(`Event received: ${JSON.stringify(data.data, null, 2)}`, 'event');
                                break;
                                
                            case 'pusher:error':
                                const errorData = typeof data.data === 'string' ? JSON.parse(data.data) : data.data;
                                addMessage(`Error: ${errorData.message} (${errorData.code})`, 'error');
                                break;
                                
                            default:
                                addMessage(`Unknown event: ${data.event}`, 'warning');
                                console.log('Unknown event data:', data);
                        }
                    } catch (error) {
                        console.error('Error processing message:', error);
                        addMessage(`Error processing message: ${error.message}`, 'error');
                    }
                };
                
                socket.onerror = function(error) {
                    console.error('WebSocket error:', error);
                    addMessage(`WebSocket error: ${error.message || 'Unknown error'}`, 'error');
                    updateStatus('error', 'Connection error');
                    isConnected = false;
                };
                
                socket.onclose = function() {
                    console.log('WebSocket connection closed');
                    addMessage('Disconnected from WebSocket server', 'warning');
                    updateStatus('disconnected', 'Disconnected from WebSocket server');
                    isConnected = false;
                };
                
            } catch (error) {
                console.error('Error creating WebSocket:', error);
                addMessage(`Error creating WebSocket: ${error.message}`, 'error');
                updateStatus('error', 'Connection failed');
                isConnected = false;
            }
        }

        // Authenticate with the server
        async function authenticate(socketId) {
            try {
                addMessage('Authenticating with server...');
                
                const response = await fetch('/test-auth', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: config.channelName
                    })
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
                
                const authData = await response.json();
                console.log('Auth response:', authData);
                
                if (!authData.auth) {
                    throw new Error('No auth data in response');
                }
                
                // Subscribe to the private channel
                const subscribeMsg = {
                    event: 'pusher:subscribe',
                    data: {
                        auth: authData.auth,
                        channel: config.channelName
                    }
                };
                
                console.log('Subscribing to channel:', subscribeMsg);
                socket.send(JSON.stringify(subscribeMsg));
                
                addMessage('Authentication successful', 'success');
                
            } catch (error) {
                console.error('Authentication failed:', error);
                addMessage(`Authentication failed: ${error.message}`, 'error');
                updateStatus('error', 'Authentication failed');
                
                if (socket) {
                    socket.close();
                }
            }
        }

        // Send a test message
        function sendTestMessage() {
            if (!isConnected || !socket) {
                addMessage('Not connected to WebSocket server', 'error');
                return;
            }
            
            try {
                const message = {
                    event: 'client-test',
                    channel: config.channelName,
                    data: JSON.stringify({
                        message: 'Test message from client',
                        timestamp: new Date().toISOString()
                    })
                };
                
                console.log('Sending test message:', message);
                socket.send(JSON.stringify(message));
                addMessage('Sent test message', 'info');
                
            } catch (error) {
                console.error('Error sending test message:', error);
                addMessage(`Error sending test message: ${error.message}`, 'error');
            }
        }

        // Test broadcast from server
        async function testBroadcast() {
            try {
                addMessage('Sending test broadcast...');
                
                const response = await fetch('/trigger-test-event', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
                
                const result = await response.json();
                console.log('Broadcast test result:', result);
                addMessage('Test broadcast sent successfully', 'success');
                
            } catch (error) {
                console.error('Error testing broadcast:', error);
                addMessage(`Error testing broadcast: ${error.message}`, 'error');
            }
        }

        // Event Listeners
        connectBtn.addEventListener('click', connectWebSocket);
        
        disconnectBtn.addEventListener('click', function() {
            if (socket) {
                socket.close();
            }
        });
        
        sendTestBtn.addEventListener('click', sendTestMessage);
        testBroadcastBtn.addEventListener('click', testBroadcast);

        // Initialize
        updateStatus('disconnected', 'Disconnected');
        addMessage('Click "Connect" to start the WebSocket connection.', 'info');
    </script>
</body>
</html>
