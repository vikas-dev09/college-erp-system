<?php
ob_start();
session_start();

/* ═══════════ DATABASE ═══════════ */
$pdo = new PDO("mysql:host=localhost;dbname=aureon;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

/* ═══════════ AUTO CREATE / UPDATE TABLE ═══════════ */
$pdo->exec("CREATE TABLE IF NOT EXISTS `gallery` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(100) DEFAULT 'Campus',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `image_name` VARCHAR(255) NULL,
    `image_path` VARCHAR(500) NOT NULL,
    `image_size` VARCHAR(50) NULL,
    `event_date` DATE NULL,
    `views_count` INT DEFAULT 0,
    `likes_count` INT DEFAULT 0,
    `added_on` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns if missing (for old tables)
try { $pdo->exec("ALTER TABLE gallery ADD COLUMN IF NOT EXISTS views_count INT NOT NULL DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE gallery ADD COLUMN IF NOT EXISTS likes_count INT NOT NULL DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE gallery ADD COLUMN IF NOT EXISTS event_date DATE NULL"); } catch(Exception $e) {}

/* ═══════════ INIT SESSION TRACKING ═══════════ */
if (!isset($_SESSION['gal_views'])) $_SESSION['gal_views'] = [];
if (!isset($_SESSION['gal_likes'])) $_SESSION['gal_likes'] = [];

/* ═══════════ AJAX: VIEW / LIKE / SHARE ═══════════ */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid ID']);
        exit;
    }

    // Check image exists
    $check = $pdo->prepare("SELECT id, views_count, likes_count FROM gallery WHERE id = ?");
    $check->execute([$id]);
    $row = $check->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['ok' => false, 'msg' => 'Image not found']);
        exit;
    }

    if ($action === 'view') {
        if (!in_array($id, $_SESSION['gal_views'])) {
            $pdo->prepare("UPDATE gallery SET views_count = views_count + 1 WHERE id = ?")->execute([$id]);
            $_SESSION['gal_views'][] = $id;
            $newViews = $row['views_count'] + 1;
        } else {
            $newViews = $row['views_count'];
        }
        echo json_encode(['ok' => true, 'views' => $newViews]);
        exit;
    }

    if ($action === 'like') {
        if (in_array($id, $_SESSION['gal_likes'])) {
            echo json_encode(['ok' => false, 'msg' => 'Already liked', 'already' => true, 'likes' => $row['likes_count']]);
            exit;
        }
        $pdo->prepare("UPDATE gallery SET likes_count = likes_count + 1 WHERE id = ?")->execute([$id]);
        $_SESSION['gal_likes'][] = $id;
        $newLikes = $row['likes_count'] + 1;
        echo json_encode(['ok' => true, 'likes' => $newLikes]);
        exit;
    }

    if ($action === 'share') {
        echo json_encode(['ok' => true, 'url' => $_GET['url'] ?? '']);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action']);
    exit;
}

/* ═══════════ FETCH GALLERY DATA ═══════════ */
$category = $_GET['cat'] ?? 'All';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM gallery WHERE 1=1";
$params = [];

