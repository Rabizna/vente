<?php
//get_factures.php
include "config.php";

$client_id = $_GET['client_id'];

// Récupérer les factures du client (groupées par numero_facture)
$factures_query = "SELECT 
                    d.numero_facture,
                    d.date_dette,
                    c.nom_complet,
                    c.telephone,
                    c.adresse,
                    u.nom_complet as vendeur,
                    SUM(d.montant_total) as total_facture,
                    SUM(d.montant_paye) as total_paye,
                    SUM(d.montant_restant) as total_reste
                   FROM dettes d
                   JOIN clients c ON d.client_id = c.id
                   JOIN utilisateurs u ON d.enregistre_par = u.id
                   WHERE d.client_id = '$client_id' 
                   AND d.statut IN ('active', 'partiellement_payee')
                   GROUP BY d.numero_facture, d.date_dette, c.nom_complet, c.telephone, c.adresse, u.nom_complet
                   ORDER BY d.date_dette DESC";
$factures_result = mysqli_query($conn, $factures_query);
?>

<button class="btn-back" onclick="backToList()">
    <i class="fas fa-arrow-left"></i> Retour à la liste
</button>

<?php while($facture = mysqli_fetch_assoc($factures_result)): 
    // Récupérer les produits de cette facture
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
                       WHERE d.numero_facture = '{$facture['numero_facture']}'";
    $produits_result = mysqli_query($conn, $produits_query);
?>

<div class="facture-card">
    <div class="facture-header">
        <div class="store-name">E-VAROOTRA STORE MAMAN'I NDOH</div>
        <div>Adresse : Ampisinkinana</div>
        <div>Téléphone : 0346046865</div>
    </div>

    <div style="text-align: center; font-size: 20px; font-weight: bold; margin: 20px 0;">
        FACTURE
    </div>

    <div class="facture-info">
        <div>
            <strong>N° :</strong> <?php echo $facture['numero_facture']; ?><br>
            <strong>Date :</strong> <?php echo date('d/m/Y', strtotime($facture['date_dette'])); ?><br>
            <strong>Vendu par :</strong> <?php echo htmlspecialchars($facture['vendeur']); ?>
        </div>
        <div style="text-align: right;">
            <strong>CLIENT :</strong><br>
            <strong>Nom :</strong> <?php echo htmlspecialchars($facture['nom_complet']); ?><br>
            <strong>Téléphone :</strong> <?php echo $facture['telephone']; ?>
        </div>
    </div>

    <div style="border-top: 2px dashed #000; margin: 20px 0;"></div>

    <table class="facture-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Produit</th>
                <th style="text-align: center;">Qté</th>
                <th style="text-align: right;">PU</th>
                <th style="text-align: right;">Total</th>
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
                <td style="text-align: center;"><?php echo $produit['quantite']; ?></td>
                <td style="text-align: right;"><?php echo number_format($produit['prix_unitaire_fige'], 0, ',', ' '); ?></td>
                <td style="text-align: right;"><?php echo number_format($produit['total_ligne'], 0, ',', ' '); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div style="border-top: 2px dashed #000; margin: 20px 0;"></div>

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
            <span>Total</span>
            <span><?php echo number_format($facture['total_facture'], 0, ',', ' '); ?> Ar</span>
        </div>
    </div>

    <div style="margin: 20px 0; background: <?php echo $facture['total_reste'] > 0 ? '#fff5f5' : '#f0fdf4'; ?>; padding: 15px; border-radius: 10px;">
        <div style="font-weight: bold; font-size: 18px;">Paiement : Dette</div>
        <div style="color: #2ed573; font-size: 20px; margin-top: 8px;">
            <strong>Payé :</strong> <?php echo number_format($facture['total_paye'], 0, ',', ' '); ?> Ar
        </div>
        <div style="color: <?php echo $facture['total_reste'] > 0 ? '#ff4757' : '#2ed573'; ?>; font-size: 24px; font-weight: bold; margin-top: 8px;">
            <strong>Reste :</strong> <?php echo number_format($facture['total_reste'], 0, ',', ' '); ?> Ar
        </div>
        <?php if($facture['total_reste'] <= 0): ?>
        <div style="color: #2ed573; font-weight: bold; margin-top: 10px; text-align: center; font-size: 20px;">
            ✅ DETTE TOTALEMENT PAYÉE
        </div>
        <?php endif; ?>
    </div>

    <div style="border-top: 2px dashed #000; margin: 20px 0;"></div>

    <div style="display: flex; justify-content: space-between; margin: 30px 0;">
        <div>Signature vendeur : __________</div>
        <div>Signature client : __________</div>
    </div>

    <div style="text-align: center; font-weight: bold; margin-top: 20px;">
        Merci !
    </div>

    <!-- Section Paiement - Visible seulement si reste à payer -->
    <?php if($facture['total_reste'] > 0): ?>
    <div class="paiement-section">
        <h3 style="margin-bottom: 15px;">💰 Enregistrer un paiement</h3>
        <form method="POST" action="dette.php" class="paiement-form">
            <input type="hidden" name="action" value="payer_dette">
            <input type="hidden" name="numero_facture" value="<?php echo $facture['numero_facture']; ?>">
            
            <div style="flex: 1;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Montant (Ar)</label>
                <input type="number" name="montant_paiement" step="0.01" min="0.01" max="<?php echo $facture['total_reste']; ?>" required placeholder="Entrez le montant">
            </div>
            
            <button type="submit" class="btn-payer">
                <i class="fas fa-money-bill-wave"></i> Payer
            </button>
        </form>
        
        <div style="margin-top: 15px; display: flex; gap: 10px;">
            <button onclick="setMontant('<?php echo $facture['numero_facture']; ?>', <?php echo round($facture['total_reste']/3, 2); ?>)" style="flex: 1; padding: 8px; background: #ffa502; color: white; border: none; border-radius: 5px; cursor: pointer;">
                1/3 (<?php echo number_format(round($facture['total_reste']/3), 0, ',', ' '); ?> Ar)
            </button>
            <button onclick="setMontant('<?php echo $facture['numero_facture']; ?>', <?php echo round($facture['total_reste']/2, 2); ?>)" style="flex: 1; padding: 8px; background: #ff6348; color: white; border: none; border-radius: 5px; cursor: pointer;">
                1/2 (<?php echo number_format(round($facture['total_reste']/2), 0, ',', ' '); ?> Ar)
            </button>
            <button onclick="setMontant('<?php echo $facture['numero_facture']; ?>', <?php echo $facture['total_reste']; ?>)" style="flex: 1; padding: 8px; background: #2ed573; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Total (<?php echo number_format($facture['total_reste'], 0, ',', ' '); ?> Ar)
            </button>
        </div>
    </div>
    <?php else: ?>
    <div style="background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; text-align: center; font-weight: bold; font-size: 18px; margin-top: 20px;">
        ✅ Cette facture a été totalement payée
    </div>
    <?php endif; ?>

    <button onclick="window.print()" style="width: 100%; padding: 15px; background: #0a4d4d; color: white; border: none; border-radius: 10px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 20px;">
        <i class="fas fa-print"></i> Imprimer la facture
    </button>
</div>

<?php endwhile; ?>

<script>
function setMontant(numeroFacture, montant) {
    const form = document.querySelector(`form input[value="${numeroFacture}"]`).closest('form');
    form.querySelector('input[name="montant_paiement"]').value = Math.round(montant);
}
</script>