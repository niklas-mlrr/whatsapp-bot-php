<!DOCTYPE html>
<html>
<head>
    <title>Simple WebSocket Test</title>
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
</head>
<body>
    <h1>Simple WebSocket Test</h1>
    <div id="status">Initializing...</div>
    <div id="messages"></div>

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

        channel.bind('pusher:subscription_succeeded', function() {
            document.getElementById('status').innerHTML = 'Subscribed to private channel';
        });

        channel.bind('pusher:subscription_error', function(err) {
            document.getElementById('status').innerHTML = 'Subscription error: ' + JSON.stringify(err);
        });

        // Bind to test events
        channel.bind('TestEvent', function(data) {
            const messages = document.getElementById('messages');
            const message = document.createElement('div');
            message.innerHTML = '<strong>TestEvent:</strong> ' + JSON.stringify(data);
            messages.appendChild(message);
        });
    </script>
</body>
</html>
