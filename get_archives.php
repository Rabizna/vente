<?php
// get_archives.php - VERSION CORRIGÉE FINALE (READONLY, MONTANTS FIGÉS)

include "config.php";

$client_id = mysqli_real_escape_string($conn, $_GET['client_id']);

// Infos client
$client_query = "SELECT nom_complet, telephone, adresse FROM clients WHERE id = '$client_id'";
$client_result = mysqli_query($conn, $client_query);
$client = mysqli_fetch_assoc($client_result);

// FACTURES PAYÉES (montants figés, structure identique à get_factures.php)
$factures_query = "SELECT 
        d.numero_facture,
        d.date_dette,
        MAX(d.date_modification) AS date_paiement_complet,
        u.nom_complet AS vendeur_nom,
        u.pseudo AS vendeur_pseudo,
        SUM(d.montant_total) AS total_facture,
        SUM(d.montant_paye) AS total_paye
    FROM dettes d
    JOIN utilisateurs u ON d.enregistre_par = u.id
    WHERE d.client_id = '$client_id'
      AND d.statut = 'payee'
    GROUP BY d.numero_facture, d.date_dette, u.nom_complet, u.pseudo
    ORDER BY date_paiement_complet DESC";

$factures_result = mysqli_query($conn, $factures_query);

// Historique des paiements
$paiements_query = "SELECT 
        p.dette_id,
        d.numero_facture,
        p.montant_paye,
        p.reference_paiement,
        p.date_paiement,
        p.date_creation,
        u.pseudo AS enregistre_par_pseudo
    FROM paiements_dette p
    JOIN dettes d ON p.dette_id = d.id
    JOIN utilisateurs u ON p.enregistre_par = u.id
    WHERE d.client_id = '$client_id'
      AND d.statut = 'payee'
    ORDER BY p.date_creation DESC, p.id DESC";

$paiements_result = mysqli_query($conn, $paiements_query);

$paiements_par_facture = [];
while ($paiement = mysqli_fetch_assoc($paiements_result)) {
    $paiements_par_facture[$paiement['numero_facture']][] = $paiement;
}
?>

