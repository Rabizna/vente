<?php
//index.php - Version avec statistiques dynamiques par mois
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);

// GESTION DU MOIS ET ANNÉE
// Si un mois est passé dans l'URL, on l'utilise, sinon on prend le mois actuel
$mois_selectionne = isset($_GET['mois']) ? intval($_GET['mois']) : intval(date('m'));
$annee_selectionnee = isset($_GET['annee']) ? intval($_GET['annee']) : intval(date('Y'));

// Validation du mois (1-12)
if ($mois_selectionne < 1 || $mois_selectionne > 12) {
    $mois_selectionne = intval(date('m'));
}

// Noms des mois en français
$noms_mois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

$nom_mois_actuel = $noms_mois[intval($mois_selectionne)];
$est_mois_actuel = ($mois_selectionne == intval(date('m')) && $annee_selectionnee == intval(date('Y')));

// Dates de début et fin du mois sélectionné
$date_debut = sprintf("%d-%02d-01", $annee_selectionnee, $mois_selectionne);
$date_fin = date("Y-m-t", strtotime($date_debut)); // Dernier jour du mois

// STATISTIQUES DYNAMIQUES FILTRÉES PAR MOIS

// 1. Nombre total de produits actifs (général, pas filtré par mois)
$query_produits = "SELECT COUNT(*) as total FROM produits WHERE actif = 1";
$result_produits = mysqli_query($conn, $query_produits);
$total_produits = mysqli_fetch_assoc($result_produits)['total'];

// 2. Nombre de dettes actives (créées ce mois-ci)
$query_dettes_actives = "SELECT COUNT(DISTINCT numero_facture) as total 
                         FROM dettes 
                         WHERE statut IN ('active', 'partiellement_payee')
                         AND date_dette BETWEEN '$date_debut' AND '$date_fin'";
$result_dettes_actives = mysqli_query($conn, $query_dettes_actives);
$total_dettes_actives = mysqli_fetch_assoc($result_dettes_actives)['total'];

// 3. Nombre de dettes payées (créées ce mois-ci et maintenant payées)
$query_dettes_payees = "SELECT COUNT(DISTINCT numero_facture) as total 
                        FROM dettes 
                        WHERE statut = 'payee'
                        AND date_dette BETWEEN '$date_debut' AND '$date_fin'";
$result_dettes_payees = mysqli_query($conn, $query_dettes_payees);
$total_dettes_payees = mysqli_fetch_assoc($result_dettes_payees)['total'];

// 4. Montant total des dettes restantes (créées ce mois-ci)
$query_montant_restant = "SELECT SUM(montant_restant) as total 
                          FROM dettes 
                          WHERE statut IN ('active', 'partiellement_payee')
                          AND date_dette BETWEEN '$date_debut' AND '$date_fin'";
$result_montant_restant = mysqli_query($conn, $query_montant_restant);
$montant_total_restant = mysqli_fetch_assoc($result_montant_restant)['total'] ?? 0;

// 5. Nombre de clients actifs (général)
$query_clients = "SELECT COUNT(*) as total FROM clients WHERE actif = 1";
$result_clients = mysqli_query($conn, $query_clients);
$total_clients = mysqli_fetch_assoc($result_clients)['total'];

// 6. Montant total payé ce mois (paiements effectués ce mois)
$query_montant_paye = "SELECT SUM(pd.montant_paye) as total 
                       FROM paiements_dette pd
                       WHERE pd.date_paiement BETWEEN '$date_debut' AND '$date_fin'";
$result_montant_paye = mysqli_query($conn, $query_montant_paye);
$montant_total_paye = mysqli_fetch_assoc($result_montant_paye)['total'] ?? 0;

// 7. Montant total de toutes les dettes créées ce mois
$query_montant_total = "SELECT SUM(montant_total) as total 
                        FROM dettes 
                        WHERE date_dette BETWEEN '$date_debut' AND '$date_fin'";
$result_montant_total = mysqli_query($conn, $query_montant_total);
$montant_total_dettes = mysqli_fetch_assoc($result_montant_total)['total'] ?? 0;

