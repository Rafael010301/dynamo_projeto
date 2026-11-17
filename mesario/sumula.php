<?php
/**
 * mesario/sumula.php
 * Súmula Eletrônica Interativa em Tempo Real
 * Implementa RN08 (minutos 1-40) e RN09 (desqualificação automática na 3ª exclusão de 2min)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../includes/protege.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/PartidaModel.php';
require_once __DIR__ . '/../app/controllers/PartidaController.php';

$partidaId = (int)($_GET['id'] ?? 0);
if ($partidaId <= 0) {
    AuthMiddleware::setFlash('erro', 'Partida não especificada.');
    header("Location: " . AuthMiddleware::getBaseUrl() . "/mesario/index.php");
    exit;
}

$db = Database::getConnection();
$partidaModel = new PartidaModel($db);
$partidaController = new PartidaController($partidaModel, null, $db);

$partida = $partidaModel->buscarPorId($partidaId);
if (!$partida) {
    AuthMiddleware::setFlash('erro', 'Partida não encontrada no sistema.');
    header("Location: " . AuthMiddleware::getBaseUrl() . "/mesario/index.php");
    exit;
}

$tituloPagina = 'Súmula: ' . $partida['equipe_casa_nome'] . ' x ' . $partida['equipe_visitante_nome'];
require_once __DIR__ . '/../includes/header.php';

// Atletas de cada equipe
$jogadoresCasa = $partidaController->listarJogadoresEquipe((int)$partida['equipe_casa_id']);
$jogadoresVis  = $partidaController->listarJogadoresEquipe((int)$partida['equipe_visitante_id']);

// Eventos da súmula
$eventos = $partidaModel->obterEventos($partidaId);
$isAndamento = $partida['status'] === PartidaModel::STATUS_ANDAMENTO;
$isEncerrada = $partida['status'] === PartidaModel::STATUS_ENCERRADA;
$isAgendada  = $partida['status'] === PartidaModel::STATUS_AGENDADA;

// Último minuto registrado ou minuto 1
$minutoSugerido = 1;
if (!empty($eventos)) {
    $ultimo = end($eventos);
    $minutoSugerido = min(40, max(1, (int)$ultimo['minuto']));
}
?>

<!-- Banner do Confronto e Placar em Tempo Real -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-body p-4 text-center">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="badge bg-light text-dark border">
                <i class="bi bi-geo-alt-fill me-1 text-primary"></i>Quadra <?= $partida['quadra'] ?> &bull; <?= date('d/m/Y', strtotime($partida['data'])) ?> às <?= substr($partida['horario'], 0, 5) ?>
            </span>
            <div>
                <?php if ($isAndamento): ?>
                    <span class="badge bg-danger fs-6 px-3 py-2 pulse"><i class="bi bi-record-circle me-1"></i>AO VIVO</span>
                <?php elseif ($isEncerrada): ?>
                    <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-all me-1"></i>FINALIZADA</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark fs-6 px-3 py-2">AGENDADA</span>
                <?php endif; ?>
                <span class="badge bg-primary fs-6 ms-1">Fase: <?= ucfirst($partida['fase']) ?> <?= $partida['grupo'] ? "(Gr. {$partida['grupo']})" : '' ?></span>
            </div>
        </div>

        <div class="row align-items-center my-3">
            <!-- Mandante -->
            <div class="col-md-5">
                <h3 class="fw-bold mb-1 text-primary"><?= htmlspecialchars($partida['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></h3>
                <span class="badge bg-light text-muted border">Uniforme: <?= htmlspecialchars($partida['equipe_casa_cor'], ENT_QUOTES, 'UTF-8') ?></span>
                <div class="small text-muted mt-1"><?= htmlspecialchars($partida['equipe_casa_turma'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <!-- Placar Central -->
            <div class="col-md-2 my-3 my-md-0">
                <div class="display-3 fw-bold text-dark" id="placar-display">
                    <span id="gols-casa"><?= $partida['gols_casa'] ?></span>
                    <span class="text-muted mx-1">&times;</span>
                    <span id="gols-vis"><?= $partida['gols_visitante'] ?></span>
                </div>
                <?php if ($partida['placar_casa_7m'] !== null): ?>
                    <div class="badge bg-danger text-white">7m: <?= $partida['placar_casa_7m'] ?> &times; <?= $partida['placar_visitante_7m'] ?></div>
                <?php endif; ?>
            </div>

            <!-- Visitante -->
            <div class="col-md-5">
                <h3 class="fw-bold mb-1 text-primary"><?= htmlspecialchars($partida['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></h3>
                <span class="badge bg-light text-muted border">Uniforme: <?= htmlspecialchars($partida['equipe_visitante_cor'], ENT_QUOTES, 'UTF-8') ?></span>
                <div class="small text-muted mt-1"><?= htmlspecialchars($partida['equipe_visitante_turma'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>

        <!-- Barra de Controle do Cronômetro / Partida -->
        <div class="border-top pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="text-muted small">Mesário: <strong><?= htmlspecialchars($partida['mesario_nome'] ?? 'Não designado', ENT_QUOTES, 'UTF-8') ?></strong></span>
            </div>

            <?php if ($isAgendada): ?>
                <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=iniciar">
                    <input type="hidden" name="partida_id" value="<?= $partidaId ?>">
                    <button type="submit" class="btn btn-success fw-bold shadow-sm">
                        <i class="bi bi-play-circle-fill me-1"></i>Iniciar Partida Agora
                    </button>
                </form>
            <?php elseif ($isAndamento): ?>
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2 bg-light p-2 rounded border">
                        <label class="form-label mb-0 fw-bold small text-dark"><i class="bi bi-stopwatch text-danger me-1"></i>Minuto Atual:</label>
                        <input type="number" id="minuto_global" class="form-control form-control-sm text-center fw-bold" style="width: 75px;" min="1" max="40" value="<?= $minutoSugerido ?>">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="incrementarMinuto()">+1'</button>
                    </div>

                    <button type="button" class="btn btn-danger btn-sm shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalEncerrarPartida">
                        <i class="bi bi-stop-circle-fill me-1"></i>Encerrar Partida
                    </button>
                </div>
            <?php else: ?>
                <span class="badge bg-secondary p-2"><i class="bi bi-lock-fill me-1"></i>Súmula Finalizada (Somente Leitura)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$isAndamento && !$isEncerrada): ?>
    <div class="alert alert-warning text-center shadow-sm">
        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
        A súmula só pode receber lançamentos após a partida ser <strong>Iniciada</strong>. Clique no botão verde acima para dar início ao cronômetro de jogo.
    </div>
<?php endif; ?>

<!-- Painel das Equipes (Atletas e Ações de 1 Clique) -->
<div class="row g-4 mb-4">
    <!-- Coluna: Mandante -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-shield-fill me-2"></i><?= htmlspecialchars($partida['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <span class="badge bg-white text-primary fw-bold"><?= count($jogadoresCasa) ?> atletas</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jogadoresCasa)): ?>
                    <p class="text-muted p-4 text-center mb-0">Nenhum atleta ativo cadastrado nesta equipe.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($jogadoresCasa as $jog): ?>
                            <?php 
                                $isDesqualificado = $partidaModel->jogadorTemVermelho($partidaId, (int)$jog['id']);
                                $qtdExclusoes    = $partidaModel->contarExclusoesJogador($partidaId, (int)$jog['id']);
                                $qtdAmarelos     = $partidaModel->contarAmarelosJogador($partidaId, (int)$jog['id']);
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-3 <?= $isDesqualificado ? 'bg-danger bg-opacity-10' : '' ?>" id="jogador-card-<?= $jog['id'] ?>">
                                <div>
                                    <span class="badge bg-dark fs-6 me-2">#<?= $jog['numero_camisa'] ?></span>
                                    <span class="fw-bold text-dark <?= $isDesqualificado ? 'text-decoration-line-through text-danger' : '' ?>">
                                        <?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <div class="small mt-1">
                                        <?php if ($qtdAmarelos > 0): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-file-fill"></i> Amarelo</span>
                                        <?php endif; ?>
                                        <?php if ($qtdExclusoes > 0): ?>
                                            <span class="badge bg-secondary"><i class="bi bi-stopwatch"></i> <?= $qtdExclusoes ?>x Exclusão (2')</span>
                                        <?php endif; ?>
                                        <?php if ($isDesqualificado): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-octagon-fill"></i> DESQUALIFICADO (RN09)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Botões Rápidos de Súmula -->
                                <?php if ($isAndamento && !$isDesqualificado): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-success" title="Registrar Gol" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'gol', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            ⚽ Gol
                                        </button>
                                        <button type="button" class="btn btn-warning text-dark" title="Cartão Amarelo" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'cartao_amarelo', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            🟨
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" title="Exclusão 2 Minutos (3ª gera vermelho)" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'exclusao_2min', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            ⏱️ 2'
                                        </button>
                                        <button type="button" class="btn btn-danger" title="Cartão Vermelho Direto" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'cartao_vermelho', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            🟥
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Coluna: Visitante -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0">
                    <i class="bi bi-shield-fill me-2"></i><?= htmlspecialchars($partida['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <span class="badge bg-white text-primary fw-bold"><?= count($jogadoresVis) ?> atletas</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jogadoresVis)): ?>
                    <p class="text-muted p-4 text-center mb-0">Nenhum atleta ativo cadastrado nesta equipe.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($jogadoresVis as $jog): ?>
                            <?php 
                                $isDesqualificado = $partidaModel->jogadorTemVermelho($partidaId, (int)$jog['id']);
                                $qtdExclusoes    = $partidaModel->contarExclusoesJogador($partidaId, (int)$jog['id']);
                                $qtdAmarelos     = $partidaModel->contarAmarelosJogador($partidaId, (int)$jog['id']);
                            ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-3 <?= $isDesqualificado ? 'bg-danger bg-opacity-10' : '' ?>" id="jogador-card-<?= $jog['id'] ?>">
                                <div>
                                    <span class="badge bg-dark fs-6 me-2">#<?= $jog['numero_camisa'] ?></span>
                                    <span class="fw-bold text-dark <?= $isDesqualificado ? 'text-decoration-line-through text-danger' : '' ?>">
                                        <?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <div class="small mt-1">
                                        <?php if ($qtdAmarelos > 0): ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-file-fill"></i> Amarelo</span>
                                        <?php endif; ?>
                                        <?php if ($qtdExclusoes > 0): ?>
                                            <span class="badge bg-secondary"><i class="bi bi-stopwatch"></i> <?= $qtdExclusoes ?>x Exclusão (2')</span>
                                        <?php endif; ?>
                                        <?php if ($isDesqualificado): ?>
                                            <span class="badge bg-danger"><i class="bi bi-x-octagon-fill"></i> DESQUALIFICADO (RN09)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Botões Rápidos de Súmula -->
                                <?php if ($isAndamento && !$isDesqualificado): ?>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-success" title="Registrar Gol" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'gol', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            ⚽ Gol
                                        </button>
                                        <button type="button" class="btn btn-warning text-dark" title="Cartão Amarelo" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'cartao_amarelo', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            🟨
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" title="Exclusão 2 Minutos (3ª gera vermelho)" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'exclusao_2min', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            ⏱️ 2'
                                        </button>
                                        <button type="button" class="btn btn-danger" title="Cartão Vermelho Direto" onclick="registrarLance(<?= $partidaId ?>, <?= $jog['id'] ?>, 'cartao_vermelho', '<?= htmlspecialchars($jog['nome'], ENT_QUOTES, 'UTF-8') ?>')">
                                            🟥
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Feed Cronológico de Eventos da Súmula -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-clock-history me-2 text-primary"></i>Linha do Tempo dos Lances da Partida
        </h5>
        <span class="badge bg-light text-dark border" id="total-eventos-badge"><?= count($eventos) ?> lance(s) gravado(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-center">
                <thead class="table-light small text-uppercase">
                    <tr>
                        <th style="width: 100px;">Minuto</th>
                        <th class="text-start">Equipe</th>
                        <th class="text-start">Atleta</th>
                        <th>Tipo de Lance</th>
                        <?php if ($isAndamento): ?>
                            <th class="text-end pe-3">Estorno</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="feed-eventos-body">
                    <?php if (empty($eventos)): ?>
                        <tr id="linha-sem-eventos">
                            <td colspan="<?= $isAndamento ? 5 : 4 ?>" class="p-4 text-muted">
                                Nenhum lance registrado na súmula ainda.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach (array_reverse($eventos) as $ev): ?>
                            <tr>
                                <td class="fw-bold fs-6"><span class="badge bg-light text-dark border"><?= $ev['minuto'] ?>'</span></td>
                                <td class="text-start fw-semibold text-primary"><?= htmlspecialchars($ev['equipe_nome'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="text-start">
                                    <span class="badge bg-dark me-1">#<?= $ev['jogador_numero'] ?></span>
                                    <?= htmlspecialchars($ev['jogador_nome'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td>
                                    <?php if ($ev['tipo'] === 'gol'): ?>
                                        <span class="badge bg-success fs-6"><i class="bi bi-balloon me-1"></i>GOL</span>
                                    <?php elseif ($ev['tipo'] === 'exclusao_2min'): ?>
                                        <span class="badge bg-secondary fs-6"><i class="bi bi-stopwatch me-1"></i>Exclusão 2 Min</span>
                                    <?php elseif ($ev['tipo'] === 'cartao_amarelo'): ?>
                                        <span class="badge bg-warning text-dark fs-6"><i class="bi bi-file-fill me-1"></i>Cartão Amarelo</span>
                                    <?php elseif ($ev['tipo'] === 'cartao_vermelho'): ?>
                                        <span class="badge bg-danger fs-6"><i class="bi bi-x-octagon-fill me-1"></i>Cartão Vermelho (Desqualificação)</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAndamento): ?>
                                    <td class="text-end pe-3">
                                        <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=remover_evento" onsubmit="return confirm('Deseja estornar este lance da súmula?');">
                                            <input type="hidden" name="evento_id" value="<?= $ev['id'] ?>">
                                            <input type="hidden" name="partida_id" value="<?= $partidaId ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Estornar Lance">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Encerrar Partida (com desempate 7m em mata-mata se necessário) -->
<div class="modal fade" id="modalEncerrarPartida" tabindex="-1" aria-labelledby="modalEncerrarPartidaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=encerrar">
                <input type="hidden" name="partida_id" value="<?= $partidaId ?>">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="modalEncerrarPartidaLabel"><i class="bi bi-stop-circle me-2"></i>Finalizar Partida</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-dark">Tem certeza que deseja consolidar e encerrar a súmula desta partida?</p>

                    <?php if ($partida['fase'] !== PartidaModel::FASE_GRUPOS): ?>
                        <div class="alert alert-warning small">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>Mata-Mata:</strong> Em caso de empate no placar regular, preencha abaixo o placar dos tiros de 7 metros para definir o vencedor classificado.
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Tiros 7m - <?= htmlspecialchars($partida['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="number" name="placar_casa_7m" class="form-control" min="0" placeholder="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Tiros 7m - <?= htmlspecialchars($partida['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input type="number" name="placar_visitante_7m" class="form-control" min="0" placeholder="0">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Continuar Jogo</button>
                    <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-check2-all me-1"></i>Confirmar Encerramento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function incrementarMinuto() {
    const input = document.getElementById('minuto_global');
    let val = parseInt(input.value) || 1;
    if (val < 40) {
        input.value = val + 1;
    }
}

function registrarLance(partidaId, jogadorId, tipo, jogadorNome) {
    const minutoInput = document.getElementById('minuto_global');
    let minuto = parseInt(minutoInput.value) || 1;
    if (minuto < 1) minuto = 1;
    if (minuto > 40) minuto = 40;

    const formData = new FormData();
    formData.append('partida_id', partidaId);
    formData.append('jogador_id', jogadorId);
    formData.append('tipo', tipo);
    formData.append('minuto', minuto);
    formData.append('ajax', '1');

    fetch('<?= $baseUrl ?>/app/controllers/PartidaController.php?action=lancar_evento', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.sucesso) {
            // Atualiza o placar visualmente
            if (data.placar) {
                document.getElementById('gols-casa').textContent = data.placar.gols_casa;
                document.getElementById('gols-vis').textContent = data.placar.gols_visitante;
            }
            // Recarrega a página para atualizar linha do tempo e badges de advertência
            location.reload();
        } else {
            alert(data.mensagem || 'Erro ao registrar lance.');
        }
    })
    .catch(error => {
        console.error('Erro na requisição AJAX:', error);
        // Fallback: submete tradicional
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= $baseUrl ?>/app/controllers/PartidaController.php?action=lancar_evento';
        
        for (const [key, value] of formData.entries()) {
            if (key !== 'ajax') {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = value;
                form.appendChild(input);
            }
        }
        document.body.appendChild(form);
        form.submit();
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
