<?php
/**
 * FrenchyBot - Fonctions utilitaires
 */

/**
 * Nettoyer une chaine pour affichage HTML
 */
function clean(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirection
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Message flash (stocke en session)
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Afficher les messages flash
 */
function displayFlashMessages(): string {
    if (empty($_SESSION['flash'])) return '';

    $html = '';
    foreach ($_SESSION['flash'] as $flash) {
        $class = $flash['type'] === 'error' ? 'alert-error' : 'alert-success';
        $html .= '<div class="alert ' . $class . '">' . clean($flash['message']) . '</div>';
    }
    $_SESSION['flash'] = [];
    return $html;
}

/**
 * Envoyer un email simple
 */
function sendEmail(string $to, string $subject, string $body, string $fromName = 'FrenchyBot'): bool {
    $from = 'noreply@frenchycompany.fr';
    $headers = [
        'From' => "$fromName <$from>",
        'Reply-To' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
    ];
    $headerStr = '';
    foreach ($headers as $k => $v) {
        $headerStr .= "$k: $v\r\n";
    }
    return mail($to, $subject, $body, $headerStr);
}

/**
 * Envoyer une notification de nouveau lead par email
 */
function notifyNewLead(array $chatbot, array $leadData): void {
    if (!$chatbot['email_notifications'] || empty($chatbot['notification_email'])) return;

    $prenom = $leadData['prenom'] ?? 'Inconnu';
    $nom = $leadData['nom'] ?? '';
    $email = $leadData['email'] ?? '';
    $tel = $leadData['telephone'] ?? '';

    $subject = "[{$chatbot['name']}] Nouveau lead : $prenom $nom";
    $body = "<h2>Nouveau lead depuis le chatbot {$chatbot['name']}</h2>"
        . "<p><strong>Prenom :</strong> $prenom</p>"
        . "<p><strong>Nom :</strong> $nom</p>"
        . "<p><strong>Email :</strong> $email</p>"
        . "<p><strong>Telephone :</strong> $tel</p>"
        . "<p><strong>Date :</strong> " . date('d/m/Y H:i') . "</p>"
        . "<hr><p><em>FrenchyBot - " . APP_URL . "</em></p>";

    sendEmail($chatbot['notification_email'], $subject, $body, $chatbot['name']);
}

/**
 * Appeler un webhook (n8n, Zapier, etc.)
 */
function triggerWebhook(string $url, array $data, array $headers = []): bool {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => array_merge(
            ['Content-Type: application/json'],
            $headers
        ),
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Declencher les webhooks configures pour un chatbot
 */
function triggerChatbotWebhooks(int $chatbot_id, string $eventType, array $data): void {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM chatbot_webhooks WHERE chatbot_id = ? AND is_active = 1 AND (event_type = ? OR event_type = 'all')");
    $stmt->execute([$chatbot_id, $eventType]);

    foreach ($stmt->fetchAll() as $webhook) {
        $headers = json_decode($webhook['headers'] ?? '{}', true) ?: [];
        $headerArray = [];
        foreach ($headers as $k => $v) {
            $headerArray[] = "$k: $v";
        }

        $success = triggerWebhook($webhook['webhook_url'], $data, $headerArray);

        $pdo->prepare("UPDATE chatbot_webhooks SET last_triggered = NOW(), last_response = ? WHERE id = ?")
            ->execute([$success ? 'OK' : 'FAIL', $webhook['id']]);
    }
}

/**
 * Formater une date en francais
 */
function formatDateFr(?string $date): string {
    if (!$date) return '-';
    return date('d/m/Y H:i', strtotime($date));
}
