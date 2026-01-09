<?php
//get_archives.php - AFFICHAGE READONLY DES FACTURES PAYÉES
include "config.php";

$client_id = mysqli_real_escape_string($conn, $_GET['client_id']);

// Récupérer les informations du client
$client_query = "SELECT nom_complet, telephone, adresse FROM clients WHERE id = '$client_id'";
$client_result = mysqli_query($conn, $client_query);
$client = mysqli_fetch_assoc($client_result);

// Récupérer les factures PAYÉES du client
$factures_query = "SELECT 
                    d.numero_facture,
                    d.date_dette,
                    d.date_modification as date_paiement_complet,
                    u.nom_complet as vendeur,
                    SUM(d.montant_total) as total_facture,
                    SUM(d.montant_paye) as total_paye
                   FROM dettes d
                   JOIN utilisateurs u ON d.enregistre_par = u.id
                   WHERE d.client_id = '$client_id' 
                   AND d.statut = 'payee'
                   GROUP BY d.numero_facture, d.date_dette, d.date_modification, u.nom_complet
                   ORDER BY d.date_modification DESC";
$factures_result = mysqli_query($conn, $factures_query);

// Récupérer l'historique des paiements
$paiements_query = "SELECT 
                        p.dette_id,
                        d.numero_facture,
                        p.montant_paye,
                        p.reference_paiement,
                        p.date_paiement,
                        u.nom_complet as enregistre_par
                    FROM paiements_dette p
                    JOIN dettes d ON p.dette_id = d.id
                    JOIN utilisateurs u ON p.enregistre_par = u.id
                    WHERE d.client_id = '$client_id' AND d.statut = 'payee'
                    ORDER BY p.date_paiement DESC";
$paiements_result = mysqli_query($conn, $paiements_query);