if ($category !== 'All' && $category !== '') {
    $sql .= " AND category = ?";
    $params[] = $category;
}
if ($search !== '') {
    $sql .= " AND (title LIKE ? OR description LIKE ? OR category LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$sql .= " ORDER BY added_on DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalPhotos = (int)$pdo->query("SELECT COUNT(*) FROM gallery")->fetchColumn();
$totalViews = (int)($pdo->query("SELECT SUM(views_count) FROM gallery")->fetchColumn() ?: 0);
$totalLikes = (int)($pdo->query("SELECT SUM(likes_count) FROM gallery")->fetchColumn() ?: 0);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Life at Aureon — Campus Gallery</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"/>
<style>
/* ════════════════════════════════════════════════
   DESIGN SYSTEM
════════════════════════════════════════════════ */
:root {
    --primary: #7c3aed;
    --primary-dark: #6d28d9;
    --primary-light: #a78bfa;
    --primary-pale: #ede9fe;
    --primary-glow: rgba(124,58,237,0.12);
    --accent: #ec4899;
    --accent-pale: #fdf2f8;
    --heart: #ef4444;
    --heart-pale: #fef2f2;
    --teal: #14b8a6;
    --blue: #3b82f6;
    --dark: #0f172a;
    --text: #334155;
    --muted: #64748b;
    --dim: #94a3b8;
    --border: #e2e8f0;
    --light: #f8fafc;
    --white: #ffffff;
    --bg: linear-gradient(160deg, #faf5ff 0%, #fff1f2 30%, #fefce8 60%, #f0fdf4 100%);
    --glass: rgba(255,255,255,0.72);
    --glass-border: rgba(255,255,255,0.85);
    --shadow-sm: 0 4px 12px rgba(0,0,0,0.04);
    --shadow: 0 12px 35px rgba(0,0,0,0.06);
    --shadow-lg: 0 25px 60px rgba(0,0,0,0.1);
    --shadow-glow: 0 15px 40px rgba(124,58,237,0.15);
    --radius: 24px;
    --radius-md: 18px;
    --radius-sm: 14px;
    --radius-pill: 100px;
}

*, *::before, *::after {
    margin: 0; padding: 0; box-sizing: border-box;
}

html { scroll-behavior: smooth; }

body {
    font-family: 'Inter', -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}

/* ════════════════════════════════════════════════
   ANIMATED BACKGROUND ORBS
════════════════════════════════════════════════ */
.bg-orbs {
    position: fixed; inset: 0; z-index: 0;
    pointer-events: none; overflow: hidden;
}

.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.12;
    animation: orbFloat 20s ease-in-out infinite;
}

.orb-1 { width: 500px; height: 500px; background: #7c3aed; top: -10%; left: -5%; }
.orb-2 { width: 400px; height: 400px; background: #ec4899; bottom: -8%; right: -3%; animation-delay: 4s; }
.orb-3 { width: 350px; height: 350px; background: #06b6d4; top: 40%; right: 20%; animation-delay: 8s; }

@keyframes orbFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -40px) scale(1.05); }
    66% { transform: translate(-20px, 25px) scale(0.95); }
}

/* ════════════════════════════════════════════════
   HEADER
════════════════════════════════════════════════ */
.header {
    position: relative; z-index: 2;
    text-align: center;
    padding: 50px 20px 30px; /* More spacing */
}

.logo-ring {
    width: 120px; height: 120px; /* Big logo size */
    margin: 0 auto 24px;
    position: relative;
    animation: logoBreath 5s ease-in-out infinite;
}

.logo-ring::before {
    content: '';
    position: absolute; inset: -4px;
    border-radius: 32px;
    background: linear-gradient(135deg, var(--primary), var(--accent), var(--teal));
    z-index: 0;
    animation: ringRotate 6s linear infinite;
}

.logo-inner {
    position: relative; z-index: 1;
    width: 100%; height: 100%;
    background: var(--white);
    border-radius: 28px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 20px 50px rgba(124,58,237,0.2);
}

.logo-inner img {
    width: 72%; height: 72%;
    object-fit: contain;
}

@keyframes logoBreath {
    0%, 100% { transform: scale(1) translateY(0); }
    50% { transform: scale(1.03) translateY(-8px); }
}

@keyframes ringRotate {
    0% { filter: hue-rotate(0deg); }
    100% { filter: hue-rotate(360deg); }
}

.hero-title {
    font-size: 52px; /* Bigger font */
    font-weight: 900;
    letter-spacing: -2px;
    line-height: 1;
    margin-bottom: 12px;
    background: linear-gradient(135deg, var(--dark) 0%, var(--primary) 50%, var(--accent) 100%);
    background-size: 200% 200%;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: textShimmer 4s ease-in-out infinite;
}

@keyframes textShimmer {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.hero-sub {
    font-size: 18px; /* Bigger font */
    font-weight: 600;
    color: var(--muted);
    max-width: 500px;
    margin: 0 auto;
}

/* ════════════════════════════════════════════════
   STICKY NAVBAR
════════════════════════════════════════════════ */
.navbar {
    position: sticky; top: 16px; z-index: 20;
    max-width: 1200px;
    margin: 0 auto 36px; /* More spacing */
    padding: 14px 28px; /* More padding */
    background: var(--glass);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 14px;
}

.search-wrap {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 380px;
}

.search-wrap i {
    position: absolute;
    left: 16px; top: 50%;
    transform: translateY(-50%);
    color: var(--dim);
    font-size: 16px;
    pointer-events: none;
}

.search-input {
    width: 100%;
    height: 48px; /* Bigger height */
    border: 2px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 0 16px 0 46px;
    font-size: 15px;
    font-weight: 600;
    color: var(--dark);
    background: var(--white);
    outline: none;
    transition: all 0.25s;
}

.search-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-glow);
}

