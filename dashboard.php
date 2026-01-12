<?php
//dashboard.php - Tableau de bord avec graphiques et analyses
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);

// GESTION DU MOIS ET ANNÉE (similaire à index.php)
$mois_selectionne = isset($_GET['mois']) ? intval($_GET['mois']) : intval(date('m'));
$annee_selectionnee = isset($_GET['annee']) ? intval($_GET['annee']) : intval(date('Y'));

if ($mois_selectionne < 1 || $mois_selectionne > 12) {
    $mois_selectionne = intval(date('m'));
}

$noms_mois = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

$nom_mois_actuel = $noms_mois[intval($mois_selectionne)];
$est_mois_actuel = ($mois_selectionne == intval(date('m')) && $annee_selectionnee == intval(date('Y')));

$date_debut = sprintf("%d-%02d-01", $annee_selectionnee, $mois_selectionne);
$date_fin = date("Y-m-t", strtotime($date_debut));

// Navigation mois
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

$mois_actuel_int = intval(date('m'));
$annee_actuelle_int = intval(date('Y'));
$peut_aller_suivant = !(
    ($annee_selectionnee > $annee_actuelle_int) || 
    ($annee_selectionnee == $annee_actuelle_int && $mois_selectionne >= $mois_actuel_int)
);

// =============== DONNÉES POUR LES GRAPHIQUES ===============

// 1. TOP 5 CLIENTS PAR MONTANT DE DETTES
$query_top_clients = "
    SELECT 
        c.nom_complet,
        SUM(d.montant_total) as total_dettes,
        SUM(d.montant_paye) as total_paye,
        SUM(d.montant_restant) as total_restant,
        COUNT(DISTINCT d.numero_facture) as nb_factures
    FROM dettes d
    JOIN clients c ON d.client_id = c.id
    WHERE d.date_dette BETWEEN '$date_debut' AND '$date_fin'
    GROUP BY c.id, c.nom_complet
    ORDER BY total_dettes DESC
    LIMIT 5";
$result_top_clients = mysqli_query($conn, $query_top_clients);
$top_clients = [];
while ($row = mysqli_fetch_assoc($result_top_clients)) {
    $top_clients[] = $row;
}

// 2. TOP 5 PRODUITS LES PLUS VENDUS
$query_top_produits = "
    SELECT 
        p.nom as produit_nom,
        SUM(d.quantite) as quantite_totale,
        SUM(d.montant_total) as montant_total,
        COUNT(d.id) as nb_ventes
    FROM dettes d
    JOIN produits_unites pu ON d.produit_unite_id = pu.id
    JOIN produits p ON pu.produit_id = p.id
    WHERE d.date_dette BETWEEN '$date_debut' AND '$date_fin'
    GROUP BY p.id, p.nom
    ORDER BY montant_total DESC
    LIMIT 5";
$result_top_produits = mysqli_query($conn, $query_top_produits);
$top_produits = [];
while ($row = mysqli_fetch_assoc($result_top_produits)) {
    $top_produits[] = $row;
}

// 3. STATISTIQUES PAR UTILISATEUR
$query_stats_users = "
    SELECT 
        u.pseudo,
        u.nom_complet,
        COUNT(DISTINCT d.numero_facture) as nb_factures,
        SUM(d.montant_total) as montant_total,
        SUM(d.montant_paye) as montant_paye
    FROM dettes d
    JOIN utilisateurs u ON d.enregistre_par = u.id
    WHERE d.date_dette BETWEEN '$date_debut' AND '$date_fin'
    GROUP BY u.id, u.pseudo, u.nom_complet
    ORDER BY montant_total DESC";
$result_stats_users = mysqli_query($conn, $query_stats_users);
$stats_users = [];
while ($row = mysqli_fetch_assoc($result_stats_users)) {
    $stats_users[] = $row;
}

