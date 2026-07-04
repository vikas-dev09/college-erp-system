<?php
ob_start();
session_start();

// ================== CONFIG ==================
$NEWS_API_KEY = 'pub_57fec3f557ab4478b29c44f5df793433';
$GEMINI_API_KEY = 'AIzaSyDYKgM87shqD1cu_zClWLazv5wokdMnct4';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Current Affairs Agent • AUREON</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #7c3aed;
            --secondary: #8b5cf6;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #fdfbff;
            color: #334155;
            min-height: 100vh;
        }

        header {
            background: white;
            padding: 1.2rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(124, 58, 237, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo {
            font-size: 1.85rem;
            font-weight: 700;
            background: linear-gradient(90deg, #7c3aed, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .dashboard-btn {
            background: rgba(124, 58, 237, 0.1);
            color: #7c3aed;
            padding: 10px 18px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid rgba(124, 58, 237, 0.2);
            transition: all 0.3s;
        }

        .dashboard-btn:hover {
            background: #7c3aed;
            color: white;
        }

        .container { 
            max-width: 1280px; 
            margin: 0 auto; 
            padding: 2rem 5%; 
        }

        .controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
        }

        select, input, button {
            padding: 14px 18px;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            color: #334155;
        }

        button {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }

        button:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.25);
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 2rem;
        }

        .news-card {
            background: rgba(255,255,255,0.85);
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            overflow: hidden;
            transition: all 0.4s;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
        }

        .news-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 25px 50px rgba(124, 58, 237, 0.15);
        }

        .news-img {
            height: 210px;
            background-size: cover;
            background-position: center;
        }

        .news-content { 
            padding: 1.6rem; 
        }

        .news-date { 
            font-size: 0.85rem; 
            color: #64748b; 
            margin-bottom: 0.8rem; 
        }

        .ai-summary {
            background: #f8fafc;
            padding: 1.1rem;
            border-radius: 14px;
            margin: 1rem 0;
            font-size: 0.97rem;
            line-height: 1.55;
            border: 1px solid #e2e8f0;
            color: #334155;
        }

        .loading {
            text-align: center;
            padding: 4rem;
            font-size: 1.2rem;
            grid-column: 1 / -1;
            color: #64748b;
        }
    </style>
</head>
<body>

<header>
    <div class="logo">AUREON <span style="font-size:1.1rem; opacity:0.8;">Current Affairs AI</span></div>
    
    <div style="display:flex; gap:15px; align-items:center;">
        <a href="student_dash.php" class="dashboard-btn">
            ⬅ Back to Dashboard
        </a>
    </div>
</header>

<div class="container">
    <h1 style="font-size:2.7rem; margin-bottom:8px; color:#1e2937;">Today's Current Affairs</h1>
    <p style="color:#64748b; margin-bottom:2rem;">Gemini AI Powered • Real-time News for Competitive Exams</p>

    <div class="controls">
        <select id="country" onchange="fetchNews()">
            <option value="in">🇮🇳 India</option>
            <option value="us">🇺🇸 USA</option>
            <option value="gb">🇬🇧 UK</option>
            <option value="au">🇦🇺 Australia</option>
            <option value="ca">🇨🇦 Canada</option>
            <option value="jp">🇯🇵 Japan</option>
        </select>
        <input type="text" id="search" placeholder="Search (UPSC, Budget, Election, Defence...)" style="width:380px;">
        <button onclick="fetchNews()"><i class="fa-solid fa-arrow-rotate-right"></i> Refresh</button>
    </div>

    <div id="newsContainer" class="news-grid"></div>
</div>

<script>
// API Keys
const NEWS_API_KEY = "<?php echo $NEWS_API_KEY; ?>";
const GEMINI_API_KEY = "<?php echo $GEMINI_API_KEY; ?>";

async function fetchNews() {
    const container = document.getElementById('newsContainer');
    const country = document.getElementById('country').value;
    const query = document.getElementById('search').value.trim();

    container.innerHTML = `<div class="loading">Fetching latest news...</div>`;

    let url = `https://newsdata.io/api/1/news?apikey=${NEWS_API_KEY}&country=${country}&language=en`;

    if (query) {
        url = `https://newsdata.io/api/1/news?apikey=${NEWS_API_KEY}&q=${encodeURIComponent(query)}&language=en`;
    }

    try {
        const response = await fetch(url);
        const data = await response.json();

        container.innerHTML = '';

        if (data.status === "success" && data.results?.length > 0) {
            data.results.slice(0, 9).forEach(article => {
                const card = document.createElement('div');
                card.className = 'news-card';
                card.innerHTML = `
                    <div class="news-img" style="background-image: url('${article.image_url || "https://via.placeholder.com/600x400/e2e8f0/64748b?text=News"}')"></div>
                    <div class="news-content">
                        <div class="news-date">${article.pubDate ? new Date(article.pubDate).toLocaleDateString('en-IN', {dateStyle: 'medium'}) : 'Recent'}</div>
                        <h3 style="margin-bottom:1rem; line-height:1.35; color:#1e2937;">${article.title}</h3>
                        
                        <div class="ai-summary" id="summary-${Math.random().toString(36).substr(2,9)}">
                            <i class="fa-solid fa-spinner fa-spin"></i> Gemini AI is summarizing...
                        </div>
                        
                        <button onclick="window.open('${article.link}', '_blank')" 
                                style="width:100%; padding:14px; background:linear-gradient(135deg,#7c3aed,#8b5cf6); color:white; border:none; border-radius:12px; font-weight:600;">
                            Read Full Article →
                        </button>
                    </div>
                `;
                container.appendChild(card);

                const summaryId = card.querySelector('.ai-summary').id;
                generateGeminiSummary(article.title + ". " + (article.description || article.content || ""), summaryId);
            });
        } else {
            container.innerHTML = `<p style="grid-column:1/-1; text-align:center; padding:4rem; color:#64748b;">No news found. Try changing country or search term.</p>`;
        }
    } catch (error) {
        container.innerHTML = `<p style="grid-column:1/-1; text-align:center; color:#ef4444;">Failed to load news. Please check your connection.</p>`;
    }
}

async function generateGeminiSummary(text, elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const prompt = `Summarize this news for UPSC/SSC/Bank exam students in simple Hindi + English mix (max 75 words). Focus on facts important for exams:

${text}`;

    try {
        const response = await fetch(
            `https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=${GEMINI_API_KEY}`,
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    contents: [{ parts: [{ text: prompt }] }]
                })
            }
        );

        const data = await response.json();
        
        if (data.candidates && data.candidates[0]) {
            let summary = data.candidates[0].content.parts[0].text;
            element.innerHTML = `<strong>🧠 Gemini AI Summary:</strong><br>${summary.replace(/\n/g, '<br>')}`;
        } else {
            throw new Error("No response");
        }
    } catch (e) {
        element.innerHTML = `<strong>🧠 AI Summary:</strong><br>Important for competitive exams. ${text.substring(0, 160)}...`;
    }
}

// Load news on page start
window.onload = () => fetchNews();

// Search with Enter key
document.getElementById('search').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') fetchNews();
});
</script>

</body>
</html>