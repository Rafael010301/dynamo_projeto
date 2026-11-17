<?php
/**
 * admin/partidas.php
 * Agendamento, Chaveamento e Gerenciamento de Partidas
 * Implementa validação de conflitos de quadra (RN07) e controle de status
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../includes/protege.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/PartidaModel.php';
require_once __DIR__ . '/../app/models/EquipeModel.php';
require_once __DIR__ . '/../app/models/UsuarioModel.php';

$tituloPagina = 'Gerenciamento de Partidas';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getConnection();
$partidaModel = new PartidaModel($db);
$equipeModel  = new EquipeModel($db);
$usuarioModel = new UsuarioModel($db);

// Carrega edições para seleção
$stmtEdicoes = $db->query("SELECT id, ano, nome, status FROM edicoes ORDER BY ano DESC");
$edicoes = $stmtEdicoes->fetchAll();

$edicaoSelecionadaId = (int)($_GET['edicao_id'] ?? 0);
if ($edicaoSelecionadaId <= 0 && !empty($edicoes)) {
    foreach ($edicoes as $ed) {
        if ($ed['status'] === 'andamento') {
            $edicaoSelecionadaId = (int)$ed['id'];
            break;
        }
    }
    if ($edicaoSelecionadaId <= 0) {
        $edicaoSelecionadaId = (int)$edicoes[0]['id'];
    }
}

// Filtros opcionais
$filtroFase   = !empty($_GET['fase']) ? trim($_GET['fase']) : null;
$filtroStatus = !empty($_GET['status']) ? trim($_GET['status']) : null;

// Lista de equipes ativas da edição para o formulário de agendamento
$equipesEdicao = $edicaoSelecionadaId > 0 ? $equipeModel->listarPorEdicao($edicaoSelecionadaId, null, true) : [];

// Lista de mesários ativos para atribuição
$mesarios = $usuarioModel->listarMesariosAtivos();

// Lista as partidas filtradas
$partidas = $edicaoSelecionadaId > 0 ? $partidaModel->listarPorEdicao($edicaoSelecionadaId, $filtroFase, $filtroStatus) : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-calendar2-week-fill text-primary me-2"></i>Gerenciamento de Partidas
        </h2>
        <p class="text-muted mb-0">Agendamento de jogos, prevenção de conflitos de quadra (RN07) e designação de mesários.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalAgendarPartida">
            <i class="bi bi-calendar-plus me-1"></i>Agendar Nova Partida
        </button>
    </div>
</div>

<!-- Filtros de Busca -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/admin/partidas.php" class="row g-2 align-items-center">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Edição:</label>
                <select name="edicao_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($edicoes as $ed): ?>
                        <option value="<?= $ed['id'] ?>" <?= $ed['id'] == $edicaoSelecionadaId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ed['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $ed['ano'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Fase:</label>
                <select name="fase" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas as Fases</option>
                    <option value="grupos" <?= $filtroFase === 'grupos' ? 'selected' : '' ?>>Fase de Grupos</option>
                    <option value="quartas" <?= $filtroFase === 'quartas' ? 'selected' : '' ?>>Quartas de Final</option>
                    <option value="semifinal" <?= $filtroFase === 'semifinal' ? 'selected' : '' ?>>Semifinal</option>
                    <option value="final" <?= $filtroFase === 'final' ? 'selected' : '' ?>>Final</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted mb-1">Status:</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos os Status</option>
                    <option value="agendada" <?= $filtroStatus === 'agendada' ? 'selected' : '' ?>>Agendada</option>
                    <option value="andamento" <?= $filtroStatus === 'andamento' ? 'selected' : '' ?>>Em Andamento</option>
                    <option value="encerrada" <?= $filtroStatus === 'encerrada' ? 'selected' : '' ?>>Encerrada</option>
                </select>
            </div>
            <div class="col-md-2 text-md-end mt-md-4">
                <span class="badge bg-light text-dark border p-2">
                    Total: <strong><?= count($partidas) ?></strong> jogos
                </span>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Partidas -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($partidas)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
                Nenhuma partida encontrada para os critérios selecionados.
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgendarPartida">
                        Agendar Partida Agora
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Data / Horário</th>
                            <th>Quadra</th>
                            <th>Fase / Grupo</th>
                            <th>Confronto</th>
                            <th class="text-center">Placar</th>
                            <th>Mesário</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidas as $p): ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold d-block text-dark"><?= date('d/m/Y', strtotime($p['data'])) ?></span>
                                    <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= substr($p['horario'], 0, 5) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        Quadra <?= $p['quadra'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark d-block"><?= ucfirst($p['fase']) ?></span>
                                    <?php if ($p['grupo']): ?>
                                        <span class="badge <?= $p['grupo'] === 'A' ? 'bg-primary' : 'bg-info text-dark' ?> small">Grupo <?= $p['grupo'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-light text-muted border">vs</span>
                                        <span class="fw-bold text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($p['equipe_casa_cor'], ENT_QUOTES, 'UTF-8') ?> x <?= htmlspecialchars($p['equipe_visitante_cor'], ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['status'] === 'agendada'): ?>
                                        <span class="text-muted small">Aguardando</span>
                                    <?php else: ?>
                                        <span class="fs-5 fw-bold text-dark"><?= $p['gols_casa'] ?> - <?= $p['gols_visitante'] ?></span>
                                        <?php if ($p['placar_casa_7m'] !== null): ?>
                                            <div class="small text-danger fw-semibold">(7m: <?= $p['placar_casa_7m'] ?>-<?= $p['placar_visitante_7m'] ?>)</div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['mesario_nome'])): ?>
                                        <span class="small fw-semibold text-dark"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($p['mesario_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Não atribuído</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($p['status'] === 'andamento'): ?>
                                        <span class="badge bg-danger text-uppercase pulse">Ao Vivo</span>
                                    <?php elseif ($p['status'] === 'encerrada'): ?>
                                        <span class="badge bg-success">Encerrada</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Agendada</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Iniciar Partida (se agendada) -->
                                        <?php if ($p['status'] === 'agendada'): ?>
                                            <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=iniciar" class="d-inline">
                                                <input type="hidden" name="partida_id" value="<?= $p['id'] ?>">
                                                <button type="submit" class="btn btn-outline-success" title="Iniciar Partida">
                                                    <i class="bi bi-play-fill"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Abrir Súmula -->
                                        <a href="<?= $baseUrl ?>/mesario/sumula.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Abrir Súmula Eletrônica">
                                            <i class="bi bi-file-earmark-medical"></i> Súmula
                                        </a>

                                        <!-- Atribuir Mesário Modal Trigger -->
                                        <button type="button" class="btn btn-outline-secondary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalAtribuirMesario"
                                                data-id="<?= $p['id'] ?>"
                                                data-confronto="<?= htmlspecialchars($p['equipe_casa_nome'] . ' x ' . $p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-mesario-id="<?= $p['mesario_id'] ?? '' ?>"
                                                title="Designar Mesário">
                                            <i class="bi bi-person-gear"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Agendar Nova Partida -->
<div class="modal fade" id="modalAgendarPartida" tabindex="-1" aria-labelledby="modalAgendarPartidaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=agendar">
                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalAgendarPartidaLabel"><i class="bi bi-calendar-plus me-2"></i>Agendar Partida</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Regra RN07:</strong> O sistema bloqueará automaticamente qualquer tentativa de agendamento na mesma quadra, data e horário já ocupados.
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Fase da Competição <span class="text-danger">*</span></label>
                            <select name="fase" id="agenda_fase" class="form-select" required>
                                <option value="grupos">Fase de Grupos</option>
                                <option value="quartas">Quartas de Final</option>
                                <option value="semifinal">Semifinal</option>
                                <option value="final">Grande Final</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="box_grupo">
                            <label class="form-label fw-semibold">Grupo (Apenas Fase de Grupos)</label>
                            <select name="grupo" id="agenda_grupo" class="form-select">
                                <option value="A">Grupo A</option>
                                <option value="B">Grupo B</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Equipe Mandante (Casa) <span class="text-danger">*</span></label>
                            <select name="equipe_casa_id" class="form-select" required>
                                <option value="">Selecione a equipe mandante...</option>
                                <?php foreach ($equipesEdicao as $eq): ?>
                                    <option value="<?= $eq['id'] ?>">
                                        <?= htmlspecialchars($eq['nome'], ENT_QUOTES, 'UTF-8') ?> (Gr. <?= $eq['grupo'] ?> - <?= $eq['turma'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Equipe Visitante <span class="text-danger">*</span></label>
                            <select name="equipe_visitante_id" class="form-select" required>
                                <option value="">Selecione a equipe visitante...</option>
                                <?php foreach ($equipesEdicao as $eq): ?>
                                    <option value="<?= $eq['id'] ?>">
                                        <?= htmlspecialchars($eq['nome'], ENT_QUOTES, 'UTF-8') ?> (Gr. <?= $eq['grupo'] ?> - <?= $eq['turma'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Data do Jogo <span class="text-danger">*</span></label>
                            <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Horário <span class="text-danger">*</span></label>
                            <input type="time" name="horario" class="form-control" value="08:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Quadra <span class="text-danger">*</span></label>
                            <select name="quadra" class="form-select" required>
                                <option value="1">Quadra 1 (Principal)</option>
                                <option value="2">Quadra 2</option>
                                <option value="3">Quadra 3</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Designar Mesário Responsável (Opcional)</label>
                        <select name="mesario_id" class="form-select">
                            <option value="">Nenhum no momento (atribuir depois)</option>
                            <?php foreach ($mesarios as $mes): ?>
                                <option value="<?= $mes['id'] ?>">
                                    <?= htmlspecialchars($mes['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($mes['email'], ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-calendar-check me-1"></i>Confirmar Agendamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Atribuir Mesário -->
<div class="modal fade" id="modalAtribuirMesario" tabindex="-1" aria-labelledby="modalAtribuirMesarioLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=atribuir_mesario">
                <input type="hidden" name="partida_id" id="atribuir_partida_id">
                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title" id="modalAtribuirMesarioLabel"><i class="bi bi-person-gear me-2"></i>Designar Mesário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Confronto selecionado:</p>
                    <p class="fw-bold text-dark fs-6" id="atribuir_confronto_label"></p>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mesário Responsável pela Súmula <span class="text-danger">*</span></label>
                        <select name="mesario_id" id="atribuir_mesario_select" class="form-select" required>
                            <option value="">Selecione um mesário ativo...</option>
                            <?php foreach ($mesarios as $mes): ?>
                                <option value="<?= $mes['id'] ?>">
                                    <?= htmlspecialchars($mes['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($mes['email'], ENT_QUOTES, 'UTF-8') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i>Salvar Atribuição</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalAtribuir = document.getElementById('modalAtribuirMesario');
    if (modalAtribuir) {
        modalAtribuir.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('atribuir_partida_id').value = button.getAttribute('data-id');
            document.getElementById('atribuir_confronto_label').textContent = button.getAttribute('data-confronto');
            const mesarioId = button.getAttribute('data-mesario-id');
            document.getElementById('atribuir_mesario_select').value = mesarioId || '';
        });
    }

    const faseSelect = document.getElementById('agenda_fase');
    const boxGrupo = document.getElementById('box_grupo');
    if (faseSelect && boxGrupo) {
        faseSelect.addEventListener('change', function () {
            if (this.value !== 'grupos') {
                boxGrupo.style.display = 'none';
            } else {
                boxGrupo.style.display = 'block';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
