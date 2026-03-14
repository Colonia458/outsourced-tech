</main>

<footer class="bg-dark text-white text-center py-5 mt-auto">
    <div class="container">
        <p class="mb-2">© <?= date('Y') ?> <?= APP_NAME ?> – Mlolongo, Kenya</p>
        <p class="mb-0 small text-white-50">
            Networking • Computers • Phones • Repairs • ISP Services
        </p>
    </div>
</footer>

<!-- Chatbot Widget -->
<div id="chatbot" class="chat-window d-none">
    <div class="chat-header">
        <strong>Live Support</strong>
        <button id="closeChat" class="btn-close btn-close-white"></button>
    </div>
    <div id="chatBody" class="chat-body"></div>
    <div class="chat-input">
        <input id="chatMsg" type="text" placeholder="Type your message..." autocomplete="off">
        <button id="sendBtn" class="btn btn-primary">Send</button>
    </div>
</div>

<!-- Chat Bubble -->
<button id="chatBubble" type="button" class="chat-bubble">💬</button>

<style>
    .chat-bubble {
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 65px;
        height: 65px;
        background: #0d6efd;
        color: white;
        border: none;
        border-radius: 50%;
        font-size: 1.8rem;
        cursor: pointer;
        box-shadow: 0 6px 20px rgba(0,0,0,0.25);
        z-index: 1050;
        transition: transform 0.2s;
    }
    .chat-bubble:hover { transform: scale(1.1); }

    .chat-window {
        position: fixed;
        bottom: 100px;
        right: 25px;
        width: 360px;
        height: 480px;
        max-height: 90vh;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        z-index: 1050;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #dee2e6;
    }

    .chat-header {
        background: #0d6efd;
        color: white;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
    }

    .chat-body {
        flex: 1;
        padding: 16px;
        overflow-y: auto;
        background: #f8f9fa;
    }

    .chat-message {
        margin: 10px 0;
        max-width: 82%;
        padding: 10px 14px;
        border-radius: 18px;
        line-height: 1.4;
        word-wrap: break-word;
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

    .chat-input {
        display: flex;
        padding: 12px;
        border-top: 1px solid #dee2e6;
        background: white;
    }
    .chat-input input {
        flex: 1;
        padding: 10px 16px;
        border: 1px solid #ced4da;
        border-radius: 50px 0 0 50px;
        outline: none;
    }
    .chat-input button {
        border-radius: 0 50px 50px 0;
        padding: 0 20px;
    }
</style>

<script>
// Chatbot logic
(function() {
    const chatbot = document.getElementById('chatbot');
    const chatBody = document.getElementById('chatBody');
    const chatMsg = document.getElementById('chatMsg');
    const sendBtn = document.getElementById('sendBtn');
    const chatBubble = document.getElementById('chatBubble');
    const closeChat = document.getElementById('closeChat');

    // Track if greeting has been shown
    let greetingShown = false;

    // Toggle chat
    chatBubble.addEventListener('click', () => {
        chatbot.classList.toggle('d-none');
        if (!chatbot.classList.contains('d-none')) {
            chatMsg.focus();
            // Only show greeting once per session
            if (!greetingShown) {
                addMessage("Hello! 👋 How can I help you today?", false);
                greetingShown = true;
            }
        }
    });

    closeChat.addEventListener('click', () => chatbot.classList.add('d-none'));

    // Send message
    const sendChat = () => {
        const msg = chatMsg.value.trim();
        if (!msg) return;

        addMessage(msg, true);
        chatMsg.value = '';

        // Show typing indicator
        const typing = document.createElement('div');
        typing.className = 'chat-message chat-bot';
        typing.innerHTML = '<em>Typing...</em>';
        chatBody.appendChild(typing);
        chatBody.scrollTop = chatBody.scrollHeight;

        fetch('../api/v1/chatbot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: msg })
        })
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(data => {
            typing.remove();
            if (data.success) {
                addMessage(data.reply, false);
            } else {
                addMessage("Sorry, something went wrong. Try again.", false);
            }
        })
        .catch(() => {
            typing.remove();
            addMessage("Can't connect right now. Please try again.", false);
        });
    };

    const addMessage = (text, isUser = false) => {
        const div = document.createElement('div');
        div.className = `chat-message ${isUser ? 'chat-user' : 'chat-bot'}`;
        div.textContent = text;
        chatBody.appendChild(div);
        chatBody.scrollTop = chatBody.scrollHeight;
    };

    // Events
    sendBtn.addEventListener('click', sendChat);

    chatMsg.addEventListener('keypress', e => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChat();
        }
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/config.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>