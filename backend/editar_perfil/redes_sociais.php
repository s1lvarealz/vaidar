<?php
session_start();

if (!isset($_SESSION["nick"])) {
    header("Location: ../../frontend/login.php");
    exit();
}

require "../ligabd.php";

// Obter os dados
$id = trim($_POST["id"]);

$x = trim($_POST["x"]);
$linkedin = trim($_POST["linkedin"]);
$github = trim(string: $_POST["github"]);


$sql = "UPDATE perfis SET 
    x='$x',
    linkedin='$linkedin',
    github='$github'

    WHERE id_utilizador='$id'";

$resultado = mysqli_query($con, $sql);

if (!$resultado) {
    $_SESSION["erro"] = "Erro ao atualizar informações.";
    header("Location: ../../frontend/editar_perfil.php#social-info");
    exit();
}


$_SESSION["erro"] = "Informações atualizadas com sucesso!";
header("Location: ../../frontend/editar_perfil.php#social-info");
?>