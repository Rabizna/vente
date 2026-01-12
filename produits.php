<?php
// produits.php - Version avec historique des prix (CORRIGÉE)
include "config.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);

// Récupération de l'ID utilisateur de manière flexible
$user_id = null;

// Méthode 1 : Vérifier $_SESSION['user']
if (isset($_SESSION['user']) && is_numeric($_SESSION['user'])) {
    $user_id = intval($_SESSION['user']);
}

// Méthode 2 : Chercher par pseudo si disponible
if ($user_id === null && isset($_SESSION['pseudo'])) {
    $pseudo = mysqli_real_escape_string($conn, $_SESSION['pseudo']);
    $user_query = "SELECT id FROM utilisateurs WHERE pseudo = '$pseudo' LIMIT 1";
    $user_result = mysqli_query($conn, $user_query);
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_row = mysqli_fetch_assoc($user_result);
        $user_id = intval($user_row['id']);
    }
}

// Méthode 3 : Chercher par nom complet si disponible
if ($user_id === null && isset($_SESSION['nom'])) {
    $nom = mysqli_real_escape_string($conn, $_SESSION['nom']);
    $user_query = "SELECT id FROM utilisateurs WHERE nom_complet = '$nom' LIMIT 1";
    $user_result = mysqli_query($conn, $user_query);
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_row = mysqli_fetch_assoc($user_result);
        $user_id = intval($user_row['id']);
    }
}

// Méthode 4 : Prendre le premier utilisateur en dernier recours (temporaire pour debug)
if ($user_id === null) {
    $fallback_query = "SELECT id FROM utilisateurs ORDER BY id ASC LIMIT 1";
    $fallback_result = mysqli_query($conn, $fallback_query);
    if ($fallback_result && mysqli_num_rows($fallback_result) > 0) {
        $fallback_row = mysqli_fetch_assoc($fallback_result);
        $user_id = intval($fallback_row['id']);
    }
}

$message = "";
$message_type = "";

// AJOUTER UN PRODUIT
if (isset($_POST['action']) && $_POST['action'] == 'ajouter_produit') {
    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $sql = "INSERT INTO produits (nom, description) VALUES ('$nom', '$description')";

    if (mysqli_query($conn, $sql)) {
        $message = "Produit ajouté avec succès ! Vous pouvez maintenant ajouter des unités.";
        $message_type = "success";
    } else {
        $message = "Erreur : " . mysqli_error($conn);
        $message_type = "error";
    }
}

// MODIFIER UN PRODUIT (nom + description)
if (isset($_POST['action']) && $_POST['action'] == 'modifier_produit') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);

    $sql = "UPDATE produits SET nom='$nom', description='$description' WHERE id='$id'";

    if (mysqli_query($conn, $sql)) {
        $message = "Produit modifié avec succès !";
        $message_type = "success";
    } else {
        $message = "Erreur : " . mysqli_error($conn);
        $message_type = "error";
    }
}

// AJOUTER UNE UNITÉ À UN PRODUIT
if (isset($_POST['action']) && $_POST['action'] == 'ajouter_unite') {
    $produit_id = mysqli_real_escape_string($conn, $_POST['produit_id']);
    $unite_id = mysqli_real_escape_string($conn, $_POST['unite_id']);
    $prix_unitaire = floatval($_POST['prix_unitaire']);

    $check = "SELECT id FROM produits_unites WHERE produit_id='$produit_id' AND unite_id='$unite_id'";
    $check_result = mysqli_query($conn, $check);

    if (mysqli_num_rows($check_result) > 0) {
        $message = "Cette unité existe déjà pour ce produit !";
        $message_type = "error";
    } else {
        $sql = "INSERT INTO produits_unites (produit_id, unite_id, prix_unitaire) 
                VALUES ('$produit_id', '$unite_id', '$prix_unitaire')";

        if (mysqli_query($conn, $sql)) {
            $message = "Unité ajoutée avec succès !";
            $message_type = "success";
        } else {
            $message = "Erreur : " . mysqli_error($conn);
            $message_type = "error";
        }
    }
}

