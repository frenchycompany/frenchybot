<?php
/**
 * FrenchyBot Admin - Parametres chatbot (redirige vers chatbot-edit.php)
 */
header('Location: chatbot-edit.php?id=' . intval($_GET['id'] ?? 0));
exit;
