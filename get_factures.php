<?php
//get_factures.php - VERSION OPTIMALE
include "config.php";

$client_id = mysqli_real_escape_string($conn, $_GET['client_id']);

// Récupérer les informations du client
$client_query = "SELECT nom_complet, telephone, adresse FROM clients WHERE id = '$client_id'";
$client_result = mysqli_query($conn, $client_query);
$client = mysqli_fetch_assoc($client_result);

// Récupérer UNIQUEMENT les factures NON payées (les payées vont en archive)
$factures_query = "SELECT 
                    d.numero_facture,
                    d.date_dette,
                    u.nom_complet as vendeur_nom,
                    u.pseudo as vendeur_pseudo,
                    SUM(d.montant_total) as total_facture,
                    SUM(d.montant_paye) as total_paye,
                    SUM(d.montant_restant) as total_reste,
                    MIN(d.statut) as statut
                   FROM dettes d
                   JOIN utilisateurs u ON d.enregistre_par = u.id
                   WHERE d.client_id = '$client_id' 
                   AND d.statut IN ('active', 'partiellement_payee')
                   GROUP BY d.numero_facture, d.date_dette, u.nom_complet, u.pseudo
                   HAVING total_reste > 0
                   ORDER BY d.date_dette DESC";
$factures_result = mysqli_query($conn, $factures_query);

// Récupérer l'historique des paiements pour toutes les factures avec infos utilisateur ET date_creation
$paiements_query = "SELECT 
                        p.dette_id,
                        d.numero_facture,
                        p.montant_paye,
                        p.reference_paiement,
                        p.date_paiement,
                        p.date_creation,
                        u.nom_complet as enregistre_par_nom,
                        u.pseudo as enregistre_par_pseudo
                    FROM paiements_dette p
                    JOIN dettes d ON p.dette_id = d.id
                    JOIN utilisateurs u ON p.enregistre_par = u.id
                    WHERE d.client_id = '$client_id'
                    ORDER BY p.date_creation DESC, p.id DESC";
$paiements_result = mysqli_query($conn, $paiements_query);

// Organiser les paiements par numéro de facture
$paiements_par_facture = [];
while($paiement = mysqli_fetch_assoc($paiements_result)){
    $paiements_par_facture[$paiement['numero_facture']][] = $paiement;
}
?>

