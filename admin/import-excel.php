<?php
/**
 * FrenchyBot Admin - Import de produits depuis fichier Excel
 * Importe dans la BDD externe du chatbot (terrains, modeles, etc.)
 */
define('FRENCHYBOT', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$admin_user = requireAdmin();
$page_title = 'Import Excel';

$chatbot_id = intval($_GET['chatbot_id'] ?? 0);
if (!$chatbot_id) { header('Location: dashboard.php'); exit; }

$client_id = getClientChatbotId();
if ($client_id) requireChatbotAccess($chatbot_id);

$chatbot = getChatbotById($chatbot_id);
if (!$chatbot) { header('Location: dashboard.php'); exit; }

$results = null;
$error = '';
$imported = 0;

// Handle import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $target_table = trim($_POST['target_table'] ?? 'terrains');
    $import_mode = $_POST['import_mode'] ?? 'add'; // add, replace
    $column_mapping = $_POST['mapping'] ?? [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Erreur upload fichier.';
    } elseif (!preg_match('/\.(xlsx|xls|csv)$/i', $file['name'])) {
        $error = 'Format accepte : .xlsx, .xls, .csv';
    } else {
        require_once __DIR__ . '/../vendor/autoload.php';

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);

            if (empty($rows)) {
                $error = 'Fichier vide.';
            } else {
                $headers = array_map('trim', $rows[0]);

                // Connexion BDD cible
                $targetDb = null;
                if ($chatbot['ext_db_enabled'] && !empty($chatbot['ext_db_name'])) {
                    try {
                        $targetDb = new PDO(
                            'mysql:host=' . ($chatbot['ext_db_host'] ?: 'localhost') . ';dbname=' . $chatbot['ext_db_name'] . ';charset=utf8mb4',
                            $chatbot['ext_db_user'] ?: 'root',
                            $chatbot['ext_db_pass'] ?: '',
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
                        );
                    } catch (PDOException $e) {
                        $error = 'Erreur connexion BDD externe : ' . $e->getMessage();
                    }
                } else {
                    $error = 'BDD externe non configuree pour ce chatbot. Configurez-la dans les parametres.';
                }

                if ($targetDb && !empty($column_mapping)) {
                    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $target_table);

                    // Mode replace : vider la table avant import
                    if ($import_mode === 'replace') {
                        $targetDb->exec("DELETE FROM $safeTable");
                    }

                    // Filtre optionnel (ex: colonne "Etat annonce" = "Active")
                    $filterCol = $_POST['filter_col'] ?? '';
                    $filterVal = $_POST['filter_val'] ?? '';

                    // Preparer les colonnes cibles
                    $dbCols = [];
                    $excelCols = [];
                    $transforms = [];
                    foreach ($column_mapping as $key => $mapping) {
                        if (empty($mapping['db_col'])) continue;
                        $dbCols[] = preg_replace('/[^a-zA-Z0-9_]/', '', $mapping['db_col']);
                        // Key format: "idx_i" — extract excel column index
                        $parts = explode('_', $key);
                        $excelCols[] = intval($parts[0]);
                        $transforms[] = $mapping['transform'] ?? 'none';
                    }

                    if (empty($dbCols)) {
                        $error = 'Aucune colonne mappee.';
                    } else {
                        $placeholders = implode(',', array_fill(0, count($dbCols), '?'));
                        $colList = implode(',', $dbCols);
                        $insertSql = "INSERT INTO $safeTable ($colList) VALUES ($placeholders)";
                        $stmt = $targetDb->prepare($insertSql);

                        $skipped = 0;
                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];

                            // Filtre
                            if ($filterCol !== '' && $filterVal !== '') {
                                $filterIdx = intval($filterCol);
                                if (isset($row[$filterIdx]) && trim($row[$filterIdx]) !== $filterVal) {
                                    $skipped++;
                                    continue;
                                }
                            }

                            // Construire les valeurs
                            $values = [];
                            foreach ($excelCols as $idx => $excelIdx) {
                                $val = $row[$excelIdx] ?? '';
                                $transform = $transforms[$idx];

                                switch ($transform) {
                                    case 'extract_number':
                                        // Extrait le premier nombre (ex: "Terrain 600 m²" → 600)
                                        preg_match('/(\d+)/', str_replace([' ', "\xc2\xa0"], '', $val), $m);
                                        $val = $m[1] ?? 0;
                                        break;
                                    case 'extract_dept':
                                        // Extrait le departement du code postal (ex: "60190" → "60")
                                        $val = substr(trim($val), 0, 2);
                                        break;
                                    case 'to_int':
                                        $val = intval(preg_replace('/[^0-9]/', '', $val));
                                        break;
                                    case 'to_float':
                                        $val = floatval(preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $val)));
                                        break;
                                    case 'is_active':
                                        // "Active" → 1, sinon 0
                                        $val = (mb_strtolower(trim($val)) === 'active') ? 1 : 0;
                                        break;
                                    case 'boolean_yes':
                                        $val = in_array(mb_strtolower(trim($val)), ['oui', 'yes', '1', 'true']) ? 1 : 0;
                                        break;
                                    default:
                                        $val = trim($val);
                                }
                                $values[] = $val;
                            }

                            try {
                                $stmt->execute($values);
                                $imported++;
                            } catch (PDOException $e) {
                                // Skip duplicates or errors
                                $skipped++;
                            }
                        }

                        flash('success', "$imported terrains importes ($skipped ignores).");
                        header('Location: import-excel.php?chatbot_id=' . $chatbot_id . '&done=1');
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $error = 'Erreur lecture fichier : ' . $e->getMessage();
        }
    }
}

