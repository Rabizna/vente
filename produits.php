<?php
//produits.php
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);
$user_id = $_SESSION['user_id'] ?? 1; // À adapter selon votre système

// Gestion des actions (Ajouter, Modifier, Supprimer)
$message = "";
$message_type = "";

// AJOUTER UN PRODUIT
if(isset($_POST['action']) && $_POST['action'] == 'ajouter'){
    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $quantite_unite = mysqli_real_escape_string($conn, $_POST['quantite_unite']);
    $unite_id = mysqli_real_escape_string($conn, $_POST['unite_id']);
    $prix_unitaire = mysqli_real_escape_string($conn, $_POST['prix_unitaire']);
    $stock_disponible = mysqli_real_escape_string($conn, $_POST['stock_disponible']);
    $seuil_alerte = mysqli_real_escape_string($conn, $_POST['seuil_alerte']);
    
    $sql = "INSERT INTO produits (nom, description, quantite_unite, unite_id, prix_unitaire, stock_disponible, seuil_alerte) 
            VALUES ('$nom', '$description', '$quantite_unite', '$unite_id', '$prix_unitaire', '$stock_disponible', '$seuil_alerte')";
    
    if(mysqli_query($conn, $sql)){
        $message = "Produit ajouté avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur : " . mysqli_error($conn);
        $message_type = "error";
    }
}

// MODIFIER UN PRODUIT
if(isset($_POST['action']) && $_POST['action'] == 'modifier'){
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $quantite_unite = mysqli_real_escape_string($conn, $_POST['quantite_unite']);
    $unite_id = mysqli_real_escape_string($conn, $_POST['unite_id']);
    $prix_unitaire = mysqli_real_escape_string($conn, $_POST['prix_unitaire']);
    $stock_disponible = mysqli_real_escape_string($conn, $_POST['stock_disponible']);
    $seuil_alerte = mysqli_real_escape_string($conn, $_POST['seuil_alerte']);
    
    $sql = "UPDATE produits SET 
            nom='$nom', 
            description='$description', 
            quantite_unite='$quantite_unite', 
            unite_id='$unite_id', 
            prix_unitaire='$prix_unitaire', 
            stock_disponible='$stock_disponible', 
            seuil_alerte='$seuil_alerte'
            WHERE id='$id'";
    
    if(mysqli_query($conn, $sql)){
        $message = "Produit modifié avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur : " . mysqli_error($conn);
        $message_type = "error";
    }
}

// SUPPRIMER UN PRODUIT (désactiver)
if(isset($_GET['supprimer'])){
    $id = mysqli_real_escape_string($conn, $_GET['supprimer']);
    $sql = "UPDATE produits SET actif=0 WHERE id='$id'";
    
    if(mysqli_query($conn, $sql)){
        $message = "Produit supprimé avec succès !";
        $message_type = "success";
    }
}

// RÉCUPÉRER TOUS LES PRODUITS
$produits_query = "SELECT p.*, u.nom as unite_nom, u.symbole as unite_symbole 
                   FROM produits p 
                   JOIN unites u ON p.unite_id = u.id 
                   WHERE p.actif = 1 
                   ORDER BY p.date_creation DESC";
$produits_result = mysqli_query($conn, $produits_query);

