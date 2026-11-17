<?php
/**
 * historico.php
 * Consulta de Edições Anteriores e Arquivo Histórico de Campeonatos
 * Modo somente leitura em conformidade com as regras RE01 a RE07
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/app/models/PartidaModel.php';

$tituloPagina = 'Histórico de Edições';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();
$partidaModel = new PartidaModel($db);

// Busca todas as edições cadastradas no histórico
$sqlEdicoes = "SELECT 
                ed.*,
                ec.nome AS campeao_nome,
                ec.turma AS campeao_turma,
                ev.nome AS vice_nome,
                ev.turma AS vice_turma
               FROM edicoes ed
               LEFT JOIN equipes ec ON ed.campeao_id = ec.id
               LEFT JOIN equipes ev ON ed.vice_id = ev.id
               ORDER BY ed.ano DESC";
$edicoes = $db->query($sqlEdicoes)->fetchAll();

$edicaoSelecionadaId = (int)($_GET['edicao_id'] ?? 0);
if ($edicaoSelecionadaId <= 0 && !empty($edicoes)) {
    $edicaoSelecionadaId = (int)$edicoes[0]['id'];
}

// Dados da edição selecionada
$edicaoAtual = null;
foreach ($edicoes as $ed) {
    if ((int)$ed['id'] === $edicaoSelecionadaId) {
        $edicaoAtual = $ed;
        break;
    }
}

// Estatísticas da edição selecionada
$totalEquipes = 0;
$totalGols    = 0;
$partidasHistorico = [];
$classificacaoHistorico = [];

if ($edicaoAtual) {
    // Total de equipes
    $stmtEq = $db->prepare("SELECT COUNT(*) FROM equipes WHERE edicao_id = :id");
    $stmtEq->execute([':id' => $edicaoSelecionadaId]);
    $totalEquipes = (int)$stmtEq->fetchColumn();

    // Total de gols
    $stmtGols = $db->prepare("SELECT COUNT(*) FROM eventos_sumula es 
                              INNER JOIN partidas p ON es.partida_id = p.id 
                              WHERE p.edicao_id = :id AND es.tipo = 'gol'");
    $stmtGols->execute([':id' => $edicaoSelecionadaId]);
    $totalGols = (int)$stmtGols->fetchColumn();

    // Partidas da edição
    $partidasHistorico = $partidaModel->listarPorEdicao($edicaoSelecionadaId);

    // Classificação da edição
    $stmtClas = $db->prepare("SELECT * FROM vw_classificacao WHERE edicao_id = :id ORDER BY grupo ASC, P DESC, SG DESC, GP DESC");
    $stmtClas->execute([':id' => $edicaoSelecionadaId]);
    $classificacaoHistorico = $stmtClas->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-clock-history text-primary me-2"></i>Histórico de Campeonatos
        </h2>
        <p class="text-muted mb-0">Consulte o arquivo oficial de edições passadas, campeões e estatísticas consolidadas (RE01-RE07).</p>
    </div>

    <!-- Seletor de Edição -->
    <?php if (!empty($edicoes)): ?>
        <div>
            <form method="GET" action="<?= $baseUrl ?>/historico.php" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small fw-semibold text-muted">Selecionar Ano:</label>
                <select name="edicao_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($edicoes as $ed): ?>
                        <option value="<?= $ed['id'] ?>" <?= $ed['id'] == $edicaoSelecionadaId ? 'selected' : '' ?>>
                            <?= $ed['ano'] ?> - <?= htmlspecialchars($ed['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= ucfirst($ed['status']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if (!$edicaoAtual): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-5 text-center text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary"></i>
            Nenhuma edição histórica encontrada.
        </div>
    </div>
<?php else: ?>
    <!-- Banner da Edição Selecionada -->
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h3 class="fw-bold text-dark mb-0">
                        <?= htmlspecialchars($edicaoAtual['nome'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <span class="text-muted small">Ano de Realização: <strong><?= $edicaoAtual['ano'] ?></strong> &bull; Formato: <strong>Grupos + Mata-mata</strong></span>
                </div>
                <div>
                    <?php if ($edicaoAtual['status'] === 'encerrada'): ?>
                        <span class="badge bg-secondary p-2"><i class="bi bi-lock-fill me-1"></i>Edição Encerrada (Arquivo Histórico)</span>
                    <?php else: ?>
                        <span class="badge bg-success p-2"><i class="bi bi-play-circle-fill me-1"></i>Edição em Andamento</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quadro de Honra: Campeão e Vice -->
            <div class="row g-3 my-2">
                <div class="col-md-6">
                    <div class="p-3 rounded bg-warning bg-opacity-10 border border-warning d-flex align-items-center">
                        <div class="display-5 text-warning me-3">🏆</div>
                        <div>
                            <span class="text-uppercase small fw-bold text-dark">Campeão da Edição</span>
                            <h5 class="fw-bold text-dark mb-0">
                                <?= $edicaoAtual['campeao_nome'] ? htmlspecialchars($edicaoAtual['campeao_nome'], ENT_QUOTES, 'UTF-8') : "A definir" ?>
                            </h5>
                            <?php if ($edicaoAtual['campeao_turma']): ?>
                                <small class="text-muted"><?= htmlspecialchars($edicaoAtual['campeao_turma'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 rounded bg-secondary bg-opacity-10 border border-secondary d-flex align-items-center">
                        <div class="display-5 text-secondary me-3">🥈</div>
                        <div>
                            <span class="text-uppercase small fw-bold text-dark">Vice-Campeão</span>
                            <h5 class="fw-bold text-dark mb-0">
                                <?= $edicaoAtual['vice_nome'] ? htmlspecialchars($edicaoAtual['vice_nome'], ENT_QUOTES, 'UTF-8') : "A definir" ?>
                            </h5>
                            <?php if ($edicaoAtual['vice_turma']): ?>
                                <small class="text-muted"><?= htmlspecialchars($edicaoAtual['vice_turma'], ENT_QUOTES, 'UTF-8') ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Métricas Rápidas -->
            <div class="row g-3 mt-2 text-center text-muted small">
                <div class="col-sm-4 border-end">
                    <span class="d-block fw-bold fs-5 text-dark"><?= $totalEquipes ?></span>
                    Equipes Participantes
                </div>
                <div class="col-sm-4 border-end">
                    <span class="d-block fw-bold fs-5 text-dark"><?= count($partidasHistorico) ?></span>
                    Partidas Realizadas
                </div>
                <div class="col-sm-4">
                    <span class="d-block fw-bold fs-5 text-dark"><?= $totalGols ?></span>
                    Gols Anotados
                </div>
            </div>
        </div>
    </div>

    <!-- Quadro de Partidas e Classificação da Edição -->
    <div class="row g-4">
        <!-- Partidas da Edição -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-calendar3 me-2 text-primary"></i>Resultados das Partidas
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($partidasHistorico)): ?>
                        <p class="text-muted p-4 text-center mb-0">Nenhuma partida registrada nesta edição.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($partidasHistorico as $p): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                    <div>
                                        <span class="small text-muted me-2"><?= date('d/m/Y', strtotime($p['data'])) ?></span>
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-dark mx-2">
                                            <?= $p['gols_casa'] ?> - <?= $p['gols_visitante'] ?>
                                            <?php if ($p['placar_casa_7m'] !== null): ?>
                                                <small class="text-danger">(7m: <?= $p['placar_casa_7m'] ?>-<?= $p['placar_visitante_7m'] ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                        <span class="fw-semibold text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                    <span class="badge bg-primary text-uppercase"><?= ucfirst($p['fase']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Classificação Final dos Grupos -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="bi bi-bar-chart-fill me-2 text-primary"></i>Classificação dos Grupos
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($classificacaoHistorico)): ?>
                        <p class="text-muted p-4 text-center mb-0">Sem registros de classificação.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 text-center small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-start ps-3">Gr. Equipe</th>
                                        <th>J</th>
                                        <th>V</th>
                                        <th>SG</th>
                                        <th>Pts</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($classificacaoHistorico as $cl): ?>
                                        <tr>
                                            <td class="text-start ps-3 fw-semibold">
                                                <span class="badge bg-light text-dark border me-1"><?= $cl['grupo'] ?></span>
                                                <?= htmlspecialchars($cl['equipe'], ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td><?= $cl['J'] ?></td>
                                            <td><?= $cl['V'] ?></td>
                                            <td><?= $cl['SG'] > 0 ? "+{$cl['SG']}" : $cl['SG'] ?></td>
                                            <td class="fw-bold text-primary"><?= $cl['P'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