<style>
    .archive-header {
        background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .archive-header h2 {
        font-size: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .btn-back {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 10px 20px;
        border: 2px solid white;
        border-radius: 10px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-back:hover {
        background: white;
        color: #2ed573;
    }

    .facture-card {
        background: white;
        border-radius: 15px;
        padding: 40px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        font-family: 'Courier New', monospace;
        position: relative;
        border: 3px solid #2ed573;
    }

    .archive-badge {
        position: absolute;
        top: -15px;
        right: 30px;
        background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
        color: white;
        padding: 10px 25px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: bold;
        box-shadow: 0 4px 15px rgba(46, 213, 115, 0.4);
        display: flex;
        align-items: center;
        gap: 8px;
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

    .facture-numero {
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        margin: 20px 0;
        color: #0a4d4d;
    }

    .facture-info {
        display: flex;
        justify-content: space-between;
        margin: 20px 0;
        font-size: 14px;
    }

    .facture-info-left, .facture-info-right {
        flex: 1;
    }

    .facture-info-right {
        text-align: right;
    }

.facture-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;     /* ← très important ! */
}

.facture-table th,
.facture-table td {
    padding: 10px 8px;
    border-bottom: 1px dashed #ccc;
}

.facture-table th:nth-child(1), td:nth-child(1) { width: 5%;    text-align: center; }
.facture-table th:nth-child(2), td:nth-child(2) { width: 45%;   text-align: left;   }   /* Produit ← prend le plus de place */
.facture-table th:nth-child(3), td:nth-child(3) { width: 12%;   text-align: center; }
.facture-table th:nth-child(4), td:nth-child(4) { width: 18%;   text-align: right;  }
.facture-table th:nth-child(5), td:nth-child(5) { width: 20%;   text-align: right;  }

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

    .status-paid-archive {
        background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
        color: white;
        padding: 25px;
        border-radius: 15px;
        margin: 25px 0;
        text-align: center;
        box-shadow: 0 6px 20px rgba(46, 213, 115, 0.3);
    }

    .status-paid-archive h3 {
        font-size: 24px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }

    .payment-date {
        font-size: 16px;
        opacity: 0.9;
    }

    .historique-paiements {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        padding: 25px;
        border-radius: 15px;
        margin-top: 25px;
        border: 2px solid #2ed573;
    }

    .historique-paiements h4 {
        color: #0a4d4d;
        margin-bottom: 20px;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .paiement-item {
        background: white;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid #2ed573;
    }

    .paiement-item-left {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .paiement-ref {
        font-weight: bold;
        color: #0a4d4d;
        font-size: 15px;
    }

    .paiement-date {
        font-size: 13px;
        color: #666;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .paiement-montant {
        font-weight: bold;
        color: #2ed573;
        font-size: 20px;
    }

    .divider {
        border-top: 2px dashed #000;
        margin: 20px 0;
    }

    .signatures {
        display: flex;
        justify-content: space-between;
        margin: 30px 0;
        font-size: 14px;
    }

    .thank-you {
        text-align: center;
        font-weight: bold;
        margin-top: 20px;
        font-size: 16px;
    }

    .btn-print {
        width: 100%;
        padding: 15px;
        background: linear-gradient(135deg, #0a4d4d 0%, #0d6666 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        margin-top: 20px;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .btn-print:hover {
        background: linear-gradient(135deg, #063838 0%, #0a4d4d 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(10, 77, 77, 0.3);
    }

    .readonly-notice {
        background: #fff3cd;
        border: 2px solid #ffc107;
        color: #856404;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }

    .readonly-notice i {
        font-size: 24px;
    }

    @media print {
        body * { visibility: hidden; }
        .facture-card, .facture-card * { visibility: visible; }
        .facture-card { 
            position: absolute; 
            left: 0; 
            top: 0; 
            box-shadow: none;
            padding: 20px;
            border: none;
        }
        .historique-paiements,
        .btn-print,
        .btn-back,
        .archive-header,
        .archive-badge,
        .readonly-notice { 
            display: none !important; 
        }
    }
</style>

<!-- ========================= AFFICHAGE ========================= -->

<div class="archive-header">
    <h2><i class="fas fa-archive"></i> Archives - <?php echo htmlspecialchars($client['nom_complet']); ?></h2>
    <button class="btn-back" onclick="backToList()">
        <i class="fas fa-arrow-left"></i> Retour
    </button>
</div>

<div class="readonly-notice">
    <i class="fas fa-lock"></i>
    <strong>Lecture seule :</strong> factures totalement payées et archivées.
</div>

<?php
$facture_count = 0;
while ($facture = mysqli_fetch_assoc($factures_result)):
    $facture_count++;

    $numero_facture = mysqli_real_escape_string($conn, $facture['numero_facture']);

    // PRODUITS — IDENTIQUE À get_factures.php
    $produits_query = "SELECT
            p.nom AS produit_nom,
            u.symbole,
            d.quantite,
            d.prix_unitaire_fige,
            d.montant_total AS total_ligne
        FROM dettes d
        JOIN produits_unites pu ON d.produit_unite_id = pu.id
        JOIN produits p ON pu.produit_id = p.id
        JOIN unites u ON pu.unite_id = u.id
        WHERE d.numero_facture = '$numero_facture'
        ORDER BY d.id";

    $produits_result = mysqli_query($conn, $produits_query);
?>

<div class="facture-card">
    <div class="archive-badge">✔ PAYÉE</div>

    <div class="facture-header">
        <div class="store-name">E-VAROOTRA STORE MAMAN'I NDOH</div>
        <div>Ampisinkinana — 034 60 468 65</div>
    </div>

    <div class="facture-numero">
        FACTURE N° <?php echo htmlspecialchars($facture['numero_facture']); ?>
    </div>

    <div class="facture-info">
        <div>
            <strong>Date :</strong> <?php echo date('d/m/Y', strtotime($facture['date_dette'])); ?><br>
            <strong>Vendeur :</strong> <?php echo htmlspecialchars($facture['vendeur_pseudo']); ?>
        </div>
        <div style="text-align:right">
            <strong>CLIENT</strong><br>
            <?php echo htmlspecialchars($client['nom_complet']); ?><br>
            <?php echo htmlspecialchars($client['telephone']); ?><br>
            <?php echo htmlspecialchars($client['adresse']); ?>
        </div>
    </div>

    <table class="facture-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Produit</th>
                <th class="text-center">Qté</th>
                <th class="text-right">PU</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php $i = 1; while ($p = mysqli_fetch_assoc($produits_result)): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($p['produit_nom']); ?></td>
                <td>
                    <?php echo number_format($p['quantite'], 0, ',', ' ') . ' ' . htmlspecialchars($p['symbole']); ?>
                </td>
                <td class="text-right"><?php echo number_format($p['prix_unitaire_fige'], 0, ',', ' '); ?></td>
                <td class="text-right"><?php echo number_format($p['total_ligne'], 0, ',', ' '); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

    <div class="facture-totals">
        <div class="total-line">
            <span>Total</span>
            <span>
            <?php echo number_format($facture['total_facture'], 0, ',', ' '); ?> Ar
            </span>
        </div>

        <div class="total-line main">
            <span>Total payé</span>
            <span>
                <?php echo number_format($facture['total_paye'], 0, ',', ' '); ?> Ar
            </span>
        </div>
    </div>


    <div class="status-paid-archive">
        ✔ Payée le <?php echo date('d/m/Y à H:i', strtotime($facture['date_paiement_complet'])); ?>
    </div>

    <?php if (!empty($paiements_par_facture[$facture['numero_facture']])): ?>
    <div class="historique-paiements">
        <h4>Historique des paiements</h4>
        <?php foreach ($paiements_par_facture[$facture['numero_facture']] as $pay): ?>
<div class="paiement-item">
    <div>
        Réf: <?php echo htmlspecialchars($pay['reference_paiement']); ?><br>
        <?php 
        $datetime = $pay['date_creation'] ?? $pay['date_paiement'] ?? '';
        echo date('d/m/Y H:i', strtotime($datetime)); 
        ?> 
        - Enregistré par <?php echo htmlspecialchars($pay['enregistre_par_pseudo'] ?? 'Inconnu'); ?>
    </div>
    <strong><?php echo number_format($pay['montant_paye'], 0, ',', ' '); ?> Ar</strong>
</div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <button class="btn-print" onclick="window.print()">🖨 Imprimer</button>
</div>

<?php endwhile; ?>

<?php if ($facture_count === 0): ?>
<div style="text-align:center;padding:60px">
    <h3>Aucune facture archivée</h3>
</div>
<?php endif; ?>