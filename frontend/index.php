<?php
session_start();
require "../backend/ligabd.php";

$currentUserId = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
$currentUserType = isset($_SESSION['id_tipos_utilizador']) ? $_SESSION['id_tipos_utilizador'] : 0;

// Função para transformar URLs em links clicáveis
function makeLinksClickable($text)
{
    $pattern = '/(https?:\/\/[^\s]+)/';
    $linkedText = preg_replace($pattern, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', $text);
    return $linkedText;
}

// Função para contar comentários
function getCommentCount($con, $postId)
{
    $sql = "SELECT COUNT(*) as count FROM comentarios WHERE id_publicacao = $postId";
    $result = mysqli_query($con, $sql);
    $data = mysqli_fetch_assoc($result);
    return $data['count'];
}

// Função para verificar se o post está salvo
function isPostSaved($con, $userId, $postId)
{
    if (!$userId) return false;
    
    $sql = "SELECT * FROM publicacao_salvas
            WHERE utilizador_id = $userId AND publicacao_id = $postId";
    $result = mysqli_query($con, $sql);
    return mysqli_num_rows($result) > 0;
}

// Função para buscar imagens da publicação
function getPostImages($con, $postId)
{
    $sql = "SELECT url, content_warning, tipo FROM publicacao_medias 
            WHERE publicacao_id = $postId
            ORDER BY ordem ASC";
    $result = mysqli_query($con, $sql);
    $medias = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $medias[] = $row;
    }
    return $medias;
}

// Função para buscar dados da poll
function getPollData($con, $postId, $userId = 0)
{
    // Verificar se a publicação tem uma poll
    $sqlPoll = "SELECT p.id, p.pergunta, p.data_expiracao, p.total_votos
                FROM polls p
                WHERE p.publicacao_id = ?";
    
    $stmt = $con->prepare($sqlPoll);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $pollResult = $stmt->get_result();
    
    if ($pollResult->num_rows === 0) {
        return null; // Não é uma poll
    }
    
    $poll = $pollResult->fetch_assoc();
    $pollId = $poll['id'];
    
    // Buscar opções da poll
    $sqlOpcoes = "SELECT id, opcao_texto, votos, ordem
                  FROM poll_opcoes
                  WHERE poll_id = ?
                  ORDER BY ordem ASC";
    
    $stmtOpcoes = $con->prepare($sqlOpcoes);
    if (!$stmtOpcoes) {
        return null;
    }
    
    $stmtOpcoes->bind_param("i", $pollId);
    $stmtOpcoes->execute();
    $opcoesResult = $stmtOpcoes->get_result();
    
    $opcoes = [];
    while ($opcao = $opcoesResult->fetch_assoc()) {
        $percentagem = $poll['total_votos'] > 0 ? 
            round(($opcao['votos'] / $poll['total_votos']) * 100, 1) : 0;
        
        $opcoes[] = [
            'id' => $opcao['id'],
            'texto' => $opcao['opcao_texto'],
            'votos' => $opcao['votos'],
            'percentagem' => $percentagem
        ];
    }
    
    // Verificar se o usuário já votou
    $userVoted = false;
    $userVotedOption = null;
    
    if ($userId > 0) {
        $sqlUserVote = "SELECT opcao_id FROM poll_votos 
                        WHERE poll_id = ? AND utilizador_id = ?";
        $stmtUserVote = $con->prepare($sqlUserVote);
        if ($stmtUserVote) {
            $stmtUserVote->bind_param("ii", $pollId, $userId);
            $stmtUserVote->execute();
            $voteResult = $stmtUserVote->get_result();
            
            if ($voteResult->num_rows > 0) {
                $userVoted = true;
                $voteData = $voteResult->fetch_assoc();
                $userVotedOption = $voteData['opcao_id'];
            }
        }
    }
    
    // Verificar se a poll expirou
    $poll['expirada'] = strtotime($poll['data_expiracao']) < time();
    
    return [
        'poll' => $poll,
        'opcoes' => $opcoes,
        'user_voted' => $userVoted,
        'user_voted_option' => $userVotedOption
    ];
}

