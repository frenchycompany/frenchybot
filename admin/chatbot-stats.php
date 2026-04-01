<?php
/**
 * Admin - Dashboard Chatbot ORCA
 */
require_once '../includes/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$page_title = 'Chatbot - Statistiques et Conversations';

// Supprimer une conversation
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM chatbot_conversations WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: chatbot.php?msg=deleted');
    exit;
}

// Vider toutes les conversations
if (isset($_GET['clear_all']) && $_GET['clear_all'] === 'confirm') {
    $pdo->query("DELETE FROM chatbot_conversations");
    header('Location: chatbot.php?msg=cleared');
    exit;
}

// Récupérer les statistiques globales
$stats = [];

// Total conversations
$stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_conversations");
$stats['total_conversations'] = $stmt->fetchColumn();

// Conversations actives (dernières 24h)
$stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_conversations 
                     WHERE last_activity > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$stats['active_24h'] = $stmt->fetchColumn();

// Leads générés
$stmt = $pdo->query("SELECT COUNT(*) FROM chatbot_conversations WHERE lead_id IS NOT NULL");
$stats['leads_generated'] = $stmt->fetchColumn();

// Taux de conversion
$stats['conversion_rate'] = $stats['total_conversations'] > 0 
    ? round(($stats['leads_generated'] / $stats['total_conversations']) * 100, 1)
    : 0;

// Conversations récentes
$stmt = $pdo->query("SELECT c.*, l.nom, l.prenom, l.email, l.telephone
                     FROM chatbot_conversations c
                     LEFT JOIN leads l ON c.lead_id = l.id
                     ORDER BY c.last_activity DESC
                     LIMIT 50");
$conversations = $stmt->fetchAll();

// Top intentions détectées
$stmt = $pdo->query("SELECT intention_detected, COUNT(*) as count 
                     FROM chatbot_messages 
                     WHERE intention_detected IS NOT NULL 
                     GROUP BY intention_detected 
                     ORDER BY count DESC 
                     LIMIT 10");
$intentions = $stmt->fetchAll();

// Conversations par jour (7 derniers jours)
$stmt = $pdo->query("SELECT DATE(started_at) as date, COUNT(*) as count
                     FROM chatbot_conversations
                     WHERE started_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                     GROUP BY DATE(started_at)
                     ORDER BY date ASC");
$conversations_by_day = $stmt->fetchAll();

include 'includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1 class="admin-title">🤖 Chatbot ORCA</h1>
        <a href="?clear_all=confirm" class="btn btn-danger" onclick="return confirm('⚠️ SUPPRIMER TOUTES LES CONVERSATIONS ? Cette action est irréversible !')">
            🗑️ Vider tout l'historique
        </a>
    </div>
    
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-success">Conversation supprimée.</div>
        <?php elseif ($_GET['msg'] === 'cleared'): ?>
        <div class="alert alert-success">Toutes les conversations ont été supprimées.</div>
        <?php elseif ($_GET['msg'] === 'learned'): ?>
        <div class="alert alert-success">✅ Le chatbot a appris ! Nouvelle intention ajoutée.</div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Statistiques -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="font-size: 32px; font-weight: 700; color: var(--color-primary);"><?php echo $stats['total_conversations']; ?></div>
            <div style="color: var(--color-gray); font-size: 14px;">Conversations totales</div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="font-size: 32px; font-weight: 700; color: #27ae60;"><?php echo $stats['active_24h']; ?></div>
            <div style="color: var(--color-gray); font-size: 14px;">Actives (24h)</div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="font-size: 32px; font-weight: 700; color: #e74c3c;"><?php echo $stats['leads_generated']; ?></div>
            <div style="color: var(--color-gray); font-size: 14px;">Leads générés</div>
        </div>
        <div class="stat-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="font-size: 32px; font-weight: 700; color: #f39c12;"><?php echo $stats['conversion_rate']; ?>%</div>
            <div style="color: var(--color-gray); font-size: 14px;">Taux conversion</div>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        <!-- Conversations récentes -->
        <div class="admin-section">
            <h2 style="margin-bottom: 20px; font-size: 18px;">Conversations récentes</h2>
            
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Visiteur</th>
                            <th>Progression</th>
                            <th>Statut</th>
                            <th>Lead</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conv): ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($conv['last_activity'])); ?></td>
                            <td>
                                <?php if ($conv['lead_id']): ?>
                                <strong><?php echo htmlspecialchars(($conv['prenom'] ?? '') . ' ' . ($conv['nom'] ?? '')); ?></strong>
                                <?php else: ?>
                                <span style="color: #999;">Anonyme</span>
                                <?php endif; ?>
                                <br>
                                <small style="color: #999;"><?php echo substr($conv['ip_address'], 0, 20); ?></small>
                            </td>
                            <td>
                                <div style="background: #eee; height: 8px; border-radius: 4px; overflow: hidden; width: 100px;">
                                    <div style="background: var(--color-primary); height: 100%; width: <?php echo min($conv['completion_score'], 100); ?>%;"></div>
                                </div>
                                <small><?php echo $conv['completion_score']; ?>%</small>
                            </td>
                            <td>
                                <?php if ($conv['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                <span class="badge badge-error">Terminée</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($conv['lead_id']): ?>
                                <a href="leads.php?id=<?php echo $conv['lead_id']; ?>" class="badge badge-success">#<?php echo $conv['lead_id']; ?></a>
                                <?php else: ?>
                                <span class="badge badge-error">Non</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions-cell">
                                <a href="chatbot-view.php?id=<?php echo $conv['id']; ?>" class="btn-icon" title="Voir" style="background: #e3f2fd; color: #1976d2;">👁️</a>
                                <a href="chatbot-learn.php?conv=<?php echo $conv['id']; ?>" class="btn-icon" title="Apprendre de cette conversation" style="background: #e8f5e9; color: #2e7d32;">🧠</a>
                                <a href="?delete=<?php echo $conv['id']; ?>" class="btn-icon" title="Supprimer" style="background: #ffebee; color: #c62828;" onclick="return confirm('Supprimer cette conversation ?')">🗑️</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div>
            <!-- Intentions populaires -->
            <div class="admin-section" style="margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px; font-size: 18px;">Intentions détectées</h2>
                
                <?php if (empty($intentions)): ?>
                <p style="color: #999;">Aucune donnée disponible</p>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($intentions as $int): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="text-transform: capitalize;"><?php echo htmlspecialchars($int['intention_detected']); ?></span>
                        <span style="background: var(--color-primary); color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                            <?php echo $int['count']; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Activité 7 jours -->
            <div class="admin-section">
                <h2 style="margin-bottom: 20px; font-size: 18px;">Activité (7 jours)</h2>
                
                <?php if (empty($conversations_by_day)): ?>
                <p style="color: #999;">Aucune donnée disponible</p>
                <?php else: ?>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <?php foreach ($conversations_by_day as $day): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span><?php echo date('d/m', strtotime($day['date'])); ?></span>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="background: #eee; height: 6px; width: 60px; border-radius: 3px; overflow: hidden;">
                                <?php 
                                $max = max(array_column($conversations_by_day, 'count'));
                                $width = $max > 0 ? ($day['count'] / $max) * 100 : 0;
                                ?>
                                <div style="background: var(--color-primary); height: 100%; width: <?php echo $width; ?>%;"></div>
                            </div>
                            <span style="font-size: 12px; font-weight: 600;"><?php echo $day['count']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
