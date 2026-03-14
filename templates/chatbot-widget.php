<!-- templates/chatbot-widget.php -->

<!-- Chat Bubble Button -->
<button id="chatBubble" class="btn btn-primary rounded-circle shadow-lg position-fixed" 
        style="bottom: 25px; right: 25px; width: 65px; height: 65px; z-index: 1050; font-size: 1.8rem;">
    💬
</button>

<!-- Chat Window -->
<div id="chatWidget" class="position-fixed bg-white rounded-4 shadow-xl overflow-hidden d-none" 
     style="bottom: 100px; right: 25px; width: 360px; height: 480px; z-index: 1050; border: 1px solid #dee2e6;">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
        <div>
            <strong>Support Chat</strong>
            <small class="d-block opacity-75">We reply fast</small>
        </div>
        <button id="closeChat" class="btn-close btn-close-white"></button>
    </div>

    <!-- Messages Area -->
    <div id="chatMessages" class="p-3 overflow-auto bg-light" style="height: calc(100% - 120px);">
        <!-- Messages will be added here -->
    </div>

    <!-- Input Area -->
    <div class="p-3 border-top bg-white">
        <div class="input-group">
            <input id="chatInput" type="text" class="form-control rounded-pill px-4" 
                   placeholder="Type your message..." autocomplete="off">
            <button id="sendBtn" class="btn btn-primary rounded-pill px-4 ms-2">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <small class="d-block text-center text-muted mt-2">Type your question about products, repairs, delivery...</small>
    </div>
</div>

<style>
    #chatBubble:hover { transform: scale(1.1); transition: 0.2s; }
    #chatWidget { max-height: 90vh; }
    .chat-message {
        margin: 10px 0;
        max-width: 80%;
        padding: 10px 14px;
        border-radius: 18px;
        line-height: 1.4;
    }
    .chat-user {
        background: #0d6efd;
        color: white;
        border-bottom-right-radius: 4px;
        margin-left: auto;
    }
    .chat-bot {
        background: #e9ecef;
        color: #333;
        border-bottom-left-radius: 4px;
    }
</style>

<script>
// Chatbot widget logic
document.addEventListener('DOMContentLoaded', () => {
    const bubble = document.getElementById('chatBubble');
    const widget = document.getElementById('chatWidget');
    const closeBtn = document.getElementById('closeChat');
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('sendBtn');
    const messages = document.getElementById('chatMessages');

    // Toggle chat
    bubble.addEventListener('click', () => {
        widget.classList.toggle('d-none');
        if (!widget.classList.contains('d-none')) input.focus();
    });

    closeBtn.addEventListener('click', () => {
        widget.classList.add('d-none');
    });

    // Send message
    const sendMessage = () => {
        const text = input.value.trim();
        if (!text) return;

        // Show user message
        const userMsg = document.createElement('div');
        userMsg.className = 'chat-message chat-user';
        userMsg.textContent = text;
        messages.appendChild(userMsg);
        messages.scrollTop = messages.scrollHeight;

        input.value = '';

        // Call backend API
        fetch(BASE_URL + '../api/v1/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const botMsg = document.createElement('div');
                botMsg.className = 'chat-message chat-bot';
                botMsg.textContent = data.reply;
                messages.appendChild(botMsg);
                messages.scrollTop = messages.scrollHeight;
            } else {
                addError("Sorry, something went wrong.");
            }
        })
        .catch(() => addError("Can't connect right now."));
    };

    const addError = (text) => {
        const err = document.createElement('div');
        err.className = 'chat-message chat-bot text-danger';
        err.textContent = text;
        messages.appendChild(err);
        messages.scrollTop = messages.scrollHeight;
    };

    sendBtn.addEventListener('click', sendMessage);

    input.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendMessage();
        }
    });
});
</script>