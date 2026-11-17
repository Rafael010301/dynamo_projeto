<?php
/**
 * partidas.php
 * Calendário Público de Jogos, Resultados e Súmulas
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/app/models/PartidaModel.php';

$tituloPagina = 'Calendário de Jogos & Resultados';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();
$partidaModel = new PartidaModel($db);

// Carrega todas as edições para filtro
$stmtEd = $db->query("SELECT id, ano, nome, status FROM edicoes ORDER BY ano DESC");
$edicoes = $stmtEd->fetchAll();

$edicaoId = (int)($_GET['edicao_id'] ?? 0);
if ($edicaoId <= 0 && !empty($edicoes)) {
    foreach ($edicoes as $e) {
        if ($e['status'] === 'andamento') {
            $edicaoId = (int)$e['id'];
            break;
        }
    }
    if ($edicaoId <= 0) {
        $edicaoId = (int)$edicoes[0]['id'];
    }
}

// Filtros de fase e status
$filtroFase   = !empty($_GET['fase']) ? trim($_GET['fase']) : null;
$filtroStatus = !empty($_GET['status']) ? trim($_GET['status']) : null;

// Lista de partidas filtradas
$partidas = $edicaoId > 0 ? $partidaModel->listarPorEdicao($edicaoId, $filtroFase, $filtroStatus) : [];

// Consulta os gols de cada partida para exibir os autores dos lances
$golsPorPartida = [];
if (!empty($partidas)) {
    $ids = array_column($partidas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlGols = "SELECT es.partida_id, es.minuto, j.nome AS jogador_nome, j.numero_camisa, j.equipe_id
                FROM eventos_sumula es
                INNER JOIN jogadores j ON es.jogador_id = j.id
                WHERE es.tipo = 'gol' AND es.partida_id IN ($placeholders)
                ORDER BY es.minuto ASC";
    $stmtGols = $db->prepare($sqlGols);
    $stmtGols->execute($ids);
    foreach ($stmtGols->fetchAll() as $g) {
        $golsPorPartida[$g['partida_id']][] = $g;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-calendar-event-fill text-primary me-2"></i>Jogos & Resultados
        </h2>
        <p class="text-muted mb-0">Tabela de confrontos, placares ao vivo e autores de gols na Copa Interclasse.</p>
    </div>

    <!-- Seletor de Edição -->
    <?php if (count($edicoes) > 1): ?>
        <div>
            <form method="GET" action="<?= $baseUrl ?>/partidas.php" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small fw-semibold text-muted">Edição:</label>
                <select name="edicao_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($edicoes as $ed): ?>
                        <option value="<?= $ed['id'] ?>" <?= $ed['id'] == $edicaoId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ed['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $ed['ano'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Filtros Rápidos de Fase e Status -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/partidas.php" class="row g-2 align-items-center">
            <input type="hidden" name="edicao_id" value="<?= $edicaoId ?>">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted mb-1">Fase:</label>
                <select name="fase" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todas as Fases</option>
                    <option value="grupos" <?= $filtroFase === 'grupos' ? 'selected' : '' ?>>Fase de Grupos</option>
                    <option value="quartas" <?= $filtroFase === 'quartas' ? 'selected' : '' ?>>Quartas de Final</option>
                    <option value="semifinal" <?= $filtroFase === 'semifinal' ? 'selected' : '' ?>>Semifinal</option>
                    <option value="final" <?= $filtroFase === 'final' ? 'selected' : '' ?>>Grande Final</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted mb-1">Status:</label>
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos os Status</option>
                    <option value="andamento" <?= $filtroStatus === 'andamento' ? 'selected' : '' ?>>Ao Vivo (Em Andamento)</option>
                    <option value="agendada" <?= $filtroStatus === 'agendada' ? 'selected' : '' ?>>Agendadas</option>
                    <option value="encerrada" <?= $filtroStatus === 'encerrada' ? 'selected' : '' ?>>Encerradas</option>
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

<!-- Lista de Partidas em Cards Esportivos -->
<?php if (empty($partidas)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-5 text-center text-muted">
            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
            Nenhum confronto encontrado para os filtros selecionados.
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($partidas as $p): ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100 overflow-hidden <?= $p['status'] === 'andamento' ? 'border-danger border-2' : '' ?>">
                    <!-- Cabeçalho do Card -->
                    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-bottom small">
                        <div>
                            <span class="fw-bold text-dark"><i class="bi bi-calendar3 me-1 text-primary"></i><?= date('d/m/Y', strtotime($p['data'])) ?></span>
                            <span class="text-muted ms-2"><i class="bi bi-clock me-1"></i><?= substr($p['horario'], 0, 5) ?></span>
                            <span class="badge bg-light text-dark border ms-2">Quadra <?= $p['quadra'] ?></span>
                        </div>
                        <div>
                            <span class="badge bg-primary text-uppercase"><?= ucfirst($p['fase']) ?></span>
                            <?php if ($p['grupo']): ?>
                                <span class="badge bg-info text-dark">Gr. <?= $p['grupo'] ?></span>
                            <?php endif; ?>
                            <?php if ($p['status'] === 'andamento'): ?>
                                <span class="badge bg-danger pulse ms-1">AO VIVO</span>
                            <?php elseif ($p['status'] === 'encerrada'): ?>
                                <span class="badge bg-success ms-1">FINAL</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-1">AGENDADA</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Placar e Equipes -->
                    <div class="card-body p-4 text-center">
                        <div class="row align-items-center">
                            <!-- Mandante -->
                            <div class="col-5">
                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></h5>
                                <span class="badge bg-light text-muted border small"><?= htmlspecialchars($p['equipe_casa_cor'], ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="text-muted small mt-1"><?= htmlspecialchars($p['equipe_casa_turma'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <!-- Placar -->
                            <div class="col-2">
                                <?php if ($p['status'] === 'agendada'): ?>
                                    <span class="badge bg-light text-muted border fs-6">VS</span>
                                <?php else: ?>
                                    <div class="fs-2 fw-bold <?= $p['status'] === 'andamento' ? 'text-danger' : 'text-dark' ?>">
                                        <?= $p['gols_casa'] ?> - <?= $p['gols_visitante'] ?>
                                    </div>
                                    <?php if ($p['placar_casa_7m'] !== null): ?>
                                        <div class="badge bg-danger small">7m: <?= $p['placar_casa_7m'] ?>-<?= $p['placar_visitante_7m'] ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Visitante -->
                            <div class="col-5">
                                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></h5>
                                <span class="badge bg-light text-muted border small"><?= htmlspecialchars($p['equipe_visitante_cor'], ENT_QUOTES, 'UTF-8') ?></span>
                                <div class="text-muted small mt-1"><?= htmlspecialchars($p['equipe_visitante_turma'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>

                        <!-- Autores dos Gols (se houver) -->
                        <?php if (!empty($golsPorPartida[$p['id']])): ?>
                            <div class="border-top mt-3 pt-2 text-start small">
                                <span class="text-muted fw-semibold d-block mb-1"><i class="bi bi-balloon me-1 text-success"></i>Gols da Partida:</span>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($golsPorPartida[$p['id']] as $g): ?>
                                        <span class="badge bg-light text-dark border">
                                            ⚽ #<?= $g['numero_camisa'] ?> <?= htmlspecialchars($g['jogador_nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $g['minuto'] ?>')
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
