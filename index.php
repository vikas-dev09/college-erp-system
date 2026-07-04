<?php
/**
 * AUREON ERP SYSTEM — Futuristic Premium Index Page
 * Single file: PHP + HTML + CSS + JavaScript
 */

$currentYear = date('Y');
$college = 'Himalaya BCA College';
$project = 'AUREON ERP SYSTEM';
$developer = 'Vikas Naik';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $project ?> | <?= $college ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ═══════════════════════════════════════════
           CSS VARIABLES — Unique Color Palette
           ═══════════════════════════════════════════ */
        :root {
            --soft-lavender: #d6c6f0;
            --royal-purple: #7c3aed;
            --peach: #fbbf7a;
            --pastel-blue: #b4d6f0;
            --cream: #fff8f0;
            --soft-cyan: #aee2e6;
            --gradient-pink: #f472b6;
            --glass-bg: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.25);
            --shadow-glass: 0 8px 32px rgba(124, 58, 237, 0.08);
            --shadow-hover: 0 20px 60px rgba(124, 58, 237, 0.12);
            --radius-sm: 12px;
            --radius-md: 24px;
            --radius-lg: 40px;
            --transition: cubic-bezier(0.22, 1, 0.36, 1);
            --font-display: 'Space Grotesk', sans-serif;
            --font-body: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            scroll-behavior: smooth;
            scrollbar-width: thin;
            scrollbar-color: var(--royal-purple) transparent;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--royal-purple); border-radius: 10px; }

        body {
            font-family: var(--font-body);
            background: linear-gradient(145deg, var(--cream) 0%, #f5e8f0 50%, #e8f0f8 100%);
            color: #1e1b2e;
            overflow-x: hidden;
        }

        /* ─── CURSOR GLOW ─── */
        .cursor-glow {
            position: fixed;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124,58,237,0.06), transparent 60%);
            pointer-events: none;
            z-index: 0;
            transform: translate(-50%,-50%);
            transition: opacity 0.2s;
        }

        /* ─── FLOATING BLOBS ─── */
        .blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            pointer-events: none;
            animation: blobFloat 25s ease-in-out infinite;
        }
        .blob-1 { width: 500px; height: 500px; top: -200px; left: -200px; background: rgba(251,191,122,0.15); animation-delay: 0s; }
        .blob-2 { width: 400px; height: 400px; bottom: -150px; right: -150px; background: rgba(124,58,237,0.1); animation-delay: -8s; }
        .blob-3 { width: 300px; height: 300px; top: 30%; left: 70%; background: rgba(180,214,240,0.12); animation-delay: -16s; }
        @keyframes blobFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(40px,-30px) scale(1.05); }
            66% { transform: translate(-20px,40px) scale(0.95); }
        }

        /* ─── PARTICLES CANVAS ─── */
        #particles-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
        }

        /* ─── GLASS CARD UTILITY ─── */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-glass);
        }
        .glass-card {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: var(--radius-md);
            padding: 2rem;
            transition: all 0.4s var(--transition);
            box-shadow: var(--shadow-glass);
        }
        .glass-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(124,58,237,0.2);
        }

        /* ─── NAVBAR ─── */
        .navbar {
            background: rgba(255,248,240,0.6);
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
            border-bottom: 1px solid rgba(0,0,0,0.04);
            padding: 0.8rem 0;
            transition: 0.3s;
            z-index: 1000;
        }
        .navbar.scrolled {
            background: rgba(255,248,240,0.9);
            box-shadow: 0 2px 20px rgba(0,0,0,0.04);
        }
        .navbar-brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.6rem;
            color: var(--royal-purple) !important;
            letter-spacing: -1px;
        }
        .navbar-brand .gradient-text {
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .navbar-brand .cap {
            -webkit-text-fill-color: initial;
            display: inline-block;
            animation: capFloat 3s ease-in-out infinite;
        }
        @keyframes capFloat {
            0%,100% { transform: rotate(-8deg) translateY(0); }
            50% { transform: rotate(6deg) translateY(-4px); }
        }
        .nav-link {
            color: #4a4458 !important;
            font-weight: 500;
            padding: 0.5rem 1.2rem !important;
            border-radius: 50px;
            transition: 0.3s;
        }
        .nav-link:hover { background: rgba(124,58,237,0.06); color: var(--royal-purple) !important; }
        .nav-btn {
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink));
            border: none;
            padding: 0.5rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            color: #fff;
            transition: 0.3s;
            text-decoration: none;
        }
        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(124,58,237,0.25);
        }

        /* ─── HERO ─── */
        .hero {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 7rem 0 4rem;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(124,58,237,0.08);
            border: 1px solid rgba(124,58,237,0.15);
            border-radius: 50px;
            padding: 0.4rem 1.2rem;
            font-size: 0.8rem;
            color: var(--royal-purple);
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.8rem, 7vw, 5rem);
            font-weight: 800;
            line-height: 1.05;
            letter-spacing: -2px;
        }
        .hero-title .gradient-text {
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink), var(--peach));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% 200%;
            animation: gradShift 6s ease-in-out infinite;
        }
        @keyframes gradShift {
            0%,100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .hero-sub {
            font-size: 1.1rem;
            color: #4a4458;
            max-width: 500px;
            line-height: 1.7;
            margin: 1.5rem 0 2.5rem;
        }
        .hero-actions { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink));
            border: none;
            padding: 0.9rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            transition: 0.4s;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }
        .btn-primary-custom::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--gradient-pink), var(--royal-purple));
            opacity: 0;
            transition: 0.4s;
        }
        .btn-primary-custom:hover::before { opacity: 1; }
        .btn-primary-custom:hover { transform: translateY(-3px); box-shadow: 0 12px 40px rgba(124,58,237,0.3); }
        .btn-primary-custom span { position: relative; z-index: 1; }

        .btn-outline-custom {
            background: rgba(255,255,255,0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(124,58,237,0.2);
            padding: 0.9rem 2.5rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            color: var(--royal-purple);
            transition: 0.3s;
            text-decoration: none;
        }
        .btn-outline-custom:hover {
            background: rgba(124,58,237,0.06);
            border-color: var(--royal-purple);
            transform: translateY(-2px);
        }

        /* ─── HERO DASHBOARD MOCKUP ─── */
        .mockup-card {
            background: rgba(255,255,255,0.7);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: 0 30px 80px rgba(124,58,237,0.08);
            transform: perspective(1000px) rotateY(-3deg) rotateX(2deg);
            transition: transform 0.5s var(--transition);
        }
        .mockup-card:hover { transform: perspective(1000px) rotateY(0deg) rotateX(0deg); }
        .mockup-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 1.2rem;
            border-bottom: 1px solid rgba(0,0,0,0.03);
        }
        .mockup-dots { display: flex; gap: 6px; }
        .mockup-dots span { width: 10px; height: 10px; border-radius: 50%; }
        .mockup-dots span:nth-child(1) { background: #f87171; }
        .mockup-dots span:nth-child(2) { background: #fbbf24; }
        .mockup-dots span:nth-child(3) { background: #34d399; }
        .mockup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.8rem;
            margin-bottom: 1.2rem;
        }
        .mockup-item {
            background: rgba(124,58,237,0.02);
            border-radius: var(--radius-sm);
            padding: 1rem;
            border: 1px solid rgba(124,58,237,0.04);
        }
        .mockup-item .label { font-size: 0.65rem; color: #6b6480; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        .mockup-item .value { font-family: var(--font-display); font-size: 1.4rem; font-weight: 700; margin-top: 0.2rem; }
        .mockup-item .value.purple { color: var(--royal-purple); }
        .mockup-item .value.peach { color: var(--peach); }
        .mockup-item .value.pink { color: var(--gradient-pink); }
        .mockup-item .value.cyan { color: var(--soft-cyan); }
        .mockup-chart {
            height: 50px; display: flex; align-items: flex-end; gap: 4px; padding-top: 0.5rem;
        }
        .mockup-chart .bar {
            flex: 1; border-radius: 4px 4px 0 0;
            background: linear-gradient(to top, var(--royal-purple), transparent);
            opacity: 0.4;
        }
        .mockup-chart .bar:nth-child(1) { height: 60%; }
        .mockup-chart .bar:nth-child(2) { height: 80%; }
        .mockup-chart .bar:nth-child(3) { height: 45%; }
        .mockup-chart .bar:nth-child(4) { height: 90%; }
        .mockup-chart .bar:nth-child(5) { height: 70%; }
        .mockup-chart .bar:nth-child(6) { height: 55%; }
        .mockup-chart .bar:nth-child(7) { height: 85%; }
        .mockup-chart .bar:nth-child(8) { height: 65%; }
        .mockup-footer {
            display: flex; justify-content: space-between;
            padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.03);
            font-size: 0.75rem; color: #6b6480;
        }

        /* ─── SECTION COMMON ─── */
        .section {
            position: relative;
            z-index: 1;
            padding: 6rem 0;
        }
        .section-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(124,58,237,0.06);
            border: 1px solid rgba(124,58,237,0.1);
            border-radius: 50px;
            padding: 0.3rem 1.2rem;
            font-size: 0.75rem;
            color: var(--royal-purple);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
        }
        .section-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 700;
            letter-spacing: -1px;
            line-height: 1.15;
        }
        .section-sub {
            color: #4a4458;
            font-size: 1.05rem;
            max-width: 560px;
            line-height: 1.7;
        }

        /* ─── STATS SECTION ─── */
        .stats-section {
            background: linear-gradient(180deg, transparent, rgba(124,58,237,0.02), transparent);
            padding: 3rem 0;
        }
        .stat-item { text-align: center; padding: 1.5rem; }
        .stat-value {
            font-family: var(--font-display);
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label {
            color: #4a4458;
            font-size: 0.95rem;
            font-weight: 500;
            margin-top: 0.3rem;
        }

        /* ─── FEATURE CARDS ─── */
        .feature-card {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: var(--radius-md);
            padding: 2rem;
            transition: all 0.4s var(--transition);
            position: relative;
            overflow: hidden;
        }
        .feature-card::after {
            content: '';
            position: absolute; top: 0; left: 0;
            width: 100%; height: 3px;
            background: linear-gradient(90deg, var(--royal-purple), var(--peach));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s var(--transition);
        }
        .feature-card:hover::after { transform: scaleX(1); }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(124,58,237,0.15);
        }
        .feature-icon {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: rgba(124,58,237,0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--royal-purple);
            margin-bottom: 1.2rem;
            transition: 0.3s;
        }
        .feature-card:hover .feature-icon {
            background: var(--royal-purple);
            color: #fff;
        }
        .feature-card h5 {
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .feature-card p {
            color: #4a4458;
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 0;
        }

        /* ─── ROLE CARDS ─── */
        .role-card {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            text-align: center;
            transition: 0.5s;
            position: relative;
            overflow: hidden;
        }
        .role-card::before {
            content: '';
            position: absolute; inset: 0;
            background: radial-gradient(circle at var(--mx,50%) var(--my,50%), rgba(124,58,237,0.06), transparent 70%);
            opacity: 0;
            transition: 0.4s;
        }
        .role-card:hover::before { opacity: 1; }
        .role-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-hover); border-color: rgba(124,58,237,0.15); }
        .role-icon { font-size: 3rem; margin-bottom: 1.2rem; }
        .role-card h4 { font-family: var(--font-display); font-weight: 700; font-size: 1.3rem; margin-bottom: 0.5rem; }
        .role-card p { color: #4a4458; font-size: 0.85rem; line-height: 1.6; }
        .role-features { list-style: none; padding: 0; margin: 1.2rem 0 1.5rem; text-align: left; }
        .role-features li {
            padding: 0.4rem 0;
            font-size: 0.82rem;
            color: #4a4458;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }
        .role-features li i { color: var(--royal-purple); font-size: 0.7rem; }

        /* ─── TESTIMONIAL ─── */
        .testimonial-card {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: var(--radius-md);
            padding: 2rem;
            min-height: 200px;
        }
        .testimonial-card .avatar {
            width: 48px; height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 1.2rem;
        }

        /* ─── FOOTER ─── */
        .footer {
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(0,0,0,0.04);
            padding: 4rem 0 2rem;
        }
        .footer-brand {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -1px;
            background: linear-gradient(135deg, var(--royal-purple), var(--gradient-pink));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .footer p, .footer a { color: #4a4458; font-size: 0.85rem; }
        .footer a { text-decoration: none; display: block; padding: 0.2rem 0; transition: 0.2s; }
        .footer a:hover { color: var(--royal-purple); }
        .footer h6 { font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1.2rem; }
        .footer-divider { border-color: rgba(0,0,0,0.04); margin: 2rem 0; }

        /* ─── REVEAL ANIMATION ─── */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s var(--transition);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 992px) {
            .hero { padding: 5rem 0 2rem; }
            .mockup-card { transform: none; }
            .section { padding: 4rem 0; }
        }
        @media (max-width: 768px) {
            .hero-title { font-size: 2.2rem; }
            .hero-actions { flex-direction: column; }
            .hero-actions .btn-primary-custom,
            .hero-actions .btn-outline-custom { width: 100%; text-align: center; }
            .stat-value { font-size: 2.2rem; }
            .role-card { padding: 1.5rem 1rem; }
        }
        @media (max-width: 480px) {
            .hero-title { font-size: 1.8rem; }
            .section-title { font-size: 1.6rem; }
            .glass-card { padding: 1.2rem; }
        }
    </style>
</head>
<body>

    <!-- Cursor Glow -->
    <div class="cursor-glow" id="cursorGlow"></div>

    <!-- Blobs -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <!-- Particles Canvas -->
    <canvas id="particles-canvas"></canvas>

    <!-- ═══════════ NAVBAR ═══════════ -->
    <nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span class="gradient-text">AU</span><span class="cap">🎓</span>EON
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCollapse"
                    aria-controls="navCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navCollapse">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#portals">Portals</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#analytics">Analytics</a></li>
                    <li class="nav-item"><a class="nav-link" href="#testimonials">Testimonials</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="login.php" class="nav-btn"><i class="fas fa-key me-2"></i>Sign In</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- ═══════════ HERO ═══════════ -->
    <section class="hero" id="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <div class="hero-badge">
                        <i class="fas fa-sparkles"></i> <?= $college ?>
                    </div>
                    <h1 class="hero-title">
                        <span class="gradient-text">Smart</span> Campus<br />
                        Management <br />Reimagined
                    </h1>
                    <p class="hero-sub">
                        A unified ERP platform for <?= $college ?> — connecting students, faculty,
                        and parents with real-time data, AI insights, and seamless academic workflows.
                    </p>
                    <div class="hero-actions">
                        <a href="login.php" class="btn-primary-custom">
                            <span><i class="fas fa-rocket me-2"></i> Explore Dashboard</span>
                        </a>
                        <a href="#about" class="btn-outline-custom">
                            <i class="fas fa-play-circle me-2"></i> Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="mockup-card">
                        <div class="mockup-header">
                            <div class="mockup-dots">
                                <span></span><span></span><span></span>
                            </div>
                            <span><i class="far fa-clock me-1"></i> Live Analytics</span>
                        </div>
                        <div class="mockup-grid">
                            <div class="mockup-item">
                                <div class="label">Active Students</div>
                                <div class="value purple">15,284</div>
                            </div>
                            <div class="mockup-item">
                                <div class="label">Attendance</div>
                                <div class="value peach">94.7%</div>
                            </div>
                            <div class="mockup-item">
                                <div class="label">Assignments</div>
                                <div class="value pink">1,842</div>
                            </div>
                            <div class="mockup-item">
                                <div class="label">AI Queries</div>
                                <div class="value cyan">3,621</div>
                            </div>
                        </div>
                        <div class="mockup-chart">
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div><div class="bar"></div>
                            <div class="bar"></div><div class="bar"></div>
                        </div>
                        <div class="mockup-footer">
                            <span><i class="fas fa-check-circle me-1" style="color:#34d399;"></i> All systems operational</span>
                            <span>Updated 1 min ago</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ STATS ═══════════ -->
    <section class="stats-section">
        <div class="container">
            <div class="row g-4">
                <div class="col-6 col-md-3">
                    <div class="stat-item reveal">
                        <div class="stat-value counter" data-target="15000+">0</div>
                        <div class="stat-label">Students Enrolled</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item reveal">
                        <div class="stat-value counter" data-target="850+">0</div>
                        <div class="stat-label">Faculty Members</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item reveal">
                        <div class="stat-value counter" data-target="98.7%">0</div>
                        <div class="stat-label">Pass Rate</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-item reveal">
                        <div class="stat-value counter" data-target="24/7">0</div>
                        <div class="stat-label">AI Support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ ABOUT ═══════════ -->
    <section class="section" id="about">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 reveal">
                    <div class="section-label"><i class="fas fa-info-circle"></i> About the System</div>
                    <h2 class="section-title">What is <span class="gradient-text">AUREON ERP</span>?</h2>
                    <p class="section-sub" style="max-width:480px;">
                        AUREON is a comprehensive College Management System designed for modern institutions.
                        It automates administrative tasks, enhances communication, and provides real-time insights.
                    </p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle" style="color: var(--royal-purple);"></i>
                            <span>Student Lifecycle Management</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle" style="color: var(--royal-purple);"></i>
                            <span>Automated Attendance</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-check-circle" style="color: var(--royal-purple);"></i>
                            <span>AI-Powered Analytics</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 reveal">
                    <div class="glass-card text-center p-5">
                        <i class="fas fa-graduation-cap" style="font-size: 4rem; color: var(--royal-purple);"></i>
                        <h4 class="mt-3"><?= $college ?></h4>
                        <p class="mb-0"><?= $project ?> • Academic Year <?= $currentYear-1 ?> – <?= $currentYear ?></p>
                        <small>Developed by <?= $developer ?></small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ ROLE PORTALS ═══════════ -->
    <section class="section" id="portals">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <div class="section-label mx-auto"><i class="fas fa-door-open"></i> Role-Based Portals</div>
                <h2 class="section-title">Tailored Experience for <span class="gradient-text">Everyone</span></h2>
                <p class="section-sub mx-auto">Each role gets a personalized dashboard with relevant tools and insights.</p>
            </div>
            <div class="row g-4">
                <?php
                $portals = [
                    ['icon'=>'⚙️','title'=>'Admin Portal','desc'=>'Full institutional control with data-driven dashboards.','features'=>['User management','Financial oversight','Analytics','System config']],
                    ['icon'=>'✏️','title'=>'Teacher Portal','desc'=>'Manage classes, grades, and communication effortlessly.','features'=>['Class management','Gradebooks','Performance analytics','Parent communication']],
                    ['icon'=>'🎓','title'=>'Student Portal','desc'=>'Your academic hub – courses, grades, assignments, AI assistant.','features'=>['Course registration','Grade tracker','Assignment submissions','AI study assistant']],
                    ['icon'=>'👨‍👩‍👧','title'=>'Parent Portal','desc'=>'Stay connected with your child\'s academic journey.','features'=>['Live attendance','Fee history','Performance reports','Direct messaging']],
                ];
                foreach ($portals as $i=>$p):
                ?>
                <div class="col-md-6 col-lg-3">
                    <div class="role-card reveal">
                        <div class="role-icon"><?= $p['icon'] ?></div>
                        <h4><?= $p['title'] ?></h4>
                        <p><?= $p['desc'] ?></p>
                        <ul class="role-features">
                            <?php foreach ($p['features'] as $f): ?>
                            <li><i class="fas fa-check-circle"></i> <?= $f ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="login.php" class="btn-primary-custom" style="font-size:0.85rem;padding:0.6rem 1.6rem;">
                            <span>Access Portal →</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════ FEATURES ═══════════ -->
    <section class="section" id="features">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <div class="section-label mx-auto"><i class="fas fa-cubes"></i> Smart Features</div>
                <h2 class="section-title">Everything you need to <span class="gradient-text">manage academia</span></h2>
                <p class="section-sub mx-auto">From attendance to analytics — a complete ecosystem for modern institutions.</p>
            </div>
            <div class="row g-4">
                <?php
                $features = [
                    ['icon'=>'fa-user-graduate','title'=>'Student Management','desc'=>'Complete lifecycle from admission to alumni tracking.'],
                    ['icon'=>'fa-chalkboard-user','title'=>'Teacher Portal','desc'=>'Lesson planning, gradebooks, attendance analytics.'],
                    ['icon'=>'fa-users','title'=>'Parent Monitoring','desc'=>'Real-time access to child\'s academic data.'],
                    ['icon'=>'fa-fingerprint','title'=>'Attendance System','desc'=>'Biometric & QR-based with instant reports.'],
                    ['icon'=>'fa-chart-simple','title'=>'Marks & Results','desc'=>'Automated grade calculation and result publishing.'],
                    ['icon'=>'fa-book-open','title'=>'Digital Library','desc'=>'E-books, journals, and AI recommendations.'],
                    ['icon'=>'fa-robot','title'=>'AI Chatbot Assistant','desc'=>'24/7 support for queries and guidance.'],
                    ['icon'=>'fa-bullhorn','title'=>'Announcements','desc'=>'Broadcast notices to targeted groups instantly.'],
                    ['icon'=>'fa-money-bill-wave','title'=>'Fee Management','desc'=>'Online payments, receipts, and reminders.'],
                    ['icon'=>'fa-chart-pie','title'=>'Reports & Analytics','desc'=>'Custom dashboards and exportable reports.'],
                ];
                foreach ($features as $i=>$f):
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card reveal">
                        <div class="feature-icon"><i class="fas <?= $f['icon'] ?>"></i></div>
                        <h5><?= $f['title'] ?></h5>
                        <p><?= $f['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════ ANALYTICS ═══════════ -->
    <section class="section" id="analytics">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 reveal">
                    <div class="section-label"><i class="fas fa-chart-line"></i> Real-Time Analytics</div>
                    <h2 class="section-title">Data-Driven <span class="gradient-text">Insights</span></h2>
                    <p class="section-sub">
                        Monitor institutional performance with live dashboards, attendance trends, and AI-generated reports.
                    </p>
                    <div class="d-flex flex-wrap gap-4 mt-4">
                        <div>
                            <div class="stat-value" style="font-size:2rem;">94.7%</div>
                            <small>Overall Attendance</small>
                        </div>
                        <div>
                            <div class="stat-value" style="font-size:2rem;">12K+</div>
                            <small>Active Users</small>
                        </div>
                        <div>
                            <div class="stat-value" style="font-size:2rem;">56</div>
                            <small>Departments</small>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 reveal">
                    <div class="glass-card p-4">
                        <h5 class="mb-3"><i class="fas fa-chart-bar me-2" style="color:var(--royal-purple);"></i> Weekly Activity</h5>
                        <div class="d-flex align-items-end gap-2" style="height: 120px;">
                            <div style="flex:1; height:60%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                            <div style="flex:1; height:80%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                            <div style="flex:1; height:45%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                            <div style="flex:1; height:90%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                            <div style="flex:1; height:70%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                            <div style="flex:1; height:55%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                            <div style="flex:1; height:85%; background:linear-gradient(to top, var(--royal-purple), transparent); border-radius: 6px;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small text-muted">
                            <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══════════ TESTIMONIALS ═══════════ -->
    <section class="section" id="testimonials">
        <div class="container">
            <div class="text-center mb-5 reveal">
                <div class="section-label mx-auto"><i class="fas fa-star"></i> What Our Users Say</div>
                <h2 class="section-title">Trusted by <span class="gradient-text">Thousands</span></h2>
            </div>
            <div class="row g-4">
                <?php
                $testimonials = [
                    ['name'=>'Rahul S.','role'=>'Student','text'=>'AUREON has made tracking my assignments and grades so easy. The AI assistant is a game-changer!'],
                    ['name'=>'Dr. Priya M.','role'=>'Teacher','text'=>'The gradebook and analytics save me hours every week. Highly intuitive platform.'],
                    ['name'=>'Mrs. Ananya K.','role'=>'Parent','text'=>'I can check my child\'s attendance and performance anytime. Very reassuring.'],
                ];
                foreach ($testimonials as $t):
                ?>
                <div class="col-md-4 reveal">
                    <div class="testimonial-card glass-card">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="avatar"><?= substr($t['name'],0,1) ?></div>
                            <div>
                                <h6 class="mb-0"><?= $t['name'] ?></h6>
                                <small class="text-muted"><?= $t['role'] ?></small>
                            </div>
                        </div>
                        <p class="mb-0">"<?= $t['text'] ?>"</p>
                        <div class="mt-2">
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═══════════ CONTACT CTA ═══════════ -->
    <section class="section" id="contact">
        <div class="container">
            <div class="glass-card text-center p-5 reveal" style="border-radius: var(--radius-lg);">
                <div class="section-label mx-auto"><i class="fas fa-key"></i> Get Started</div>
                <h2 class="section-title">Ready to <span class="gradient-text">transform</span> your campus?</h2>
                <p class="section-sub mx-auto">Join <?= $college ?> in the digital era. Access your dashboard now.</p>
                <a href="login.php" class="btn-primary-custom" style="font-size:1.1rem;padding:1rem 3rem;">
                    <span><i class="fas fa-arrow-right me-2"></i> Access Dashboard</span>
                </a>
            </div>
        </div>
    </section>

    <!-- ═══════════ FOOTER ═══════════ -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="footer-brand">AUREON ERP</div>
                    <p class="mt-3">Next-generation college management system.<br />Smart, secure, intuitive.</p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-github"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6>Platform</h6>
                    <a href="#">Features</a>
                    <a href="#">Portals</a>
                    <a href="#">AI Assistant</a>
                    <a href="#">Pricing</a>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6>Resources</h6>
                    <a href="#">Documentation</a>
                    <a href="#">API</a>
                    <a href="#">Blog</a>
                    <a href="#">Support</a>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6>Company</h6>
                    <a href="#">About</a>
                    <a href="#">Careers</a>
                    <a href="#">Contact</a>
                    <a href="#">Privacy</a>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <h6>Legal</h6>
                    <a href="#">Terms</a>
                    <a href="#">Privacy</a>
                    <a href="#">Security</a>
                    <a href="#">GDPR</a>
                </div>
            </div>
            <hr class="footer-divider" />
            <div class="text-center">
                <p class="mb-0">&copy; <?= $currentYear ?> <?= $project ?> — <?= $college ?>. Developed by <?= $developer ?>.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // ═══════════════════════════════════════════════
    // PARTICLES (lightweight canvas)
    // ═══════════════════════════════════════════════
    (function() {
        const canvas = document.getElementById('particles-canvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        let w, h;

        function resize() {
            w = canvas.width = window.innerWidth;
            h = canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                this.reset();
            }
            reset() {
                this.x = Math.random() * w;
                this.y = Math.random() * h;
                this.size = 1.5 + Math.random() * 2.5;
                this.speedX = (Math.random() - 0.5) * 0.2;
                this.speedY = (Math.random() - 0.5) * 0.2;
                this.opacity = 0.2 + Math.random() * 0.3;
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x < 0 || this.x > w) this.speedX *= -1;
                if (this.y < 0 || this.y > h) this.speedY *= -1;
            }
            draw() {
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(124, 58, 237, ${this.opacity})`;
                ctx.fill();
            }
        }

        for (let i = 0; i < 80; i++) particles.push(new Particle());

        function connect() {
            for (let a = 0; a < particles.length; a++) {
                for (let b = a+1; b < particles.length; b++) {
                    const dx = particles[a].x - particles[b].x;
                    const dy = particles[a].y - particles[b].y;
                    const dist = Math.sqrt(dx*dx + dy*dy);
                    if (dist < 120) {
                        ctx.beginPath();
                        ctx.moveTo(particles[a].x, particles[a].y);
                        ctx.lineTo(particles[b].x, particles[b].y);
                        ctx.strokeStyle = `rgba(124, 58, 237, ${0.04 * (1 - dist/120)})`;
                        ctx.lineWidth = 0.5;
                        ctx.stroke();
                    }
                }
            }
        }

        function animate() {
            ctx.clearRect(0, 0, w, h);
            particles.forEach(p => { p.update(); p.draw(); });
            connect();
            requestAnimationFrame(animate);
        }
        animate();
    })();

    // ═══════════════════════════════════════════════
    // CURSOR GLOW
    // ═══════════════════════════════════════════════
    (function() {
        const glow = document.getElementById('cursorGlow');
        document.addEventListener('mousemove', e => {
            glow.style.transform = `translate(${e.clientX}px, ${e.clientY}px) translate(-50%, -50%)`;
        });
        document.addEventListener('mouseleave', () => glow.style.opacity = '0');
        document.addEventListener('mouseenter', () => glow.style.opacity = '1');
    })();

    // ═══════════════════════════════════════════════
    // NAVBAR SCROLL
    // ═══════════════════════════════════════════════
    (function() {
        const nav = document.getElementById('mainNav');
        window.addEventListener('scroll', () => {
            nav.classList.toggle('scrolled', window.scrollY > 50);
        });
    })();

    // ═══════════════════════════════════════════════
    // REVEAL ON SCROLL
    // ═══════════════════════════════════════════════
    (function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    })();

    // ═══════════════════════════════════════════════
    // COUNTER ANIMATION
    // ═══════════════════════════════════════════════
    (function() {
        const counters = document.querySelectorAll('.counter');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const el = entry.target;
                    const target = el.getAttribute('data-target');
                    // Handle various formats
                    const isPercent = target.includes('%');
                    const isPlus = target.includes('+');
                    const isComma = target.includes(',');
                    const numeric = parseFloat(target.replace(/[^0-9.]/g, ''));
                    const suffix = isPercent ? '%' : isPlus ? '+' : '';
                    let current = 0;
                    const increment = numeric / 60;

                    function update() {
                        current += increment;
                        if (current < numeric) {
                            if (isComma) {
                                el.textContent = Math.floor(current).toLocaleString() + suffix;
                            } else {
                                el.textContent = Math.floor(current) + suffix;
                            }
                            requestAnimationFrame(update);
                        } else {
                            el.textContent = target;
                        }
                    }
                    update();
                    observer.unobserve(el);
                }
            });
        }, { threshold: 0.5 });

        counters.forEach(c => observer.observe(c));
    })();

    // ═══════════════════════════════════════════════
    // ROLE CARD MOUSE GLOW
    // ═══════════════════════════════════════════════
    document.querySelectorAll('.role-card').forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = ((e.clientX - rect.left) / rect.width) * 100;
            const y = ((e.clientY - rect.top) / rect.height) * 100;
            card.style.setProperty('--mx', x + '%');
            card.style.setProperty('--my', y + '%');
        });
    });
    </script>
</body>
</html>