// Calcul du mois précédent et suivant pour la navigation
$mois_precedent = $mois_selectionne - 1;
$annee_precedente = $annee_selectionnee;
if ($mois_precedent < 1) {
    $mois_precedent = 12;
    $annee_precedente--;
}

$mois_suivant = $mois_selectionne + 1;
$annee_suivante = $annee_selectionnee;
if ($mois_suivant > 12) {
    $mois_suivant = 1;
    $annee_suivante++;
}

// Ne pas permettre d'aller au-delà du mois actuel
$mois_actuel_int = intval(date('m'));
$annee_actuelle_int = intval(date('Y'));
$peut_aller_suivant = !(
    ($annee_selectionnee > $annee_actuelle_int) || 
    ($annee_selectionnee == $annee_actuelle_int && $mois_selectionne >= $mois_actuel_int)
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-varootra - Accueil</title>
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

        /* Header */
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

        .logo {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .logo-text {
            font-size: 28px;
            font-weight: bold;
            color: white;
            letter-spacing: 2px;
        }

        .logo-highlight {
            background: #ff8c42;
            padding: 8px 20px;
            margin-left: 5px;
            border-radius: 5px;
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
            cursor: pointer;
            border: 3px solid #ff8c42;
        }

        /* Container */
        .container {
            display: flex;
            height: calc(100vh - 70px);
        }

        /* Sidebar */
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
            cursor: pointer;
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

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
            background: #f5f5f5;
        }

        .welcome-section {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            color: #0a4d4d;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome-section p {
            color: #666;
            font-size: 18px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            position: relative;
            z-index: 1;
        }

        .stat-icon.products {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.clients {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.debts-active {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
        }

        .stat-icon.debts-paid {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon.money-remaining {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-icon.money-paid {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-icon.money-total {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .stat-card h3 {
            color: #333;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #0a4d4d;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }

        .stat-subtitle {
            font-size: 13px;
            color: #999;
            margin-top: 8px;
            font-weight: normal;
        }

        /* Animation d'apparition */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            animation: fadeInUp 0.6s ease forwards;
            opacity: 0;
        }

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
        .stat-card:nth-child(7) { animation-delay: 0.7s; }

        /* Section Résumé */
        .summary-section {
            background: linear-gradient(135deg, #0a4d4d 0%, #0d6666 100%);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            margin-top: 30px;
            color: white;
        }

        .summary-section h2 {
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .summary-item {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }

        .summary-item label {
            font-size: 14px;
            opacity: 0.9;
            display: block;
            margin-bottom: 8px;
        }

        .summary-item .value {
            font-size: 24px;
            font-weight: bold;
        }

        /* Navigation par mois */
        .month-navigation {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .month-display {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .month-display h2 {
            color: #0a4d4d;
            font-size: 28px;
            margin: 0;
        }

        .badge-current {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-nav {
            background: #0a4d4d;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-nav:hover {
            background: #0d6666;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(10,77,77,0.3);
        }

        .btn-nav:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-nav:disabled:hover {
            background: #ccc;
            box-shadow: none;
        }

        .btn-current-month {
            background: #ff8c42;
        }

        .btn-current-month:hover {
            background: #ff7a2e;
        }
    </style>
</head>
<body>
    <!-- Header -->
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

    <!-- Container -->
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="menu">
                <a href="index.php" class="menu-item active">Accueil</a>
                <a href="dashboard.php" class="menu-item">Tableau de bord</a>
                <a href="client.php" class="menu-item">Client</a>
                <a href="produits.php" class="menu-item">Produits</a>
                <a href="dette.php" class="menu-item">Dette</a>
                <a href="archive.php" class="menu-item">Archive</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="welcome-section">
                <h1>👋 Bienvenue, <?php echo $nom_utilisateur; ?>!</h1>
                <p>Gérez facilement vos produits et vos dettes avec E-varootra</p>
            </div>

            <!-- Navigation par mois -->
            <div class="month-navigation">
                <div class="month-display">
                    <h2>
                        <i class="fas fa-calendar-alt"></i> 
                        <?php echo $nom_mois_actuel . ' ' . $annee_selectionnee; ?>
                    </h2>
                    <?php if ($est_mois_actuel): ?>
                        <span class="badge-current">Mois actuel</span>
                    <?php endif; ?>
                </div>
                <div class="nav-buttons">
                    <a href="?mois=<?php echo $mois_precedent; ?>&annee=<?php echo $annee_precedente; ?>" class="btn-nav">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                    
                    <?php if (!$est_mois_actuel): ?>
                        <a href="index.php" class="btn-nav btn-current-month">
                            <i class="fas fa-calendar-day"></i> Mois actuel
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($peut_aller_suivant): ?>
                        <a href="?mois=<?php echo $mois_suivant; ?>&annee=<?php echo $annee_suivante; ?>" class="btn-nav">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <button class="btn-nav" disabled>
                            Suivant <i class="fas fa-chevron-right"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3>Produits actifs</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_produits, 0, ',', ' '); ?></div>
                    <div class="stat-subtitle">Produits disponibles</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon clients">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Clients actifs</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_clients, 0, ',', ' '); ?></div>
                    <div class="stat-subtitle">Clients enregistrés</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon debts-active">
                            <i class="fas fa-exclamation-circle"></i>
                        </div>
                        <h3>Dettes actives</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_dettes_actives, 0, ',', ' '); ?></div>
                    <div class="stat-subtitle">Factures créées en <?php echo $nom_mois_actuel; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon debts-paid">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Dettes payées</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_dettes_payees, 0, ',', ' '); ?></div>
                    <div class="stat-subtitle">Factures soldées en <?php echo $nom_mois_actuel; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon money-remaining">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <h3>Montant restant</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($montant_total_restant, 0, ',', ' '); ?> Ar</div>
                    <div class="stat-subtitle">Des dettes de <?php echo $nom_mois_actuel; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon money-paid">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h3>Paiements reçus</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($montant_total_paye, 0, ',', ' '); ?> Ar</div>
                    <div class="stat-subtitle">Paiements effectués en <?php echo $nom_mois_actuel; ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon money-total">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Total des dettes</h3>
                    </div>
                    <div class="stat-value"><?php echo number_format($montant_total_dettes, 0, ',', ' '); ?> Ar</div>
                    <div class="stat-subtitle">Dettes créées en <?php echo $nom_mois_actuel; ?></div>
                </div>
            </div>

            <!-- Summary Section -->
            <div class="summary-section">
                <h2>
                    <i class="fas fa-chart-pie"></i>
                    Résumé financier - <?php echo $nom_mois_actuel . ' ' . $annee_selectionnee; ?>
                </h2>
                <div class="summary-grid">
                    <div class="summary-item">
                        <label>Taux de recouvrement</label>
                        <div class="value">
                            <?php 
                            $taux_recouvrement = $montant_total_dettes > 0 ? 
                                ($montant_total_paye / $montant_total_dettes) * 100 : 0;
                            echo number_format($taux_recouvrement, 1, ',', ' '); 
                            ?>%
                        </div>
                    </div>
                    <div class="summary-item">
                        <label>Taux de dettes soldées</label>
                        <div class="value">
                            <?php 
                            $total_factures = $total_dettes_actives + $total_dettes_payees;
                            $taux_soldees = $total_factures > 0 ? 
                                ($total_dettes_payees / $total_factures) * 100 : 0;
                            echo number_format($taux_soldees, 1, ',', ' '); 
                            ?>%
                        </div>
                    </div>
                    <div class="summary-item">
                        <label>Moyenne par dette</label>
                        <div class="value">
                            <?php 
                            $moyenne_dette = $total_factures > 0 ? 
                                $montant_total_dettes / $total_factures : 0;
                            echo number_format($moyenne_dette, 0, ',', ' '); 
                            ?> Ar
                        </div>
                    </div>
                    <div class="summary-item">
                        <label>Performance du mois</label>
                        <div class="value" style="color: <?php echo $taux_recouvrement >= 70 ? '#43e97b' : ($taux_recouvrement >= 40 ? '#ffa502' : '#ff4757'); ?>">
                            <?php 
                            if ($taux_recouvrement >= 70) echo '🌟 Excellent';
                            elseif ($taux_recouvrement >= 40) echo '👍 Bien';
                            else echo '⚠️ À améliorer';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>