<?php
session_start();

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_name = $_SESSION['full_name'] ?? $_SESSION['name'] ?? 'Admin';
$admin_id   = $_SESSION['user_id'] ?? 'SA001';
$initials   = strtoupper(substr($admin_name, 0, 1) . substr(strrchr($admin_name, ' ') ?: $admin_name, 1, 1));

// Database connection for real stats
$db_host = 'localhost';
$db_name = 'aureon';
$db_user = 'root';
$db_pass = '';

$total_students = $total_teachers = $fees_collected = $library_books = 0;
$puc_sci = $puc_com = $bca_count = $mca_count = 0;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $total_students = $pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn() ?: 0;
    $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND status='Active'")->fetchColumn() ?: 0;

    // Course wise counts
    $puc_sci  = $pdo->query("SELECT COUNT(*) FROM students WHERE course='PUC' AND stream='Science'")->fetchColumn() ?: 0;
    $puc_com  = $pdo->query("SELECT COUNT(*) FROM students WHERE course='PUC' AND stream='Commerce'")->fetchColumn() ?: 0;
    $bca_count = $pdo->query("SELECT COUNT(*) FROM students WHERE course='BCA'")->fetchColumn() ?: 0;
    $mca_count = $pdo->query("SELECT COUNT(*) FROM students WHERE course='MCA'")->fetchColumn() ?: 0;

} catch(PDOException $e) {
    // Use defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard | AUREON ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>
        *{margin:0;padding:0;box-sizing:border-box}

        :root{
            --violet:#7c3aed;
            --violet-dark:#6d28d9;
            --violet-light:#a78bfa;
            --violet-pale:#ede9fe;
            --violet-glow:rgba(124,58,237,0.12);
            --orange:#f97316;
            --orange-pale:#fff7ed;
            --pink:#ec4899;
            --pink-pale:#fdf2f8;
            --teal:#14b8a6;
            --teal-pale:#f0fdfa;
            --blue:#3b82f6;
            --blue-pale:#eff6ff;
            --green:#10b981;
            --green-pale:#ecfdf5;
            --red:#ef4444;
            --red-pale:#fef2f2;
            --yellow:#f59e0b;
            --yellow-pale:#fffbeb;
            --indigo:#6366f1;
            --indigo-pale:#eef2ff;
            --cyan:#06b6d4;
            --cyan-pale:#ecfeff;
            --dark:#1f1635;
            --text:#334155;
            --text-muted:#64748b;
            --text-dim:#94a3b8;
            --border:#e2e8f0;
            --border-light:#f1f5f9;
            --white:#ffffff;
            --bg:linear-gradient(135deg,#fdfbff 0%,#fff8f5 50%,#f8fcff 100%);
            --card-shadow:0 10px 30px rgba(0,0,0,0.06);
            --radius:20px;
            --radius-md:16px;
            --radius-sm:12px;
            --radius-xs:8px;
        }

        html{scroll-behavior:smooth}

        body{
            min-height:100vh;
            font-family:'Inter','Segoe UI',sans-serif;
            background:var(--bg);
            color:var(--text);
            display:flex;
        }

        /* ═══════════════════════════════
           SIDEBAR
        ═══════════════════════════════ */
        /* ═══════════════════════════════
   SIDEBAR (UPDATED FULL TEXT MENU)
═══════════════════════════════ */

.sidebar{
    width:240px;
    background:rgba(255,255,255,0.6);
    backdrop-filter:blur(16px);
    -webkit-backdrop-filter:blur(16px);
    border-right:1px solid rgba(255,255,255,0.8);
    display:flex;
    flex-direction:column;
    align-items:stretch;
    position:fixed;
    top:0;
    bottom:0;
    z-index:100;
    padding:18px 0;
    transition:all 0.3s;
}

/* Logo */
.sidebar-logo{
    width:100%;
    padding:0 18px;
    margin-bottom:20px;
}

.sidebar-logo img{
    height:42px;
    object-fit:contain;
    filter:drop-shadow(0 4px 10px rgba(124,58,237,0.2));
}

/* Navigation */
.sidebar-nav{
    flex:1;
    display:flex;
    flex-direction:column;
    align-items:stretch;
    gap:6px;
    width:100%;
    padding:0 10px;
}

/* Menu Item */
.s-item{
    width:100%;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:flex-start;
    gap:14px;
    padding:12px 16px;
    font-size:14px;
    font-weight:600;
    color:var(--text-muted);
    text-decoration:none;
    transition:all 0.25s ease;
}

/* Icon */
.s-item i{
    font-size:18px;
    min-width:22px;
    text-align:center;
}

/* Text */
.s-item span{
    flex:1;
}

/* Hover */
.s-item:hover{
    background:var(--violet-pale);
    color:var(--violet);
    transform:translateX(4px);
}

/* Active */
.s-item.active{
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:white;
    box-shadow:0 6px 18px rgba(124,58,237,0.25);
}

/* Bottom Section */
.sidebar-bottom{
    padding:12px;
}

/* Logout Button */
.s-logout{
    width:100%;
    border-radius:12px;
    border:none;
    background:var(--red-pale);
    color:var(--red);
    font-size:14px;
    font-weight:600;
    padding:12px;
    cursor:pointer;
    transition:all 0.25s;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
}

.s-logout:hover{
    background:rgba(239,68,68,0.15);
    transform:translateY(-2px);
}

/* ═══════════════════════════════
   MAIN FIX (IMPORTANT)
═══════════════════════════════ */

.main{
    margin-left:240px; /* ✅ match sidebar width */
    flex:1;
    padding:0;
    min-height:100vh;
}

/* ═══════════════════════════════
   MOBILE RESPONSIVE
═══════════════════════════════ */

@media(max-width:900px){

    .sidebar{
        transform:translateX(-100%);
        width:240px;
        background:rgba(255,255,255,0.95);
        padding:20px;
    }

    .sidebar.open{
        transform:translateX(0);
    }

    .main{
        margin-left:0;
    }

    .s-item{
        padding:14px;
        font-size:15px;
    }

    ..s-logout{
    width:100%;
    border-radius:12px;
    border:none;
    background:var(--red-pale);
    color:var(--red);
    font-size:14px;
    font-weight:600;
    padding:12px;
    cursor:pointer;
    transition:all 0.25s;

    display:flex;
    align-items:center;
    justify-content:flex-start; /* align like menu */
    gap:10px;
    padding-left:16px;
}

.s-logout i{
    font-size:16px;
}
}

        /* ═══════════════════════════════
           MAIN
        ═══════════════════════════════ */
        .main{
            margin-left:240px;
            flex:1;
            padding:0;
            min-height:100vh;
        }

        /* Top Header */
        .top-header{
            position:sticky;top:0;z-index:50;
            background:rgba(255,255,255,0.75);
            backdrop-filter:blur(14px);
            border-bottom:1px solid var(--border-light);
            padding:14px 36px;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .header-brand{
            display:flex;
            align-items:center;
            gap:12px;
        }

        .header-icon{
            width:38px;height:38px;
            border-radius:12px;
            background:linear-gradient(135deg,var(--violet),var(--pink));
            display:flex;
            align-items:center;
            justify-content:center;
            color:white;font-size:15px;
        }

        .header-title{
            font-size:16px;
            font-weight:700;
            color:var(--dark);
        }

        .header-title span{
            color:var(--text-muted);
            font-weight:500;
            margin-left:6px;
            font-size:13px;
        }

        .notification-bell{
    position:relative;
    width:44px;
    height:44px;
    border-radius:14px;
    background:#ffffff;
    border:1px solid #e2e8f0;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:all .3s ease;
    box-shadow:0 4px 12px rgba(0,0,0,.04);
}

.notification-bell:hover{
    background:#ede9fe;
    color:#7c3aed;
    transform:translateY(-2px);
}

.notification-bell i{
    font-size:18px;
}

.notification-count{
    position:absolute;
    top:-4px;
    right:-4px;
    width:18px;
    height:18px;
    border-radius:50%;
    background:#ef4444;
    color:#fff;
    font-size:10px;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
    border:2px solid #fff;
}

        /* Content */
        .content{
            padding:32px 36px;
        }

        /* ═══════════════════════════════
           WELCOME SECTION
        ═══════════════════════════════ */
        .welcome{
            background:var(--white);
            border-radius:var(--radius);
            padding:28px 32px;
            box-shadow:var(--card-shadow);
            margin-bottom:28px;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .welcome h2{
            font-size:24px;
            font-weight:700;
            color:var(--dark);
            margin-bottom:4px;
        }

        .welcome p{
            font-size:14px;
            color:var(--text-muted);
        }

        .welcome p strong{
            color:var(--violet);
            font-weight:700;
        }

        .welcome-icon{
            width:60px;height:60px;
            border-radius:16px;
            background:var(--violet-pale);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:26px;
            color:var(--violet);
        }

        /* ═══════════════════════════════
           STATS GRID
        ═══════════════════════════════ */
        .stats-grid{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:18px;
            margin-bottom:28px;
        }

        .stat-card{
            background:var(--white);
            border-radius:var(--radius-md);
            padding:24px;
            box-shadow:var(--card-shadow);
            transition:all 0.3s ease;
            display:flex;
            align-items:center;
            gap:16px;
        }

        .stat-card:hover{
            transform:translateY(-4px);
            box-shadow:0 20px 50px rgba(0,0,0,0.08);
        }

        .stat-icon{
            width:52px;height:52px;
            border-radius:14px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:22px;
            flex-shrink:0;
        }

        .stat-card:nth-child(1) .stat-icon{background:var(--violet-pale);color:var(--violet)}
        .stat-card:nth-child(2) .stat-icon{background:var(--blue-pale);color:var(--blue)}
        .stat-card:nth-child(3) .stat-icon{background:var(--green-pale);color:var(--green)}
        .stat-card:nth-child(4) .stat-icon{background:var(--orange-pale);color:var(--orange)}

        .stat-info .stat-value{
            font-size:28px;
            font-weight:800;
            color:var(--dark);
        }

        .stat-info .stat-label{
            font-size:13px;
            color:var(--text-muted);
            font-weight:500;
        }

        /* ═══════════════════════════════
           QUICK ACTIONS
        ═══════════════════════════════ */
        .section-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:18px;
        }

        .section-header h3{
            font-size:18px;
            font-weight:700;
            color:var(--dark);
            display:flex;
            align-items:center;
            gap:8px;
        }

        .actions-grid{
            display:grid;
            grid-template-columns:repeat(5,1fr);
            gap:16px;
            margin-bottom:32px;
        }

        .action-card{
            background:var(--white);
            border-radius:var(--radius-md);
            padding:24px 20px;
            box-shadow:var(--card-shadow);
            text-decoration:none;
            transition:all 0.3s cubic-bezier(0.4,0,0.2,1);
            position:relative;
            overflow:hidden;
        }

        .action-card::before{
            content:'';
            position:absolute;top:0;left:0;right:0;
            height:3px;
            border-radius:20px 20px 0 0;
        }

        .action-card:hover{
            transform:translateY(-6px) scale(1.02);
            box-shadow:0 20px 50px rgba(0,0,0,0.1);
        }

        .action-card:hover .action-icon i{
            transform:rotate(10deg) scale(1.15);
        }

        .action-icon{
            width:48px;height:48px;
            border-radius:14px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:20px;
            margin-bottom:14px;
        }

        .action-icon i{transition:all 0.3s}

        .action-title{
            font-size:14px;
            font-weight:700;
            color:var(--dark);
            margin-bottom:4px;
        }

        .action-desc{
            font-size:12px;
            color:var(--text-muted);
            line-height:1.4;
        }

        .action-badge{
            position:absolute;
            top:14px;right:14px;
            padding:3px 9px;
            border-radius:8px;
            font-size:10px;
            font-weight:700;
        }

        /* Action Card Colors */
        .ac-violet::before{background:var(--violet)}
        .ac-violet .action-icon{background:var(--violet-pale);color:var(--violet)}
        .ac-violet .action-badge{background:var(--violet-pale);color:var(--violet)}

        .ac-blue::before{background:var(--blue)}
        .ac-blue .action-icon{background:var(--blue-pale);color:var(--blue)}
        .ac-blue .action-badge{background:var(--blue-pale);color:var(--blue)}

        .ac-green::before{background:var(--green)}
        .ac-green .action-icon{background:var(--green-pale);color:var(--green)}
        .ac-green .action-badge{background:var(--green-pale);color:var(--green)}

        .ac-orange::before{background:var(--orange)}
        .ac-orange .action-icon{background:var(--orange-pale);color:var(--orange)}
        .ac-orange .action-badge{background:var(--orange-pale);color:var(--orange)}

        .ac-pink::before{background:var(--pink)}
        .ac-pink .action-icon{background:var(--pink-pale);color:var(--pink)}
        .ac-pink .action-badge{background:var(--pink-pale);color:var(--pink)}

        .ac-teal::before{background:var(--teal)}
        .ac-teal .action-icon{background:var(--teal-pale);color:var(--teal)}

        .ac-indigo::before{background:var(--indigo)}
        .ac-indigo .action-icon{background:var(--indigo-pale);color:var(--indigo)}

        .ac-cyan::before{background:var(--cyan)}
        .ac-cyan .action-icon{background:var(--cyan-pale);color:var(--cyan)}

        .ac-yellow::before{background:var(--yellow)}
        .ac-yellow .action-icon{background:var(--yellow-pale);color:var(--yellow)}
        .ac-yellow .action-badge{background:var(--yellow-pale);color:var(--yellow)}

        .ac-red::before{background:var(--red)}
        .ac-red .action-icon{background:var(--red-pale);color:var(--red)}
        .ac-red .action-badge{background:var(--red-pale);color:var(--red)}

        /* ═══════════════════════════════
           CHARTS
        ═══════════════════════════════ */
        .charts-grid{
            display:grid;
            grid-template-columns:1.2fr 0.8fr;
            gap:20px;
        }

        .chart-card{
            background:var(--white);
            border-radius:var(--radius);
            padding:28px;
            box-shadow:var(--card-shadow);
        }

        .chart-card h3{
            font-size:16px;
            font-weight:700;
            color:var(--dark);
            margin-bottom:24px;
            display:flex;
            align-items:center;
            gap:8px;
        }

        .chart-card h3 i{color:var(--violet)}

        /* Bar Chart */
        .bar-chart{
            display:flex;
            align-items:flex-end;
            justify-content:space-around;
            height:220px;
            padding:0 10px;
            gap:16px;
        }

        .bar-group{
            flex:1;
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:8px;
        }

        .bar-value{
            font-size:14px;
            font-weight:700;
            color:var(--dark);
        }

        .bar{
            width:100%;
            max-width:60px;
            border-radius:10px 10px 4px 4px;
            min-height:8px;
            transition:height 1s cubic-bezier(0.4,0,0.2,1);
            position:relative;
        }

        .bar::after{
            content:'';
            position:absolute;
            inset:0;
            border-radius:inherit;
            background:linear-gradient(180deg,rgba(255,255,255,0.25) 0%,transparent 100%);
        }

        .bar-label{
            font-size:11px;
            font-weight:600;
            color:var(--text-muted);
            text-align:center;
        }

        .bar-1{background:linear-gradient(180deg,var(--violet),var(--violet-dark))}
        .bar-2{background:linear-gradient(180deg,var(--pink),#be185d)}
        .bar-3{background:linear-gradient(180deg,var(--blue),#2563eb)}
        .bar-4{background:linear-gradient(180deg,var(--teal),#0d9488)}

        /* Pie Chart */
        .pie-wrap{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:32px;
        }

        .pie{
            width:180px;height:180px;
            border-radius:50%;
            position:relative;
        }

        .pie-center{
            position:absolute;
            inset:30px;
            background:white;
            border-radius:50%;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
        }

        .pie-center .total{
            font-size:24px;
            font-weight:800;
            color:var(--dark);
        }

        .pie-center .total-label{
            font-size:10px;
            color:var(--text-muted);
            font-weight:600;
        }

        .legend{
            display:flex;
            flex-direction:column;
            gap:10px;
        }

        .legend-item{
            display:flex;
            align-items:center;
            gap:8px;
            font-size:13px;
            color:var(--text);
            font-weight:500;
        }

        .legend-dot{
            width:12px;height:12px;
            border-radius:4px;
            flex-shrink:0;
        }

        /* ═══════════════════════════════
           MOBILE HEADER
        ═══════════════════════════════ */
        .mobile-header{
            display:none;
            position:fixed;top:0;left:0;right:0;
            height:58px;
            background:rgba(255,255,255,0.9);
            backdrop-filter:blur(12px);
            border-bottom:1px solid var(--border-light);
            z-index:90;padding:0 16px;
            align-items:center;
            justify-content:space-between;
        }

        .mobile-header .mb{
            display:flex;align-items:center;gap:8px;
            font-weight:700;font-size:16px;color:var(--violet);
        }

        .mobile-header .mb img{height:28px}

        .hamburger{
            width:40px;height:40px;border:none;
            background:var(--violet-pale);border-radius:10px;
            color:var(--violet);font-size:18px;
            cursor:pointer;display:flex;
            align-items:center;justify-content:center;
        }

        .overlay{
            display:none;position:fixed;inset:0;
            background:rgba(0,0,0,0.25);z-index:95;
        }

        /* ═══════════════════════════════
           RESPONSIVE
        ═══════════════════════════════ */
        @media(max-width:1200px){
            .actions-grid{grid-template-columns:repeat(3,1fr)}
        }

        @media(max-width:900px){
            .sidebar{
                transform:translateX(-100%);
                width:240px;padding:20px;
                background:rgba(255,255,255,0.95);
                align-items:stretch;
            }
            .sidebar.open{transform:translateX(0)}
            .sidebar .s-item{
                width:100%;height:auto;
                flex-direction:row;
                justify-content:flex-start;
                gap:12px;padding:13px 14px;
                font-size:13px;
            }
            .sidebar .s-item .tip{display:none}
            .sidebar .s-logout{
                width:100%;height:auto;
                padding:13px;font-size:14px;
            }
            .main{margin-left:0}
            .mobile-header{display:flex}
            .overlay.show{display:block}
            .content{padding:80px 16px 32px}
            .top-header{display:none}
            .stats-grid{grid-template-columns:repeat(2,1fr)}
            .actions-grid{grid-template-columns:repeat(2,1fr)}
            .charts-grid{grid-template-columns:1fr}
            .welcome{flex-direction:column;text-align:center;gap:16px}
        }

        @media(max-width:480px){
            .stats-grid{grid-template-columns:1fr}
            .actions-grid{grid-template-columns:1fr}
            .pie-wrap{flex-direction:column}
        }

        /* Load animation */
        @keyframes fadeUp{
            from{opacity:0;transform:translateY(16px)}
            to{opacity:1;transform:translateY(0)}
        }

        .welcome{animation:fadeUp 0.4s ease}
        .stats-grid .stat-card:nth-child(1){animation:fadeUp 0.4s ease 0.05s both}
        .stats-grid .stat-card:nth-child(2){animation:fadeUp 0.4s ease 0.1s both}
        .stats-grid .stat-card:nth-child(3){animation:fadeUp 0.4s ease 0.15s both}
        .stats-grid .stat-card:nth-child(4){animation:fadeUp 0.4s ease 0.2s both}
        .chart-card{animation:fadeUp 0.5s ease 0.3s both}
        .notification-bell{
    position:relative;
    width:42px;
    height:42px;
    border-radius:12px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    display:flex;
    align-items:center;
    justify-content:center;
    cursor:pointer;
    transition:0.3s;
}

.notification-bell:hover{
    background:#ede9fe;
    color:#7c3aed;
}

.notification-bell i{
    font-size:18px;
}

.notification-count{
    position:absolute;
    top:-5px;
    right:-5px;
    min-width:18px;
    height:18px;
    border-radius:50%;
    background:#ef4444;
    color:white;
    font-size:10px;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;

}
.header-profile{
    display:flex;
    align-items:center;
    gap:14px;
    margin-left:auto;
}

.profile-info{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    line-height:1.2;
}

.profile-info .name{
    font-size:14px;
    font-weight:700;
    color:var(--dark);
}

.profile-info .role{
    font-size:12px;
    color:var(--text-muted);
}

.profile-avatar{
    width:42px;
    height:42px;
    border-radius:12px;
    background:linear-gradient(135deg,var(--violet),var(--pink));
    color:#fff;
    font-weight:700;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:14px;
}
    </style>
</head>
<body>

<!-- Mobile Header -->
<div class="mobile-header">
    <div class="mb">
        <img src="logo.png" alt="AUREON">
        AUREON ERP
    </div>
    <button class="hamburger" onclick="toggleSidebar()">
        <i class="fa-solid fa-bars"></i>
    </button>
</div>
<div class="overlay" id="overlay" onclick="toggleSidebar()"></div>


<!-- ═══════════ SIDEBAR ═══════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <!-- ================= LOGO START ================= -->

<div class="sidebar-logo">

    <div class="aureon-logo">
        <span class="logo-letter">A</span>
        <i class="fa-solid fa-graduation-cap logo-cap"></i>
    </div>

    <h2>AUREON ERP</h2>

</div>

<style>

/* ===============================
   AUREON ERP LOGO
================================= */

.sidebar-logo{
    width:100%;
    padding:0 18px;
    margin-bottom:24px;

    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
}

/* Logo Box */
.aureon-logo{
    width:82px;
    height:82px;

    border-radius:22px;

    background:
    linear-gradient(
        135deg,
        #ede9fe,
        #fdf2f8
    );

    display:flex;
    align-items:center;
    justify-content:center;

    position:relative;

    box-shadow:
    0 10px 25px rgba(124,58,237,0.12),
    inset 0 1px 0 rgba(255,255,255,0.8);

    border:1px solid rgba(255,255,255,0.9);

    transition:0.35s ease;
}

/* Hover */
.aureon-logo:hover{
    transform:
    translateY(-4px)
    scale(1.04);

    box-shadow:
    0 18px 40px rgba(124,58,237,0.18);
}

/* A Letter */
.logo-letter{
    font-size:52px;
    font-weight:900;

    font-family:'Inter',sans-serif;

    color:#7c3aed;

    line-height:1;

    text-shadow:
    0 4px 10px rgba(124,58,237,0.12);
}

/* Graduation Cap */
.logo-cap{
    position:absolute;

    top:10px;
    right:10px;

    font-size:18px;

    color:#f97316;

    transform:rotate(-15deg);

    filter:
    drop-shadow(0 4px 8px rgba(0,0,0,0.15));
}

/* Text */
.sidebar-logo h2{
    margin-top:14px;

    font-size:18px;
    font-weight:800;

    letter-spacing:0.5px;

    background:
    linear-gradient(
        135deg,
        #7c3aed,
        #ec4899
    );

    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
}

</style>

<!-- ================= LOGO END ================= -->
    </div>
<nav class="sidebar-nav">

    <a href="super_admin.php" class="s-item active">
        <i class="fa-solid fa-gauge-high"></i>
        <span class="tip">Dashboard</span>
    </a>

    <a href="view_students.php" class="s-item">
        <i class="fa-solid fa-user-graduate"></i>
        <span class="tip">Student Management</span>
    </a>

    <a href="view_teachers.php" class="s-item">
        <i class="fa-solid fa-chalkboard-user"></i>
        <span class="tip">Faculty Management</span>
    </a>

    <a href="fees.php" class="s-item">
        <i class="fa-solid fa-indian-rupee-sign"></i>
        <span class="tip">Fee Management</span>
    </a>

    <a href="library.php" class="s-item">
        <i class="fa-solid fa-book-open"></i>
        <span class="tip">Library System</span>
    </a>

    <a href="gallary_admin.php" class="s-item">
        <i class="fa-solid fa-images"></i>
        <span class="tip">Media Gallery</span>
    </a>

    <a href="create_event.php" class="s-item">
        <i class="fa-solid fa-calendar-days"></i>
        <span class="tip">Event Management</span>
    </a>

    <a href="annocement.php" class="s-item">
        <i class="fa-solid fa-bullhorn"></i>
        <span class="tip">Announcements</span>
    </a>

    <a href="reports.php" class="s-item">
        <i class="fa-solid fa-chart-line"></i>
        <span class="tip">Reports & Analytics</span>
    </a>

    <a href="security_access_control.php" class="s-item">
        <i class="fa-solid fa-user-shield"></i>
        <span class="tip">User Roles</span>
    </a>

    <a href="settings.php" class="s-item">
        <i class="fa-solid fa-gear"></i>
        <span class="tip">System Settings</span>
    </a>

</nav>

   <div class="sidebar-bottom">
    <button class="s-logout" onclick="confirmLogout()">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span>Logout</span>
    </button>
</div>
</aside>


<!-- ═══════════ MAIN ═══════════ -->
<main class="main">

    <!-- Top Header -->
    <div class="top-header">
        <div class="header-brand">
            <div class="header-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="header-title">
                AUREON ERP <span>| SUPER ADMIN PANEL</span>
            </div>
        </div>

        <div class="header-profile">

    <!-- Notification Bell -->
    <a href="admin_notification.php" class="notification-bell">
    <i class="fa-solid fa-bell"></i>
    <span class="notification-count">12</span>
</a>
    <div class="profile-info">
        <div class="name"><?= htmlspecialchars($admin_name) ?></div>
        <div class="role">Super Admin</div>
    </div>

    <div class="profile-avatar"><?= $initials ?></div>

</div>
    </div>

<div class="content">

        <!-- Welcome -->
        <div class="welcome">
            <div>
                <h2>Welcome back, <?= htmlspecialchars($admin_name) ?>! 👋</h2>
                <p>You have <strong><?= $total_students ?> students</strong> enrolled and <strong>12 new updates</strong> today.</p>
            </div>
            <div class="welcome-icon">
                <i class="fa-solid fa-rocket"></i>
            </div>
        </div>


        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $total_students ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                <div class="stat-info">
                    <div class="stat-value"><?= $total_teachers ?></div>
                    <div class="stat-label">Total Teachers</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-indian-rupee-sign"></i></div>
                <div class="stat-info">
                    <div class="stat-value">₹4.2L</div>
                    <div class="stat-label">Fees Collected</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-book"></i></div>
                <div class="stat-info">
                    <div class="stat-value">1,250</div>
                    <div class="stat-label">Library Books</div>
                </div>
            </div>
        </div>


        <!-- Quick Actions -->
        <div class="section-header">
            <h3><i class="fa-solid fa-bolt" style="color:var(--violet)"></i> Quick Actions</h3>
        </div>

        <div class="actions-grid">
            <a href="add_student.php" class="action-card ac-violet">
                <div class="action-icon"><i class="fa-solid fa-user-plus"></i></div>
                <div class="action-title">Add Student</div>
                <div class="action-desc">Register new student</div>
            </a>

<a href="add_teacher.php" class="action-card ac-blue"> <!-- Removed ID and JS block -->
    <div class="action-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
    <div class="action-title">Add Teacher</div>
    <div class="action-desc">Onboard faculty</div>
</a>  


<a href="fee_receipt.php" class="action-card ac-green">
                <div class="action-icon"><i class="fa-solid fa-receipt"></i></div>
                <div class="action-title">Fee Receipt</div>
                <div class="action-desc">Generate receipts</div>
                <span class="action-badge">5 Pending</span>
            </a>

            <a href="libarary.php" class="action-card ac-orange">
                <div class="action-icon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <div class="action-title">Library Upload</div>
                <div class="action-desc">Upload books & resources</div>
            </a>

            <a href="gallary_admin.php" class="action-card ac-pink">
                <div class="action-icon"><i class="fa-solid fa-images"></i></div>
                <div class="action-title">Gallery Upload</div>
                <div class="action-desc">Upload event photos</div>
            </a>

            <a href="security_access_control.php" class="action-card ac-teal">
                <div class="action-icon"><i class="fa-solid fa-user-shield"></i></div>
                <div class="action-title">Role Manager</div>
                <div class="action-desc">Manage user roles</div>
            </a>

            <a href="annocement.php" class="action-card ac-indigo">
                <div class="action-icon"><i class="fa-solid fa-bullhorn"></i></div>
                <div class="action-title">Announcement</div>
                <div class="action-desc">Post announcements</div>
                <span class="action-badge">Important</span>
            </a>

            <a href="create_event.php" class="action-card ac-cyan">
                <div class="action-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <div class="action-title">Events</div>
                <div class="action-desc">Manage school events</div>
            </a>

            <a href="report.php" class="action-card ac-yellow">
                <div class="action-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div class="action-title">Reports</div>
                <div class="action-desc">Analytics & reports</div>
            </a>

            <a href="add_user.php" class="action-card ac-red">
                <div class="action-icon"><i class="fa-solid fa-lock"></i></div>
                <div class="action-title">View Users</div>
                <div class="action-desc">Access control & logs</div>
            </a>
        </div>


        <!-- Charts -->
        <div class="charts-grid">

            <!-- Bar Chart -->
            <div class="chart-card">
                <h3><i class="fa-solid fa-chart-bar"></i> Student Enrollment</h3>
                <div class="bar-chart" id="barChart">
                    <div class="bar-group">
                        <div class="bar-value" id="bv1"><?= $puc_sci ?></div>
                        <div class="bar bar-1" id="b1"></div>
                        <div class="bar-label">PUC Sci</div>
                    </div>
                    <div class="bar-group">
                        <div class="bar-value" id="bv2"><?= $puc_com ?></div>
                        <div class="bar bar-2" id="b2"></div>
                        <div class="bar-label">PUC Com</div>
                    </div>
                    <div class="bar-group">
                        <div class="bar-value" id="bv3"><?= $bca_count ?></div>
                        <div class="bar bar-3" id="b3"></div>
                        <div class="bar-label">BCA</div>
                    </div>
                    <div class="bar-group">
                        <div class="bar-value" id="bv4"><?= $mca_count ?></div>
                        <div class="bar bar-4" id="b4"></div>
                        <div class="bar-label">MCA</div>
                    </div>
                </div>
            </div>

            <!-- Pie Chart -->
            <div class="chart-card">
                <h3><i class="fa-solid fa-chart-pie"></i> Fee Status</h3>
                <div class="pie-wrap">
                    <div class="pie" id="pieChart">
                        <div class="pie-center">
                            <div class="total">₹4.2L</div>
                            <div class="total-label">Total Fees</div>
                        </div>
                    </div>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--green)"></div>
                            Collected (65%)
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--orange)"></div>
                            Pending (20%)
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--yellow)"></div>
                            Partial (10%)
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background:var(--text-dim)"></div>
                            Exempted (5%)
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</main>


<script>
    // Sidebar toggle
    function toggleSidebar(){
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('overlay').classList.toggle('show');
    }

    // Logout confirmation
    function confirmLogout(){
        if(confirm('Are you sure you want to logout?')){
            window.location.href = 'logout.php';
        }
    }

    // ═══════════ BAR CHART ANIMATION ═══════════
    function animateBars(){
        const values = [
            <?= $puc_sci ?: 0 ?>,
            <?= $puc_com ?: 0 ?>,
            <?= $bca_count ?: 0 ?>,
            <?= $mca_count ?: 0 ?>
        ];

        const maxVal = Math.max(...values, 1);
        const maxHeight = 180;

        for(let i = 1; i <= 4; i++){
            const bar = document.getElementById('b' + i);
            const height = (values[i-1] / maxVal) * maxHeight;
            bar.style.height = Math.max(height, 10) + 'px';
        }
    }

    // ═══════════ PIE CHART ═══════════
    function drawPie(){
        const pie = document.getElementById('pieChart');
        // Collected 65%, Pending 20%, Partial 10%, Exempted 5%
        const collected = 65;
        const pending   = 20;
        const partial   = 10;
        const exempted  = 5;

        const g = `conic-gradient(
            var(--green) 0% ${collected}%,
            var(--orange) ${collected}% ${collected + pending}%,
            var(--yellow) ${collected + pending}% ${collected + pending + partial}%,
            var(--text-dim) ${collected + pending + partial}% 100%
        )`;

        pie.style.background = g;
    }

    // Init
    setTimeout(animateBars, 300);
    drawPie();
</script>

</body>
</html>-