// Verificar se o utilizador está autenticado
if (!isset($_SESSION["id"])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION["id"];
$sqlPerfil = "SELECT * FROM perfis WHERE id_utilizador = $userId";
$resultPerfil = mysqli_query($con, $sqlPerfil);
$perfilData = mysqli_fetch_assoc($resultPerfil);
?>

<!DOCTYPE html>
<html lang="pt">

<head>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orange - Rede Social</title>
    <link rel="stylesheet" href="css/style_index.css">
    <link rel="stylesheet" href="css/style_polls.css">
    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/video_player.css">
    <link rel="icon" type="image/x-icon" href="images/favicon/favicon_orange.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .no-comments {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
            font-style: italic;
            border-top: 1px solid var(--border-light);
            margin-top: 15px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .confirmation-modal {
            background-color: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: modalFadeIn 0.3s ease;
        }

        .confirmation-modal h3 {
            margin-top: 0;
            color: var(--text-light);
            font-size: 1.2rem;
        }

        .confirmation-modal p {
            margin: 15px 0 25px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .confirmation-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .confirmation-buttons button {
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .confirm-btn {
            background-color: var(--color-primary);
            color: white;
            border: none;
        }

        .confirm-btn:hover {
            background-color: var(--color-primary-dark);
        }

        .cancel-btn {
            background-color: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-light);
        }

        .cancel-btn:hover {
            background-color: var(--bg-input);
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .post-actions .delete-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            margin-left: auto;
            padding: 5px;
            transition: color 0.2s ease;
        }

        .post-actions .delete-btn:hover {
            color: #ff3333;
        }

        .comment-item .delete-comment-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            margin-left: 10px;
            padding: 2px;
            font-size: 0.8rem;
            transition: color 0.2s ease;
        }

        .comment-item .delete-comment-btn:hover {
            color: #ff3333;
        }
    </style>
</head>

<body>
    <div id="confirmationModal" class="modal-overlay" style="display: none; z-index: 1001;">
        <div class="confirmation-modal">
            <h3>Confirmar ação</h3>
            <p id="confirmationMessage">Tem a certeza que deseja apagar esta publicação?</p>
            <div class="confirmation-buttons">
                <button id="confirmCancel" class="cancel-btn">Cancelar</button>
                <button id="confirmAction" class="confirm-btn">Confirmar</button>
            </div>
        </div>
    </div>

    <?php require "parciais/header.php" ?>

    <!-- Comments Modal -->
    <div id="commentsModal" class="modal-overlay" style="display: none; z-index: 1000;">
        <div class="comment-modal">
            <div class="modal-post" id="modalPostContent"></div>
            <div class="modal-comments">
                <div class="comments-list" id="commentsList"></div>
                <form class="comment-form" id="commentForm">
                    <input type="hidden" id="currentPostId" value="">
                    <input type="text" class="comment-input" id="commentInput" placeholder="Adicione um comentário..."
                        required>
                    <button type="submit" class="comment-submit">Publicar</button>
                </form>
            </div>
            <button class="close-button">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    <!-- Modal para mídia expandida -->
    <div id="imageModal" class="image-modal">
        <div class="image-modal-content">
            <button class="close-image-modal">&times;</button>
            <div id="modalImage" class="modal-image-container"></div>
        </div>
        <div class="image-modal-nav">
            <button id="prevImageBtn" class="modal-nav-btn">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span id="imageCounter" class="image-counter">1 / 1</span>
            <button id="nextImageBtn" class="modal-nav-btn">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <div class="container">
        <?php require("parciais/sidebar.php"); ?>

        <main class="feed">

    <!-- Create Post -->
<div class="create-post">
    <form method="POST" action="../backend/criar_publicacao.php" enctype="multipart/form-data" id="postForm">
        <!-- Inputs de arquivo ocultos -->
        <input type="file" name="media0" hidden id="media0" accept="image/*,video/*">
        <input type="file" name="media1" hidden id="media1" accept="image/*,video/*">
        <input type="file" name="media2" hidden id="media2" accept="image/*,video/*">
        <input type="file" name="media3" hidden id="media3" accept="image/*,video/*">


        <div class="post-input">
            <img src="images/perfil/<?php echo $perfilData['foto_perfil'] ?: 'default-profile.jpg'; ?>" 
                 alt="User" class="profile-pic">
            <textarea name="conteudo" placeholder="No que está a pensar?"></textarea>
        </div>

        <!-- Container de pré-visualização das imagens -->
        <div id="previewGrid" style="display: flex; gap: 5px; margin-top: 10px;">
            <?php for ($i = 0; $i < 4; $i++): ?>
                <div style="position: relative; width: 100px; height: 100px; border-radius: 8px; background: #f0f0f0; overflow: hidden; display: none;" 
                     id="preview-container-<?= $i ?>">
                    <img id="preview-img-<?= $i ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <button type="button" onclick="removeFile(<?= $i ?>)" 
                            style="position: absolute; top: 5px; right: 5px; background: #FF5722; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                        ×
                    </button>
                </div>
            <?php endfor; ?>
        </div>

        <div class="post-actions">
            <button type="button" id="uploadImage">
                <i class="fas fa-image"></i>
            </button>
            <button type="submit" name="publicar" class="publish-btn">Publicar</button>
        </div>
    </form>
</div>

            <!-- Posts -->
            <div class="posts">
                <?php
                $sql = "SELECT p.id_publicacao, p.conteudo, p.data_criacao, p.likes, p.tipo,
                               u.id AS id_utilizador, u.nick, 
                               pr.foto_perfil, pr.ocupacao 
                        FROM publicacoes p
                        JOIN utilizadores u ON p.id_utilizador = u.id
                        LEFT JOIN perfis pr ON u.id = pr.id_utilizador
                        WHERE p.deletado_em = '0000-00-00 00:00:00'
                        ORDER BY p.data_criacao DESC";

                $resultado = mysqli_query($con, $sql);

                if (mysqli_num_rows($resultado) > 0) {
                    while ($linha = mysqli_fetch_assoc($resultado)) {
                        $foto = $linha['foto_perfil'] ?: 'default-profile.jpg';
                        $ocupacao = $linha['ocupacao'] ?: 'Utilizador';
                        $publicacaoId = $linha['id_publicacao'];

                        // Verificar se o usuário logado já deu like
                        $likedClass = '';
                        if ($currentUserId) {
                            $checkSql = "SELECT * FROM publicacao_likes 
                                         WHERE publicacao_id = $publicacaoId AND utilizador_id = $currentUserId";
                            $checkResult = mysqli_query($con, $checkSql);
                            if (mysqli_num_rows($checkResult) > 0) {
                                $likedClass = 'liked';
                            }
                        }

                        // Verificar se está salvo
                        $savedClass = '';
                        if (isPostSaved($con, $currentUserId, $publicacaoId)) {
                            $savedClass = 'saved';
                        }

                        // Buscar imagens da publicação
                        $images = getPostImages($con, $publicacaoId);

                        // Buscar dados da poll se for uma poll
                        $pollData = null;
                        if ($linha['tipo'] === 'poll') {
                            $pollData = getPollData($con, $publicacaoId, $currentUserId);
                        }
                        ?>
                        <article class="post" data-post-id="<?php echo $publicacaoId; ?>">
                            <div class="post-header">
                                <a href="perfil.php?id=<?php echo $linha['id_utilizador']; ?>">
                                    <img src="images/perfil/<?php echo htmlspecialchars($foto); ?>" alt="User"
                                        class="profile-pic">
                                </a>
                                <div class="post-info">
                                    <div>
                                        <a href="perfil.php?id=<?php echo $linha['id_utilizador']; ?>" class="profile-link">
                                            <h3><?php echo htmlspecialchars($linha['nick']); ?></h3>
                                        </a>
                                        <p><?php echo htmlspecialchars($ocupacao); ?></p>
                                    </div>
                                    <span
                                        class="timestamp"><?php echo date('d-m-Y H:i', strtotime($linha['data_criacao'])); ?></span>
                                </div>
                            </div>
                            <div class="post-content">
                                <p><?php echo nl2br(makeLinksClickable($linha['conteudo'])); ?></p>

                                <?php if ($linha['tipo'] === 'poll' && $pollData !== null): ?>
    <!-- Renderizar Poll -->
    <div class="poll-container" data-poll-id="<?php echo htmlspecialchars($pollData['poll']['id']); ?>">
        <div class="poll-question"><?php echo htmlspecialchars($pollData['poll']['pergunta']); ?></div>
        
        <div class="poll-options">
            <?php foreach ($pollData['opcoes'] as $opcao): ?>
                <?php
                $optionClasses = [];
                if ($pollData['user_voted'] || $pollData['poll']['expirada']) {
                    $optionClasses[] = 'disabled';
                }
                if ($pollData['user_voted']) {
                    $optionClasses[] = 'voted';
                }
                if ($pollData['user_voted_option'] == $opcao['id']) {
                    $optionClasses[] = 'user-voted';
                }
                $onclickAttr = '';
                if (!$pollData['user_voted'] && !$pollData['poll']['expirada']) {
                    $onclickAttr = 'onclick="voteInPoll('.(int)$pollData['poll']['id'].','.(int)$opcao['id'].')"';
                }
                ?>
                <div class="poll-option <?php echo implode(' ', $optionClasses); ?>" 
                     data-opcao-id="<?php echo htmlspecialchars($opcao['id']); ?>"
                     <?php echo $onclickAttr ?>>
                    
                    <div class="poll-option-progress" 
                         style="width: <?php echo htmlspecialchars($opcao['percentagem']); ?>%"></div>
                    
                    <div class="poll-option-content">
                        <span class="poll-option-text"><?php echo htmlspecialchars($opcao['texto']); ?></span>
                        <?php if ($pollData['user_voted'] || $pollData['poll']['expirada']): ?>
                            <div class="poll-option-stats">
                                <span class="poll-option-percentage"><?php echo htmlspecialchars($opcao['percentagem']); ?>%</span>
                                <span class="poll-option-votes"><?php echo htmlspecialchars($opcao['votos']); ?> voto<?php echo $opcao['votos'] !== 1 ? 's' : ''; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="poll-meta">
            <span class="poll-total-votes"><?php echo htmlspecialchars($pollData['poll']['total_votos']); ?> voto<?php echo $pollData['poll']['total_votos'] !== 1 ? 's' : ''; ?></span>
            <span class="poll-time-left <?php echo $pollData['poll']['expirada'] ? 'poll-expired' : ''; ?>">
                <i class="fas fa-clock"></i>
                <?php echo $pollData['poll']['expirada'] ? 'Poll encerrada' : 'Ativa'; ?>
            </span>
        </div>
    </div>
<?php endif; ?>

                                <?php if (!empty($images)): ?>
                                    <div class="post-images">
                                        <?php
        $imageCount = count($images);
        $gridClass = '';
        if ($imageCount == 1)
            $gridClass = 'single';
        elseif ($imageCount == 2)
            $gridClass = 'double';
        elseif ($imageCount == 3)
            $gridClass = 'triple';
        else
            $gridClass = 'quad';
        ?>
                                        <div class="images-grid <?php echo $gridClass; ?>">
            <?php foreach ($images as $i => $media): ?>
                <?php if ($i < 4): ?>
                    <div class="media-item"
                        onclick="openMediaModal(<?php echo $publicacaoId; ?>, <?php echo $i; ?>)">
                        <?php if ($media['tipo'] === 'video'): ?>
                            <div class="video-container">
                                <video muted preload="metadata" playsInline>
                                    <source
                                        src="images/publicacoes/<?php echo htmlspecialchars($media['url']); ?>"
                                        type="video/mp4">
                                    Seu navegador não suporta vídeos.
                                </video>
                            </div>
                        <?php else: ?>
                            <img src="images/publicacoes/<?php echo htmlspecialchars($media['url']); ?>"
                                alt="Imagem da publicação" class="post-media">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
                            </div>
                            <div class="post-actions">
                                <button class="like-btn <?php echo $likedClass; ?>"
                                    data-publicacao-id="<?php echo $publicacaoId; ?>">
                                    <i class="fas fa-thumbs-up"></i>
                                    <span class="like-count"><?php echo $linha['likes']; ?></span>
                                </button>
                                <button class="comment-btn" onclick="openCommentsModal(<?php echo $linha['id_publicacao']; ?>)">
                                    <i class="fas fa-comment"></i>
                                    <span
                                        class="comment-count"><?php echo getCommentCount($con, $linha['id_publicacao']); ?></span>
                                </button>
                                <button class="save-btn <?php echo $savedClass; ?>"
                                    data-publicacao-id="<?php echo $publicacaoId; ?>">
                                    <i class="fas fa-bookmark"></i>
                                </button>
                                <?php if ($currentUserId && ($currentUserId == $linha['id_utilizador'] || $currentUserType == 2)): ?>
                                    <button class="delete-btn" onclick="deletePost(<?php echo $publicacaoId; ?>, this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php
                    }
                } else {
                    echo "<p class='no-posts'>Ainda não há publicações para mostrar.</p>";
                }
                ?>
            </div>

            <div id="toast" class="toast">
                <div class="toast-icon">
                    <i class="fas fa-bookmark"></i>
                </div>
                <div class="toast-content">
                    <p id="toast-message">Mensagem aqui</p>
                </div>
            </div>
        </main>

        <!-- Sugestões de Utilizadores -->
        <?php require("parciais/suggestions.php"); ?>
    </div>

    <?php require "parciais/footer.php" ?>

    <!-- Include Video Player JavaScript -->
    <script src="js/video-player.js"></script>
    <script src="js/polls.js"></script>

    <script>
        // Variáveis globais para controle da confirmação
        let pendingDelete = {
            postId: null,
            element: null,
            type: null // 'post' ou 'comment'
        };

        // Função para votar em poll
        async function voteInPoll(pollId, opcaoId) {
    try {
        const optionElement = document.querySelector(`[data-opcao-id="${opcaoId}"]`);
        if (optionElement) {
            optionElement.classList.add('voting');
        }

        const formData = new FormData();
        formData.append('poll_id', pollId);
        formData.append('opcao_id', opcaoId);

        const response = await fetch('../backend/votar_poll.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            // Atualizar todas as opções da poll
            const pollContainer = document.querySelector(`[data-poll-id="${pollId}"]`);
            if (pollContainer) {
                // Desabilitar todas as opções
                const options = pollContainer.querySelectorAll('.poll-option');
                options.forEach(option => {
                    option.classList.add('disabled', 'voted');
                    option.style.pointerEvents = 'none';
                    
                    // Destacar a opção que o usuário votou
                    if (option.dataset.opcaoId == opcaoId) {
                        option.classList.add('user-voted');
                    }
                });

                // Atualizar estatísticas para cada opção
                data.opcoes.forEach(opcao => {
                    const optionElement = pollContainer.querySelector(`[data-opcao-id="${opcao.id}"]`);
                    if (optionElement) {
                        // Atualizar barra de progresso
                        const progressBar = optionElement.querySelector('.poll-option-progress');
                        if (progressBar) {
                            progressBar.style.width = `${opcao.percentagem}%`;
                        }

                        // Atualizar estatísticas
                        const percentage = optionElement.querySelector('.poll-option-percentage');
                        const votes = optionElement.querySelector('.poll-option-votes');
                        
                        if (percentage) {
                            percentage.textContent = `${opcao.percentagem}%`;
                        }
                        
                        if (votes) {
                            votes.textContent = `${opcao.votos} voto${opcao.votos !== 1 ? 's' : ''}`;
                        }

                        // Mostrar estatísticas se ainda não estiverem visíveis
                        const statsContainer = optionElement.querySelector('.poll-option-stats');
                        if (!statsContainer) {
                            const optionContent = optionElement.querySelector('.poll-option-content');
                            if (optionContent) {
                                const statsHTML = `
                                    <div class="poll-option-stats">
                                        <span class="poll-option-percentage">${opcao.percentagem}%</span>
                                        <span class="poll-option-votes">${opcao.votos} voto${opcao.votos !== 1 ? 's' : ''}</span>
                                    </div>
                                `;
                                optionContent.insertAdjacentHTML('beforeend', statsHTML);
                            }
                        }
                    }
                });

                // Atualizar total de votos
                const totalVotesElement = pollContainer.querySelector('.poll-total-votes');
                if (totalVotesElement) {
                    totalVotesElement.textContent = `${data.total_votos} voto${data.total_votos !== 1 ? 's' : ''}`;
                }
            }
            
            showToast('Voto registado com sucesso!');
        } else {
            showToast(data.message || 'Erro ao votar', 'error');
        }
    } catch (error) {
        console.error('Erro ao votar:', error);
        showToast('Erro de conexão', 'error');
    } finally {
        const optionElement = document.querySelector(`[data-opcao-id="${opcaoId}"]`);
        if (optionElement) {
            optionElement.classList.remove('voting');
        }
    }
}

        // Função para atualizar display da poll após voto
        function updatePollDisplay(pollId, data) {
            const pollContainer = document.querySelector(`[data-poll-id="${pollId}"]`);
            if (!pollContainer) return;

            // Atualizar opções
            if (data.opcoes && Array.isArray(data.opcoes)) {
                data.opcoes.forEach(opcao => {
                    const optionElement = pollContainer.querySelector(`[data-opcao-id="${opcao.id}"]`);
                    if (optionElement) {
                        // Atualizar barra de progresso
                        const progressBar = optionElement.querySelector('.poll-option-progress');
                        if (progressBar) {
                            progressBar.style.width = `${opcao.percentagem}%`;
                        }

                        // Atualizar estatísticas
                        const percentage = optionElement.querySelector('.poll-option-percentage');
                        const votes = optionElement.querySelector('.poll-option-votes');
                        
                        if (percentage) {
                            percentage.textContent = `${opcao.percentagem}%`;
                        }
                        
                        if (votes) {
                            votes.textContent = `${opcao.votos} voto${opcao.votos !== 1 ? 's' : ''}`;
                        }

                        // Marcar como votada e desabilitar
                        optionElement.classList.add('voted', 'disabled');
                        optionElement.style.pointerEvents = 'none';

                        // Destacar opção do usuário
                        if (opcao.user_voted) {
                            optionElement.classList.add('user-voted');
                        }
                    }
                });
            }

            // Atualizar total de votos
            const totalVotesElement = pollContainer.querySelector('.poll-total-votes');
            if (totalVotesElement && data.total_votos !== undefined) {
                totalVotesElement.textContent = `${data.total_votos} voto${data.total_votos !== 1 ? 's' : ''}`;
            }
        }

        // Função para apagar publicação com modal de confirmação
        function deletePost(postId, element) {
            pendingDelete = {
                postId,
                element,
                type: 'post'
            };

            document.getElementById('confirmationMessage').textContent = 'Tem certeza que deseja apagar esta publicação?';
            showConfirmation(function (confirmed) {
                if (confirmed) {
                    fetch('../backend/delete_post.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `id_publicacao=${postId}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                element.closest('.post').style.opacity = '0';
                                element.closest('.post').style.transform = 'translateX(-100px)';
                                setTimeout(() => {
                                    element.closest('.post').remove();
                                }, 300);
                                showToast('Publicação apagada com sucesso');
                            } else {
                                showToast('Erro ao apagar publicação');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('Erro ao apagar publicação');
                        });
                }
            });
        }

        // Função para apagar comentário com modal de confirmação
        function deleteComment(commentId, element) {
            pendingDelete = {
                commentId,
                element,
                type: 'comment'
            };

            document.getElementById('confirmationMessage').textContent = 'Tem a certeza que deseja apagar este comentário?';
            showConfirmation(function (confirmed) {
                if (confirmed) {
                    fetch('../backend/delete_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `id_comentario=${commentId}`
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                element.closest('.comment-item').remove();
                                showToast('Comentário apagado com sucesso');

                                const commentCount = document.querySelector(`.comment-btn[onclick*="${currentPostId}"] .comment-count`);
                                if (commentCount) {
                                    commentCount.textContent = parseInt(commentCount.textContent) - 1;
                                }
                            } else {
                                showToast('Erro ao apagar comentário');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('Erro ao apagar comentário');
                        });
                }
            });
        }

        // Função para mostrar o modal de confirmação
        function showConfirmation(callback) {
            const modal = document.getElementById('confirmationModal');
            const confirmBtn = document.getElementById('confirmAction');
            const cancelBtn = document.getElementById('confirmCancel');

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';

            confirmBtn.onclick = null;
            cancelBtn.onclick = null;

            confirmBtn.onclick = function () {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                callback(true);
            };

            cancelBtn.onclick = function () {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                callback(false);
            };
        }

        // Inicialização
        document.addEventListener('DOMContentLoaded', function () {
            initializeVideoThumbnails();
            
            // Garante que o modal de comentários está fechado
            document.getElementById('commentsModal').style.display = 'none';
        });

        // Sistema de visualização de mídia
