<?php
/**
 * FrenchyBot Admin - Header
 */
if (!defined('FRENCHYBOT')) {
    define('FRENCHYBOT', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
$admin_user = requireAdmin();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FrenchyBot Admin<?= isset($page_title) ? " - $page_title" : '' ?></title>
    <style>
        :root {
            --color-primary: #1a5653;
            --color-primary-dark: #0f3d3a;
            --color-primary-light: #e8f0ef;
            --color-gray: #6b7280;
            --color-gray-light: #e5e7eb;
            --color-bg: #f5f7f9;
            --color-white: #ffffff;
            --color-success: #10b981;
            --color-warning: #f59e0b;
            --color-danger: #ef4444;
            --color-info: #3b82f6;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--color-bg);
            color: #333;
            min-height: 100vh;
        }
        .navbar {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-dark));
            color: #fff;
            padding: 0 24px;
            display: flex;
            align-items: center;
            height: 56px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            margin-right: 32px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .navbar-brand span {
            opacity: 0.7;
            font-weight: 400;
            font-size: 12px;
        }
        .navbar-nav {
            display: flex;
            gap: 4px;
            list-style: none;
            flex: 1;
        }
        .navbar-nav a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
        }
        .navbar-nav a:hover, .navbar-nav a.active {
            color: #fff;
            background: rgba(255,255,255,0.15);
        }
        .navbar-user {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: rgba(255,255,255,0.8);
        }
        .navbar-user a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            padding: 6px 12px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 6px;
            font-size: 12px;
            transition: all 0.15s;
        }
        .navbar-user a:hover {
            background: rgba(255,255,255,0.15);
            color: #fff;
        }
        main {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px;
        }
        .admin-content { margin-bottom: 40px; }
        .admin-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #1f2937;
        }
        .admin-section {
            background: var(--color-white);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            margin-bottom: 20px;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .admin-table th {
            text-align: left;
            padding: 10px 12px;
            background: var(--color-bg);
            font-weight: 600;
            color: var(--color-gray);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--color-gray-light);
        }
        .admin-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .admin-table tr:hover { background: #fafbfc; }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }
        .btn-primary {
            background: var(--color-primary);
            color: #fff;
        }
        .btn-primary:hover { background: var(--color-primary-dark); }
        .btn-outline {
            background: transparent;
            color: var(--color-primary);
            border: 1.5px solid var(--color-primary);
        }
        .btn-outline:hover { background: var(--color-primary-light); }
        .btn-danger {
            background: var(--color-danger);
            color: #fff;
        }
        .btn-danger:hover { opacity: 0.9; }
        .btn-sm { padding: 5px 10px; font-size: 12px; border-radius: 6px; }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .form-control {
            padding: 10px 14px;
            border: 1.5px solid var(--color-gray-light);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.15s;
            width: 100%;
        }
        .form-control:focus { border-color: var(--color-primary); }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: #374151;
        }
        .form-group small { color: var(--color-gray); font-size: 12px; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--color-white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
        }
        .stat-card .stat-label {
            font-size: 13px;
            color: var(--color-gray);
            margin-top: 4px;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 16px;
            border-radius: 8px;
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 13px;
            line-height: 1.5;
            overflow-x: auto;
            position: relative;
        }
        .code-block .copy-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255,255,255,0.1);
            color: #94a3b8;
            border: none;
            padding: 4px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }
        .code-block .copy-btn:hover { background: rgba(255,255,255,0.2); color: #fff; }
        @media (max-width: 768px) {
            .navbar { padding: 0 12px; }
            .navbar-nav { gap: 0; }
            .navbar-nav a { padding: 8px 10px; font-size: 13px; }
            main { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">FrenchyBot <span>v<?= FB_VERSION ?></span></a>
        <ul class="navbar-nav">
            <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
            <?php if (isAdmin()): ?>
            <li><a href="chatbot-create.php" class="<?= basename($_SERVER['PHP_SELF']) === 'chatbot-create.php' ? 'active' : '' ?>">Nouveau chatbot</a></li>
            <?php endif; ?>
            <?php
            $nav_chatbot_param = getClientChatbotId() ? '?chatbot_id=' . getClientChatbotId() : '';
            ?>
            <li><a href="chatbot-intentions.php<?= $nav_chatbot_param ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'chatbot-intentions.php' ? 'active' : '' ?>">Intentions</a></li>
            <li><a href="chatbot-stats.php<?= $nav_chatbot_param ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'chatbot-stats.php' ? 'active' : '' ?>">Stats</a></li>
            <li><a href="leads.php<?= $nav_chatbot_param ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'leads.php' ? 'active' : '' ?>">Leads</a></li>
        </ul>
        <div class="navbar-user">
            <span><?= e($admin_user['username']) ?></span>
            <a href="index.php?logout=1">Deconnexion</a>
        </div>
    </nav>
<?php
    // Flash messages
    $flash_messages = getFlash();
    if (!empty($flash_messages)):
?>
    <div style="max-width:1280px;margin:16px auto 0;padding:0 24px;">
    <?php foreach ($flash_messages as $fm): ?>
        <div class="alert alert-<?= $fm['type'] === 'error' ? 'error' : ($fm['type'] === 'warning' ? 'warning' : 'success') ?>"><?= $fm['message'] ?></div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
    <main>