.search-input::placeholder { color: var(--dim); }

.nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.mini-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    font-weight: 800;
    font-size: 14px;
    color: var(--dark);
    white-space: nowrap;
}

.mini-stat i {
    font-size: 16px;
}

.mini-stat .num {
    color: var(--primary);
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border: none;
    border-radius: var(--radius-sm);
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    font-family: inherit;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: var(--white);
    box-shadow: var(--shadow-glow);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(124,58,237,0.25);
}

/* ════════════════════════════════════════════════
   FILTER TABS
════════════════════════════════════════════════ */
.filters-wrap {
    max-width: 1200px;
    margin: 0 auto 36px;
    padding: 0 20px;
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 11px 24px;
    border-radius: var(--radius-pill);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
    background: var(--white);
    color: var(--muted);
    box-shadow: var(--shadow-sm);
}

.chip:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow);
    color: var(--primary);
    border-color: var(--primary-pale);
}

.chip.active {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: var(--white);
    border-color: transparent;
    box-shadow: var(--shadow-glow);
}

.chip .count {
    background: rgba(255,255,255,0.25);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
}

.chip:not(.active) .count {
    background: var(--primary-pale);
    color: var(--primary);
}

/* ════════════════════════════════════════════════
   GALLERY GRID — 4 COLUMN CARDS
════════════════════════════════════════════════ */
.container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 80px;
}

.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 28px;
}

/* ════════════════════════════════════════════════
   PHOTO CARD
════════════════════════════════════════════════ */
.card {
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.9);
    box-shadow: var(--shadow);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
}

.card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-lg);
}

.card-img {
    position: relative;
    height: 250px;
    overflow: hidden;
    cursor: pointer;
}

.card-img img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), filter 0.4s;
}

.card:hover .card-img img {
    transform: scale(1.12);
}

/* Glass overlay on hover */
.card-img-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(180deg, transparent 30%, rgba(15,23,42,0.75) 100%);
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    padding: 20px;
    gap: 10px;
    opacity: 0;
    transition: all 0.35s;
}

.card:hover .card-img-overlay {
    opacity: 1;
}

.overlay-btns {
    display: flex;
    gap: 8px;
}

.ov-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 16px;
    background: rgba(255,255,255,0.92);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.5);
    border-radius: 12px;
    font-size: 13px;
    font-weight: 700;
    color: var(--dark);
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    flex: 1;
    justify-content: center;
}

.ov-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
    background: var(--white);
}

.ov-btn i {
    font-size: 14px;
}

.card-badge {
    position: absolute;
    top: 14px; left: 14px;
    padding: 5px 14px;
    border-radius: var(--radius-pill);
    background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.6);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--primary);
    z-index: 2;
}

/* Card Body */
.card-body {
    padding: 18px 20px 20px;
}

.card-title {
    font-size: 17px;
    font-weight: 800;
    color: var(--dark);
    line-height: 1.3;
    margin-bottom: 14px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 14px;
    border-top: 1px solid var(--light);
}

.stats-row {
    display: flex;
    gap: 14px;
}

.stat-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
    font-weight: 700;
    color: var(--muted);
}

.stat-chip i {
    font-size: 14px;
}

.stat-chip.views i { color: var(--blue); }
.stat-chip.likes i { color: var(--heart); }

.action-row {
    display: flex;
    gap: 6px;
}

.act-btn {
    width: 38px; height: 38px;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all 0.25s;
    position: relative;
}

.act-like {
    background: var(--heart-pale);
    color: var(--heart);
}

.act-like:hover {
    background: var(--heart);
    color: var(--white);
    transform: scale(1.15);
}

.act-like.liked {
    background: var(--heart);
    color: var(--white);
    pointer-events: none;
}

.act-like.liked::after {
    content: '✓';
    position: absolute;
    top: -4px; right: -4px;
    width: 16px; height: 16px;
    background: var(--teal);
    color: white;
    border-radius: 50%;
    font-size: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 900;
}

.act-share {
    background: var(--primary-pale);
    color: var(--primary);
}

.act-share:hover {
    background: var(--primary);
    color: var(--white);
    transform: scale(1.15);
}

