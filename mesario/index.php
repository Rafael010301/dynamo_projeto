<?php
/**
 * mesario/index.php
 * Painel de Controle do Mesário - Seleção e Abertura de Súmula Eletrônica
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../includes/protege.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/PartidaModel.php';

$tituloPagina = 'Painel do Mesário';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getConnection();
$partidaModel = new PartidaModel($db);

$currentUser = AuthMiddleware::getUser();
$userId = (int)($currentUser['id'] ?? 0);
$isOrg  = AuthMiddleware::isOrganizador();

// Se for organizador, lista todos os jogos; se for mesário, lista apenas os designados a ele
if ($isOrg) {
    // Busca a edição ativa
    $stmtEd = $db->query("SELECT id, nome, ano FROM edicoes WHERE status = 'andamento' ORDER BY ano DESC LIMIT 1");
    $edicaoAtiva = $stmtEd->fetch();
    $edicaoId = $edicaoAtiva ? (int)$edicaoAtiva['id'] : 0;
    $todasPartidas = $edicaoId > 0 ? $partidaModel->listarPorEdicao($edicaoId) : [];
} else {
    $todasPartidas = $partidaModel->listarPorMesario($userId);
}

// Separação por status
$partidasAoVivo     = array_filter($todasPartidas, fn($p) => $p['status'] === PartidaModel::STATUS_ANDAMENTO);
$partidasAgendadas  = array_filter($todasPartidas, fn($p) => $p['status'] === PartidaModel::STATUS_AGENDADA);
$partidasEncerradas = array_filter($todasPartidas, fn($p) => $p['status'] === PartidaModel::STATUS_ENCERRADA);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-stopwatch-fill text-warning me-2"></i>Mesa de Arbitragem & Súmula
        </h2>
        <p class="text-muted mb-0">
            <?= $isOrg ? "Modo de Supervisão Geral (Organizador)" : "Partidas designadas para " . htmlspecialchars($currentUser['nome'], ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
    <div>
        <span class="badge bg-primary fs-6 p-2">
            <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($currentUser['nome'], ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>
</div>

<!-- Seção: Partidas Ao Vivo (Em Andamento) -->
<?php if (!empty($partidasAoVivo)): ?>
    <div class="card border-danger shadow-sm mb-4">
        <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">
                <span class="spinner-grow spinner-grow-sm me-2" role="status"></span>
                Partidas Em Andamento (Ao Vivo)
            </h5>
            <span class="badge bg-white text-danger fw-bold"><?= count($partidasAoVivo) ?> jogo(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Horário / Quadra</th>
                            <th>Confronto</th>
                            <th class="text-center">Placar Parcial</th>
                            <th class="text-end pe-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidasAoVivo as $p): ?>
                            <tr class="table-danger table-opacity-10">
                                <td class="ps-3">
                                    <span class="fw-bold d-block text-dark"><?= date('d/m/Y', strtotime($p['data'])) ?></span>
                                    <span class="text-muted small"><?= substr($p['horario'], 0, 5) ?> &bull; Quadra <?= $p['quadra'] ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-danger text-uppercase">vs</span>
                                        <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <small class="text-muted">Fase: <?= ucfirst($p['fase']) ?> <?= $p['grupo'] ? "(Gr. {$p['grupo']})" : '' ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger fs-5 px-3 py-2 shadow-sm">
                                        <?= $p['gols_casa'] ?> &times; <?= $p['gols_visitante'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= $baseUrl ?>/mesario/sumula.php?id=<?= $p['id'] ?>" class="btn btn-danger btn-sm shadow-sm fw-bold">
                                        <i class="bi bi-stopwatch me-1"></i>Controlar Súmula
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Seção: Próximas Partidas Agendadas -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-calendar2-check text-warning me-2"></i>Partidas Agendadas
        </h5>
        <span class="badge bg-light text-dark border"><?= count($partidasAgendadas) ?> jogo(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($partidasAgendadas)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-calendar-check fs-2 d-block mb-1 text-secondary"></i>
                Nenhuma partida agendada pendente no momento.
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
                            <th class="text-end pe-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidasAgendadas as $p): ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="fw-bold d-block text-dark"><?= date('d/m/Y', strtotime($p['data'])) ?></span>
                                    <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= substr($p['horario'], 0, 5) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">Quadra <?= $p['quadra'] ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= ucfirst($p['fase']) ?></span>
                                    <?php if ($p['grupo']): ?>
                                        <span class="badge bg-primary small">Gr. <?= $p['grupo'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-muted small">vs</span>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <form method="POST" action="<?= $baseUrl ?>/app/controllers/PartidaController.php?action=iniciar" class="d-inline">
                                            <input type="hidden" name="partida_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-success fw-semibold" title="Iniciar Partida">
                                                <i class="bi bi-play-circle-fill me-1"></i>Iniciar Jogo
                                            </button>
                                        </form>
                                        <a href="<?= $baseUrl ?>/mesario/sumula.php?id=<?= $p['id'] ?>" class="btn btn-outline-primary" title="Visualizar Súmula">
                                            <i class="bi bi-file-earmark-medical"></i>
                                        </a>
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

<!-- Seção: Partidas Encerradas -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-archive text-success me-2"></i>Partidas Concluídas
        </h5>
        <span class="badge bg-light text-dark border"><?= count($partidasEncerradas) ?> jogo(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($partidasEncerradas)): ?>
            <div class="p-4 text-center text-muted">
                Nenhuma partida encerrada ainda.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Data</th>
                            <th>Confronto</th>
                            <th class="text-center">Placar Final</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partidasEncerradas as $p): ?>
                            <tr>
                                <td class="ps-3 text-muted small">
                                    <?= date('d/m/Y', strtotime($p['data'])) ?> &bull; Quadra <?= $p['quadra'] ?>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-muted small">vs</span>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td class="text-center fw-bold">
                                    <?= $p['gols_casa'] ?> - <?= $p['gols_visitante'] ?>
                                    <?php if ($p['placar_casa_7m'] !== null): ?>
                                        <div class="small text-danger">(7m: <?= $p['placar_casa_7m'] ?>-<?= $p['placar_visitante_7m'] ?>)</div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-success">Finalizada</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="<?= $baseUrl ?>/mesario/sumula.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm" title="Consultar Súmula">
                                        <i class="bi bi-eye me-1"></i>Ver Súmula
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
