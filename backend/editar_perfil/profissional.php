<?php
session_start();

if (!isset($_SESSION["nick"])) {
    header("Location: ../../frontend/login.php");
    exit();
}

require "../ligabd.php";

// Obter os dados
$id = trim($_POST["id"]);

$ocupacao = trim($_POST["ocupacao"]);


$sql = "UPDATE perfis SET 
    ocupacao='$ocupacao'
    WHERE id_utilizador='$id'";

$resultado = mysqli_query($con, $sql);

if (!$resultado) {
    $_SESSION["erro"] = "Erro ao atualizar informações.";
    header("Location: ../../frontend/editar_perfil.php#professional-info");
    exit();
}


$_SESSION["erro"] = "Informações atualizadas com sucesso!";
header("Location: ../../frontend/editar_perfil.php#professional-info");
?>