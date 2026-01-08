<?php
//dette.php
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);
$user_id = 1;

$message = "";
$message_type = "";

// AJOUTER COLONNE numero_facture SI N'EXISTE PAS
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM dettes LIKE 'numero_facture'");
if(mysqli_num_rows($check_column) == 0){
    mysqli_query($conn, "ALTER TABLE dettes ADD COLUMN numero_facture VARCHAR(50) AFTER id");
    mysqli_query($conn, "ALTER TABLE dettes ADD INDEX idx_numero_facture (numero_facture)");
} else {
    // Vérifier si l'index unique existe et le supprimer
    $check_unique = mysqli_query($conn, "SHOW INDEXES FROM dettes WHERE Key_name = 'numero_facture' AND Non_unique = 0");
    if(mysqli_num_rows($check_unique) > 0){
        mysqli_query($conn, "ALTER TABLE dettes DROP INDEX numero_facture");
        mysqli_query($conn, "ALTER TABLE dettes ADD INDEX idx_numero_facture (numero_facture)");
    }
}

// ENREGISTRER UNE DETTE
if(isset($_POST['action']) && $_POST['action'] == 'enregistrer_dette'){
    $client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
    $date_dette = mysqli_real_escape_string($conn, $_POST['date_dette']);
    
    // Générer UN SEUL numéro de facture pour tous les produits
    $annee = date('Y');
    $last_facture = mysqli_query($conn, "SELECT numero_facture FROM dettes WHERE numero_facture LIKE 'FAC-$annee-%' ORDER BY CAST(SUBSTRING_INDEX(numero_facture, '-', -1) AS UNSIGNED) DESC LIMIT 1");
    
    if(mysqli_num_rows($last_facture) > 0){
        $last = mysqli_fetch_assoc($last_facture);
        $num = intval(substr($last['numero_facture'], -3)) + 1;
    } else {
        $num = 1;
    }
    $numero_facture = "FAC-$annee-" . str_pad($num, 3, '0', STR_PAD_LEFT);
    
    $produits = $_POST['produits'];
    $quantites = $_POST['quantites'];
    $prix = $_POST['prix'];
    
    $success = true;
    $nb_produits = 0;
    
    // IMPORTANT: Utiliser le MÊME numéro de facture pour TOUS les produits
    for($i = 0; $i < count($produits); $i++){
        if(!empty($produits[$i]) && !empty($quantites[$i])){
            $produit_id = mysqli_real_escape_string($conn, $produits[$i]);
            $quantite = mysqli_real_escape_string($conn, $quantites[$i]);
            $prix_unitaire = mysqli_real_escape_string($conn, $prix[$i]);
            
            $sql = "INSERT INTO dettes (numero_facture, client_id, produit_id, quantite, prix_unitaire_fige, montant_paye, enregistre_par, date_dette) 
                    VALUES ('$numero_facture', '$client_id', '$produit_id', '$quantite', '$prix_unitaire', 0, '$user_id', '$date_dette')";
            
            if(mysqli_query($conn, $sql)){
                $nb_produits++;
            } else {
                $success = false;
                $message = "Erreur produit " . ($i+1) . ": " . mysqli_error($conn);
                $message_type = "error";
                break;
            }
        }
    }
    
    if($success && $nb_produits > 0){
        $message = "Dette enregistrée avec succès ! N°: $numero_facture ($nb_produits produit(s))";
        $message_type = "success";
    } elseif($nb_produits == 0) {
        $message = "Aucun produit valide ajouté !";
        $message_type = "error";
    }
}

