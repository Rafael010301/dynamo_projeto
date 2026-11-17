<?php
/**
 * admin/index.php
 * Painel Principal do Organizador (Dashboard com visão geral do campeonato)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../includes/protege.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/PartidaModel.php';

$tituloPagina = 'Painel Administrativo';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getConnection();

// Busca a edição ativa
$stmtEdicao = $db->query("SELECT * FROM edicoes WHERE status = 'andamento' ORDER BY ano DESC LIMIT 1");
$edicaoAtiva = $stmtEdicao->fetch();
$edicaoId = $edicaoAtiva ? (int)$edicaoAtiva['id'] : 0;

// Estatísticas gerais da edição
$totalEquipes   = 0;
$totalJogadores = 0;
$totalPartidas  = 0;
$partidasAgendadas = 0;
$partidasAndamento = 0;
$partidasEncerradas = 0;

if ($edicaoId > 0) {
    $stmtEq = $db->prepare("SELECT COUNT(*) FROM equipes WHERE edicao_id = :id AND situacao = 'ativo'");
    $stmtEq->execute([':id' => $edicaoId]);
    $totalEquipes = (int)$stmtEq->fetchColumn();

    $stmtJog = $db->prepare("SELECT COUNT(*) FROM jogadores WHERE edicao_id = :id AND situacao = 'ativo'");
    $stmtJog->execute([':id' => $edicaoId]);
    $totalJogadores = (int)$stmtJog->fetchColumn();

    $stmtP = $db->prepare("SELECT status, COUNT(*) as total FROM partidas WHERE edicao_id = :id GROUP BY status");
    $stmtP->execute([':id' => $edicaoId]);
    foreach ($stmtP->fetchAll() as $row) {
        if ($row['status'] === 'agendada')  $partidasAgendadas = (int)$row['total'];
        if ($row['status'] === 'andamento') $partidasAndamento = (int)$row['total'];
        if ($row['status'] === 'encerrada') $partidasEncerradas = (int)$row['total'];
    }
    $totalPartidas = $partidasAgendadas + $partidasAndamento + $partidasEncerradas;
}

// Próximas ou recentes partidas
$partidasRecentes = [];
if ($edicaoId > 0) {
    $partidaModel = new PartidaModel($db);
    $partidasRecentes = $partidaModel->listarPorEdicao($edicaoId);
    $partidasRecentes = array_slice($partidasRecentes, 0, 6);
}

// Classificação rápida dos grupos
$classificacaoGrupoA = [];
$classificacaoGrupoB = [];
if ($edicaoId > 0) {
    $stmtClasA = $db->prepare("SELECT * FROM vw_classificacao WHERE edicao_id = :id AND grupo = 'A' ORDER BY P DESC, SG DESC, GP DESC, equipe ASC LIMIT 3");
    $stmtClasA->execute([':id' => $edicaoId]);
    $classificacaoGrupoA = $stmtClasA->fetchAll();

    $stmtClasB = $db->prepare("SELECT * FROM vw_classificacao WHERE edicao_id = :id AND grupo = 'B' ORDER BY P DESC, SG DESC, GP DESC, equipe ASC LIMIT 3");
    $stmtClasB->execute([':id' => $edicaoId]);
    $classificacaoGrupoB = $stmtClasB->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-speedometer2 text-primary me-2"></i>Painel do Organizador
        </h2>
        <p class="text-muted mb-0">
            <?= $edicaoAtiva ? htmlspecialchars($edicaoAtiva['nome'], ENT_QUOTES, 'UTF-8') . " (" . $edicaoAtiva['ano'] . ")" : "Nenhuma edição ativa no momento" ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= $baseUrl ?>/admin/equipes.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-shield-plus me-1"></i>Equipes
        </a>
        <a href="<?= $baseUrl ?>/admin/partidas.php" class="btn btn-primary btn-sm shadow-sm">
            <i class="bi bi-calendar-plus me-1"></i>Agendar Partida
        </a>
    </div>
</div>

<!-- Cards de Indicadores (KPIs) -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100 bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-white-50 small fw-semibold text-uppercase">Edição Ativa</span>
                        <h4 class="fw-bold mb-0 mt-1"><?= $edicaoAtiva ? $edicaoAtiva['ano'] : '—' ?></h4>
                        <small class="text-white-50"><?= $edicaoAtiva ? 'Formato: Grupos + Mata' : 'Inativa' ?></small>
                    </div>
                    <div class="bg-white bg-opacity-25 p-3 rounded-circle">
                        <i class="bi bi-trophy-fill fs-3 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Equipes Ativas</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= $totalEquipes ?></h3>
                        <small class="text-success"><i class="bi bi-people me-1"></i><?= $totalJogadores ?> atletas inscritos</small>
                    </div>
                    <div class="bg-light p-3 rounded-circle text-primary">
                        <i class="bi bi-shield-fill-check fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Jogos Agendados</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= $partidasAgendadas ?></h3>
                        <small class="text-info"><i class="bi bi-play-circle me-1"></i><?= $partidasAndamento ?> ao vivo</small>
                    </div>
                    <div class="bg-light p-3 rounded-circle text-info">
                        <i class="bi bi-calendar2-event fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted small fw-semibold text-uppercase">Jogos Concluídos</span>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= $partidasEncerradas ?></h3>
                        <small class="text-muted"><?= $totalPartidas ?> partidas no total</small>
                    </div>
                    <div class="bg-light p-3 rounded-circle text-success">
                        <i class="bi bi-check-circle-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Coluna da Esquerda: Partidas Recentes e Agendadas -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar3 me-2 text-primary"></i>Quadro de Partidas Recentes
                </h5>
                <a href="<?= $baseUrl ?>/admin/partidas.php" class="btn btn-sm btn-link text-decoration-none">Ver Todas &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($partidasRecentes)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary"></i>
                        Nenhuma partida agendada até o momento.
                        <div class="mt-2">
                            <a href="<?= $baseUrl ?>/admin/partidas.php" class="btn btn-sm btn-primary">Agendar Primeiro Jogo</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Horário / Quadra</th>
                                    <th>Confronto</th>
                                    <th class="text-center">Placar</th>
                                    <th>Status</th>
                                    <th class="text-end">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($partidasRecentes as $p): ?>
                                    <tr>
                                        <td class="small">
                                            <span class="fw-semibold d-block"><?= date('d/m/Y', strtotime($p['data'])) ?></span>
                                            <span class="text-muted"><?= substr($p['horario'], 0, 5) ?> &bull; Quadra <?= $p['quadra'] ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-muted small">vs</span>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <div class="small text-muted">Fase: <?= ucfirst($p['fase']) ?> <?= $p['grupo'] ? "(Gr. {$p['grupo']})" : '' ?></div>
                                        </td>
                                        <td class="text-center fw-bold">
                                            <?php if ($p['status'] === 'agendada'): ?>
                                                <span class="badge bg-secondary">Aguardando</span>
                                            <?php else: ?>
                                                <span class="fs-6"><?= $p['gols_casa'] ?> - <?= $p['gols_visitante'] ?></span>
                                                <?php if ($p['placar_casa_7m'] !== null): ?>
                                                    <div class="small text-muted">(7m: <?= $p['placar_casa_7m'] ?>-<?= $p['placar_visitante_7m'] ?>)</div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['status'] === 'andamento'): ?>
                                                <span class="badge bg-danger text-uppercase pulse">Ao Vivo</span>
                                            <?php elseif ($p['status'] === 'encerrada'): ?>
                                                <span class="badge bg-success">Encerrada</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Agendada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= $baseUrl ?>/mesario/sumula.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Abrir Súmula">
                                                <i class="bi bi-file-earmark-medical"></i>
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
    </div>

    <!-- Coluna da Direita: Classificação Rápida e Ações -->
    <div class="col-lg-5">
        <!-- Grupo A -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning me-2"></i>Líderes Grupo A</h6>
                <a href="<?= $baseUrl ?>/classificacao.php" class="small text-decoration-none">Classificação Completa &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classificacaoGrupoA)): ?>
                    <p class="text-muted p-3 mb-0 small text-center">Nenhuma equipe pontuou ainda no Grupo A.</p>
                <?php else: ?>
                    <table class="table table-sm table-striped align-middle mb-0 text-center small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-3">Equipe</th>
                                <th>J</th>
                                <th>V</th>
                                <th>SG</th>
                                <th>Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classificacaoGrupoA as $idx => $cl): ?>
                                <tr>
                                    <td class="text-start ps-3 fw-semibold">
                                        <span class="badge <?= $idx < 2 ? 'bg-primary' : 'bg-secondary' ?> me-1"><?= $idx + 1 ?>º</span>
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
                <?php endif; ?>
            </div>
        </div>

        <!-- Grupo B -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-info me-2"></i>Líderes Grupo B</h6>
                <a href="<?= $baseUrl ?>/classificacao.php" class="small text-decoration-none">Classificação Completa &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classificacaoGrupoB)): ?>
                    <p class="text-muted p-3 mb-0 small text-center">Nenhuma equipe pontuou ainda no Grupo B.</p>
                <?php else: ?>
                    <table class="table table-sm table-striped align-middle mb-0 text-center small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-3">Equipe</th>
                                <th>J</th>
                                <th>V</th>
                                <th>SG</th>
                                <th>Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classificacaoGrupoB as $idx => $cl): ?>
                                <tr>
                                    <td class="text-start ps-3 fw-semibold">
                                        <span class="badge <?= $idx < 2 ? 'bg-primary' : 'bg-secondary' ?> me-1"><?= $idx + 1 ?>º</span>
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
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