// Organiser les paiements par facture
$paiements_par_facture = [];
while($paiement = mysqli_fetch_assoc($paiements_result)){
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

    .facture-table td {
        font-size: 14px;
    }

    .facture-table td.text-center {
        text-align: center;
    }

    .facture-table td.text-right {
        text-align: right;
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

<div class="archive-header">
    <h2>
        <i class="fas fa-archive"></i>
        Archives - <?php echo htmlspecialchars($client['nom_complet']); ?>
    </h2>
    <button class="btn-back" onclick="backToList()">
        <i class="fas fa-arrow-left"></i> Retour
    </button>
</div>

<div class="readonly-notice">
    <i class="fas fa-lock"></i>
    <div>
        <strong>Mode Lecture Seule</strong> - Ces factures sont archivées et ne peuvent plus être modifiées ni supprimées.
    </div>
</div>

<?php 
$facture_count = 0;
while($facture = mysqli_fetch_assoc($factures_result)): 
    $facture_count++;
    
    // Récupérer les produits de cette facture
    $numero_facture_escaped = mysqli_real_escape_string($conn, $facture['numero_facture']);
    $produits_query = "SELECT 
                        p.nom as produit_nom,
                        p.quantite_unite,
                        u.symbole,
                        d.quantite,
                        d.prix_unitaire_fige,
                        (d.quantite * d.prix_unitaire_fige) as total_ligne
                       FROM dettes d
                       JOIN produits p ON d.produit_id = p.id
                       JOIN unites u ON p.unite_id = u.id
                       WHERE d.numero_facture = '$numero_facture_escaped'
                       ORDER BY d.id";
    $produits_result = mysqli_query($conn, $produits_query);
?>

<div class="facture-card">
    <div class="archive-badge">
        <i class="fas fa-check-circle"></i>
        PAYÉE - Archivée
    </div>

    <div class="facture-header">
        <div class="store-name">E-VAROOTRA STORE MAMAN'I NDOH</div>
        <div>Adresse : Ampisinkinana</div>
        <div>Téléphone : 0346046865</div>
    </div>

    <div class="facture-numero">
        FACTURE N° <?php echo htmlspecialchars($facture['numero_facture']); ?>
    </div>

    <div class="facture-info">
        <div class="facture-info-left">
            <div><strong>Date émission :</strong> <?php echo date('d/m/Y', strtotime($facture['date_dette'])); ?></div>
            <div><strong>Vendu par :</strong> <?php echo htmlspecialchars($facture['vendeur']); ?></div>
        </div>
        <div class="facture-info-right">
            <div><strong>CLIENT</strong></div>
            <div><strong>Nom :</strong> <?php echo htmlspecialchars($client['nom_complet']); ?></div>
            <div><strong>Tél :</strong> <?php echo htmlspecialchars($client['telephone']); ?></div>
            <div><strong>Adresse :</strong> <?php echo htmlspecialchars($client['adresse']); ?></div>
        </div>
    </div>

    <div class="divider"></div>

    <table class="facture-table">
        <thead>
            <tr>
                <th style="width: 40px;">#</th>
                <th>Produit</th>
                <th class="text-center" style="width: 100px;">Qté</th>
                <th class="text-right" style="width: 120px;">PU (Ar)</th>
                <th class="text-right" style="width: 120px;">Total (Ar)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $index = 1;
            while($produit = mysqli_fetch_assoc($produits_result)): 
            ?>
            <tr>
                <td><?php echo $index++; ?></td>
                <td><?php echo htmlspecialchars($produit['produit_nom']) . ' ' . $produit['quantite_unite'] . $produit['symbole']; ?></td>
                <td class="text-center"><?php echo $produit['quantite']; ?></td>
                <td class="text-right"><?php echo number_format($produit['prix_unitaire_fige'], 0, ',', ' '); ?></td>
                <td class="text-right"><?php echo number_format($produit['total_ligne'], 0, ',', ' '); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="divider"></div>

    <div class="facture-totals">
        <div class="total-line">
            <span>Sous-total</span>
            <span><?php echo number_format($facture['total_facture'], 0, ',', ' '); ?> Ar</span>
        </div>
        <div class="total-line">
            <span>Remise</span>
            <span>0 Ar</span>
        </div>
        <div class="total-line main">
            <span>TOTAL PAYÉ</span>
            <span><?php echo number_format($facture['total_paye'], 0, ',', ' '); ?> Ar</span>
        </div>
    </div>

    <div class="status-paid-archive">
        <h3>
            <i class="fas fa-check-double"></i>
            DETTE TOTALEMENT PAYÉE
        </h3>
        <div class="payment-date">
            <i class="fas fa-calendar-check"></i>
            Payée le <?php echo date('d/m/Y à H:i', strtotime($facture['date_paiement_complet'])); ?>
        </div>
    </div>

    <?php if(isset($paiements_par_facture[$facture['numero_facture']])): ?>
    <div class="historique-paiements">
        <h4>
            <i class="fas fa-history"></i>
            Historique des Paiements
        </h4>
        <?php foreach($paiements_par_facture[$facture['numero_facture']] as $paiement): ?>
        <div class="paiement-item">
            <div class="paiement-item-left">
                <div class="paiement-ref">
                    <i class="fas fa-receipt"></i>
                    Réf: <?php echo htmlspecialchars($paiement['reference_paiement']); ?>
                </div>
                <div class="paiement-date">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('d/m/Y', strtotime($paiement['date_paiement'])); ?> 
                    - Enregistré par <?php echo htmlspecialchars($paiement['enregistre_par']); ?>
                </div>
            </div>
            <div class="paiement-montant">
                <?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> Ar
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="divider"></div>

    <div class="signatures">
        <div>Signature vendeur : __________</div>
        <div>Signature client : __________</div>
    </div>

    <div class="thank-you">
        Merci de votre confiance !
    </div>

    <button onclick="window.print()" class="btn-print">
        <i class="fas fa-print"></i> Imprimer cette facture
    </button>
</div>

<?php endwhile; ?>

<?php if($facture_count == 0): ?>
<div style="text-align: center; padding: 80px; background: white; border-radius: 15px;">
    <i class="fas fa-inbox" style="font-size: 100px; color: #e0e0e0; margin-bottom: 25px;"></i>
    <h3 style="color: #0a4d4d; margin-bottom: 15px; font-size: 26px;">Aucune archive disponible</h3>
    <p style="color: #999; font-size: 16px;">Ce client n'a pas encore de factures payées</p>
</div>
<?php endif; ?>