let currentImageModal = {
    postId: null,
    currentIndex: 0,
    medias: []
};

function openMediaModal(postId, mediaIndex = 0) {
    const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
    if (!postElement) return;

    // Buscar todas as mídias da publicação (máximo 4)
    const medias = [];
    const mediaElements = postElement.querySelectorAll('.media-item');
    
    // Coletar todas as mídias (até 4)
    mediaElements.forEach(item => {
        if (medias.length >= 4) return;
        
        const videoElement = item.querySelector('video');
        const imgElement = item.querySelector('img');

        if (videoElement) {
            const source = videoElement.querySelector('source');
            medias.push({
                url: source ? source.src.split('/').pop() : '',
                tipo: 'video'
            });
        } else if (imgElement) {
            medias.push({
                url: imgElement.src.split('/').pop(),
                tipo: 'imagem'
            });
        }
    });

    if (medias.length === 0) return;

    currentImageModal = {
        postId,
        currentIndex: mediaIndex,
        medias
    };

    showMediaInModal();
    document.getElementById('imageModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

        function showMediaInModal() {
            const modal = document.getElementById('imageModal');
            const modalContent = document.getElementById('modalImage');
            const imageCounter = document.getElementById('imageCounter');
            const prevBtn = document.getElementById('prevImageBtn');
            const nextBtn = document.getElementById('nextImageBtn');

            modalContent.innerHTML = '';

            const currentMedia = currentImageModal.medias[currentImageModal.currentIndex];

            if (currentMedia.tipo === 'video') {
                const videoContainer = document.createElement('div');
                videoContainer.className = 'modal-video-container';

                const video = document.createElement('video');
                video.autoplay = false;
                video.controls = false;
                video.className = 'modal-media';
                video.muted = false;
                video.preload = 'metadata';
                video.playsInline = true;

                const source = document.createElement('source');
                source.src = `images/publicacoes/${currentMedia.url}`;
                source.type = 'video/mp4';

                video.appendChild(source);
                video.appendChild(document.createTextNode('Seu navegador não suporta vídeos.'));
                videoContainer.appendChild(video);
                modalContent.appendChild(videoContainer);

                setTimeout(() => {
                    new ModernVideoPlayer(video);
                }, 100);
            } else {
                const img = document.createElement('img');
                img.src = `images/publicacoes/${currentMedia.url}`;
                img.className = 'modal-media';
                img.alt = 'Imagem expandida';
                modalContent.appendChild(img);
            }

            imageCounter.textContent = `${currentImageModal.currentIndex + 1} / ${currentImageModal.medias.length}`;

            prevBtn.disabled = currentImageModal.currentIndex === 0;
            nextBtn.disabled = currentImageModal.currentIndex === currentImageModal.medias.length - 1;
        }

        function closeImageModal() {
            const modalContent = document.getElementById('modalImage');
            const videos = modalContent.getElementsByTagName('video');
            for (let video of videos) {
                video.pause();
            }

            document.getElementById('imageModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function navigateImage(direction) {
            if (direction === 'prev' && currentImageModal.currentIndex > 0) {
                currentImageModal.currentIndex--;
            } else if (direction === 'next' && currentImageModal.currentIndex < currentImageModal.medias.length - 1) {
                currentImageModal.currentIndex++;
            }
            showMediaInModal();
        }

        // Event listeners para o modal
        document.querySelector('.close-image-modal').addEventListener('click', closeImageModal);
        document.getElementById('prevImageBtn').addEventListener('click', () => navigateImage('prev'));
        document.getElementById('nextImageBtn').addEventListener('click', () => navigateImage('next'));

        document.getElementById('imageModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

        document.addEventListener('keydown', function (e) {
            const modal = document.getElementById('imageModal');
            if (modal.style.display === 'flex') {
                if (e.key === 'Escape') {
                    closeImageModal();
                } else if (e.key === 'ArrowLeft') {
                    navigateImage('prev');
                } else if (e.key === 'ArrowRight') {
                    navigateImage('next');
                }
            }
        });

        // Like functionality
        document.querySelectorAll('.like-btn').forEach(button => {
            button.addEventListener('click', function () {
                const publicacaoId = this.getAttribute('data-publicacao-id');
                const likeCount = this.querySelector('.like-count');

                fetch('../backend/like.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_publicacao=${publicacaoId}`
                })
                    .then(response => response.text())
                    .then(data => {
                        if (data === 'liked') {
                            this.classList.add('liked');
                            likeCount.textContent = parseInt(likeCount.textContent) + 1;
                        } else if (data === 'unliked') {
                            this.classList.remove('liked');
                            likeCount.textContent = parseInt(likeCount.textContent) - 1;
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });
        });

        // Save functionality
        document.querySelectorAll('.save-btn').forEach(button => {
            button.addEventListener('click', function () {
                const publicacaoId = this.getAttribute('data-publicacao-id');

                fetch('../backend/save_post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id_publicacao=${publicacaoId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.action === 'saved') {
                                this.classList.add('saved');
                                showToast('Adicionado aos itens salvos');
                            } else {
                                this.classList.remove('saved');
                                showToast('Removido dos itens salvos');
                            }
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });
        });

        // Modal de comentários
        const modal = document.getElementById('commentsModal');
        const closeButton = modal.querySelector('.close-button');

        function closeModal() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        closeButton.addEventListener('click', closeModal);

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        let currentPostId = null;

        function openCommentsModal(postId) {
            currentPostId = postId;

            const postElement = document.querySelector(`.post[data-post-id="${postId}"]`);
            if (postElement) {
                const postClone = postElement.cloneNode(true);
                const actions = postClone.querySelector('.post-actions');
                if (actions) actions.remove();

                document.getElementById('modalPostContent').innerHTML = '';
                document.getElementById('modalPostContent').appendChild(postClone);

                loadComments(postId);
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        // Envio de comentário
        document.getElementById('commentForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const commentInput = document.getElementById('commentInput');
            const content = commentInput.value.trim();

            if (content && currentPostId) {
                const formData = new FormData();
                formData.append('post_id', currentPostId);
                formData.append('content', content);

                fetch('../backend/add_comment.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            commentInput.value = '';
                            loadComments(currentPostId);

                            const commentCount = document.querySelector(`.comment-btn[onclick*="${currentPostId}"] .comment-count`);
                            if (commentCount) {
                                commentCount.textContent = parseInt(commentCount.textContent) + 1;
                            }
                        }
                    });
            }
        });

        function loadComments(postId) {
            fetch(`../backend/get_comments.php?post_id=${postId}`)
                .then(response => response.json())
                .then(comments => {
                    const commentsList = document.getElementById('commentsList');
                    commentsList.innerHTML = '';

                    if (comments.length === 0) {
                        const noCommentsMsg = document.createElement('div');
                        noCommentsMsg.className = 'no-comments';
                        noCommentsMsg.textContent = 'Ainda sem comentários. Seja o primeiro a comentar!';
                        commentsList.appendChild(noCommentsMsg);
                        return;
                    }

                    comments.forEach(comment => {
                        const dataComentario = new Date(comment.data);
                        const dataComentarioFormatada = `${dataComentario.getDate().toString().padStart(2, '0')}-${(dataComentario.getMonth() + 1).toString().padStart(2, '0')}-${dataComentario.getFullYear()} ${dataComentario.getHours().toString().padStart(2, '0')}:${dataComentario.getMinutes().toString().padStart(2, '0')}`;

                        const commentItem = document.createElement('div');
                        commentItem.className = 'comment-item';
                        commentItem.innerHTML = `
                    <a href="perfil.php?id=${comment.utilizador_id}">
                        <img src="images/perfil/${comment.foto_perfil || 'default-profile.jpg'}" alt="User" class="comment-avatar">
                    </a>
                    <div class="comment-content">
                        <div class="comment-header">
                            <div class="comment-user-info">
                                <a href="perfil.php?id=${comment.utilizador_id}" class="profile-link">
                                    <span class="comment-username">${comment.nick}</span>
                                </a>
                                <span class="comment-time">${dataComentarioFormatada}</span>
                            </div>
                            ${(<?php echo $_SESSION['id']; ?> == comment.utilizador_id || <?php echo $_SESSION['id_tipos_utilizador']; ?> == 2) ?
                                `<button class="delete-comment-btn" onclick="deleteComment(${comment.id}, this)">
                                    <i class="fas fa-trash"></i>
                                </button>` : ''}
                        </div>
                        <p class="comment-text">${comment.conteudo}</p>
                    </div>
                `;
                        commentsList.appendChild(commentItem);
                    });
                });
        }

        let currentFileIndex = 0;

// Evento para o botão de upload de imagem
document.getElementById('uploadImage').addEventListener('click', function() {
    // Encontrar o próximo índice disponível
    for (let i = 0; i < 4; i++) {
        const container = document.getElementById(`preview-container-${i}`);
        if (container.style.display === 'none') {
            document.getElementById(`media${i}`).click();
            currentFileIndex = i;
            break;
        }
    }
});

        // Função para mostrar toast
        function showToast(message) {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toast-message');
            toastMessage.textContent = message;

            toast.style.display = 'flex';
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 300);
            }, 3000);
        }

        // Media preview functionality
        let selectedFiles = [];

        function previewMedia(input) {
    const previewContainer = document.getElementById('mediaPreview');
    previewContainer.innerHTML = '';
    previewContainer.style.display = 'block';
    
    // Limpa arquivos anteriores
    selectedFiles = [];
    
    // Processa cada arquivo selecionado
    for (let i = 0; i < input.files.length; i++) {
        const file = input.files[i];
        selectedFiles.push(file);
        
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const mediaDiv = document.createElement('div');
            mediaDiv.style.cssText = 'position: relative; display: inline-block; margin: 5px;';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 8px;';
                mediaDiv.appendChild(img);
            } else if (file.type.startsWith('video/')) {
                const video = document.createElement('video');
                video.src = e.target.result;
                video.style.cssText = 'width: 100px; height: 100px; object-fit: cover; border-radius: 8px;';
                video.muted = true;
                mediaDiv.appendChild(video);
            }
            
            const removeBtn = document.createElement('button');
            removeBtn.innerHTML = '×';
            removeBtn.style.cssText = 'position: absolute; top: -5px; right: -5px; background: red; color: white; border: none; border-radius: 50%; width: 20px; height: 20px; cursor: pointer;';
            removeBtn.onclick = () => removeMedia(i);
            mediaDiv.appendChild(removeBtn);
            
            previewContainer.appendChild(mediaDiv);
        };
        
        reader.readAsDataURL(file);
    }
}

