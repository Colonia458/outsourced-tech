// assets/js/chatbot.js - Upgraded Floating Chatbot Widget

document.addEventListener('DOMContentLoaded', function () {
    // Create chat widget if not already in HTML
    if (!document.getElementById('chatbot')) {
        const chatDiv = document.createElement('div');
        chatDiv.id = 'chatbot';
        chatDiv.style.cssText = `
            position: fixed; bottom: 90px; right: 20px; width: 380px; max-width: calc(100vw - 40px);
            background: white; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none; z-index: 1050; overflow: hidden; font-family: 'Segoe UI', sans-serif;
        `;
        
        // Header
        chatDiv.innerHTML = `
            <div style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white; padding: 16px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    🤖
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 16px;">Outsourced Support</div>
                    <div style="font-size: 12px; opacity: 0.9;">Always here to help</div>
                </div>
                <button onclick="toggleChat()" style="background: none; border: none; color: white; font-size: 24px; cursor: pointer; opacity: 0.8;">×</button>
            </div>
            <div id="chatBody" style="height: 320px; overflow-y: auto; padding: 16px; background: #f8f9fa;"></div>
            <div id="quickReplies" style="padding: 8px 12px; background: white; border-top: 1px solid #eee; display: flex; flex-wrap: wrap; gap: 6px;"></div>
            <div style="display: flex; padding: 12px; border-top: 1px solid #eee; background: white;">
                <input id="chatMsg" type="text" placeholder="Type your message..." style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 24px; outline: none; font-size: 14px;">
                <button onclick="sendChat()" style="margin-left: 8px; width: 44px; height: 44px; background: #0d6efd; color: white; border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
        `;
        document.body.appendChild(chatDiv);

        // Open button
        const openBtn = document.createElement('button');
        openBtn.innerHTML = `
            <span style="position: relative;">
                💬
                <span id="chatBadge" style="display: none; position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: #dc3545; color: white; border-radius: 50%; font-size: 11px; display: flex; align-items: center; justify-content: center;">1</span>
            </span>
        `;
        openBtn.style.cssText = `
            position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); color: white; border: none; border-radius: 50%;
            font-size: 24px; cursor: pointer; box-shadow: 0 4px 20px rgba(13, 110, 253, 0.4); z-index: 1049;
            display: flex; align-items: center; justify-content: center; transition: transform 0.2s;
        `;
        openBtn.onmouseover = () => openBtn.style.transform = 'scale(1.1)';
        openBtn.onmouseout = () => openBtn.style.transform = 'scale(1)';
        openBtn.onclick = toggleChat;
        document.body.appendChild(openBtn);

        // Load chat history from localStorage
        loadChatHistory();
        
        // Show welcome message if no history
        setTimeout(() => {
            if (!localStorage.getItem('chatHistory')) {
                // Check if user is logged in
                const isLoggedIn = document.body.classList.contains('logged-in') || 
                                  document.querySelector('.user-menu') !== null ||
                                  document.querySelector('.dropdown-item[href*="logout"]') !== null;
                
                if (isLoggedIn) {
                    addMessage("👋 Welcome back! Great to see you again!", false);
                } else {
                    addMessage("👋 Hello! Welcome to Outsourced Technologies!", false);
                }
                
                setTimeout(() => {
                    if (isLoggedIn) {
                        addMessage("I can help you track orders, check your points, view your cart, and more! What would you like to do?", false);
                    } else {
                        addMessage("How can I help you today? Choose from the options below or type your question.", false);
                    }
                    showQuickReplies();
                }, 500);
            }
        }, 300);
    }
});

function toggleChat() {
    const chat = document.getElementById('chatbot');
    const isHidden = chat.style.display === 'none' || chat.style.display === '';
    chat.style.display = isHidden ? 'block' : 'none';
    
    if (isHidden) {
        document.getElementById('chatMsg').focus();
        // Clear unread badge
        const badge = document.getElementById('chatBadge');
        if (badge) badge.style.display = 'none';
    }
}

function addMessage(text, isUser = false, save = true) {
    const body = document.getElementById('chatBody');
    const div = document.createElement('div');
    div.style.margin = '10px 0';
    div.style.display = 'flex';
    div.style.justifyContent = isUser ? 'flex-end' : 'flex-start';
    
    const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    div.innerHTML = `
        <div style="max-width: 80%;">
            <div style="
                padding: 12px 16px; border-radius: ${isUser ? '18px 18px 4px 18px' : '18px 18px 18px 4px'};
                background: ${isUser ? 'linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%)' : 'white'}; 
                color: ${isUser ? 'white' : '#333'}; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                display: inline-block; max-width: 100%; word-wrap: break-word; font-size: 14px; line-height: 1.5;">
                ${text.replace(/\n/g, '<br>')}
            </div>
            <div style="font-size: 11px; color: #999; margin-top: 4px; text-align: ${isUser ? 'right' : 'left'};">
                ${time}
            </div>
        </div>
    `;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    
    // Save to localStorage
    if (save) {
        saveChatHistory();
    }
}

