<?php
session_start();

if (!isset($_POST["submitBtn"]) || isset($_SESSION["email"])) {
    header("Location: ../");
    exit();
}

$nick = $_POST["nick"];
$name = $_POST["name"];
$email = $_POST["email"];
$date = $_POST["date"];
$password = $_POST["password"];

// Validações adicionais no backend
$errors = [];

// Validar nome
if (empty(trim($name)) || strlen(trim($name)) < 3) {
    $errors[] = "O nome deve ter pelo menos 3 caracteres.";
}

if (preg_match('/\d/', $name)) {
    $errors[] = "O nome não pode conter números.";
}

// Validar nick
if (empty(trim($nick)) || strlen(trim($nick)) < 3 || strlen(trim($nick)) > 16) {
    $errors[] = "O nome de utilizador deve ter entre 3 e 16 caracteres.";
}

if (!preg_match('/^[a-zA-Z0-9._]+$/', $nick)) {
    $errors[] = "O nome de utilizador só pode conter letras, números, pontos e sublinhados.";
}

if (preg_match('/\s/', $nick)) {
    $errors[] = "O nome de utilizador não pode conter espaços.";
}

// Validar email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Email inválido.";
}

// Validar password
if (strlen($password) < 6) {
    $errors[] = "A palavra-passe deve ter pelo menos 6 caracteres.";
}

// Validar idade
if (!empty($date)) {
    $birthDate = new DateTime($date);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;

    if ($age < 13) {
        $errors[] = "Deve ter pelo menos 13 anos para se registar.";
    }

    if ($age > 115) {
        $errors[] = "Por favor, introduza uma data de nascimento válida.";
    }
}

// Se há erros de validação, retornar
if (!empty($errors)) {
    $_SESSION["erro"] = implode(" ", $errors);
    header("Location: ../frontend/registar.php");
    exit();
}

require "ligabd.php";

// Verificar se email já existe
$sql_check_email = "SELECT * FROM utilizadores WHERE email='$email'";
$sql_check_nick = "SELECT * FROM utilizadores WHERE nick='$nick'";

$check_email = mysqli_query($con, $sql_check_email);
$check_nick = mysqli_query($con, $sql_check_nick);

if (mysqli_num_rows($check_email) > 0) {
    $_SESSION["erro"] = "Email já registado.";
    header("Location: ../frontend/registar.php");
    exit();
}

if (mysqli_num_rows($check_nick) > 0) {
    $_SESSION["erro"] = "Nome de utilizador já registado.";
    header("Location: ../frontend/registar.php");
    exit();
}

// Sanitizar dados antes de inserir
$name = mysqli_real_escape_string($con, trim($name));
$nick = mysqli_real_escape_string($con, trim($nick));
$email = mysqli_real_escape_string($con, trim($email));
$password = mysqli_real_escape_string($con, $password);

// Inserir utilizador
$sql_inserir = "INSERT INTO utilizadores VALUES
     (null, '$name', '$email', password('$password'), '$date', '$nick', '0', null)";

$resultado = mysqli_query($con, $sql_inserir);

if (!$resultado) {
    $_SESSION["erro"] = "Não foi possivel inserir o utilizador. Erro: " . mysqli_error($con);
    header("Location: ../frontend/registar.php");
    exit();
}

// Buscar o utilizador recém-criado para obter o ID
$sqlUser = "SELECT * FROM utilizadores WHERE email = '$email'";
$resultUser = mysqli_query($con, $sqlUser);
$userData = mysqli_fetch_assoc($resultUser);

if (!$userData) {
    $_SESSION["erro"] = "Erro ao criar conta.";
    header("Location: ../frontend/registar.php");
    exit();
}

$id = (int)$userData["id"];
$foto_perfil = "default-profile.jpg";
$biografia = "";
$data_criacao = $userData["data_criacao"];
$foto_capa = "default-capa.png";
$x = "";
$linkedin = "";
$github = "";
$ocupacao = "";
$pais = "";
$cidade = "";

// Criar perfil
$sql_inserir_perfil = "INSERT INTO perfis VALUES
     (null, '$id', '$biografia', '$foto_perfil', '$data_criacao', '$foto_capa', '$x', '$linkedin', '$github', '$ocupacao', '$pais', '$cidade')";

$resultado_perfil = mysqli_query($con, $sql_inserir_perfil);

if (!$resultado_perfil) {
    $_SESSION["erro"] = "Erro ao criar perfil. Erro: " . mysqli_error($con);
    header("Location: ../frontend/registar.php");
    exit();
}

// FAZER LOGIN AUTOMÁTICO
// Definir todas as variáveis de sessão necessárias
$_SESSION["id"] = $userData["id"];
$_SESSION["nome_completo"] = $userData["nome_completo"];
$_SESSION["email"] = $userData["email"];
$_SESSION["data_nascimento"] = $userData["data_nascimento"];
$_SESSION["nick"] = $userData["nick"];
$_SESSION["id_tipos_utilizador"] = $userData["id_tipos_utilizador"];
$_SESSION["data_criacao"] = $userData["data_criacao"];

// Definir mensagem de sucesso
$_SESSION["sucesso"] = "Conta criada com sucesso! Bem-vindo à Orange!";

// Redirecionar para a página principal já logado
header("Location: ../frontend/index.php");
exit();
?>