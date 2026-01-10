<?php
//dette.php - VERSION OPTIMALE
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);
// CORRECTION: Récupérer le VRAI ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'] ?? 1;

// Si user_id n'existe pas dans la session, le chercher
if(!isset($_SESSION['user_id']) && isset($_SESSION['user'])){
    $pseudo_session = mysqli_real_escape_string($conn, $_SESSION['user']);
    $user_query = mysqli_query($conn, "SELECT id FROM utilisateurs WHERE pseudo = '$pseudo_session'");
    if($user_row = mysqli_fetch_assoc($user_query)){
        $_SESSION['user_id'] = $user_row['id'];
        $user_id = $user_row['id'];
    }
}

$message = "";
$message_type = "";

// VÉRIFIER ET CRÉER LA COLONNE numero_facture
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM dettes LIKE 'numero_facture'");
if(mysqli_num_rows($check_column) == 0){
    mysqli_query($conn, "ALTER TABLE dettes ADD COLUMN numero_facture VARCHAR(50) AFTER id");
    mysqli_query($conn, "ALTER TABLE dettes ADD INDEX idx_numero_facture (numero_facture)");
}

// ============================================
// ENREGISTRER UNE NOUVELLE DETTE
// ============================================
if(isset($_POST['action']) && $_POST['action'] == 'enregistrer_dette'){
    $client_id = mysqli_real_escape_string($conn, $_POST['client_id']);
    $date_dette = mysqli_real_escape_string($conn, $_POST['date_dette']);
    
    // Générer le numéro de facture
    $annee = date('Y', strtotime($date_dette));
    $query_last = "SELECT numero_facture FROM dettes 
                   WHERE numero_facture LIKE 'FAC-$annee-%' 
                   ORDER BY CAST(SUBSTRING_INDEX(numero_facture, '-', -1) AS UNSIGNED) DESC 
                   LIMIT 1";
    $result_last = mysqli_query($conn, $query_last);
    
    if(mysqli_num_rows($result_last) > 0){
        $last = mysqli_fetch_assoc($result_last);
        $num = intval(substr($last['numero_facture'], -3)) + 1;
    } else {
        $num = 1;
    }
    $numero_facture = "FAC-$annee-" . str_pad($num, 3, '0', STR_PAD_LEFT);
    
    // Insérer les produits
    $produits = $_POST['produits'];
    $quantites = $_POST['quantites'];
    $prix = $_POST['prix'];
    
    mysqli_begin_transaction($conn);
    
    try {
        $nb_produits = 0;
        
        for($i = 0; $i < count($produits); $i++){
            if(!empty($produits[$i]) && !empty($quantites[$i]) && $quantites[$i] > 0){
                $produit_id = mysqli_real_escape_string($conn, $produits[$i]);
                $quantite = mysqli_real_escape_string($conn, $quantites[$i]);
                $prix_unitaire = mysqli_real_escape_string($conn, $prix[$i]);
                
                $sql = "INSERT INTO dettes 
                        (numero_facture, client_id, produit_id, quantite, prix_unitaire_fige, 
                         montant_paye, enregistre_par, date_dette) 
                        VALUES 
                        ('$numero_facture', '$client_id', '$produit_id', '$quantite', 
                         '$prix_unitaire', 0, '$user_id', '$date_dette')";
                
                if(!mysqli_query($conn, $sql)){
                    throw new Exception("Erreur ligne " . ($i+1) . ": " . mysqli_error($conn));
                }
                $nb_produits++;
            }
        }
        
        if($nb_produits == 0){
            throw new Exception("Aucun produit valide ajouté !");
        }
        
        mysqli_commit($conn);
        $message = "✅ Dette enregistrée ! N°: <strong>$numero_facture</strong> ($nb_produits produit(s))";
        $message_type = "success";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $message = "❌ " . $e->getMessage();
        $message_type = "error";
    }
}

