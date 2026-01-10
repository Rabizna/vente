<?php
//client.php - VERSION CORRIGÉE
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);

// Gestion des actions (Ajouter, Modifier, Supprimer)
$message = "";
$message_type = "";

// AJOUTER UN CLIENT
if(isset($_POST['action']) && $_POST['action'] == 'ajouter'){
    $nom_complet = mysqli_real_escape_string($conn, $_POST['nom_complet']);
    $telephone = mysqli_real_escape_string($conn, $_POST['telephone']);
    $adresse = mysqli_real_escape_string($conn, $_POST['adresse']);
    
    $sql = "INSERT INTO clients (nom_complet, telephone, adresse) 
            VALUES ('$nom_complet', '$telephone', '$adresse')";
    
    if(mysqli_query($conn, $sql)){
        $message = "✅ Client ajouté avec succès !";
        $message_type = "success";
    } else {
        $message = "❌ Erreur : " . mysqli_error($conn);
        $message_type = "error";
    }
}

// MODIFIER UN CLIENT
if(isset($_POST['action']) && $_POST['action'] == 'modifier'){
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nom_complet = mysqli_real_escape_string($conn, $_POST['nom_complet']);
    $telephone = mysqli_real_escape_string($conn, $_POST['telephone']);
    $adresse = mysqli_real_escape_string($conn, $_POST['adresse']);
    
    $sql = "UPDATE clients SET 
            nom_complet='$nom_complet', 
            telephone='$telephone', 
            adresse='$adresse'
            WHERE id='$id'";
    
    if(mysqli_query($conn, $sql)){
        $message = "✅ Client modifié avec succès !";
        $message_type = "success";
    } else {
        $message = "❌ Erreur : " . mysqli_error($conn);
        $message_type = "error";
    }
}