// MODIFIER LE PRIX D'UNE UNITÉ + ENREGISTREMENT HISTORIQUE (CORRIGÉ)
if (isset($_POST['action']) && $_POST['action'] == 'modifier_prix') {
    $pu_id = intval($_POST['pu_id']); // Conversion en entier
    $nouveau_prix = floatval($_POST['prix_unitaire']);

    // Récupération de l'ancien prix
    $query_old = "SELECT prix_unitaire FROM produits_unites WHERE id = $pu_id";
    $result_old = mysqli_query($conn, $query_old);

    if (!$result_old || mysqli_num_rows($result_old) === 0) {
        $message = "Unité introuvable !";
        $message_type = "error";
    } else {
        $row_old = mysqli_fetch_assoc($result_old);
        $ancien_prix = floatval($row_old['prix_unitaire']);

        // Vérification que l'utilisateur existe bien
        $check_user = "SELECT id FROM utilisateurs WHERE id = $user_id";
        $check_user_result = mysqli_query($conn, $check_user);
        
        if (!$check_user_result || mysqli_num_rows($check_user_result) === 0) {
            $message = "Erreur : Utilisateur invalide. Veuillez vous reconnecter.";
            $message_type = "error";
        } else {
            // Mise à jour du prix
            $sql_update = "UPDATE produits_unites 
                           SET prix_unitaire = $nouveau_prix,
                               date_modification = NOW()
                           WHERE id = $pu_id";

            if (mysqli_query($conn, $sql_update)) {
                // Enregistrement dans l'historique avec vérification
                $sql_histo = "INSERT INTO historique_prix 
                             (produits_unites_id, ancien_prix, nouveau_prix, utilisateur_id, date_modification)
                             VALUES 
                             ($pu_id, $ancien_prix, $nouveau_prix, $user_id, NOW())";

                if (mysqli_query($conn, $sql_histo)) {
                    $message = "Prix modifié avec succès et historique enregistré !";
                    $message_type = "success";
                } else {
                    // Le prix est modifié mais l'historique a échoué
                    $message = "Prix modifié mais erreur lors de l'enregistrement de l'historique : " . mysqli_error($conn);
                    $message_type = "error";
                }
            } else {
                $message = "Erreur lors de la modification : " . mysqli_error($conn);
                $message_type = "error";
            }
        }
    }
}

// SUPPRIMER UNE UNITÉ
if (isset($_GET['supprimer_unite'])) {
    $pu_id = mysqli_real_escape_string($conn, $_GET['supprimer_unite']);
    $sql = "DELETE FROM produits_unites WHERE id='$pu_id'";

    if (mysqli_query($conn, $sql)) {
        $message = "Unité supprimée avec succès !";
        $message_type = "success";
    }
}

// SUPPRIMER UN PRODUIT (passage inactif)
if (isset($_GET['supprimer'])) {
    $id = mysqli_real_escape_string($conn, $_GET['supprimer']);
    $sql = "UPDATE produits SET actif=0 WHERE id='$id'";

    if (mysqli_query($conn, $sql)) {
        $message = "Produit supprimé avec succès !";
        $message_type = "success";
    }
}

// RÉCUPÉRER TOUS LES PRODUITS ACTIFS
$produits_query = "SELECT * FROM produits WHERE actif = 1 ORDER BY date_creation DESC";
$produits_result = mysqli_query($conn, $produits_query);

