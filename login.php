<?php
//login.php - VERSION CORRIGÉE
include "config.php";
$message = "";
$message_type = ""; // Pour différencier succès et erreur

// Empêcher le cache du formulaire - forcer le rechargement complet
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

/************ LOGIN ************/
if (isset($_POST['action']) && $_POST['action'] == "login") {
    $pseudo = mysqli_real_escape_string($conn, $_POST['pseudo']);
    $password = mysqli_real_escape_string($conn, $_POST['mot_de_passe']);

    $sql = "SELECT * FROM utilisateurs WHERE pseudo='$pseudo' AND mot_de_passe='$password'";
    $result = mysqli_query($conn,$sql);

    if(mysqli_num_rows($result) > 0){
        $row = mysqli_fetch_assoc($result);
        $_SESSION['user'] = $row['pseudo'];
        $_SESSION['nom']  = $row['nom_complet'];
        $_SESSION['user_id'] = $row['id'];  // ✅ CORRECTION CRITIQUE AJOUTÉE !
        header("Location: index.php");
        exit();
    } else {
        $message = "Identifiants incorrects !";
        $message_type = "error";
    }
}


/************ SIGNUP ************/
if (isset($_POST['action']) && $_POST['action'] == "signup") {
    $nom = mysqli_real_escape_string($conn, $_POST['nom_complet']);
    $pseudo = mysqli_real_escape_string($conn, $_POST['pseudo']);
    $password = mysqli_real_escape_string($conn, $_POST['mot_de_passe']);

    $check = mysqli_query($conn,"SELECT * FROM utilisateurs WHERE pseudo='$pseudo'");
    if(mysqli_num_rows($check) > 0){
        $message = "Ce pseudo existe déjà !";
        $message_type = "error";
    } else {
        $sql = "INSERT INTO utilisateurs(nom_complet,pseudo,mot_de_passe)
        VALUES('$nom','$pseudo','$password')";
        mysqli_query($conn,$sql);
        $message = "Compte créé avec succès ! Connecte-toi 😊";
        $message_type = "success";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
<title>Login / Signup</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<style>
<?php echo file_get_contents("style-auth.css"); ?>
</style>
</head>

<body>

<?php if($message): ?>
<p style="color:<?php echo $message_type == 'success' ? 'green' : 'red'; ?>; font-weight:bold">
<?php echo $message; ?>
</p>
<?php endif; ?>

<div class="container" id="container">

	<!-- SIGN UP -->
	<div class="form-container sign-up-container">
		<form method="POST" id="signupForm" autocomplete="off">
			<h1>Créer un compte</h1>
			<input type="hidden" name="action" value="signup">
			<input type="text" name="nom_complet" placeholder="Nom complet" required autocomplete="off" value="" />
			<input type="text" name="pseudo" placeholder="pseudo" required autocomplete="off" value="" />
			<input type="password" name="mot_de_passe" placeholder="Mot de passe" required autocomplete="new-password" value="" />
			<button type="submit">S'inscrire</button>
		</form>
	</div>

	<!-- LOGIN -->
	<div class="form-container sign-in-container">
		<form method="POST" id="loginForm" autocomplete="off">
			<h1>Se connecter</h1>
			<input type="hidden" name="action" value="login">
			<input type="text" name="pseudo" placeholder="pseudo" required autocomplete="off" value="" />
			<input type="password" name="mot_de_passe" placeholder="Mot de passe" required autocomplete="current-password" value="" />
			<button type="submit">Se connecter</button>
		</form>
	</div>

	<!-- PANEL -->
	<div class="overlay-container">
		<div class="overlay">
			<div class="overlay-panel overlay-left">
				<h1>Tongasoa indray!</h1>
				<p>Mba hifandraisana hatrany aminay dia midira amin'ny mombamomba anao manokana.</p>
				<button class="ghost" id="signIn">Se connecter</button>
			</div>
			<div class="overlay-panel overlay-right">
				<h1>Salama, namana!</h1>
				<p>Ampidiro ny mombamomba anao ary atombohy miaraka aminay ny dianao.</p>
				<button class="ghost" id="signUp">S'inscrire</button>
			</div>
		</div>
	</div>
</div>

<script>
const signUpButton = document.getElementById('signUp');
const signInButton = document.getElementById('signIn');
const container = document.getElementById('container');
const loginForm = document.getElementById('loginForm');
const signupForm = document.getElementById('signupForm');

// FONCTION POUR EFFACER COMPLÈTEMENT TOUS LES CHAMPS
function clearAllInputs() {
	document.querySelectorAll('input[type="text"], input[type="password"]').forEach(input => {
		input.value = '';
		input.defaultValue = '';
		input.setAttribute('value', '');
	});
	loginForm.reset();
	signupForm.reset();
}

// Effacer au chargement de la page
window.addEventListener('load', function() {
	clearAllInputs();
});

// Effacer avant de quitter la page
window.addEventListener('beforeunload', function() {
	clearAllInputs();
});

// Effacer lors de l'événement pageshow (détecte l'utilisation du cache)
window.addEventListener('pageshow', function(event) {
	// Si la page vient du cache (bouton retour ou F5)
	if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
		clearAllInputs();
	}
});

// Effacer lors de l'utilisation du bouton retour
window.addEventListener('popstate', function() {
	clearAllInputs();
});

signUpButton.addEventListener('click', () => {
	container.classList.add("right-panel-active");
	clearAllInputs();
});

signInButton.addEventListener('click', () => {
	container.classList.remove("right-panel-active");
	clearAllInputs();
});

// Effacer après soumission
loginForm.addEventListener('submit', function(e) {
	// Laisser le formulaire se soumettre normalement
	setTimeout(() => {
		clearAllInputs();
	}, 10);
});

signupForm.addEventListener('submit', function(e) {
	setTimeout(() => {
		clearAllInputs();
	}, 10);
});

// Effacer périodiquement (au cas où)
setInterval(function() {
	if (!document.activeElement || !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
		// Ne pas effacer si l'utilisateur est en train de taper
		const allEmpty = Array.from(document.querySelectorAll('input[type="text"], input[type="password"]'))
			.every(input => input.value === '');
		
		if (!allEmpty) {
			// Si des champs ont des valeurs mais qu'on ne tape pas, les effacer
			setTimeout(clearAllInputs, 1000);
		}
	}
}, 2000);

// Gérer les messages PHP
<?php if($message_type == "success"): ?>
	setTimeout(() => {
		clearAllInputs();
		container.classList.remove("right-panel-active");
	}, 100);
<?php endif; ?>

<?php if($message_type == "error" && isset($_POST['action']) && $_POST['action'] == "signup"): ?>
	container.classList.add("right-panel-active");
	setTimeout(clearAllInputs, 100);
<?php elseif($message_type == "error"): ?>
	setTimeout(clearAllInputs, 100);
<?php endif; ?>

// Empêcher l'autocomplétion
document.querySelectorAll('input').forEach(input => {
	input.setAttribute('autocomplete', 'off');
	input.setAttribute('autocorrect', 'off');
	input.setAttribute('autocapitalize', 'off');
	input.setAttribute('spellcheck', 'false');
});

// Nettoyer une dernière fois après un court délai
setTimeout(clearAllInputs, 200);
</script>

</body>
</html>