// SUPPRIMER UN CLIENT (désactiver) - AVEC VÉRIFICATION
if(isset($_GET['supprimer'])){
    $id = mysqli_real_escape_string($conn, $_GET['supprimer']);
    
    // Vérifier si le client a des dettes actives
    $check_dettes = mysqli_query($conn, "SELECT COUNT(*) as nb FROM dettes 
                                         WHERE client_id = '$id' 
                                         AND statut IN ('active', 'partiellement_payee')");
    $dette_count = mysqli_fetch_assoc($check_dettes)['nb'];
    
    if($dette_count > 0){
        $message = "❌ Impossible de supprimer ce client ! Il a encore $dette_count facture(s) active(s).";
        $message_type = "error";
    } else {
        $sql = "UPDATE clients SET actif=0 WHERE id='$id'";
        
        if(mysqli_query($conn, $sql)){
            $message = "✅ Client supprimé avec succès !";
            $message_type = "success";
        }
    }
}

// RÉCUPÉRER TOUS LES CLIENTS
$clients_query = "SELECT c.*, 
                  (SELECT COUNT(DISTINCT d.numero_facture) 
                   FROM dettes d 
                   WHERE d.client_id = c.id 
                   AND d.statut IN ('active', 'partiellement_payee')) as dettes_actives,
                  (SELECT COALESCE(SUM(d2.montant_restant), 0) 
                   FROM dettes d2 
                   WHERE d2.client_id = c.id 
                   AND d2.statut IN ('active', 'partiellement_payee')) as total_dette
                  FROM clients c 
                  WHERE c.actif = 1 
                  ORDER BY c.date_creation DESC";
$clients_result = mysqli_query($conn, $clients_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-varootra - Clients</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            z-index: 100;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: white;
            letter-spacing: 2px;
        }

        .user-profile {
            position: absolute;
            right: 30px;
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
            width: 320px;
            background: linear-gradient(180deg, #0a4d4d 0%, #063838 100%);
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
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
            letter-spacing: 1px;
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
            padding: 18px 25px;
            background: #d9534f;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .logout-btn:hover {
            background: #c9302c;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(217, 83, 79, 0.4);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            background: #f5f5f5;
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
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .search-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .search-bar input {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .search-bar input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .clients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .client-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .client-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .client-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .client-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .client-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .client-info {
            flex: 1;
        }

        .client-name {
            font-size: 20px;
            font-weight: bold;
            color: #0a4d4d;
            margin-bottom: 5px;
        }

        .client-date {
            font-size: 13px;
            color: #999;
        }

        .client-details {
            margin-bottom: 20px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            color: #555;
        }

        .detail-row i {
            width: 20px;
            color: #667eea;
        }

        .client-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0a4d4d;
        }

        .stat-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        .client-actions {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-edit {
            background: #ffa502;
            color: white;
        }

        .btn-edit:hover {
            background: #ff7f00;
            transform: scale(1.05);
        }

        .btn-delete {
            background: #ff4757;
            color: white;
        }

        .btn-delete:hover {
            background: #d63031;
            transform: scale(1.05);
        }

        /* ✅ NOUVEAU: Style pour bouton désactivé */
        .btn-delete:disabled {
            background: #cccccc;
            color: #999;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .btn-delete:disabled:hover {
            background: #cccccc;
            transform: none;
        }

        .badge-dette {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            background: #ff4757;
            color: white;
        }

        .badge-dette.no-dette {
            background: #2ed573;
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
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 90%;
            max-width: 550px;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .modal-header h2 {
            color: #0a4d4d;
            font-size: 28px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 30px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            color: #ff4757;
            transform: rotate(90deg);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 100px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            font-size: 16px;
        }

        /* Tooltip pour bouton désactivé */
        .tooltip {
            position: relative;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 220px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -110px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <span class="logo-text">E-VAROOTRA</span>
        </div>
        <div class="user-profile">
            <div class="user-avatar" title="<?php echo $nom_utilisateur; ?>">
                <?php echo strtoupper(substr($nom_utilisateur, 0, 1)); ?>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="sidebar">
            <div class="menu">
                <a href="index.php" class="menu-item">Accueil</a>
                <a href="dashboard.php" class="menu-item">Tableau de bord</a>
                <a href="client.php" class="menu-item active">Client</a>
                <a href="produits.php" class="menu-item">Produits</a>
                <a href="dette.php" class="menu-item">Dette</a>
                <a href="archive.php" class="menu-item">Archive</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>
                    <i class="fas fa-users"></i>
                    Gestion des Clients
                </h1>
                <button class="btn-add" onclick="openModal('add')">
                    <i class="fas fa-user-plus"></i> Ajouter un client
                </button>
            </div>

            <?php if($message): ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="search-bar">
                <input type="text" id="searchClient" placeholder="🔍 Rechercher un client par nom, téléphone ou adresse..." onkeyup="filterClients()">
            </div>

            <?php if(mysqli_num_rows($clients_result) > 0): ?>
            <div class="clients-grid" id="clientsGrid">
                <?php while($client = mysqli_fetch_assoc($clients_result)): 
                    $has_dettes = $client['dettes_actives'] > 0;
                ?>
                <div class="client-card" 
                     data-name="<?php echo strtolower($client['nom_complet']); ?>" 
                     data-phone="<?php echo strtolower($client['telephone']); ?>" 
                     data-address="<?php echo strtolower($client['adresse']); ?>">
                    <div class="client-header">
                        <div class="client-avatar">
                            <?php echo strtoupper(substr($client['nom_complet'], 0, 1)); ?>
                        </div>
                        <div class="client-info">
                            <div class="client-name"><?php echo htmlspecialchars($client['nom_complet']); ?></div>
                            <div class="client-date">
                                <i class="fas fa-calendar"></i> 
                                Ajouté le <?php echo date('d/m/Y', strtotime($client['date_creation'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="client-details">
                        <?php if($client['telephone']): ?>
                        <div class="detail-row">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($client['telephone']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if($client['adresse']): ?>
                        <div class="detail-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($client['adresse']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="client-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $client['dettes_actives']; ?></div>
                            <div class="stat-label">Facture(s) active(s)</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value" style="color: <?php echo $client['total_dette'] > 0 ? '#ff4757' : '#2ed573'; ?>">
                                <?php echo number_format($client['total_dette'], 0, ',', ' '); ?> Ar
                            </div>
                            <div class="stat-label">Total à payer</div>
                        </div>
                    </div>

                    <div class="client-actions">
                        <button class="btn-action btn-edit" 
                                onclick='editClient(<?php echo htmlspecialchars(json_encode($client), ENT_QUOTES, 'UTF-8'); ?>)'>
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        
                        <?php if($has_dettes): ?>
                        <div class="tooltip">
                            <button class="btn-action btn-delete" disabled>
                                <i class="fas fa-lock"></i> Supprimer
                            </button>
                            <span class="tooltiptext">
                                ⚠️ Ce client a <?php echo $client['dettes_actives']; ?> facture(s) active(s). Impossible de le supprimer.
                            </span>
                        </div>
                        <?php else: ?>
                        <button class="btn-action btn-delete" onclick="deleteClient(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars(addslashes($client['nom_complet'])); ?>')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users"></i>
                <h3>Aucun client enregistré</h3>
                <p>Commencez par ajouter votre premier client</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Ajouter/Modifier -->
    <div class="modal" id="clientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Ajouter un client</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="clientForm">
                <input type="hidden" name="action" id="formAction" value="ajouter">
                <input type="hidden" name="id" id="clientId">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nom complet *</label>
                    <input type="text" name="nom_complet" id="nom_complet" required placeholder="Ex: Rakoto Jean">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Téléphone</label>
                    <input type="tel" name="telephone" id="telephone" placeholder="Ex: 0340000000" pattern="03[0-9]{8}" maxlength="10" title="Le numéro doit commencer par 03 et contenir 10 chiffres">
                    <small style="color:#999; font-size:12px;">Format: 03XXXXXXXX (10 chiffres)</small>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-map-marker-alt"></i> Adresse</label>
                    <textarea name="adresse" id="adresse" rows="3" placeholder="Ex: Antananarivo, Madagascar"></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode) {
            const modal = document.getElementById('clientModal');
            const form = document.getElementById('clientForm');
            const modalTitle = document.getElementById('modalTitle');
            
            form.reset();
            
            if(mode === 'add') {
                modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Ajouter un client';
                document.getElementById('formAction').value = 'ajouter';
                document.getElementById('clientId').value = '';
            }
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('clientModal').classList.remove('active');
        }

        // ✅ CORRECTION: Fonction editClient améliorée
        function editClient(client) {
            console.log('Editing client:', client); // Debug
            
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Modifier le client';
            document.getElementById('formAction').value = 'modifier';
            document.getElementById('clientId').value = client.id;
            document.getElementById('nom_complet').value = client.nom_complet || '';
            document.getElementById('telephone').value = client.telephone || '';
            document.getElementById('adresse').value = client.adresse || '';
            
            document.getElementById('clientModal').classList.add('active');
        }

        // ✅ CORRECTION: Fonction deleteClient avec nom du client
        function deleteClient(id, nomClient) {
            if(confirm('⚠️ Êtes-vous sûr de vouloir supprimer "' + nomClient + '" ?\n\nCette action est irréversible.')) {
                window.location.href = 'client.php?supprimer=' + id;
            }
        }

        function filterClients() {
            const searchValue = document.getElementById('searchClient').value.toLowerCase();
            const cards = document.querySelectorAll('.client-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const phone = card.getAttribute('data-phone');
                const address = card.getAttribute('data-address');
                
                if(name.includes(searchValue) || phone.includes(searchValue) || address.includes(searchValue)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Afficher un message si aucun résultat
            const grid = document.getElementById('clientsGrid');
            let noResultMsg = document.getElementById('noResultMessage');
            
            if(visibleCount === 0 && searchValue !== '') {
                if(!noResultMsg) {
                    noResultMsg = document.createElement('div');
                    noResultMsg.id = 'noResultMessage';
                    noResultMsg.className = 'empty-state';
                    noResultMsg.innerHTML = '<i class="fas fa-search"></i><h3>Aucun résultat trouvé</h3><p>Essayez avec d\'autres mots-clés</p>';
                    grid.parentNode.appendChild(noResultMsg);
                }
                grid.style.display = 'none';
            } else {
                if(noResultMsg) {
                    noResultMsg.remove();
                }
                grid.style.display = 'grid';
            }
        }

        // Validation du numéro de téléphone en temps réel
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('telephone');
            
            phoneInput.addEventListener('input', function(e) {
                // Supprimer tous les caractères non-numériques
                let value = e.target.value.replace(/\D/g, '');
                
                // Limiter à 10 chiffres
                if(value.length > 10) {
                    value = value.slice(0, 10);
                }
                
                e.target.value = value;
                
                // Validation visuelle
                if(value.length > 0) {
                    if(value.length === 10 && value.startsWith('03')) {
                        e.target.style.borderColor = '#2ed573';
                    } else {
                        e.target.style.borderColor = '#ff4757';
                    }
                } else {
                    e.target.style.borderColor = '#e0e0e0';
                }
            });
            
            // Validation avant soumission
            document.getElementById('clientForm').addEventListener('submit', function(e) {
                const phone = phoneInput.value;
                
                if(phone && phone.length > 0) {
                    if(phone.length !== 10) {
                        e.preventDefault();
                        alert('❌ Le numéro de téléphone doit contenir exactement 10 chiffres');
                        phoneInput.focus();
                        return false;
                    }
                    
                    if(!phone.startsWith('03')) {
                        e.preventDefault();
                        alert('❌ Le numéro de téléphone doit commencer par 03');
                        phoneInput.focus();
                        return false;
                    }
                }
            });
        });

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('clientModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });

        // Auto-hide alert messages
        <?php if($message): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if(alert) {
                alert.style.transition = 'opacity 0.3s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>