<?php

$MAX_MEDIA = 5;
// Adicionar extensões de vídeo permitidas
$EXT_PERMITIDAS = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mov'];
// Tamanho máximo diferente para imagens e vídeos
$MAX_SIZE_IMAGEM = 5 * 1024 * 1024; // 5MB
$MAX_SIZE_VIDEO = 50 * 1024 * 1024; // 50MB

session_start();
require 'ligabd.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publicar'])) {
    // Verificar se o usuário está logado
    if (!isset($_SESSION['id'])) {
        $_SESSION['erro'] = "Por favor, faça login para publicar.";
        header('Location: ../frontend/login.php');
        exit();
    }

    // Sanitizar e validar entrada de texto
    $conteudo = trim(htmlspecialchars($_POST['conteudo']));

    try {
        // Inserir no banco de dados
        $stmt = $con->prepare("
            INSERT INTO publicacoes 
            (id_utilizador, conteudo, data_criacao) 
            VALUES (?, ?, NOW())
        ");

        $stmt->bind_param("is", $_SESSION['id'], $conteudo);

        if ($stmt->execute()) {
            $publicacaoId = $stmt->insert_id;

            for ($i = 0; $i < $MAX_MEDIA; $i++) {
                $media = $_FILES["media" . $i];

                if (empty($media['name'])) {
                    continue;
                }

                $ext = strtolower(pathinfo(basename($media["name"]), PATHINFO_EXTENSION));

                if (!in_array($ext, $EXT_PERMITIDAS)) {
                    die("Erro: Apenas imagens (JPG, JPEG, PNG, GIF) e vídeos (MP4, WEBM, MOV) são permitidos.");
                }

                // Verificar tamanho máximo baseado no tipo de arquivo
                $max_size = in_array($ext, ['mp4', 'webm', 'mov']) ? $MAX_SIZE_VIDEO : $MAX_SIZE_IMAGEM;
                
                if ($media['size'] > $max_size) {
                    $tipo = in_array($ext, ['mp4', 'webm', 'mov']) ? 'vídeo' : 'imagem';
                    $_SESSION['erro'] = "O arquivo de $tipo é muito grande. Tamanho máximo: " . 
                                       ($max_size / (1024 * 1024)) . "MB";
                    header('Location: ../frontend/index.php');
                    exit();
                }

                $novo_nome = uniqid('pub_' . time() . '_' . $i . '_') . '.' . $ext;
                $destino = "../frontend/images/publicacoes/" . $novo_nome;

                if (move_uploaded_file($media['tmp_name'], $destino)) {
                    $sql_pub_medias = "INSERT INTO publicacao_medias
                        (publicacao_id, url, content_warning, ordem, tipo) VALUES
                        (?, ?, 'none', ?, ?)";

                    $stmt_media = $con->prepare($sql_pub_medias);
                    $tipo_media = in_array($ext, ['mp4', 'webm', 'mov']) ? 'video' : 'imagem';
                    $stmt_media->bind_param("isis", $publicacaoId, $novo_nome, $i, $tipo_media);
                    $stmt_media->execute();
                }
            }

            $_SESSION['sucesso'] = "Publicação criada com sucesso!";
            header('Location: ../frontend/index.php');
            exit();
        } else {
            throw new Exception("Erro ao publicar no banco de dados.");
        }

    } catch (Exception $e) {
        $_SESSION['erro'] = "Erro: " . $e->getMessage();
        header('Location: ../frontend/index.php');
        exit();
    } finally {
        if (isset($stmt))
            $stmt->close();
    }
} else {
    // Se alguém tentar acessar diretamente o arquivo
    header('Location: ../frontend/index.php');
    exit();
}
?>