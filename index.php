<?php
include "config.php";

if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$nom_utilisateur = htmlspecialchars($_SESSION['nom']);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
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
        }

        .stat-icon.products {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.debts {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.paid {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon.total {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-card h3 {
            color: #333;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            color: #0a4d4d;
            margin-top: 10px;
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
                <a href="archive.php" class="menu-item">Archive de dettes payées</a>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="welcome-section">
                <h1>👋 Bienvenue, <?php echo $nom_utilisateur; ?>!</h1>
                <p>Gérez facilement vos produits et vos dettes avec E-varootra</p>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3>Produits</h3>
                    </div>
                    <div class="stat-value">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon debts">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h3>Dettes actives</h3>
                    </div>
                    <div class="stat-value">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon paid">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>Dettes payées</h3>
                    </div>
                    <div class="stat-value">0</div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-header">
                        <div class="stat-icon total">
                            <i class="fas fa-coins"></i>
                        </div>
                        <h3>Total dettes</h3>
                    </div>
                    <div class="stat-value">0 Ar</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>