// 4. ÉVOLUTION DES PAIEMENTS PAR JOUR DU MOIS
$query_paiements_jour = "
    SELECT 
        DAY(pd.date_paiement) as jour,
        SUM(pd.montant_paye) as total_paye
    FROM paiements_dette pd
    WHERE pd.date_paiement BETWEEN '$date_debut' AND '$date_fin'
    GROUP BY DAY(pd.date_paiement)
    ORDER BY jour";
$result_paiements_jour = mysqli_query($conn, $query_paiements_jour);
$paiements_par_jour = [];
while ($row = mysqli_fetch_assoc($result_paiements_jour)) {
    $paiements_par_jour[$row['jour']] = $row['total_paye'];
}

// 5. RÉPARTITION DES DETTES PAR STATUT
$query_statut = "
    SELECT 
        statut,
        COUNT(DISTINCT numero_facture) as nb_factures,
        SUM(montant_total) as montant_total
    FROM dettes
    WHERE date_dette BETWEEN '$date_debut' AND '$date_fin'
    GROUP BY statut";
$result_statut = mysqli_query($conn, $query_statut);
$stats_statut = [];
while ($row = mysqli_fetch_assoc($result_statut)) {
    $stats_statut[$row['statut']] = $row;
}

// 6. STATISTIQUES GLOBALES DU MOIS
$query_stats_globales = "
    SELECT 
        COUNT(DISTINCT numero_facture) as total_factures,
        SUM(montant_total) as montant_total,
        SUM(montant_paye) as montant_paye,
        SUM(montant_restant) as montant_restant
    FROM dettes
    WHERE date_dette BETWEEN '$date_debut' AND '$date_fin'";
