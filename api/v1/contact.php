<?php
/**
 * FrenchyBot - Handler formulaire de contact landing page
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$nom = trim($_POST['nom'] ?? '');
$email = trim($_POST['email'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$secteur = trim($_POST['secteur'] ?? '');
$site = trim($_POST['site'] ?? '');

if (empty($nom) || empty($email)) {
    echo json_encode(['error' => 'Nom et email requis']);
    exit;
}

// Envoyer email a l'admin
$subject = "FrenchyBot - Nouvelle demande de demo : $nom";
$body = "
<div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;'>
    <div style='background:#1a5653;color:white;padding:20px;border-radius:8px 8px 0 0;'>
        <h2 style='margin:0;'>Nouvelle demande de demo</h2>
    </div>
    <div style='background:white;padding:25px;border:1px solid #eee;border-radius:0 0 8px 8px;'>
        <table style='width:100%;border-collapse:collapse;'>
            <tr><td style='padding:8px 0;color:#888;width:120px;'>Nom :</td><td style='padding:8px 0;font-weight:bold;'>$nom</td></tr>
            <tr><td style='padding:8px 0;color:#888;'>Email :</td><td style='padding:8px 0;'><a href='mailto:$email'>$email</a></td></tr>
            <tr><td style='padding:8px 0;color:#888;'>Telephone :</td><td style='padding:8px 0;font-weight:bold;'>$telephone</td></tr>
            <tr><td style='padding:8px 0;color:#888;'>Secteur :</td><td style='padding:8px 0;'>$secteur</td></tr>
            <tr><td style='padding:8px 0;color:#888;'>Site web :</td><td style='padding:8px 0;'><a href='$site'>$site</a></td></tr>
        </table>
    </div>
</div>";

sendEmail('raphael@frenchycompany.fr', $subject, $body, 'FrenchyBot');

echo json_encode(['success' => true]);