// ENREGISTRER UN PAIEMENT
if(isset($_POST['action']) && $_POST['action'] == 'payer_dette'){
    $numero_facture = mysqli_real_escape_string($conn, $_POST['numero_facture']);
    $montant_paiement = mysqli_real_escape_string($conn, $_POST['montant_paiement']);
    
    // Récupérer les infos de la dette et du client
    $info_query = mysqli_query($conn, "SELECT d.id, d.client_id, c.nom_complet 
                                        FROM dettes d 
                                        JOIN clients c ON d.client_id = c.id 
                                        WHERE d.numero_facture = '$numero_facture' 
                                        LIMIT 1");
    $info = mysqli_fetch_assoc($info_query);
    
    // Générer la référence unique: Prénom + Date
    $prenom = explode(' ', $info['nom_complet'])[0]; // Prendre le premier mot du nom
    $date_ref = date('Ymd'); // Format: 20260108
    // Nettoyer le prénom des caractères spéciaux
    $prenom_clean = str_replace("'", "", $prenom); // Enlever les apostrophes
    $reference_paiement = strtoupper($prenom_clean) . '-' . $date_ref;
    
    // Vérifier si une référence identique existe déjà aujourd'hui
    // Vérifier si une référence identique existe déjà aujourd'hui
    $reference_escaped = mysqli_real_escape_string($conn, $reference_paiement);
    $count_ref = mysqli_query($conn, "SELECT COUNT(*) as nb FROM paiements_dette 
                                    WHERE reference_paiement LIKE '$reference_escaped%' 
                                    AND DATE(date_paiement) = CURDATE()");
    $count = mysqli_fetch_assoc($count_ref)['nb'];
    
    // Ajouter un compteur si nécessaire
    if($count > 0){
        $reference_paiement .= '-' . ($count + 1);
    }
    
    // Mettre à jour toutes les lignes de dettes avec ce numéro de facture
    $update_sql = "UPDATE dettes 
                   SET montant_paye = montant_paye + $montant_paiement 
                   WHERE numero_facture = '$numero_facture'";
    
    if(mysqli_query($conn, $update_sql)){
        // Enregistrer dans l'historique des paiements
        $insert_paiement = "INSERT INTO paiements_dette 
                           (dette_id, montant_paye, mode_paiement, reference_paiement, enregistre_par, date_paiement) 
                           VALUES ('{$info['id']}', '$montant_paiement', 'especes', '$reference_paiement', '$user_id', CURDATE())";
        
        if(mysqli_query($conn, $insert_paiement)){
            $message = "✅ Paiement de " . number_format($montant_paiement, 0, ',', ' ') . " Ar enregistré ! Réf: $reference_paiement";
            $message_type = "success";
        } else {
            $message = "⚠️ Dette mise à jour mais erreur historique: " . mysqli_error($conn);
            $message_type = "error";
        }
    } else {
        $message = "❌ Erreur lors du paiement: " . mysqli_error($conn);
        $message_type = "error";
    }
}

// RÉCUPÉRER LES CLIENTS AVEC DETTES ACTIVES
$clients_dettes_query = "SELECT 
                            c.id,
                            c.nom_complet,
                            c.telephone,
                            c.adresse,
                            COUNT(DISTINCT d.numero_facture) as nb_factures,
                            SUM(d.montant_restant) as total_reste
                         FROM clients c
                         INNER JOIN dettes d ON c.id = d.client_id
                         WHERE d.statut IN ('active', 'partiellement_payee')
                         GROUP BY c.id, c.nom_complet, c.telephone, c.adresse
                         ORDER BY c.nom_complet";
$clients_dettes_result = mysqli_query($conn, $clients_dettes_query);

// RÉCUPÉRER LES CLIENTS
$clients_query = "SELECT * FROM clients WHERE actif = 1 ORDER BY nom_complet";
$clients_result = mysqli_query($conn, $clients_query);

// RÉCUPÉRER LES PRODUITS
$produits_query = "SELECT p.*, u.symbole as unite_symbole 
                   FROM produits p 
                   JOIN unites u ON p.unite_id = u.id 
                   WHERE p.actif = 1 
                   ORDER BY p.nom";
$produits_result = mysqli_query($conn, $produits_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>E-varootra - Dettes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #0a4d4d 0%, #0d6666 100%);
            height: 70px;
            display: flex;
            align-items: center;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: white;
            letter-spacing: 2px;
        }

        .user-avatar {
            position: absolute;
            right: 30px;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #0a4d4d;
            font-weight: bold;
            border: 3px solid #ff8c42;
        }

        .container {
            display: flex;
            height: calc(100vh - 70px);
        }

        .sidebar {
            width: 320px;
            background: linear-gradient(180deg, #0a4d4d 0%, #063838 100%);
            padding: 30px 0;
            display: flex;
            flex-direction: column;
        }

        .menu {
            flex: 1;
            padding: 0 15px;
        }

        .menu-item {
            display: block;
            padding: 18px 25px;
            margin: 8px 0;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: #ff8c42;
        }

        .logout-btn {
            margin: 20px 15px;
            padding: 18px 25px;
            background: #d9534f;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #0a4d4d;
            font-size: 32px;
        }

        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }

        .clients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .client-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
        }

        .client-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .client-box-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .client-box-name {
            font-size: 20px;
            font-weight: bold;
            color: #0a4d4d;
        }

        .badge-factures {
            background: #ff4757;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .client-box-info {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }

        .client-box-total {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px dashed #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-weight: 600;
            color: #333;
        }

        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #ff4757;
        }

        .factures-container {
            display: none;
        }

        .factures-container.active {
            display: block;
        }

        .facture-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            font-family: 'Courier New', monospace;
        }

        .facture-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px double #000;
            padding-bottom: 20px;
        }

        .store-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .facture-info {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
        }

        .facture-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .facture-table th,
        .facture-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px dashed #ccc;
        }

        .facture-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .facture-totals {
            margin-top: 20px;
            border-top: 2px solid #000;
            padding-top: 15px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 16px;
        }

        .total-line.main {
            font-size: 20px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 15px;
            margin-top: 10px;
        }

        .paiement-section {
            background: #fff5f5;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .paiement-form {
            display: flex;
            gap: 15px;
            align-items: end;
            margin-top: 15px;
        }

        .paiement-form input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }

        .btn-payer {
            background: #2ed573;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn-payer:hover {
            background: #26d467;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 95%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 30px;
            color: #999;
            cursor: pointer;
        }

        .form-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .produit-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 60px;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
        }

        .btn-remove {
            height: 45px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-add-produit {
            background: #2ed573;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }

        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-top: 25px;
            text-align: center;
        }

        .total-amount-modal {
            font-size: 42px;
            font-weight: bold;
        }

        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }

        @media print {
            body * { visibility: hidden; }
            .facture-card, .facture-card * { visibility: visible; }
            .facture-card { position: absolute; left: 0; top: 0; }
            .paiement-section { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="logo-text">E-VAROOTRA</span>
        <div class="user-avatar"><?php echo strtoupper(substr($nom_utilisateur, 0, 1)); ?></div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="menu">
                <a href="index.php" class="menu-item">Accueil</a>
                <a href="dashboard.php" class="menu-item">Tableau de bord</a>
                <a href="client.php" class="menu-item">Client</a>
                <a href="produits.php" class="menu-item">Produits</a>
                <a href="dette.php" class="menu-item active">Dette</a>
                <a href="archive.php" class="menu-item">Archive</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <div class="main-content">
            <?php if($message): ?>
                <div class="alert <?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Vue Liste Clients -->
            <div id="clientsList">
                <div class="page-header">
                    <h1><i class="fas fa-file-invoice-dollar"></i> Clients avec Dettes</h1>
                    <button class="btn-add" onclick="openModal()">
                        <i class="fas fa-plus"></i> Nouvelle dette
                    </button>
                </div>

                <div class="clients-grid">
                    <?php while($client = mysqli_fetch_assoc($clients_dettes_result)): ?>
                    <div class="client-box" onclick="showFactures(<?php echo $client['id']; ?>)">
                        <div class="client-box-header">
                            <div class="client-box-name"><?php echo htmlspecialchars($client['nom_complet']); ?></div>
                            <div class="badge-factures"><?php echo $client['nb_factures']; ?> facture(s)</div>
                        </div>
                        <div class="client-box-info">
                            <i class="fas fa-phone"></i> <?php echo $client['telephone']; ?>
                        </div>
                        <div class="client-box-info">
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($client['adresse']); ?>
                        </div>
                        <div class="client-box-total">
                            <span class="total-label">Total à payer:</span>
                            <span class="total-amount"><?php echo number_format($client['total_reste'], 0, ',', ' '); ?> Ar</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Vue Factures -->
            <div id="facturesView" class="factures-container"></div>
        </div>
    </div>

    <!-- Modal Nouvelle Dette -->
    <div class="modal" id="detteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-invoice"></i> Nouvelle dette</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="enregistrer_dette">

                <div class="form-section">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Client *</label>
                            <select name="client_id" required>
                                <option value="">-- Choisir --</option>
                                <?php 
                                mysqli_data_seek($clients_result, 0);
                                while($client = mysqli_fetch_assoc($clients_result)): 
                                ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($client['nom_complet']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="date_dette" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div id="produitsList">
                        <div class="produit-row">
                            <div class="form-group">
                                <label>Produit</label>
                                <select name="produits[]" class="produit-select" required onchange="updatePrice(this)">
                                    <option value="">-- Choisir --</option>
                                    <?php 
                                    mysqli_data_seek($produits_result, 0);
                                    while($produit = mysqli_fetch_assoc($produits_result)): 
                                    ?>
                                    <option value="<?php echo $produit['id']; ?>" data-prix="<?php echo $produit['prix_unitaire']; ?>">
                                        <?php echo htmlspecialchars($produit['nom']) . ' - ' . $produit['quantite_unite'] . $produit['unite_symbole']; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Qté</label>
                                <input type="number" name="quantites[]" class="quantite-input" step="0.01" required onchange="calculateTotal()">
                            </div>
                            <div class="form-group">
                                <label>Prix U.</label>
                                <input type="number" name="prix[]" class="prix-input" step="0.01" required readonly>
                            </div>
                            <div class="form-group">
                                <label>Total</label>
                                <input type="text" class="total-input" readonly style="background:#f0f0f0;">
                            </div>
                            <button type="button" class="btn-remove" onclick="removeRow(this)" style="display:none;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-produit" onclick="addRow()">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>

                <div class="total-section">
                    <div>TOTAL</div>
                    <div class="total-amount-modal" id="totalGeneral">0 Ar</div>
                </div>

                <button type="submit" class="btn-submit">Enregistrer</button>
            </form>
        </div>
    </div>

    <script>
        function showFactures(clientId) {
            document.getElementById('clientsList').style.display = 'none';
            
            fetch(`get_factures.php?client_id=${clientId}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('facturesView').innerHTML = html;
                    document.getElementById('facturesView').classList.add('active');
                });
        }

        function backToList() {
            document.getElementById('facturesView').classList.remove('active');
            document.getElementById('clientsList').style.display = 'block';
        }

        function openModal() {
            document.getElementById('detteModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('detteModal').classList.remove('active');
        }

        function addRow() {
            const container = document.getElementById('produitsList');
            const newRow = container.querySelector('.produit-row').cloneNode(true);
            newRow.querySelectorAll('input').forEach(i => i.value = '');
            newRow.querySelector('select').selectedIndex = 0;
            newRow.querySelector('.btn-remove').style.display = 'block';
            container.appendChild(newRow);
        }

        function removeRow(btn) {
            btn.closest('.produit-row').remove();
            calculateTotal();
        }

        function updatePrice(select) {
            const row = select.closest('.produit-row');
            const prix = select.options[select.selectedIndex].getAttribute('data-prix');
            row.querySelector('.prix-input').value = prix || '';
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.produit-row').forEach(row => {
                const qte = parseFloat(row.querySelector('.quantite-input').value) || 0;
                const prix = parseFloat(row.querySelector('.prix-input').value) || 0;
                const sousTotal = qte * prix;
                row.querySelector('.total-input').value = sousTotal.toLocaleString('fr-FR') + ' Ar';
                total += sousTotal;
            });
            document.getElementById('totalGeneral').textContent = total.toLocaleString('fr-FR') + ' Ar';
        }

        document.getElementById('detteModal').addEventListener('click', function(e) {
            if(e.target === this) closeModal();
        });
    </script>
</body>
</html>