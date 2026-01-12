<?php
$host = "localhost";
$user = "root";
$pass = "";
$db_name = "e_varootra";

$conn = mysqli_connect($host, $user, $pass, $db_name);

if(!$conn){
    die("Échec de connexion : " . mysqli_connect_error());
}

session_start();
?>