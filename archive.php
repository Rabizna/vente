<?php
//archive.php - SYSTÈME D'ARCHIVAGE AUTOMATIQUE
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);
$user_id = $_SESSION['user_id'] ?? 1;

// Statistiques globales des archives
$stats_query = "SELECT 
                    COUNT(DISTINCT d.numero_facture) as total_factures_payees,
                    COUNT(DISTINCT d.client_id) as total_clients,
                    SUM(d.montant_total) as montant_total_archive,
                    SUM(d.montant_paye) as montant_total_paye
                FROM dettes d
                WHERE d.statut = 'payee'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Récupérer les clients avec des factures archivées (payées)
$clients_archives_query = "SELECT 
                            c.id,
                            c.nom_complet,
                            c.telephone,
                            c.adresse,
                            COUNT(DISTINCT d.numero_facture) as nb_factures_payees,
                            SUM(d.montant_total) as total_paye,
                            MAX(d.date_modification) as derniere_modification
                         FROM clients c
                         INNER JOIN dettes d ON c.id = d.client_id
                         WHERE d.statut = 'payee'
                         GROUP BY c.id, c.nom_complet, c.telephone, c.adresse
                         ORDER BY derniere_modification DESC, c.nom_complet";
$clients_archives_result = mysqli_query($conn, $clients_archives_query);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-varootra - Archives</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
        }

        .stat-icon.green { background: linear-gradient(135deg, #2ed573 0%, #26de81 100%); }
        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #ffa502 0%, #ff7f00 100%); }

        .stat-info h3 {
            font-size: 28px;
            color: #0a4d4d;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #666;
            font-size: 14px;
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
            border-left: 5px solid #2ed573;
            position: relative;
            overflow: hidden;
        }

        .client-box::before {
            content: '✓';
            position: absolute;
            top: 10px;
            right: 10px;
            width: 35px;
            height: 35px;
            background: #2ed573;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 20px;
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
            max-width: 70%;
        }

        .badge-factures {
            background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(46, 213, 115, 0.3);
        }

        .client-box-info {
            color: #666;
            font-size: 14px;
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .client-box-total {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-label {
            font-weight: 600;
            color: #333;
            font-size: 15px;
        }

        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #2ed573;
        }

        .archive-date {
            font-size: 12px;
            color: #999;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
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
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 26px;
            color: #0a4d4d;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
            font-size: 16px;
        }

        .archive-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 3px 10px rgba(46, 213, 115, 0.3);
        }

        /* Scrollbar */
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
                <a href="dette.php" class="menu-item">
                    <i class="fas fa-file-invoice-dollar"></i> Dettes
                </a>
                <a href="archive.php" class="menu-item active">
                    <i class="fas fa-archive"></i> Archives
                </a>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>

        <div class="main-content">
            <!-- Vue Liste Archives -->
            <div id="clientsList">
                <div class="page-header">
                    <h1>
                        <i class="fas fa-archive"></i>
                        Archives des Dettes Payées
                    </h1>
                </div>

                <!-- Statistiques -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_factures_payees'] ?? 0; ?></h3>
                            <p>Factures payées</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $stats['total_clients'] ?? 0; ?></h3>
                            <p>Clients</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon orange">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($stats['montant_total_paye'] ?? 0, 0, ',', ' '); ?> Ar</h3>
                            <p>Total encaissé</p>
                        </div>
                    </div>
                </div>

                <?php if(mysqli_num_rows($clients_archives_result) > 0): ?>
                <div class="clients-grid">
                    <?php while($client = mysqli_fetch_assoc($clients_archives_result)): ?>
                    <div class="client-box" onclick="showArchives(<?php echo $client['id']; ?>)">
                        <div class="client-box-header">
                            <div class="client-box-name"><?php echo htmlspecialchars($client['nom_complet']); ?></div>
                            <div class="badge-factures">
                                <?php echo $client['nb_factures_payees']; ?> facture<?php echo $client['nb_factures_payees'] > 1 ? 's' : ''; ?>
                            </div>
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
                            <span class="total-label">Total payé:</span>
                            <span class="total-amount"><?php echo number_format($client['total_paye'], 0, ',', ' '); ?> Ar</span>
                        </div>
                        <div class="archive-date">
                            <i class="fas fa-clock"></i>
                            Dernière modification: <?php echo date('d/m/Y', strtotime($client['derniere_modification'])); ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-archive"></i>
                    <h3>Aucune archive disponible</h3>
                    <p>Les dettes payées apparaîtront automatiquement ici</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Vue Factures Archivées -->
            <div id="facturesView" class="factures-container"></div>
        </div>
    </div>

    <script>
        // Afficher les factures archivées d'un client
        function showArchives(clientId) {
            document.getElementById('clientsList').style.display = 'none';
            
            fetch(`get_archives.php?client_id=${clientId}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('facturesView').innerHTML = html;
                    document.getElementById('facturesView').classList.add('active');
                })
                .catch(err => {
                    alert('Erreur lors du chargement des archives');
                    console.error(err);
                });
        }

        // Retour à la liste
        function backToList() {
            document.getElementById('facturesView').classList.remove('active');
            document.getElementById('clientsList').style.display = 'block';
        }
    </script>
</body>
</html>