// RÉCUPÉRER LES UNITÉS
$unites_query = "SELECT * FROM unites WHERE actif = 1";
$unites_result = mysqli_query($conn, $unites_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-varootra - Produits</title>
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

        /* Message Alert */
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

        /* Table */
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #0a4d4d 0%, #0d6666 100%);
            color: white;
        }

        thead th {
            padding: 20px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }

        tbody td {
            padding: 18px 20px;
            color: #333;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge.low-stock {
            background: #ff4757;
            color: white;
        }

        .badge.normal {
            background: #2ed573;
            color: white;
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-edit {
            background: #ffa502;
            color: white;
        }

        .btn-edit:hover {
            background: #ff7f00;
            transform: scale(1.1);
        }

        .btn-delete {
            background: #ff4757;
            color: white;
        }

        .btn-delete:hover {
            background: #d63031;
            transform: scale(1.1);
        }

        /* Modal */
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
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

        .price-display {
            font-weight: bold;
            color: #2ed573;
            font-size: 16px;
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
                <a href="client.php" class="menu-item">Client</a>
                <a href="produits.php" class="menu-item active">Produits</a>
                <a href="dette.php" class="menu-item">Dette</a>
                <a href="archive.php" class="menu-item">Archive de dettes payées</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-box"></i> Gestion des Produits</h1>
                <button class="btn-add" onclick="openModal('add')">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
            </div>

            <?php if($message): ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="table-container">
                <?php if(mysqli_num_rows($produits_result) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Désignation</th>
                            <th>Prix Unitaire</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($produit = mysqli_fetch_assoc($produits_result)): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($produit['nom']); ?></strong>
                                <?php if($produit['description']): ?>
                                <br><small style="color:#999;"><?php echo htmlspecialchars($produit['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="font-weight:500;">
                                    <?php echo $produit['quantite_unite'] . ' ' . $produit['unite_symbole']; ?>
                                </span>
                            </td>
                            <td>
                                <span class="price-display"><?php echo number_format($produit['prix_unitaire'], 0, ',', ' '); ?> Ar</span>
                            </td>
                            <td>
                                <strong><?php echo $produit['stock_disponible']; ?></strong> en stock
                            </td>
                            <td>
                                <?php if($produit['stock_disponible'] <= $produit['seuil_alerte']): ?>
                                    <span class="badge low-stock">⚠️ Stock Bas</span>
                                <?php else: ?>
                                    <span class="badge normal">✓ Normal</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon btn-edit" onclick='editProduct(<?php echo json_encode($produit); ?>)' title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon btn-delete" onclick="deleteProduct(<?php echo $produit['id']; ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Aucun produit disponible</h3>
                    <p>Commencez par ajouter votre premier produit</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Ajouter/Modifier -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Ajouter un produit</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="productForm">
                <input type="hidden" name="action" id="formAction" value="ajouter">
                <input type="hidden" name="id" id="productId">

                <div class="form-group">
                    <label>Nom du produit *</label>
                    <input type="text" name="nom" id="nom" required placeholder="Ex: Farine, Riz, Sucre...">
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" rows="3" placeholder="Description du produit (optionnel)"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Quantité *</label>
                        <input type="number" name="quantite_unite" id="quantite_unite" step="0.01" required placeholder="Ex: 50">
                    </div>
                    <div class="form-group">
                        <label>Unité *</label>
                        <select name="unite_id" id="unite_id" required>
                            <option value="">-- Choisir --</option>
                            <?php 
                            mysqli_data_seek($unites_result, 0);
                            while($unite = mysqli_fetch_assoc($unites_result)): 
                            ?>
                            <option value="<?php echo $unite['id']; ?>">
                                <?php echo $unite['nom'] . ' (' . $unite['symbole'] . ')'; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Prix unitaire (Ar) *</label>
                        <input type="number" name="prix_unitaire" id="prix_unitaire" step="0.01" required placeholder="Ex: 45000">
                    </div>
                    <div class="form-group">
                        <label>Stock initial *</label>
                        <input type="number" name="stock_disponible" id="stock_disponible" step="0.01" required placeholder="Ex: 100">
                    </div>
                </div>

                <div class="form-group">
                    <label>Seuil d'alerte *</label>
                    <input type="number" name="seuil_alerte" id="seuil_alerte" step="0.01" required placeholder="Ex: 10">
                    <small style="color:#999;">Le système vous alertera quand le stock atteint ce niveau</small>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(mode) {
            const modal = document.getElementById('productModal');
            const form = document.getElementById('productForm');
            const modalTitle = document.getElementById('modalTitle');
            
            form.reset();
            
            if(mode === 'add') {
                modalTitle.textContent = 'Ajouter un produit';
                document.getElementById('formAction').value = 'ajouter';
            }
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').classList.remove('active');
        }

        function editProduct(produit) {
            document.getElementById('modalTitle').textContent = 'Modifier le produit';
            document.getElementById('formAction').value = 'modifier';
            document.getElementById('productId').value = produit.id;
            document.getElementById('nom').value = produit.nom;
            document.getElementById('description').value = produit.description || '';
            document.getElementById('quantite_unite').value = produit.quantite_unite;
            document.getElementById('unite_id').value = produit.unite_id;
            document.getElementById('prix_unitaire').value = produit.prix_unitaire;
            document.getElementById('stock_disponible').value = produit.stock_disponible;
            document.getElementById('seuil_alerte').value = produit.seuil_alerte;
            
            document.getElementById('productModal').classList.add('active');
        }

        function deleteProduct(id) {
            if(confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                window.location.href = 'produits.php?supprimer=' + id;
            }
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('productModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });

        // Auto-hide alert messages
        <?php if($message): ?>
        setTimeout(function() {
            const alert = document.querySelector('.alert');
            if(alert) {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>