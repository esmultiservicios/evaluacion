<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$appName = setting('app_name', 'Evaluación Corporativa');
$company = setting('company_name', 'Tu empresa');
$logo = setting('logo_path', '');
$browserTitle = browser_title();
$favicon = site_favicon('../');
$questionCampaignImage = setting('question_campaign_image', 'assets/img/soar-cybersecurity-2026.jpg');
$sessionBadge = trim((string)($_SESSION['participant_employee_badge'] ?? $_SESSION['game_employee_badge'] ?? ''));
$participantQuery = $sessionBadge !== '' ? '?participant='.rawurlencode($sessionBadge) : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#073763">
    <title><?=e($browserTitle)?> · Preguntas</title>
    <link rel="icon" href="<?=e($favicon)?>">
    <link rel="shortcut icon" href="<?=e($favicon)?>">
    <link rel="apple-touch-icon" href="<?=e($favicon)?>">
    <link rel="stylesheet" href="../assets/css/app.css?v=29">
    <link rel="stylesheet" href="../assets/css/notify.css?v=8">
    <link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.local.css?v=8">
    <script>window.APP={csrf:<?=json_encode(csrf_token())?>,questionsPerAttempt:<?=json_encode(max(1,(int)setting('questions_per_attempt','5')))?>,campaignImage:<?=json_encode('../'.ltrim($questionCampaignImage,'/'),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>,apiBase:'../api/',questionsPath:'./',gamesPath:'../juegos/'};</script>
</head>
<body class="public-body evaluation-booting">
<header class="public-topbar">
    <div class="public-nav">
        <a class="public-brand" href="./">
            <span class="heds-navbar-logo"><img src="../assets/img/heds-logo-navbar.jpg" alt="HEDS · Honduras Electrical Distribution Systems"></span>
            <span><strong><?=e($appName)?></strong><small>Zona interactiva · <?=e($company)?></small></span>
        </a>
        <nav class="public-nav-actions">
            <div class="site-switch" aria-label="Cambiar entre sitios">
                <span class="site-switch-label">SITIOS</span>
                <a class="site-link is-current" href="./<?=e($participantQuery)?>" aria-current="page" title="Sitio de evaluación"><?=ui_icon('question')?><span>Preguntas</span></a>
                <a class="site-link" href="../juegos/<?=e($participantQuery)?>" title="Ir al sitio de juegos"><?=ui_icon('external')?><span>Juegos</span></a>
            </div>
            <button type="button" class="sound-toggle" data-public-sound-toggle aria-label="Activar o desactivar sonido">🔊 <span>Sonido</span></button>
            <button class="screen-control" type="button" data-fullscreen-toggle aria-label="Pantalla completa" title="Pantalla completa"><?=ui_icon('fullscreen')?><span data-fullscreen-label>Pantalla completa</span></button>
            <a href="../admin/" class="admin-access" target="_blank" rel="noopener noreferrer" aria-label="Administración" title="Administración · abrir en otra pestaña"><?=ui_icon('settings')?><span>Administración</span><b>→</b></a>
        </nav>
    </div>
</header>

<main class="public-shell">
    <section class="evaluation-identify-shell motion-card" id="evaluationGate">
        <div class="evaluation-identify-visual">
            <div class="soar-identify-banner" aria-label="SOAR · Security Opportunity Action Recognition">
                <img src="../<?=e($questionCampaignImage)?>" alt="SOAR · Security Opportunity Action Recognition">
            </div>
            <p class="hero-kicker">EXPERIENCIA PERSONALIZADA</p>
            <h1>Antes de evaluar,<br>¿quién eres?</h1>
            <p>Ingresa tu gafete. Usaremos tu nombre para saludarte, asignarte las preguntas según la configuración y registrar tu participación.</p>
            <div class="evaluation-identify-points">
                <span>✓ Preguntas asignadas para ti</span>
                <span>✓ Una participación identificada por gafete</span>
                <span>✓ Experiencia personalizada</span>
            </div>
        </div>

        <div class="evaluation-identify-card">
            <div id="identifyStep">
                <span class="eyebrow">IDENTIFICACIÓN</span>
                <h2>Ingresa tu gafete</h2>
                <p class="evaluation-identify-copy">Confirmaremos tu nombre antes de mostrar tu evaluación.</p>
                <div class="field evaluation-badge-field">
                    <label for="badge">Número de gafete</label>
                    <div class="evaluation-badge-row">
                        <div class="input-icon"><?=ui_icon('users')?> <input id="badge" name="badge" autocomplete="off" inputmode="numeric" pattern="[0-9]+" maxlength="40" placeholder="Ej. 4500329" autofocus></div>
                        <button class="btn btn-primary evaluation-continue" id="continueBtn" disabled><span>Continuar</span><span>→</span></button>
                    </div>
                </div>
                <div id="employeePreview" class="employee-preview hidden"></div>
                <div class="public-note">Tus respuestas quedarán registradas una sola vez y se asociarán a tu gafete.</div>
            </div>
        </div>
    </section>

    <section class="brand-card motion-card hidden" id="evaluationBrand">
        <div class="brand-logo">
            <?php if($logo):?><img src="<?=e($logo)?>" alt="<?=e($company)?>"><?php else:?><div class="logo-placeholder"><span>EC</span></div><?php endif;?>
        </div>
        <div>
            <p class="eyebrow">DINÁMICA CORPORATIVA</p>
            <h1><?=e($appName)?></h1>
            <p><?=e(setting('welcome_text','Ingresa tu número de gafete para comenzar. La evaluación solo puede completarse una vez.'))?></p>
        </div>
        <span class="single-entry-badge">1 participación por empleado</span>
    </section>

    <section class="evaluation-card motion-card hidden" id="evaluacion">
        <div id="identityConfirmStep" class="hidden"></div>
        <div id="quizStep" class="hidden"></div>
    </section>

    <footer><?=e($company)?> · <?=date('Y')?> · <?=e($appName)?></footer>
</main>
<script src="../assets/vendor/sweetalert2/sweetalert2.local.js?v=7"></script>
<script src="../assets/js/notify.js?v=7"></script>
<script src="../assets/js/fullscreen.js?v=1"></script>
<script src="../assets/js/public-sound.js?v=1"></script>
<script src="../assets/js/app.js?v=23"></script>
</body>
</html>