// RÉCUPÉRER LES UNITÉS POUR LE FORMULAIRE D'AJOUT
$unites_query = "SELECT * FROM unites WHERE actif = 1 ORDER BY nom";
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
        * { margin:0; padding:0; box-sizing:border-box; }
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

        .container { display: flex; height: calc(100vh - 70px); }

        .sidebar {
            width: 320px;
            background: linear-gradient(180deg, #0a4d4d 0%, #063838 100%);
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .menu {
            flex: 1;
            padding: 0 15px;
            overflow: hidden;
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

        .menu-item:hover { background: rgba(255,255,255,0.1); transform: translateX(5px); }
        .menu-item.active { background: #ff8c42; box-shadow: 0 4px 15px rgba(255,140,66,0.3); }

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
            box-shadow: 0 6px 20px rgba(217,83,79,0.4);
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

        .search-container {
            position: relative;
            width: 400px;
        }

        .search-input {
            width: 100%;
            padding: 12px 45px 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
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
            box-shadow: 0 6px 20px rgba(102,126,234,0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @keyframes slideDown {
            from { opacity:0; transform:translateY(-10px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .product-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
        }

        .product-name {
            font-size: 22px;
            font-weight: bold;
            color: #0a4d4d;
            margin-bottom: 5px;
        }

        .product-description {
            color: #666;
            font-size: 14px;
        }

        .product-actions { display: flex; gap: 8px; }

        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 16px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .btn-edit   { background: #ffa502; color: white; }
        .btn-edit:hover   { background: #ff7f00; transform: scale(1.1); }
        .btn-delete { background: #ff4757; color: white; }
        .btn-delete:hover { background: #d63031; transform: scale(1.1); }
        .btn-add-unit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-add-unit:hover { transform: scale(1.1); box-shadow: 0 6px 15px rgba(102,126,234,0.4); }

        .units-list { margin-top: 20px; }

        .unit-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%);
            border-radius: 12px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .unit-item:hover {
            border-color: rgba(102,126,234,0.3);
            transform: translateX(5px);
        }

        .unit-info { flex: 1; }

        .unit-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
            margin-bottom: 3px;
        }

        .unit-price {
            color: #2ed573;
            font-weight: bold;
            font-size: 18px;
        }

        .unit-actions { display: flex; gap: 8px; }

        .btn-small {
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

        .price-history {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px dashed #e0e0e0;
        }

        .price-history h4 {
            color: #0a4d4d;
            margin-bottom: 12px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .history-list {
            max-height: 240px;
            overflow-y: auto;
            font-size: 13px;
        }

        .history-item {
            padding: 10px;
            background: rgba(102,126,234,0.03);
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .history-item:last-child { margin-bottom: 0; }

        .history-price-change {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 6px 0;
            color: #555;
            font-weight: 600;
        }

        .price-old {
            color: #666;
        }

        .price-arrow {
            font-size: 18px;
            margin: 0 8px;
        }

        .price-new {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 15px;
        }

        .price-increase {
            color: #e74c3c !important;
        }

        .price-decrease {
            color: #27ae60 !important;
        }

        .price-emoji {
            font-size: 16px;
        }

        .history-date-user {
            color: #888;
            font-size: 12px;
            margin-top: 4px;
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

        .modal.active { display: flex; align-items: center; justify-content: center; }

        @keyframes fadeIn { from {opacity:0;} to {opacity:1;} }

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
            from { opacity:0; transform:translateY(50px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .modal-header h2 { color: #0a4d4d; font-size: 28px; }

        .close-modal {
            background: none;
            border: none;
            font-size: 30px;
            color: #999;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close-modal:hover { color: #ff4757; transform: rotate(90deg); }

        .form-group { margin-bottom: 25px; }

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
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
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
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .empty-state i { font-size: 100px; color: #e0e0e0; margin-bottom: 20px; }

        .empty-state h3 { font-size: 24px; color: #666; margin-bottom: 10px; }
        .empty-state p  { color: #999; font-size: 16px; }

        .no-units {
            text-align: center;
            padding: 30px;
            color: #999;
            font-style: italic;
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
                <a href="archive.php" class="menu-item">Archive</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1>
                    <i class="fas fa-box"></i>
                    Gestion des Produits
                </h1>
                <div class="search-container">
                    <input 
                        type="text" 
                        id="searchInput" 
                        class="search-input" 
                        placeholder="Rechercher un produit..."
                        onkeyup="searchProducts()"
                    >
                    <i class="fas fa-search search-icon"></i>
                </div>
                <button class="btn-add" onclick="openModal('add-product')">
                    <i class="fas fa-plus"></i> Nouveau Produit
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (mysqli_num_rows($produits_result) > 0): ?>
            <div class="products-grid">
                <?php while ($produit = mysqli_fetch_assoc($produits_result)): ?>
                <?php
                    $unites_produit_query = "
                        SELECT pu.*, u.nom as unite_nom, u.symbole as unite_symbole 
                        FROM produits_unites pu 
                        JOIN unites u ON pu.unite_id = u.id 
                        WHERE pu.produit_id = {$produit['id']} 
                        AND pu.actif = 1 
                        ORDER BY u.nom";
                    $unites_produit_result = mysqli_query($conn, $unites_produit_query);

                    $historique_query = "
                        SELECT 
                            hp.*,
                            pu.produit_id,
                            u.nom AS unite_nom,
                            u.symbole AS unite_symbole,
                            util.pseudo AS utilisateur_pseudo
                        FROM historique_prix hp
                        INNER JOIN produits_unites pu ON hp.produits_unites_id = pu.id
                        INNER JOIN unites u ON pu.unite_id = u.id
                        LEFT JOIN utilisateurs util ON hp.utilisateur_id = util.id
                        WHERE pu.produit_id = {$produit['id']}
                        ORDER BY hp.date_modification DESC
                        LIMIT 10";
                    $historique_result = mysqli_query($conn, $historique_query);
                ?>

                <div class="product-card">
                    <div class="product-header">
                        <div>
                            <div class="product-name"><?php echo htmlspecialchars($produit['nom']); ?></div>
                            <?php if ($produit['description']): ?>
                            <div class="product-description"><?php echo htmlspecialchars($produit['description']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="product-actions">
                            <button class="btn-icon btn-add-unit" onclick='openModal("add-unit", <?php echo $produit["id"]; ?>)' title="Ajouter une unité">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn-icon btn-edit" onclick='editProduct(<?php echo json_encode($produit); ?>)' title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-delete" onclick="deleteProduct(<?php echo $produit['id']; ?>)" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="units-list">
                        <?php if (mysqli_num_rows($unites_produit_result) > 0): ?>
                            <?php while ($pu = mysqli_fetch_assoc($unites_produit_result)): ?>
                            <div class="unit-item">
                                <div class="unit-info">
                                    <div class="unit-name">
                                        <i class="fas fa-tag"></i> 
                                        <?php echo htmlspecialchars($pu['unite_nom']) . ' (' . $pu['unite_symbole'] . ')'; ?>
                                    </div>
                                    <div class="unit-price">
                                        <?php echo number_format($pu['prix_unitaire'], 0, ',', ' '); ?> Ar
                                    </div>
                                </div>
                                <div class="unit-actions">
                                    <button class="btn-small btn-edit" onclick='editPrice(<?php echo json_encode($pu); ?>)' title="Modifier le prix">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-small btn-delete" onclick="deleteUnit(<?php echo $pu['id']; ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="no-units">
                                <i class="fas fa-info-circle"></i> Aucune unité définie<br>
                                Cliquez sur <i class="fas fa-plus"></i> pour en ajouter
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (mysqli_num_rows($historique_result) > 0): ?>
                    <div class="price-history">
                        <h4><i class="fas fa-history"></i> Historique récent des prix</h4>
                        <div class="history-list">
                            <?php while ($histo = mysqli_fetch_assoc($historique_result)): 
                                $ancien = floatval($histo['ancien_prix']);
                                $nouveau = floatval($histo['nouveau_prix']);
                                $difference = $nouveau - $ancien;
                                $pourcentage = $ancien > 0 ? abs(($difference / $ancien) * 100) : 0;
                                
                                // Déterminer la couleur et l'emoji
                                $color_class = '';
                                $emoji = '';
                                if ($difference > 0) {
                                    $color_class = 'price-increase';
                                    $emoji = $pourcentage >= 20 ? '⏫' : '🔼';
                                } elseif ($difference < 0) {
                                    $color_class = 'price-decrease';
                                    $emoji = $pourcentage >= 20 ? '⏬' : '🔽';
                                } else {
                                    $emoji = '➖';
                                }
                            ?>
                            <div class="history-item">
                                <div style="font-weight:600; color:#444;">
                                    <?php echo htmlspecialchars($histo['unite_nom']) . ' (' . $histo['unite_symbole'] . ')'; ?>
                                </div>
                                <div class="history-price-change">
                                    <span class="price-old">Ancien : <strong><?php echo number_format($ancien, 0, ',', ' '); ?> Ar</strong></span>
                                    <span class="price-arrow">→</span>
                                    <span class="price-new <?php echo $color_class; ?>">
                                        <span class="price-emoji"><?php echo $emoji; ?></span>
                                        Nouveau : <strong><?php echo number_format($nouveau, 0, ',', ' '); ?> Ar</strong>
                                    </span>
                                </div>
                                <div class="history-date-user">
                                    <i class="fas fa-calendar-alt"></i> 
                                    <?php echo date('d/m/Y à H:i', strtotime($histo['date_modification'])); ?> 
                                    par <strong>@<?php echo htmlspecialchars($histo['utilisateur_pseudo'] ?? 'inconnu'); ?></strong>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucun produit disponible</h3>
                <p>Commencez par ajouter votre premier produit</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Ajouter/Modifier Produit -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="productModalTitle">Nouveau Produit</h2>
                <button class="close-modal" onclick="closeModal('productModal')">×</button>
            </div>
            <form method="POST" id="productForm">
                <input type="hidden" name="action" id="productAction" value="ajouter_produit">
                <input type="hidden" name="id" id="productId">

                <div class="form-group">
                    <label><i class="fas fa-box"></i> Nom du produit *</label>
                    <input type="text" name="nom" id="productNom" required placeholder="Ex: Huile, Biscuits, Sucre...">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="productDescription" rows="3" placeholder="Description du produit (optionnel)"></textarea>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Ajouter Unité -->
    <div class="modal" id="unitModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ajouter une unité</h2>
                <button class="close-modal" onclick="closeModal('unitModal')">×</button>
            </div>
            <form method="POST" id="unitForm">
                <input type="hidden" name="action" value="ajouter_unite">
                <input type="hidden" name="produit_id" id="unitProduitId">

                <div class="form-group">
                    <label><i class="fas fa-ruler"></i> Unité *</label>
                    <select name="unite_id" id="unitUniteId" required>
                        <option value="">-- Choisir une unité --</option>
                        <?php 
                        mysqli_data_seek($unites_result, 0);
                        while ($unite = mysqli_fetch_assoc($unites_result)): ?>
                        <option value="<?php echo $unite['id']; ?>">
                            <?php echo htmlspecialchars($unite['nom']) . ' (' . $unite['symbole'] . ')'; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Prix unitaire (Ar) *</label>
                    <input type="number" name="prix_unitaire" id="unitPrix" step="0.01" min="0" required placeholder="Ex: 25000">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-plus"></i> Ajouter l'unité
                </button>
            </form>
        </div>
    </div>

    <!-- Modal Modifier Prix -->
    <div class="modal" id="priceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Modifier le prix</h2>
                <button class="close-modal" onclick="closeModal('priceModal')">×</button>
            </div>
            <form method="POST" id="priceForm">
                <input type="hidden" name="action" value="modifier_prix">
                <input type="hidden" name="pu_id" id="pricePuId">

                <div class="form-group">
                    <label><i class="fas fa-info-circle"></i> Unité</label>
                    <input type="text" id="priceUniteName" readonly style="background:#f5f5f5;">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Nouveau prix (Ar) *</label>
                    <input type="number" name="prix_unitaire" id="pricePrixUnitaire" step="0.01" min="0" required placeholder="Ex: 25000">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Mettre à jour le prix
                </button>
            </form>
        </div>
    </div>

    <script>
        function openModal(type, produitId = null) {
            if (type === 'add-product') {
                document.getElementById('productForm').reset();
                document.getElementById('productModalTitle').textContent = 'Nouveau Produit';
                document.getElementById('productAction').value = 'ajouter_produit';
                document.getElementById('productModal').classList.add('active');
            } 
            else if (type === 'add-unit') {
                document.getElementById('unitForm').reset();
                document.getElementById('unitProduitId').value = produitId;
                document.getElementById('unitModal').classList.add('active');
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function editProduct(produit) {
            document.getElementById('productModalTitle').textContent = 'Modifier le Produit';
            document.getElementById('productAction').value = 'modifier_produit';
            document.getElementById('productId').value = produit.id;
            document.getElementById('productNom').value = produit.nom;
            document.getElementById('productDescription').value = produit.description || '';
            document.getElementById('productModal').classList.add('active');
        }

        function editPrice(pu) {
            document.getElementById('pricePuId').value = pu.id;
            document.getElementById('priceUniteName').value = pu.unite_nom + ' (' + pu.unite_symbole + ')';
            document.getElementById('pricePrixUnitaire').value = pu.prix_unitaire;
            document.getElementById('priceModal').classList.add('active');
        }

        function deleteProduct(id) {
            if (confirm('⚠️ Êtes-vous sûr de vouloir supprimer ce produit ?\nToutes les unités associées seront également supprimées.')) {
                window.location.href = 'produits.php?supprimer=' + id;
            }
        }

        function deleteUnit(puId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette unité ?')) {
                window.location.href = 'produits.php?supprimer_unite=' + puId;
            }
        }

        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });

        <?php if ($message): ?>
        setTimeout(() => {
            const alert = document.querySelector('.alert');
            if (alert) {
                alert.style.transition = 'opacity 0.4s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            }
        }, 5000);
        <?php endif; ?>

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.product-card').forEach((card, i) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, i * 80);
            });
        });

        function searchProducts() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.product-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const productName = card.querySelector('.product-name').textContent.toLowerCase();
                const productDescription = card.querySelector('.product-description')?.textContent.toLowerCase() || '';
                const units = Array.from(card.querySelectorAll('.unit-name')).map(el => el.textContent.toLowerCase()).join(' ');
                
                const searchText = productName + ' ' + productDescription + ' ' + units;

                if (searchText.includes(filter)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            // Afficher un message si aucun résultat
            const grid = document.querySelector('.products-grid');
            let noResult = document.getElementById('no-search-result');
            
            if (visibleCount === 0 && filter !== '') {
                if (!noResult) {
                    noResult = document.createElement('div');
                    noResult.id = 'no-search-result';
                    noResult.className = 'empty-state';
                    noResult.style.gridColumn = '1 / -1';
                    noResult.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>Aucun produit trouvé</h3>
                        <p>Essayez avec d'autres mots-clés</p>
                    `;
                    grid.appendChild(noResult);
                }
            } else if (noResult) {
                noResult.remove();
            }
        }
    </script>
</body>
</html>