$result_stats = mysqli_query($conn, $query_stats_globales);
$stats_globales = mysqli_fetch_assoc($result_stats);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-varootra - Tableau de bord</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        }

        .menu { flex: 1; padding: 0 15px; }

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

        .menu-item:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(5px); }
        .menu-item.active { background: #ff8c42; box-shadow: 0 4px 15px rgba(255, 140, 66, 0.3); }

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

        .month-navigation {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
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
            font-size: 26px;
            margin: 0;
        }

        .badge-current {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .nav-buttons { display: flex; gap: 10px; }

        .btn-nav {
            background: #0a4d4d;
            color: white;
            border: none;
            padding: 10px 18px;
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

        .btn-current-month {
            background: #ff8c42;
        }

        .btn-current-month:hover {
            background: #ff7a2e;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .chart-header h3 {
            color: #0a4d4d;
            font-size: 18px;
            font-weight: 600;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        .chart-container.small {
            height: 250px;
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #0a4d4d;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .chart-card {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
        }

        .chart-card:nth-child(1) { animation-delay: 0.1s; }
        .chart-card:nth-child(2) { animation-delay: 0.2s; }
        .chart-card:nth-child(3) { animation-delay: 0.3s; }
        .chart-card:nth-child(4) { animation-delay: 0.4s; }
        .chart-card:nth-child(5) { animation-delay: 0.5s; }
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
                <a href="dashboard.php" class="menu-item active">Tableau de bord</a>
                <a href="client.php" class="menu-item">Client</a>
                <a href="produits.php" class="menu-item">Produits</a>
                <a href="dette.php" class="menu-item">Dette</a>
                <a href="archive.php" class="menu-item">Archive</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <div class="main-content">
            <!-- Navigation par mois -->
            <div class="month-navigation">
                <div class="month-display">
                    <h2>
                        <i class="fas fa-chart-line"></i> 
                        Tableau de bord - <?php echo $nom_mois_actuel . ' ' . $annee_selectionnee; ?>
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
                        <a href="dashboard.php" class="btn-nav btn-current-month">
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

            <!-- Graphiques -->
            <div class="dashboard-grid">
                <!-- Top 5 Clients -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Top 5 Clients</h3>
                    </div>
                    <div class="chart-container small">
                        <canvas id="topClientsChart"></canvas>
                    </div>
                </div>

                <!-- Top 5 Produits -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3>Top 5 Produits</h3>
                    </div>
                    <div class="chart-container small">
                        <canvas id="topProduitsChart"></canvas>
                    </div>
                </div>

                <!-- Répartition par statut -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h3>Répartition des dettes</h3>
                    </div>
                    <div class="chart-container small">
                        <canvas id="statutChart"></canvas>
                    </div>
                </div>

                <!-- Performance utilisateurs -->
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3>Performance par utilisateur</h3>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Factures</th>
                                    <th>Montant total</th>
                                    <th>Payé</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats_users as $user): ?>
                                <tr>
                                    <td><strong>@<?php echo htmlspecialchars($user['pseudo']); ?></strong></td>
                                    <td><?php echo $user['nb_factures']; ?></td>
                                    <td><?php echo number_format($user['montant_total'], 0, ',', ' '); ?> Ar</td>
                                    <td><?php echo number_format($user['montant_paye'], 0, ',', ' '); ?> Ar</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Évolution des paiements -->
                <div class="chart-card full-width">
                    <div class="chart-header">
                        <div class="chart-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <i class="fas fa-chart-area"></i>
                        </div>
                        <h3>Évolution des paiements durant le mois</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="paiementsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Configuration des couleurs
        const colors = {
            primary: '#667eea',
            secondary: '#764ba2',
            success: '#43e97b',
            danger: '#f5576c',
            warning: '#ffa502',
            info: '#4facfe'
        };

        // 1. Graphique Top Clients
        const topClientsData = <?php echo json_encode($top_clients); ?>;
        new Chart(document.getElementById('topClientsChart'), {
            type: 'bar',
            data: {
                labels: topClientsData.map(c => c.nom_complet),
                datasets: [{
                    label: 'Montant total',
                    data: topClientsData.map(c => c.total_dettes),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' Ar';
                            }
                        }
                    }
                }
            }
        });

        // 2. Graphique Top Produits
        const topProduitsData = <?php echo json_encode($top_produits); ?>;
        new Chart(document.getElementById('topProduitsChart'), {
            type: 'doughnut',
            data: {
                labels: topProduitsData.map(p => p.produit_nom),
                datasets: [{
                    data: topProduitsData.map(p => p.montant_total),
                    backgroundColor: [
                        'rgba(245, 87, 108, 0.8)',
                        'rgba(250, 112, 154, 0.8)',
                        'rgba(255, 165, 2, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(67, 233, 123, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // 3. Graphique Statut des dettes
        const statsStatut = <?php echo json_encode($stats_statut); ?>;
        const statutLabels = {
            'active': 'Actives',
            'partiellement_payee': 'Partiellement payées',
            'payee': 'Payées'
        };
        
        new Chart(document.getElementById('statutChart'), {
            type: 'pie',
            data: {
                labels: Object.keys(statsStatut).map(s => statutLabels[s] || s),
                datasets: [{
                    data: Object.values(statsStatut).map(s => s.nb_factures),
                    backgroundColor: [
                        'rgba(255, 71, 87, 0.8)',
                        'rgba(255, 165, 2, 0.8)',
                        'rgba(67, 233, 123, 0.8)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // 4. Graphique Évolution des paiements
        const paiementsData = <?php echo json_encode($paiements_par_jour); ?>;
        const joursDuMois = <?php echo date('t', strtotime($date_debut)); ?>;
        const labelsJours = [];
        const dataJours = [];
        
        for (let i = 1; i <= joursDuMois; i++) {
            labelsJours.push(i);
            dataJours.push(paiementsData[i] || 0);
        }

        new Chart(document.getElementById('paiementsChart'), {
            type: 'line',
            data: {
                labels: labelsJours,
                datasets: [{
                    label: 'Paiements reçus (Ar)',
                    data: dataJours,
                    borderColor: 'rgba(250, 112, 154, 1)',
                    backgroundColor: 'rgba(250, 112, 154, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + ' Ar';
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Jour du mois'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>