/* ════════════════════════════════════════════════
   LIGHTBOX
════════════════════════════════════════════════ */
.lightbox {
    position: fixed; inset: 0;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(24px);
    -webkit-backdrop-filter: blur(24px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 30px;
}

.lightbox.open {
    display: flex;
}

.lb-card {
    width: 100%;
    max-width: 960px;
    background: var(--white);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: 0 40px 100px rgba(0,0,0,0.4);
    transform: scale(0.92) translateY(20px);
    opacity: 0;
    transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.lightbox.open .lb-card {
    transform: scale(1) translateY(0);
    opacity: 1;
}

.lb-img-wrap {
    background: var(--dark);
    display: flex;
    align-items: center;
    justify-content: center;
    max-height: 65vh;
    overflow: hidden;
}

.lb-img-wrap img {
    width: 100%;
    max-height: 65vh;
    object-fit: contain;
}

.lb-info {
    padding: 24px 28px;
}

.lb-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}

.lb-meta-left {
    flex: 1;
}

.lb-cat {
    display: inline-block;
    padding: 5px 14px;
    border-radius: var(--radius-pill);
    background: var(--primary-pale);
    color: var(--primary);
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

.lb-title {
    font-size: 26px;
    font-weight: 900;
    color: var(--dark);
    letter-spacing: -0.5px;
    line-height: 1.2;
}

.lb-desc {
    margin-top: 10px;
    color: var(--muted);
    font-weight: 600;
    font-size: 14px;
    line-height: 1.6;
}

.lb-close {
    width: 44px; height: 44px;
    border-radius: 14px;
    border: none;
    background: var(--light);
    color: var(--muted);
    font-size: 20px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s;
    flex-shrink: 0;
}

.lb-close:hover {
    background: var(--heart-pale);
    color: var(--heart);
    transform: rotate(90deg);
}

.lb-stats-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid var(--light);
}

.lb-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: var(--light);
    border: 1px solid var(--border);
    border-radius: 12px;
    font-weight: 700;
    font-size: 14px;
    color: var(--dark);
}

.lb-pill i { font-size: 16px; }
.lb-pill.v i { color: var(--blue); }
.lb-pill.l i { color: var(--heart); }

.lb-actions {
    display: flex;
    gap: 10px;
    margin-top: 18px;
}

.lb-act {
    flex: 1;
    padding: 14px;
    border-radius: 14px;
    border: none;
    font-weight: 700;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.25s;
    font-family: inherit;
}

.lb-like-btn {
    background: var(--heart-pale);
    color: var(--heart);
}

.lb-like-btn:hover {
    background: var(--heart);
    color: var(--white);
    transform: translateY(-2px);
}

.lb-like-btn.liked {
    background: var(--heart);
    color: var(--white);
    pointer-events: none;
}

.lb-share-btn {
    background: var(--primary-pale);
    color: var(--primary);
}

.lb-share-btn:hover {
    background: var(--primary);
    color: var(--white);
    transform: translateY(-2px);
}

/* ════════════════════════════════════════════════
   TOAST
════════════════════════════════════════════════ */
.toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%) translateY(80px);
    padding: 16px 28px;
    border-radius: 16px;
    font-weight: 700;
    font-size: 15px;
    z-index: 2000;
    box-shadow: 0 15px 40px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    opacity: 0;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    pointer-events: none;
}

.toast.show {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
}

.toast.success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.toast.error {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.toast.info {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
}

/* ════════════════════════════════════════════════
   EMPTY STATE
════════════════════════════════════════════════ */
.empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 100px 20px;
    color: var(--muted);
}

.empty i {
    font-size: 80px;
    color: var(--primary-pale);
    margin-bottom: 20px;
    display: block;
}

.empty h3 {
    font-size: 24px;
    font-weight: 800;
    color: var(--dark);
    margin-bottom: 8px;
}

.empty p {
    color: var(--muted);
    font-size: 16px;
    font-weight: 600;
}

/* ════════════════════════════════════════════════
   FOOTER
════════════════════════════════════════════════ */
.footer {
    text-align: center;
    padding: 30px 20px;
    color: var(--dim);
    font-size: 13px;
    font-weight: 600;
}

.footer span {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 800;
}

/* ════════════════════════════════════════════════
   RESPONSIVE
════════════════════════════════════════════════ */
@media (max-width: 1200px) {
    .grid { grid-template-columns: repeat(3, 1fr); gap: 24px; }
    .hero-title { font-size: 44px; }
    .header { padding: 40px 20px 20px; }
}

