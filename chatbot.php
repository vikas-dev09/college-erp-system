<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AI Assistant Chat - Student ERP</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.3/dist/tailwind.min.css" rel="stylesheet" />
  
  <style>
    body {
      background: #fdfbff;
      font-family: 'Inter', sans-serif;
      color: #334155;
    }

    .chat-header {
      background: linear-gradient(135deg, #7c3aed, #8b5cf6);
      padding: 16px 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
      font-weight: 700;
      box-shadow: 0 10px 25px rgba(124, 58, 237, 0.25);
    }

    .dashboard-btn {
      background: rgba(255,255,255,0.25);
      padding: 8px 16px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,0.4);
      color: white;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s;
    }

    .dashboard-btn:hover {
      background: rgba(255,255,255,0.35);
      transform: translateY(-1px);
    }

    .chat-wrapper {
      max-width: 820px;
      margin: 30px auto;
      background: rgba(255,255,255,0.95);
      border-radius: 20px;
      box-shadow: 0 20px 50px rgba(124, 58, 237, 0.12);
      overflow: hidden;
      border: 1px solid #e2e8f0;
    }

    #chatbox {
      height: 68vh;
      overflow-y: auto;
      padding: 20px;
      background: #fdfbff;
    }

    #chatbox div {
      padding: 12px 16px;
      margin: 10px 0;
      border-radius: 16px;
      max-width: 78%;
      font-size: 15px;
      line-height: 1.55;
    }

    /* User Message */
    .self-end {
      background: #7c3aed;
      color: white;
      margin-left: auto;
      border-bottom-right-radius: 6px;
    }

    /* Bot Message */
    .self-start {
      background: white;
      border: 1px solid #e2e8f0;
      margin-right: auto;
      border-bottom-left-radius: 6px;
      color: #334155;
    }

    .input-box {
      display: flex;
      padding: 16px;
      gap: 12px;
      border-top: 1px solid #e2e8f0;
      background: white;
    }

    input {
      flex: 1;
      padding: 14px 18px;
      border-radius: 14px;
      border: 1px solid #e2e8f0;
      outline: none;
      font-size: 15px;
    }

    input:focus {
      border-color: #8b5cf6;
      box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    }

    button {
      background: linear-gradient(135deg, #7c3aed, #8b5cf6);
      color: white;
      padding: 14px 24px;
      border-radius: 14px;
      font-weight: 600;
      border: none;
      cursor: pointer;
      transition: all 0.3s;
    }

    button:hover {
      transform: scale(1.05);
      box-shadow: 0 10px 20px rgba(124, 58, 237, 0.3);
    }
  </style>
</head>
<body>

  <!-- HEADER -->
  <div class="chat-header">
    <div class="flex items-center gap-3">
      <span class="text-2xl">🤖</span>
      <div>AI Assistant - Student ERP</div>
    </div>
    <a href="student_dash.php" class="dashboard-btn">
      ⬅ Back to Dashboard
    </a>
  </div>

  <!-- CHAT CONTAINER -->
  <div class="chat-wrapper">
    <div id="chatbox"></div>

    <div class="input-box">
      <input id="messageInput" type="text" placeholder="Ask about attendance, marks, timetable, assignments..." />
      <button id="sendButton">Send</button>
    </div>
  </div>

<script>
  const chatbox = document.getElementById("chatbox");
  const messageInput = document.getElementById("messageInput");
  const sendButton = document.getElementById("sendButton");
  const chatId = crypto.randomUUID();

  let receiving = false;
  const systemPrompt = "Your persona is a smart AI assistant like ChatGPT. You are knowledgeable, helpful, and accurate in your responses. You excel in: - Providing answers to general knowledge questions - Offering programming and coding assistance - Explaining college subjects in a clear manner In addition, you serve as a college ERP assistant by providing support with: - Student attendance - Marks and grades - Timetable inquiries (based on available data) Ensure that your responses are detailed, step-by-step when necessary, and always aim to help the user in the best way possible.";

  function createMessageElement(text, alignment) {
    const messageElement = document.createElement("div");
    messageElement.className = alignment === "left" ? "self-start" : "self-end";
    messageElement.textContent = text;
    return messageElement;
  }

  function connectWebSocket(message) {
    receiving = true;
    const url = "wss://backend.buildpicoapps.com/api/chatbot/chat";
    const websocket = new WebSocket(url);

    websocket.addEventListener("open", () => {
      websocket.send(JSON.stringify({
        chatId: chatId,
        appId: "blood-thousand",
        systemPrompt: systemPrompt,
        message: message,
      }));
    });

    const messageElement = createMessageElement("", "left");
    chatbox.appendChild(messageElement);

    websocket.onmessage = (event) => {
      messageElement.innerText += event.data;
      chatbox.scrollTop = chatbox.scrollHeight;
    };

    websocket.onclose = (event) => {
      receiving = false;
      if (event.code !== 1000) {
        messageElement.textContent += "\n\nError: Connection closed. Please try again.";
      }
    };
  }

  sendButton.addEventListener("click", () => {
    if (!receiving && messageInput.value.trim() !== "") {
      const messageText = messageInput.value.trim();
      messageInput.value = "";

      const userMessage = createMessageElement(messageText, "right");
      chatbox.appendChild(userMessage);
      chatbox.scrollTop = chatbox.scrollHeight;

      connectWebSocket(messageText);
    }
  });

  messageInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !receiving && messageInput.value.trim() !== "") {
      event.preventDefault();
      sendButton.click();
    }
  });

  // Welcome Message
  window.onload = () => {
    const welcome = createMessageElement("Hello! 👋 How can I help you today with your studies or ERP system?", "left");
    chatbox.appendChild(welcome);
  };
</script>

</body>
</html>