function showTyping() {
    const body = document.getElementById('chatBody');
    const div = document.createElement('div');
    div.style.margin = '10px 0';
    div.style.display = 'flex';
    div.innerHTML = `
        <div style="padding: 12px 16px; border-radius: 18px 18px 18px 4px; background: white; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; gap: 4px;">
                <div style="width: 8px; height: 8px; background: #999; border-radius: 50%; animation: typing 1.4s infinite;"></div>
                <div style="width: 8px; height: 8px; background: #999; border-radius: 50%; animation: typing 1.4s infinite 0.2s;"></div>
                <div style="width: 8px; height: 8px; background: #999; border-radius: 50%; animation: typing 1.4s infinite 0.4s;"></div>
            </div>
        </div>
    `;
    body.appendChild(div);
    body.scrollTop = body.scrollHeight;
    return div;
}

function showQuickReplies() {
    const container = document.getElementById('quickReplies');
    
    // Check if user is logged in
    const isLoggedIn = document.body.classList.contains('logged-in') || 
                      document.querySelector('.user-menu') !== null ||
                      document.querySelector('.dropdown-item[href*="logout"]') !== null;
    
    let options;
    
    if (isLoggedIn) {
        // Logged in user options
        options = [
            { text: '🛒 Products', value: 'products' },
            { text: '📦 My Orders', value: 'my orders' },
            { text: '⭐ My Points', value: 'loyalty points' },
            { text: '🛍️ My Cart', value: 'my cart' },
            { text: '🔧 Book Service', value: 'service' },
            { text: '📞 Contact', value: 'contact' }
        ];
    } else {
        // Guest user options
        options = [
            { text: '🛒 Products', value: 'products' },
            { text: '💰 Prices', value: 'prices' },
            { text: '📦 Track Order', value: 'track order' },
            { text: '🔧 Book Service', value: 'service' },
            { text: '📞 Contact', value: 'contact' },
            { text: '⭐ Loyalty', value: 'loyalty program' }
        ];
    }
    
    container.innerHTML = options.map(opt => `
        <button onclick="quickReply('${opt.value}')" style="
            padding: 6px 12px; font-size: 12px; background: #f0f0f0; border: none; border-radius: 16px; 
            cursor: pointer; transition: all 0.2s; color: #333;
        " onmouseover="this.style.background='#e0e0e0'" onmouseout="this.style.background='#f0f0f0'">
            ${opt.text}
        </button>
    `).join('');
}

function quickReply(value) {
    let msg = '';
    switch(value) {
        case 'products': msg = 'Show me products'; break;
        case 'prices': msg = 'What are your prices?'; break;
        case 'track order': msg = 'I want to track my order'; break;
        case 'my orders': msg = 'Show my orders'; break;
        case 'my cart': msg = 'Show my cart'; break;
        case 'loyalty points': msg = 'Show my loyalty points'; break;
        case 'service': msg = 'I want to book a service'; break;
        case 'contact': msg = 'What are your contact details?'; break;
        default: msg = value;
    }
    document.getElementById('chatMsg').value = msg;
    sendChat();
}

function sendChat() {
    const input = document.getElementById('chatMsg');
    const msg = input.value.trim();
    if (!msg) return;

    addMessage(msg, true);
    input.value = '';
    
    // Hide quick replies
    document.getElementById('quickReplies').innerHTML = '';
    
    // Show typing indicator
    const typingDiv = showTyping();

    fetch(BASE_URL + '../api/v1/chatbot.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg })
    })
    .then(response => response.json())
    .then(data => {
        typingDiv.remove();
        if (data.success) {
            addMessage(data.reply, false);
            // Show quick replies again
            showQuickReplies();
        } else {
            addMessage("Sorry, I didn't understand that. Can you try again?", false);
        }
    })
    .catch(() => {
        typingDiv.remove();
        addMessage("Can't connect right now. Please check your internet connection.", false);
    });
}

function saveChatHistory() {
    const body = document.getElementById('chatBody');
    localStorage.setItem('chatHistory', body.innerHTML);
    localStorage.setItem('chatScroll', body.scrollTop);
}

function loadChatHistory() {
    const body = document.getElementById('chatBody');
    const history = localStorage.getItem('chatHistory');
    if (history) {
        body.innerHTML = history;
        setTimeout(() => {
            body.scrollTop = parseInt(localStorage.getItem('chatScroll') || 0);
            showQuickReplies();
        }, 100);
    }
}

// Allow pressing Enter to send
document.addEventListener('keypress', e => {
    if (e.key === 'Enter' && document.activeElement.id === 'chatMsg') {
        sendChat();
    }
});

// Add typing animation CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes typing {
        0%, 60%, 100% { transform: translateY(0); }
        30% { transform: translateY(-4px); }
    }
`;
document.head.appendChild(style);
