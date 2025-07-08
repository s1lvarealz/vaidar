<?php
session_start();

if (!isset($_SESSION["nick"])) {
    header("Location: ../../frontend/login.php");
    exit();
}

require "../ligabd.php";

// Obter os dados



$id = trim($_POST["id"]);

$pass_atual = trim($_POST["pass_atual"]);
$pass_nova = trim($_POST["pass_nova"]);

$sql = "SELECT * FROM utilizadores WHERE id='$id' AND palavra_passe = password('$pass_atual')";
$resultado = mysqli_query($con, $sql );
if (mysqli_num_rows($resultado)<1) {
    $_SESSION["erro"] = "Palavra passe atual errada.";
    header("Location: ../../frontend/editar_perfil.php#security-info");
    exit();
}

$sql = "UPDATE utilizadores SET 
    palavra_passe=password('$pass_nova')

    WHERE id='$id'";

$resultado = mysqli_query($con, $sql);


$_SESSION["erro"] = "Informações atualizadas com sucesso!";
header("Location: ../../frontend/editar_perfil.php#security-info");
?>