// ============================================
// ENREGISTRER UN PAIEMENT (VERSION AMÉLIORÉE)
// ============================================
if(isset($_POST['action']) && $_POST['action'] == 'payer_dette'){
    $numero_facture = mysqli_real_escape_string($conn, $_POST['numero_facture']);
    $montant_paiement = floatval($_POST['montant_paiement']);
    
    if($montant_paiement <= 0){
        $message = "❌ Le montant doit être supérieur à 0 !";
        $message_type = "error";
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            // 1. Récupérer les infos de la facture
            $info_query = "SELECT 
                            d.id as dette_id,
                            d.client_id,
                            c.nom_complet,
                            SUM(d.montant_total) as total_facture,
                            SUM(d.montant_paye) as total_paye,
                            SUM(d.montant_restant) as total_reste
                          FROM dettes d
                          JOIN clients c ON d.client_id = c.id
                          WHERE d.numero_facture = '$numero_facture'
                          GROUP BY d.client_id, c.nom_complet
                          LIMIT 1";
            
            $info_result = mysqli_query($conn, $info_query);
            
            if(mysqli_num_rows($info_result) == 0){
                throw new Exception("Facture introuvable !");
            }
            
            $info = mysqli_fetch_assoc($info_result);
            
            // 2. Vérifier que le paiement ne dépasse pas le reste
            if($montant_paiement > $info['total_reste']){
                throw new Exception("Le montant ($montant_paiement Ar) dépasse le reste à payer (" . $info['total_reste'] . " Ar) !");
            }
            
            // 3. Générer la référence de paiement
            $prenom = explode(' ', $info['nom_complet'])[0];
            $prenom_clean = preg_replace("/[^A-Za-z0-9]/", '', $prenom); // Enlever tous les caractères spéciaux
            $date_ref = date('Ymd');
            $reference_base = strtoupper($prenom_clean) . '-' . $date_ref;
            
            // Vérifier si la référence existe déjà
            $ref_escaped = mysqli_real_escape_string($conn, $reference_base);
            $count_query = "SELECT COUNT(*) as nb FROM paiements_dette 
                           WHERE reference_paiement LIKE '$ref_escaped%' 
                           AND DATE(date_paiement) = CURDATE()";
            $count_result = mysqli_query($conn, $count_query);
            $count = mysqli_fetch_assoc($count_result)['nb'];
            
            $reference_paiement = $reference_base;
            if($count > 0){
                $reference_paiement .= '-' . ($count + 1);
            }
            
            // 4. Récupérer toutes les lignes de la facture avec leur reste
            $lignes_query = "SELECT id, montant_total, montant_paye, montant_restant 
                            FROM dettes 
                            WHERE numero_facture = '$numero_facture' 
                            AND montant_restant > 0
                            ORDER BY id";
            $lignes_result = mysqli_query($conn, $lignes_query);
            
            // 5. Répartir le paiement proportionnellement
            $reste_a_distribuer = $montant_paiement;
            
            while($ligne = mysqli_fetch_assoc($lignes_result)){
                if($reste_a_distribuer <= 0) break;
                
                // Calculer la part proportionnelle
                $part = min($ligne['montant_restant'], $reste_a_distribuer);
                
                // Mettre à jour cette ligne
                $update_sql = "UPDATE dettes 
                              SET montant_paye = montant_paye + $part
                              WHERE id = {$ligne['id']}";
                
                if(!mysqli_query($conn, $update_sql)){
                    throw new Exception("Erreur mise à jour ligne {$ligne['id']}");
                }
                
                $reste_a_distribuer -= $part;
            }
            
            // 6. Enregistrer dans l'historique
            $insert_paiement = "INSERT INTO paiements_dette 
                               (dette_id, montant_paye, mode_paiement, reference_paiement, 
                                enregistre_par, date_paiement) 
                               VALUES 
                               ('{$info['dette_id']}', '$montant_paiement', 'especes', 
                                '$reference_paiement', '$user_id', CURDATE())";
            
            if(!mysqli_query($conn, $insert_paiement)){
                throw new Exception("Erreur historique: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            
            $nouveau_reste = $info['total_reste'] - $montant_paiement;
            $message = "✅ Paiement de <strong>" . number_format($montant_paiement, 0, ',', ' ') . " Ar</strong> enregistré !<br>";
            $message .= "📄 Réf: <strong>$reference_paiement</strong><br>";
            $message .= "💰 Reste à payer: <strong>" . number_format($nouveau_reste, 0, ',', ' ') . " Ar</strong>";
            $message_type = "success";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "❌ " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// ============================================
// RÉCUPÉRER LES DONNÉES
// ============================================

// Clients avec dettes actives
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
                         HAVING total_reste > 0
                         ORDER BY total_reste DESC, c.nom_complet";
$clients_dettes_result = mysqli_query($conn, $clients_dettes_query);

// Tous les clients actifs
$clients_query = "SELECT * FROM clients WHERE actif = 1 ORDER BY nom_complet";
$clients_result = mysqli_query($conn, $clients_query);

// Tous les produits actifs
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-varootra - Gestion des Dettes</title>
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
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: white;
            letter-spacing: 2px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .user-avatar {
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
            width: 280px;
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
            padding: 15px 20px;
            margin: 5px 0;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .menu-item.active {
            background: #ff8c42;
            box-shadow: 0 4px 15px rgba(255, 140, 66, 0.3);
        }

        .logout-btn {
            margin: 20px 15px;
            padding: 15px 20px;
            background: #d9534f;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #c9302c;
            transform: translateY(-2px);
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
            display: flex;
            align-items: center;
            gap: 15px;
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
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            line-height: 1.6;
            animation: slideIn 0.3s ease;
        }

        .alert.success { 
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .alert.error { 
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .clients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
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
            position: relative;
            overflow: hidden;
        }

        .client-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, transparent 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .client-box:hover::before {
            opacity: 1;
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
            position: relative;
            z-index: 1;
        }

        .client-box-name {
            font-size: 20px;
            font-weight: bold;
            color: #0a4d4d;
        }

        .badge-factures {
            background: linear-gradient(135deg, #ff4757 0%, #ff6348 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(255, 71, 87, 0.3);
        }

        .client-box-info {
            color: #666;
            font-size: 14px;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .client-box-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .total-label {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        .total-amount {
            font-size: 26px;
            font-weight: bold;
            color: #ff4757;
        }

        .factures-container {
            display: none;
        }

        .factures-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            background: #5a6268;
            transform: translateX(-5px);
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
            backdrop-filter: blur(5px);
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 95%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-header h2 {
            color: #0a4d4d;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 30px;
            color: #999;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: #f0f0f0;
            color: #333;
            transform: rotate(90deg);
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
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .produit-row:hover {
            border-color: #667eea;
        }

        .btn-remove {
            height: 45px;
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-remove:hover {
            background: #ff3838;
            transform: scale(1.05);
        }

        .btn-add-produit {
            background: #2ed573;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-add-produit:hover {
            background: #26de81;
            transform: translateY(-2px);
        }

        .total-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 25px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .total-section > div:first-child {
            font-size: 18px;
            margin-bottom: 10px;
            opacity: 0.9;
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
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        /* Scrollbar personnalisée */
        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .main-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="logo-text">E-VAROOTRA</span>
        <div class="user-info">
            <span><?php echo $nom_utilisateur; ?></span>
            <div class="user-avatar"><?php echo strtoupper(substr($nom_utilisateur, 0, 1)); ?></div>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="menu">
                <a href="index.php" class="menu-item">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-chart-line"></i> Tableau de bord
                </a>
                <a href="client.php" class="menu-item">
                    <i class="fas fa-users"></i> Clients
                </a>
                <a href="produits.php" class="menu-item">
                    <i class="fas fa-box"></i> Produits
                </a>
                <a href="dette.php" class="menu-item active">
                    <i class="fas fa-file-invoice-dollar"></i> Dettes
                </a>
                <a href="archive.php" class="menu-item">
                    <i class="fas fa-archive"></i> Archive
                </a>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>

        <div class="main-content">
            <?php if($message): ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Vue Liste Clients -->
            <div id="clientsList">
                <div class="page-header">
                    <h1>
                        <i class="fas fa-file-invoice-dollar"></i>
                        Clients avec Dettes
                    </h1>
                    <button class="btn-add" onclick="openModal()">
                        <i class="fas fa-plus"></i> Nouvelle dette
                    </button>
                </div>

                <?php if(mysqli_num_rows($clients_dettes_result) > 0): ?>
                <div class="clients-grid">
                    <?php while($client = mysqli_fetch_assoc($clients_dettes_result)): ?>
                    <div class="client-box" onclick="showFactures(<?php echo $client['id']; ?>)">
                        <div class="client-box-header">
                            <div class="client-box-name"><?php echo htmlspecialchars($client['nom_complet']); ?></div>
                            <div class="badge-factures"><?php echo $client['nb_factures']; ?> facture<?php echo $client['nb_factures'] > 1 ? 's' : ''; ?></div>
                        </div>
                        <div class="client-box-info">
                            <i class="fas fa-phone"></i> 
                            <?php echo htmlspecialchars($client['telephone']); ?>
                        </div>
                        <div class="client-box-info">
                            <i class="fas fa-map-marker-alt"></i> 
                            <?php echo htmlspecialchars($client['adresse']); ?>
                        </div>
                        <div class="client-box-total">
                            <span class="total-label">Total à payer:</span>
                            <span class="total-amount"><?php echo number_format($client['total_reste'], 0, ',', ' '); ?> Ar</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune dette en cours</h3>
                    <p>Tous les clients ont payé leurs dettes !</p>
                </div>
                <?php endif; ?>
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

            <form method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="enregistrer_dette">

                <div class="form-section">
                    <h3 style="margin-bottom: 20px; color: #0a4d4d;">
                        <i class="fas fa-user"></i> Informations Client
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Client *</label>
                            <select name="client_id" required>
                                <option value="">-- Choisir un client --</option>
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
                            <input type="date" name="date_dette" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 style="margin-bottom: 20px; color: #0a4d4d;">
                        <i class="fas fa-shopping-cart"></i> Produits
                    </h3>
                    <div id="produitsList">
                        <div class="produit-row">
                            <div class="form-group">
                                <label>Produit *</label>
                                <select name="produits[]" class="produit-select" required onchange="updatePrice(this)">
                                    <option value="">-- Choisir --</option>
                                    <?php 
                                    mysqli_data_seek($produits_result, 0);
                                    while($produit = mysqli_fetch_assoc($produits_result)): 
                                    ?>
                                    <option value="<?php echo $produit['id']; ?>" data-prix="<?php echo $produit['prix_unitaire']; ?>">
                                        <?php echo htmlspecialchars($produit['nom']) . ' - ' . $produit['quantite_unite'] . $produit['unite_symbole'] . ' (' . number_format($produit['prix_unitaire'], 0, ',', ' ') . ' Ar)'; ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantité *</label>
                                <input type="number" name="quantites[]" class="quantite-input" step="0.01" min="0.01" required onchange="calculateTotal()">
                            </div>
                            <div class="form-group">
                                <label>Prix U. (Ar)</label>
                                <input type="number" name="prix[]" class="prix-input" step="0.01" required readonly style="background: #f0f0f0;">
                            </div>
                            <div class="form-group">
                                <label>Total (Ar)</label>
                                <input type="text" class="total-input" readonly style="background:#f0f0f0; font-weight: bold;">
                            </div>
                            <button type="button" class="btn-remove" onclick="removeRow(this)" style="display:none;" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-add-produit" onclick="addRow()">
                        <i class="fas fa-plus"></i> Ajouter un produit
                    </button>
                </div>

                <div class="total-section">
                    <div>MONTANT TOTAL DE LA DETTE</div>
                    <div class="total-amount-modal" id="totalGeneral">0 Ar</div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Enregistrer la dette
                </button>
            </form>
        </div>
    </div>

    <script>
        // Afficher les factures d'un client
        function showFactures(clientId) {
            document.getElementById('clientsList').style.display = 'none';
            
            fetch(`get_factures.php?client_id=${clientId}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('facturesView').innerHTML = html;
                    document.getElementById('facturesView').classList.add('active');
                })
                .catch(err => {
                    alert('Erreur lors du chargement des factures');
                    console.error(err);
                });
        }

        // Retour à la liste des clients
        function backToList() {
            document.getElementById('facturesView').classList.remove('active');
            document.getElementById('clientsList').style.display = 'block';
        }

        // Ouvrir le modal
        function openModal() {
            document.getElementById('detteModal').classList.add('active');
        }

        // Fermer le modal
        function closeModal() {
            document.getElementById('detteModal').classList.remove('active');
        }

        // Ajouter une ligne de produit
        function addRow() {
            const container = document.getElementById('produitsList');
            const firstRow = container.querySelector('.produit-row');
            const newRow = firstRow.cloneNode(true);
            
            // Réinitialiser les valeurs
            newRow.querySelectorAll('input').forEach(i => i.value = '');
            newRow.querySelector('select').selectedIndex = 0;
            newRow.querySelector('.btn-remove').style.display = 'block';
            
            container.appendChild(newRow);
            calculateTotal();
        }

        // Supprimer une ligne de produit
        function removeRow(btn) {
            const container = document.getElementById('produitsList');
            if(container.querySelectorAll('.produit-row').length > 1){
                btn.closest('.produit-row').remove();
                calculateTotal();
            } else {
                alert('Vous devez garder au moins un produit !');
            }
        }

        // Mettre à jour le prix quand on change de produit
        function updatePrice(select) {
            const row = select.closest('.produit-row');
            const prix = select.options[select.selectedIndex].getAttribute('data-prix');
            row.querySelector('.prix-input').value = prix || '';
            calculateTotal();
        }

        // Calculer le total
        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.produit-row').forEach(row => {
                const qte = parseFloat(row.querySelector('.quantite-input').value) || 0;
                const prix = parseFloat(row.querySelector('.prix-input').value) || 0;
                const sousTotal = qte * prix;
                row.querySelector('.total-input').value = sousTotal > 0 ? sousTotal.toLocaleString('fr-FR') + ' Ar' : '';
                total += sousTotal;
            });
            document.getElementById('totalGeneral').textContent = total.toLocaleString('fr-FR') + ' Ar';
        }

        // Valider le formulaire
        function validateForm() {
            const rows = document.querySelectorAll('.produit-row');
            let hasValidProduct = false;
            
            rows.forEach(row => {
                const produit = row.querySelector('.produit-select').value;
                const quantite = parseFloat(row.querySelector('.quantite-input').value) || 0;
                
                if(produit && quantite > 0) {
                    hasValidProduct = true;
                }
            });
            
            if(!hasValidProduct) {
                alert('Veuillez ajouter au moins un produit avec une quantité valide !');
                return false;
            }
            
            return confirm('Voulez-vous enregistrer cette dette ?');
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('detteModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });

        // Fermer avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>