// Função para pré-visualizar o arquivo selecionado
function previewFile(index, input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        const container = document.getElementById(`preview-container-${index}`);
        const img = document.getElementById(`preview-img-${index}`);
        
        reader.onload = function(e) {
            if (file.type.startsWith('image/')) {
                img.src = e.target.result;
                container.style.display = 'block';
            } else if (file.type.startsWith('video/')) {
                // Para vídeos, mostramos um thumbnail estático com ícone de play
                img.src = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 24 24" fill="%23cccccc"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>';
                container.style.display = 'block';
                
                // Adicionar ícone de play
                const playIcon = document.createElement('div');
                playIcon.innerHTML = '<i class="fas fa-play" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 20px; background: rgba(0,0,0,0.5); border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;"></i>';
                playIcon.style.position = 'absolute';
                playIcon.style.top = '50%';
                playIcon.style.left = '50%';
                playIcon.style.transform = 'translate(-50%, -50%)';
                playIcon.style.zIndex = '10';
                container.appendChild(playIcon);
            }
        };
        
        reader.readAsDataURL(file);
    }
}


     // Função para remover um arquivo
function removeFile(index) {
    // Resetar o input de arquivo
    document.getElementById(`media${index}`).value = '';
    
    // Ocultar o preview
    const container = document.getElementById(`preview-container-${index}`);
    container.style.display = 'none';
    
    // Remover ícone de play se existir
    const playIcon = container.querySelector('.fa-play');
    if (playIcon) {
        playIcon.parentElement.remove();
    }
}

// Adicionar event listeners para todos os inputs de arquivo
for (let i = 0; i < 5; i++) {
    document.getElementById(`media${i}`).addEventListener('change', function() {
        previewFile(i, this);
    });
}
    
    // Atualiza o input de arquivo
    const mediaInput = document.getElementById('mediaInput');
    mediaInput.files = dataTransfer.files;
    
    // Atualiza o array selectedFiles
    selectedFiles = Array.from(mediaInput.files);
    
    // Atualiza a pré-visualização
    previewMedia(mediaInput);

    </script>
</body>

</html>