// Preview: charger les en-tetes si un fichier est uploade en preview
$previewHeaders = [];
$previewRows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview']) && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    if ($file['error'] === UPLOAD_ERR_OK && preg_match('/\.(xlsx|xls|csv)$/i', $file['name'])) {
        require_once __DIR__ . '/../vendor/autoload.php';
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            if (!empty($rows)) {
                $previewHeaders = $rows[0];
                $previewRows = array_slice($rows, 1, 5);
            }
            // Sauvegarder temporairement
            $tmpPath = sys_get_temp_dir() . '/fb_import_' . session_id() . '.xlsx';
            copy($file['tmp_name'], $tmpPath);
            $_SESSION['import_tmp_file'] = $tmpPath;
        } catch (Exception $e) {
            $error = 'Erreur lecture : ' . $e->getMessage();
        }
    }
}

// Charger les colonnes de la table cible
$targetColumns = [];
if ($chatbot['ext_db_enabled'] && !empty($chatbot['ext_db_name'])) {
    try {
        $extPdo = new PDO(
            'mysql:host=' . ($chatbot['ext_db_host'] ?: 'localhost') . ';dbname=' . $chatbot['ext_db_name'] . ';charset=utf8mb4',
            $chatbot['ext_db_user'] ?: 'root',
            $chatbot['ext_db_pass'] ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $tables = $extPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $tables = [];
    }
}

include __DIR__ . '/includes/admin-header.php';
?>

<div class="admin-content">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="chatbot-edit.php?id=<?= $chatbot_id ?>" style="color:var(--color-primary);text-decoration:none;font-size:20px;">&larr;</a>
        <h1 class="admin-title" style="margin:0;">Import Excel — <?= e($chatbot['name']) ?></h1>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['done'])): ?>
    <div class="alert alert-success">Import termine avec succes !</div>
    <?php endif; ?>

    <?php foreach (getFlash() as $f): ?>
    <div class="alert alert-<?= $f['type'] === 'error' ? 'error' : 'success' ?>"><?= $f['message'] ?></div>
    <?php endforeach; ?>

    <?php if (!$chatbot['ext_db_enabled']): ?>
    <div class="alert alert-warning">
        La BDD externe n'est pas configuree pour ce chatbot.
        <a href="chatbot-edit.php?id=<?= $chatbot_id ?>">Configurer maintenant</a>
    </div>
    <?php else: ?>

    <!-- Formulaire d'import -->
    <div class="admin-section">
        <h2 style="font-size:18px;margin-bottom:16px;">1. Selectionnez le fichier Excel</h2>

        <form method="post" enctype="multipart/form-data" id="importForm">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:16px;">
                <div class="form-group">
                    <label>Fichier (.xlsx, .xls, .csv)</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Table cible</label>
                    <select name="target_table" class="form-control">
                        <?php foreach ($tables ?? [] as $t): ?>
                        <option value="<?= e($t) ?>"><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mode d'import</label>
                    <select name="import_mode" class="form-control">
                        <option value="add">Ajouter aux donnees existantes</option>
                        <option value="replace">Remplacer toutes les donnees</option>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <div class="form-group">
                    <label>Filtrer : colonne Excel (numero, 0=premiere)</label>
                    <input type="text" name="filter_col" class="form-control" placeholder="3" value="3">
                </div>
                <div class="form-group">
                    <label>Filtrer : valeur requise</label>
                    <input type="text" name="filter_val" class="form-control" placeholder="Active" value="Active">
                </div>
            </div>

            <h2 style="font-size:18px;margin:24px 0 16px;">2. Mapping des colonnes</h2>
            <p style="font-size:13px;color:var(--color-gray);margin-bottom:16px;">
                Pour chaque colonne de votre fichier Excel, indiquez la colonne cible dans la base de donnees et la transformation a appliquer.
            </p>

            <div id="mappings">
                <!-- Mapping pre-configure pour terrains ORCA -->
                <div class="mapping-row" style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;gap:8px;margin-bottom:8px;align-items:center;">
                    <strong style="font-size:12px;color:var(--color-gray);">Col</strong>
                    <strong style="font-size:12px;color:var(--color-gray);">Colonne Excel</strong>
                    <strong style="font-size:12px;color:var(--color-gray);">Colonne BDD cible</strong>
                    <strong style="font-size:12px;color:var(--color-gray);">Transformation</strong>
                </div>

                <?php
                // Mapping par defaut pour terrains ORCA
                $defaultMappings = [
                    0 => ['label' => 'Reference', 'db_col' => 'reference', 'transform' => 'none'],
                    1 => ['label' => 'Titre', 'db_col' => 'surface', 'transform' => 'extract_number'],
                    2 => ['label' => 'Prix', 'db_col' => 'prix', 'transform' => 'to_float'],
                    3 => ['label' => 'Etat annonce', 'db_col' => 'is_available', 'transform' => 'is_active'],
                    8 => ['label' => 'Ville', 'db_col' => 'ville', 'transform' => 'none'],
                    9 => ['label' => 'Code postal', 'db_col' => 'code_postal', 'transform' => 'none'],
                    9 => ['label' => 'Code postal → Dept', 'db_col' => 'departement', 'transform' => 'extract_dept'],
                    12 => ['label' => 'Description', 'db_col' => 'description', 'transform' => 'none'],
                ];
                // On a besoin de 2 mappings pour col 9 (code_postal + departement)
                $allMappings = [
                    ['idx' => 0, 'label' => 'Reference', 'db_col' => 'reference', 'transform' => 'none'],
                    ['idx' => 1, 'label' => 'Titre (surface)', 'db_col' => 'surface', 'transform' => 'extract_number'],
                    ['idx' => 2, 'label' => 'Prix', 'db_col' => 'prix', 'transform' => 'to_float'],
                    ['idx' => 3, 'label' => 'Etat annonce', 'db_col' => 'is_available', 'transform' => 'is_active'],
                    ['idx' => 8, 'label' => 'Ville', 'db_col' => 'ville', 'transform' => 'none'],
                    ['idx' => 9, 'label' => 'Code postal', 'db_col' => 'code_postal', 'transform' => 'none'],
                    ['idx' => 9, 'label' => 'CP → Departement', 'db_col' => 'departement', 'transform' => 'extract_dept'],
                    ['idx' => 12, 'label' => 'Description', 'db_col' => 'description', 'transform' => 'none'],
                ];
                foreach ($allMappings as $i => $m):
                ?>
                <div style="display:grid;grid-template-columns:40px 1fr 1fr 1fr;gap:8px;margin-bottom:6px;align-items:center;">
                    <span style="font-size:13px;color:var(--color-gray);text-align:center;"><?= $m['idx'] ?></span>
                    <span style="font-size:13px;"><?= e($m['label']) ?></span>
                    <input type="text" name="mapping[<?= $m['idx'] ?>_<?= $i ?>][db_col]" class="form-control" style="padding:6px 10px;font-size:13px;" value="<?= e($m['db_col']) ?>">
                    <select name="mapping[<?= $m['idx'] ?>_<?= $i ?>][transform]" class="form-control" style="padding:6px 10px;font-size:13px;">
                        <option value="none" <?= $m['transform'] === 'none' ? 'selected' : '' ?>>Texte brut</option>
                        <option value="extract_number" <?= $m['transform'] === 'extract_number' ? 'selected' : '' ?>>Extraire nombre</option>
                        <option value="extract_dept" <?= $m['transform'] === 'extract_dept' ? 'selected' : '' ?>>Extraire departement (CP)</option>
                        <option value="to_int" <?= $m['transform'] === 'to_int' ? 'selected' : '' ?>>Nombre entier</option>
                        <option value="to_float" <?= $m['transform'] === 'to_float' ? 'selected' : '' ?>>Nombre decimal</option>
                        <option value="is_active" <?= $m['transform'] === 'is_active' ? 'selected' : '' ?>>Active → 1/0</option>
                        <option value="boolean_yes" <?= $m['transform'] === 'boolean_yes' ? 'selected' : '' ?>>Oui/Non → 1/0</option>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:24px;display:flex;gap:12px;">
                <button type="submit" name="import" value="1" class="btn btn-primary" style="padding:12px 32px;"
                        onclick="return confirm('Lancer l\'import ?')">Importer</button>
            </div>
        </form>
    </div>

    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