@media (max-width: 900px) {
    .grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .hero-title { font-size: 38px; }
    .navbar { flex-direction: column; align-items: stretch; }
    .search-wrap { max-width: none; }
    .nav-right { justify-content: center; width: 100%; }
    .mini-stat { flex: 1; justify-content: center; }
    .lb-card { max-width: 800px; }
}

@media (max-width: 600px) {
    .grid { grid-template-columns: 1fr; gap: 16px; }
    .hero-title { font-size: 32px; }
    .logo-ring { width: 90px; height: 90px; margin-bottom: 18px; }
    .logo-inner img { width: 70%; height: 70%; }
    .header { padding: 30px 14px 20px; }
    .navbar { padding: 12px 16px; margin-bottom: 20px; }
    .filters-wrap { margin-bottom: 25px; padding: 0 10px; }
    .chip { padding: 10px 18px; font-size: 13px; }
    .card-img { height: 200px; }
    .card-title { font-size: 16px; }
    .container { padding: 0 14px 50px; }
    .lb-card { max-width: 100%; margin: 0 10px; }
    .lb-title { font-size: 22px; }
}
</style>
</head>
<body>

<!-- ═══════════ BACKGROUND ORBS ═══════════ -->
<div class="bg-orbs">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
</div>

<!-- ═══════════ HEADER ═══════════ -->
<header class="header">
    <div class="logo-ring">
        <div class="logo-inner">
            <img src="logo.png" alt="Aureon">
        </div>
    </div>
    <h1 class="hero-title">Life at Aureon</h1>
    <p class="hero-sub">Where every moment becomes a beautiful memory</p>
</header>

<!-- ═══════════ NAVBAR ═══════════ -->
<nav class="navbar">
    <form method="GET" class="search-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="hidden" name="cat" value="<?= h($category) ?>">
        <input type="text" name="q" class="search-input"
               placeholder="Search photos..." value="<?= h($search) ?>">
    </form>

    <div class="nav-right">
        <div class="mini-stat">
            <i class="fa-solid fa-images" style="color:var(--primary)"></i>
            <span class="num"><?= number_format($totalPhotos) ?></span> Photos
        </div>
        <div class="mini-stat">
            <i class="fa-solid fa-eye" style="color:var(--blue)"></i>
            <span class="num"><?= number_format($totalViews) ?></span> Views
        </div>
        <div class="mini-stat">
            <i class="fa-solid fa-heart" style="color:var(--heart)"></i>
            <span class="num"><?= number_format($totalLikes) ?></span> Likes
        </div>
        <a href="super_admin.php" class="btn btn-primary">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</nav>

<!-- ═══════════ FILTERS ═══════════ -->
<div class="filters-wrap">
    <?php
    $filters = ['All', 'Events', 'Sports', 'Cultural', 'Campus'];
    foreach ($filters as $filterName):
        $active = ($category === $filterName) ? 'active' : '';
        $url = '?cat=' . urlencode($filterName) . (!empty($search) ? '&q=' . urlencode($search) : '');
    ?>
    <a href="<?= $url ?>" class="chip <?= $active ?>">
        <i class="fa-solid <?= $filterName === 'All' ? 'fa-border-all' : ($filterName === 'Events' ? 'fa-calendar-days' : ($filterName === 'Sports' ? 'fa-futbol' : ($filterName === 'Cultural' ? 'fa-music' : 'fa-building-columns'))) ?>"></i>
        <?= $filterName ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ═══════════ GALLERY GRID ═══════════ -->
