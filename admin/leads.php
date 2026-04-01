<?php
/**
 * Admin - Leads Management
 */
require_once '../includes/config.php';

if (!isset($_SESSION['admin_logged_in'])) {
    redirect('index.php');
}

$page_title = 'Gestion des leads';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtres
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_source = isset($_GET['source']) ? $_GET['source'] : '';

// Construction de la requête
$where = [];
$params = [];

if ($filter_status === 'new') {
    $where[] = 'l.is_treated = 0';
} elseif ($filter_status === 'treated') {
    $where[] = 'l.is_treated = 1';
}

if ($filter_type) {
    $where[] = 'l.type_demande = ?';
    $params[] = $filter_type;
}

if ($filter_source) {
    $where[] = 'l.source = ?';
    $params[] = $filter_source;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Compte total
$count_sql = "SELECT COUNT(*) FROM leads l $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$total_pages = ceil($total / $per_page);

// Récupération des leads
$sql = "SELECT l.*, m.nom as modele_nom 
        FROM leads l 
        LEFT JOIN modeles m ON l.modele_interesse = m.id 
        $where_clause 
        ORDER BY l.created_at DESC 
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$leads = $stmt->fetchAll();

include 'includes/admin-header.php';
?>

<div class="admin-content">
    <h1 class="admin-title">Gestion des leads</h1>
    
    <?php echo displayFlashMessages(); ?>
    
    <!-- Filtres -->
    <div class="admin-section">
        <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div>
                <label style="display: block; font-size: 13px; margin-bottom: 5px;">Statut</label>
                <select name="status" class="form-control" style="padding: 8px 12px; border: 1px solid var(--color-gray-light); border-radius: 6px;">
                    <option value="">Tous</option>
                    <option value="new" <?php echo $filter_status == 'new' ? 'selected' : ''; ?>>Nouveaux</option>
                    <option value="treated" <?php echo $filter_status == 'treated' ? 'selected' : ''; ?>>Traités</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; margin-bottom: 5px;">Type</label>
                <select name="type" class="form-control" style="padding: 8px 12px; border: 1px solid var(--color-gray-light); border-radius: 6px;">
                    <option value="">Tous</option>
                    <option value="devis" <?php echo $filter_type == 'devis' ? 'selected' : ''; ?>>Devis</option>
                    <option value="rappel" <?php echo $filter_type == 'rappel' ? 'selected' : ''; ?>>Rappel</option>
                    <option value="info" <?php echo $filter_type == 'info' ? 'selected' : ''; ?>>Information</option>
                </select>
            </div>
            <div>
                <label style="display: block; font-size: 13px; margin-bottom: 5px;">Source</label>
                <select name="source" class="form-control" style="padding: 8px 12px; border: 1px solid var(--color-gray-light); border-radius: 6px;">
                    <option value="">Toutes</option>
                    <option value="chatbot" <?php echo $filter_source == 'chatbot' ? 'selected' : ''; ?>>Chatbot</option>
                    <option value="site-web" <?php echo $filter_source == 'site-web' ? 'selected' : ''; ?>>Site web</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filtrer</button>
            <a href="leads.php" class="btn btn-outline">Réinitialiser</a>
        </form>
    </div>
    
    <!-- Export -->
    <div class="admin-section" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="margin: 0; color: white;">📊 Export des données</h3>
                <p style="margin: 5px 0 0 0; opacity: 0.9;">Téléchargez tous vos leads au format CSV (Excel)</p>
            </div>
            <a href="export-leads.php?status=<?php echo $filter_status; ?>" class="btn btn-white" style="background: white; color: #667eea;">
                📥 Exporter en CSV
            </a>
        </div>
    </div>
    
    <!-- Table -->
    <div class="admin-section">
        <div class="section-header">
            <h2><?php echo $total; ?> lead(s) trouvé(s)</h2>
        </div>
        
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Nom</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Source</th>
                    <th>Modèle</th>
                    <th>Localisation</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr class="<?php echo $lead['is_treated'] ? '' : 'unread'; ?>">
                    <td><?php echo date('d/m/Y H:i', strtotime($lead['created_at'])); ?></td>
                    <td>
                        <strong><?php echo clean($lead['prenom'] . ' ' . $lead['nom']); ?></strong>
                    </td>
                    <td>
                        <a href="mailto:<?php echo $lead['email']; ?>"><?php echo $lead['email']; ?></a><br>
                        <a href="tel:<?php echo str_replace(' ', '', $lead['telephone']); ?>"><?php echo $lead['telephone']; ?></a>
                    </td>
                    <td><?php echo ucfirst($lead['type_demande']); ?></td>
                    <td>
                        <?php if ($lead['source'] === 'chatbot'): ?>
                        <span class="badge" style="background:#1a5653;color:#fff;padding:3px 8px;border-radius:4px;font-size:11px;">Chatbot</span>
                        <?php else: ?>
                        <span style="font-size:12px;color:#888;"><?php echo clean($lead['source'] ?? 'site-web'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $lead['modele_nom'] ?? '-'; ?></td>
                    <td><?php echo $lead['ville'] ? clean($lead['ville'] . ' (' . $lead['code_postal'] . ')') : '-'; ?></td>
                    <td>
                        <?php if ($lead['is_treated']): ?>
                        <span class="badge badge-success">Traité</span>
                        <?php else: ?>
                        <span class="badge badge-warning">Nouveau</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="lead-view.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-primary">Voir</a>
                        <a href="lead-delete.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ce lead définitivement ?')" style="background: #e74c3c; color: white; margin-left: 5px;">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>&source=<?php echo $filter_source; ?>" class="btn btn-outline">← Précédent</a>
            <?php endif; ?>
            
            <span style="padding: 10px;">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>&source=<?php echo $filter_source; ?>" class="btn btn-outline">Suivant →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