<style>
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

    .facture-card {
        background: white;
        border-radius: 15px;
        padding: 40px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        font-family: 'Courier New', monospace;
        position: relative;
        overflow: hidden;
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

    .status-box {
        margin: 20px 0;
        padding: 20px;
        border-radius: 10px;
        border: 2px solid;
    }

    .status-box.unpaid {
        background: #fff5f5;
        border-color: #ff4757;
    }

    .status-box.partial {
        background: #fff9e6;
        border-color: #ffa502;
    }

    .status-box.paid {
        background: #f0fdf4;
        border-color: #2ed573;
    }

    .status-box h3 {
        margin-bottom: 12px;
        font-size: 18px;
    }

    .status-line {
        display: flex;
        justify-content: space-between;
        margin: 8px 0;
        font-size: 16px;
    }

    .status-line.total {
        font-size: 22px;
        font-weight: bold;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 2px dashed rgba(0,0,0,0.2);
    }

    .status-paid {
        text-align: center;
        background: #2ed573;
        color: white;
        padding: 15px;
        border-radius: 10px;
        font-weight: bold;
        font-size: 18px;
        margin-top: 15px;
    }

    .paiement-section {
        background: #f8f9fa;
        padding: 25px;
        border-radius: 12px;
        margin-top: 25px;
        border: 2px dashed #0a4d4d;
    }

    .paiement-section h3 {
        color: #0a4d4d;
        margin-bottom: 15px;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .paiement-form {
        display: flex;
        gap: 15px;
        align-items: end;
        margin-top: 15px;
    }

    .paiement-form > div {
        flex: 1;
    }

    .paiement-form label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .paiement-form input {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 16px;
        font-family: 'Segoe UI', sans-serif;
    }

    .paiement-form input:focus {
        outline: none;
        border-color: #2ed573;
    }

    .btn-payer {
        background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
    }

    .btn-payer:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(46, 213, 115, 0.4);
    }

    .quick-buttons {
        margin-top: 15px;
        display: flex;
        gap: 10px;
    }

    .btn-quick {
        flex: 1;
        padding: 10px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
        font-size: 14px;
    }

    .btn-quick:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .btn-quick.third {
        background: linear-gradient(135deg, #ffa502 0%, #ff7f00 100%);
    }

    .btn-quick.half {
        background: linear-gradient(135deg, #ff6348 0%, #ff4757 100%);
    }

    .btn-quick.full {
        background: linear-gradient(135deg, #2ed573 0%, #26de81 100%);
    }

    .btn-print {
        width: 100%;
        padding: 15px;
        background: #0a4d4d;
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
        background: #063838;
        transform: translateY(-2px);
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

    .historique-paiements {
        background: #e8f5e9;
        padding: 20px;
        border-radius: 10px;
        margin-top: 20px;
    }

    .historique-paiements h4 {
        color: #0a4d4d;
        margin-bottom: 15px;
        font-size: 18px;
    }

    .paiement-item {
        background: white;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .paiement-item-left {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .paiement-ref {
        font-weight: bold;
        color: #0a4d4d;
        font-size: 14px;
    }

    .paiement-date {
        font-size: 12px;
        color: #666;
    }

    .paiement-montant {
        font-weight: bold;
        color: #2ed573;
        font-size: 18px;
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
        }
        .paiement-section,
        .btn-print,
        .btn-back,
        .historique-paiements { 
            display: none !important; 
        }
    }
</style>

<button class="btn-back" onclick="backToList()">
    <i class="fas fa-arrow-left"></i> Retour à la liste
</button>

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
    
    // Déterminer le statut de la facture
    $is_paid = $facture['total_reste'] <= 0;
    $is_partial = $facture['total_paye'] > 0 && $facture['total_reste'] > 0;
?>

<div class="facture-card">
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
            <div><strong>Date :</strong> <?php echo date('d/m/Y', strtotime($facture['date_dette'])); ?></div>
            <div><strong>Vendu par :</strong> <?php echo htmlspecialchars($facture['vendeur_pseudo'] ?? 'Non spécifié'); ?></div>
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
            <span>TOTAL</span>
            <span><?php echo number_format($facture['total_facture'], 0, ',', ' '); ?> Ar</span>
        </div>
    </div>

    <?php 
    // Déterminer la classe du statut
    $status_class = 'unpaid';
    if($is_paid) $status_class = 'paid';
    elseif($is_partial) $status_class = 'partial';
    ?>

    <div class="status-box <?php echo $status_class; ?>">
        <h3>💰 Paiement : Dette</h3>
        <div class="status-line">
            <span>Montant payé :</span>
            <span style="color: #2ed573; font-weight: bold;">
                <?php echo number_format($facture['total_paye'], 0, ',', ' '); ?> Ar
            </span>
        </div>
        <div class="status-line total">
            <span>Reste à payer :</span>
            <span style="color: <?php echo $is_paid ? '#2ed573' : '#ff4757'; ?>;">
                <?php echo number_format($facture['total_reste'], 0, ',', ' '); ?> Ar
            </span>
        </div>
        <?php if($is_paid): ?>
        <div class="status-paid">
            ✅ DETTE TOTALEMENT PAYÉE
        </div>
        <?php endif; ?>
    </div>

    <?php if(isset($paiements_par_facture[$facture['numero_facture']])): ?>
    <div class="historique-paiements">
        <h4>📜 Historique des paiements</h4>
        <?php foreach($paiements_par_facture[$facture['numero_facture']] as $paiement): ?>
        <div class="paiement-item">
            <div class="paiement-item-left">
                <div class="paiement-ref">Réf: <?php echo htmlspecialchars($paiement['reference_paiement']); ?></div>
                <div class="paiement-date">
                    <?php 
                    // Vérifier si date_creation existe et l'utiliser pour l'heure
                    $datetime = $paiement['date_creation'] ?? $paiement['date_paiement'];
                    echo date('d/m/Y à H:i', strtotime($datetime)); 
                    ?> 
                    - Enregistré par <?php echo htmlspecialchars($paiement['enregistre_par_pseudo'] ?? 'Inconnu'); ?>
                </div>
            </div>
            <div class="paiement-montant">
                +<?php echo number_format($paiement['montant_paye'], 0, ',', ' '); ?> Ar
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

    <!-- Section Paiement -->
    <?php if(!$is_paid): ?>
    <div class="paiement-section">
        <h3>
            <i class="fas fa-money-bill-wave"></i>
            Enregistrer un paiement
        </h3>
        <form method="POST" action="dette.php" class="paiement-form" onsubmit="return confirmPaiement(this)">
            <input type="hidden" name="action" value="payer_dette">
            <input type="hidden" name="numero_facture" value="<?php echo htmlspecialchars($facture['numero_facture']); ?>">
            
            <div>
                <label>Montant (Ariary)</label>
                <input 
                    type="number" 
                    name="montant_paiement" 
                    step="0.01" 
                    min="0.01" 
                    max="<?php echo $facture['total_reste']; ?>" 
                    required 
                    placeholder="Entrez le montant"
                    data-facture="<?php echo htmlspecialchars($facture['numero_facture']); ?>"
                >
            </div>
            
            <button type="submit" class="btn-payer">
                <i class="fas fa-check"></i> Payer
            </button>
        </form>
        
        <div class="quick-buttons">
            <button 
                type="button" 
                class="btn-quick third" 
                onclick="setMontant('<?php echo htmlspecialchars($facture['numero_facture']); ?>', <?php echo round($facture['total_reste']/3, 2); ?>)"
            >
                1/3 (<?php echo number_format(round($facture['total_reste']/3), 0, ',', ' '); ?> Ar)
            </button>
            <button 
                type="button" 
                class="btn-quick half" 
                onclick="setMontant('<?php echo htmlspecialchars($facture['numero_facture']); ?>', <?php echo round($facture['total_reste']/2, 2); ?>)"
            >
                1/2 (<?php echo number_format(round($facture['total_reste']/2), 0, ',', ' '); ?> Ar)
            </button>
            <button 
                type="button" 
                class="btn-quick full" 
                onclick="setMontant('<?php echo htmlspecialchars($facture['numero_facture']); ?>', <?php echo $facture['total_reste']; ?>)"
            >
                Total (<?php echo number_format($facture['total_reste'], 0, ',', ' '); ?> Ar)
            </button>
        </div>
    </div>
    <?php endif; ?>

    <button onclick="window.print()" class="btn-print">
        <i class="fas fa-print"></i> Imprimer la facture
    </button>
</div>

<?php endwhile; ?>

<?php if($facture_count == 0): ?>
<div style="text-align: center; padding: 60px; background: white; border-radius: 15px;">
    <i class="fas fa-check-circle" style="font-size: 80px; color: #2ed573; margin-bottom: 20px;"></i>
    <h3 style="color: #0a4d4d; margin-bottom: 10px;">Aucune dette en cours</h3>
    <p style="color: #666;">Ce client a payé toutes ses dettes !</p>
</div>
<?php endif; ?>

<script>
// Définir le montant dans le formulaire
function setMontant(numeroFacture, montant) {
    const input = document.querySelector(`input[data-facture="${numeroFacture}"]`);
    if(input) {
        input.value = Math.round(montant);
        input.focus();
    }
}

// Confirmer le paiement
function confirmPaiement(form) {
    const montant = parseFloat(form.querySelector('input[name="montant_paiement"]').value);
    const montantFormat = montant.toLocaleString('fr-FR');
    return confirm(`Confirmer le paiement de ${montantFormat} Ar ?`);
}
</script>