<div class="container">
    <div class="grid">
        <?php if (empty($images)): ?>
        <div class="empty">
            <i class="fa-regular fa-images"></i>
            <h3>No images found</h3>
            <p>Try selecting a different category or changing your search</p>
        </div>
        <?php else: ?>
        <?php foreach ($images as $img):
            $id = (int)$img['id'];
            $isLiked = in_array($id, $_SESSION['gal_likes']);
        ?>
        <div class="card" id="card-<?= $id ?>"
             data-id="<?= $id ?>"
             data-title="<?= h($img['title']) ?>"
             data-cat="<?= h($img['category']) ?>"
             data-desc="<?= h($img['description'] ?? '') ?>"
             data-img="<?= h($img['image_path']) ?>"
             data-views="<?= (int)($img['views_count'] ?? 0) ?>"
             data-likes="<?= (int)($img['likes_count'] ?? 0) ?>"
             data-date="<?= h($img['event_date'] ?? '') ?>">

            <div class="card-img">
                <img src="<?= h($img['image_path']) ?>"
                     alt="<?= h($img['title']) ?>"
                     loading="lazy"
                     onclick="openLB(this.closest('.card'))">
                
                <span class="card-badge"><?= h($img['category']) ?></span>

                <div class="card-img-overlay">
                    <div class="overlay-btns">
                        <button class="ov-btn" type="button"
                                onclick="event.stopPropagation(); openLB(this.closest('.card'))">
                            <i class="fa-solid fa-expand"></i> View
                        </button>
                        <button class="ov-btn" type="button"
                                onclick="event.stopPropagation(); doShare(this.closest('.card'))">
                            <i class="fa-solid fa-share-nodes"></i> Share
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <h3 class="card-title"><?= h($img['title']) ?></h3>

                <div class="card-footer">
                    <div class="stats-row">
                        <div class="stat-chip views">
                            <i class="fa-solid fa-eye"></i>
                            <span class="v-num"><?= number_format($img['views_count'] ?? 0) ?></span>
                        </div>
                        <div class="stat-chip likes">
                            <i class="fa-solid fa-heart"></i>
                            <span class="l-num"><?= number_format($img['likes_count'] ?? 0) ?></span>
                        </div>
                    </div>

                    <div class="action-row">
                        <button class="act-btn act-like <?= $isLiked ? 'liked' : '' ?>"
                                type="button"
                                onclick="doLike(<?= $id ?>, this)"
                                title="Like">
                            <i class="fa-solid fa-heart"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ═══════════ LIGHTBOX ═══════════ -->
<div class="lightbox" id="lightbox">
    <div class="lb-card">
        <div class="lb-top">
            <div class="lb-meta-left">
                <span class="lb-cat" id="lb-cat">Category</span>
                <h2 class="lb-title" id="lb-title">Title</h2>
                <p class="lb-desc" id="lb-desc"></p>
            </div>
            <button class="lb-close" type="button" onclick="closeLB()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="lb-img-wrap">
            <img id="lb-img" src="" alt="">
        </div>
        
        <div class="lb-info">
            <div class="lb-stats-row">
                <div class="lb-pill v">
                    <i class="fa-solid fa-eye"></i>
                    <span id="lb-views">0</span> views
                </div>
                <div class="lb-pill l">
                    <i class="fa-solid fa-heart"></i>
                    <span id="lb-likes">0</span> likes
                </div>
                <div class="lb-pill">
                    <i class="fa-solid fa-calendar"></i>
                    <span id="lb-date"></span>
                </div>
            </div>

            <div class="lb-actions">
                <button class="lb-act lb-like-btn" id="lb-like-btn" type="button" onclick="lbLike()">
                    <i class="fa-solid fa-heart"></i> Like This Photo
                </button>
                <button class="lb-act lb-share-btn" type="button" onclick="lbShare()">
                    <i class="fa-solid fa-share-nodes"></i> Share
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ TOAST ═══════════ -->
<div class="toast" id="toast">
    <i id="toast-icon" class="fa-solid fa-check-circle"></i>
    <span id="toast-text">Done</span>
</div>

<!-- ═══════════ FOOTER ═══════════ -->
<div class="footer">
    © <?= date('Y') ?> Powered by <span>Aureon ERP</span> — Capturing Campus Memories
</div>

<script>
/* ════════════ TOAST ════════════ */
function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    const icon = document.getElementById('toast-icon');
    const text = document.getElementById('toast-text');
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };
    
    el.className = 'toast ' + type;
    icon.className = 'fa-solid ' + (icons[type] || icons.success);
    text.textContent = msg;
    el.classList.add('show');
    
    setTimeout(() => el.classList.remove('show'), 3200);
}

/* ════════════ LIKE ════════════ */
function doLike(id, btn) {
    if (btn.classList.contains('liked')) {
        toast('You already liked this photo!', 'info');
        return;
    }

    fetch('gallery.php?ajax=like&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                btn.classList.add('liked');
                
                // Update card count
                const card = document.getElementById('card-' + id);
                if (card) {
                    const lNum = card.querySelector('.l-num');
                    if (lNum) lNum.textContent = data.likes;
                    card.setAttribute('data-likes', data.likes);
                }
                toast('Liked! ❤️ Thanks!', 'success');
            } else if (data.already) {
                btn.classList.add('liked'); // Visually mark as liked even if backend already knew
                toast('You already liked this photo!', 'info');
            } else {
                toast(data.msg || 'Error liking photo.', 'error');
            }
        })
        .catch(err => {
            console.error('Like error:', err);
            toast('Connection error. Please try again.', 'error');
        });
}

