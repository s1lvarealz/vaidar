<?php
session_start();

if (!isset($_SESSION["nick"])) {
    header("Location: ../../frontend/login.php");
    exit();
}

require "../ligabd.php";

// Obter os dados
$id = trim($_POST["id"]);

$nick = trim($_POST["nick"]);
$biografia = trim(string: $_POST["biografia"]);
$email = trim($_POST["email"]);
$data = trim($_POST["data"]);
$pais = trim($_POST["país"]);
$cidade = trim($_POST["cidade"]);

// Validações
if (!preg_match("/^[a-zA-Z0-9._]{3,16}$/", $nick)) {
    $_SESSION["erro"] = "O nome de utilizador deve ter entre 3 e 16 caracteres e só pode conter letras, números, pontos e sublinhados.";
    header("Location: ../../frontend/editar_perfil.php#profile-info");
    exit();
}

function isValidBirthDate($date) {
    // Verifica se o formato está correto (YYYY-MM-DD)
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    // Extrai ano, mês e dia
    list($year, $month, $day) = explode('-', $date);

    // Verifica se a data é real (exclui 30 de fevereiro, por exemplo)
    if (!checkdate((int)$month, (int)$day, (int)$year)) {
        return false;
    }

    // Calcula a idade mínima e máxima
    $minAge = 13;
    $maxAge = 120;
    $currentYear = (int)date('Y');
    $birthYear = (int)$year;

    $age = $currentYear - $birthYear;

    // Ajusta a idade se o aniversário ainda não ocorreu este ano
    $today = new DateTime();
    $birthDate = new DateTime($date);
    if ($birthDate > $today->modify("-$age years")) {
        $age--;
    }

    // Verifica se a idade está dentro dos limites
    return $age >= $minAge && $age <= $maxAge;
}

if (!isValidBirthDate($data)){

    $_SESSION["erro"] = "A data é inválida.";
    header("Location: ../../frontend/editar_perfil.php#profile-info");
    exit();
}



if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION["erro"] = "O email é inválido.";
    header("Location: ../../frontend/editar_perfil.php#profile-info");
    exit();
}

$sql = "UPDATE perfis SET 
    biografia='$biografia',
    pais='$pais',
    cidade='$cidade'
    WHERE id_utilizador='$id'";

$resultado = mysqli_query($con, $sql);

if(!$resultado){
    $_SESSION["erro"] = "Erro ao atualizar informações.";
    header("Location: ../../frontend/editar_perfil.php#profile-info");
    exit();
}

$sql = "UPDATE utilizadores SET
    nick='$nick',
    email='$email',
    data_nascimento='$data'
    WHERE id='$id'";
    
$resultado = mysqli_query($con, $sql);

if(!$resultado){
    $_SESSION["erro"] = "Erro ao atualizar informações.";
    header("Location: ../../frontend/editar_perfil.php#profile-info");
    exit();
}

$_SESSION["erro"] = "Informações atualizadas com sucesso!";
header("Location: ../../frontend/editar_perfil.php#profile-info");
?>