/* ════════════ SHARE ════════════ */
function doShare(card) {
    const title = card.getAttribute('data-title') || 'Aureon Gallery Photo';
    const imgSrc = card.getAttribute('data-img');
    const shareUrl = window.location.href.split('?')[0] + '?cat=' + h(card.getAttribute('data-cat')) + '&q=' + h(card.getAttribute('data-title'));

    if (navigator.share) {
        navigator.share({
            title: title + ' — Aureon Campus Gallery',
            text: 'Check out this beautiful photo from Aureon Campus!',
            url: shareUrl
        }).then(() => {
            toast('Shared successfully!', 'success');
        }).catch(() => {});
    } else {
        navigator.clipboard.writeText(shareUrl).then(() => {
            toast('Link copied to clipboard!', 'info');
        }).catch(() => {
            toast('Could not copy link.', 'error');
        });
    }
}

/* ════════════ LIGHTBOX ════════════ */
let currentLBId = null;

function openLB(card) {
    const id = card.getAttribute('data-id');
    currentLBId = parseInt(id);

    // Populate lightbox
    document.getElementById('lb-img').src = card.getAttribute('data-img');
    document.getElementById('lb-title').textContent = card.getAttribute('data-title');
    document.getElementById('lb-cat').textContent = card.getAttribute('data-cat');
    document.getElementById('lb-desc').textContent = card.getAttribute('data-desc') || '';
    document.getElementById('lb-views').textContent = card.getAttribute('data-views');
    document.getElementById('lb-likes').textContent = card.getAttribute('data-likes');
    
    const eventDate = card.getAttribute('data-date');
    const lbDateSpan = document.getElementById('lb-date');
    if (eventDate && eventDate !== '0000-00-00') { // Check for valid date
        const date = new Date(eventDate);
        lbDateSpan.textContent = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        lbDateSpan.closest('.lb-pill').style.display = 'flex';
    } else {
        lbDateSpan.closest('.lb-pill').style.display = 'none';
    }

    // Check if already liked for lightbox button
    const lbLikeBtn = document.getElementById('lb-like-btn');
    const cardLikeBtn = card.querySelector('.act-like');
    if (cardLikeBtn && cardLikeBtn.classList.contains('liked')) {
        lbLikeBtn.classList.add('liked');
        lbLikeBtn.innerHTML = '<i class="fa-solid fa-heart"></i> Liked';
    } else {
        lbLikeBtn.classList.remove('liked');
        lbLikeBtn.innerHTML = '<i class="fa-solid fa-heart"></i> Like This Photo';
    }

    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden'; // Prevent scrolling

    // Track view
    fetch('gallery.php?ajax=view&id=' + id)
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Update card view count
                const cardElement = document.getElementById('card-' + id);
                if (cardElement) {
                    const vNum = cardElement.querySelector('.v-num');
                    if (vNum) vNum.textContent = data.views;
                    cardElement.setAttribute('data-views', data.views);
                }
                // Update lightbox view count
                document.getElementById('lb-views').textContent = data.views;
            }
        })
        .catch(() => {});
}

function closeLB() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
    currentLBId = null;
}

function lbLike() {
    if (!currentLBId) return;
    const likeBtn = document.getElementById('lb-like-btn');
    if (likeBtn.classList.contains('liked')) {
        toast('You already liked this photo!', 'info');
        return;
    }

    doLike(currentLBId, document.querySelector('#card-' + currentLBId + ' .act-like'));
    
    // Update lightbox UI
    setTimeout(() => {
        const card = document.getElementById('card-' + currentLBId);
        if (card) {
            document.getElementById('lb-likes').textContent = card.getAttribute('data-likes');
        }
        likeBtn.classList.add('liked');
        likeBtn.innerHTML = '<i class="fa-solid fa-heart"></i> Liked';
    }, 300); // Small delay to allow main like to process
}

function lbShare() {
    if (!currentLBId) return;
    const card = document.getElementById('card-' + currentLBId);
    if (card) doShare(card);
}

// Global event listeners
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLB();
});

document.getElementById('lightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLB();
});
</script>
</body>
</html>