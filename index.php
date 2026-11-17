<?php
/**
 * index.php
 * Página Inicial Pública - Dashboard de Boas-Vindas da Copa Interclasse de Handebol
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/app/models/PartidaModel.php';

$tituloPagina = 'Início & Destaques';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();
$partidaModel = new PartidaModel($db);

// Busca edição ativa
$stmtEd = $db->query("SELECT * FROM edicoes WHERE status = 'andamento' ORDER BY ano DESC LIMIT 1");
$edicaoAtiva = $stmtEd->fetch();
$edicaoId = $edicaoAtiva ? (int)$edicaoAtiva['id'] : 0;

// Partidas ao vivo e próximas partidas
$partidasAoVivo   = $edicaoId > 0 ? $partidaModel->listarPorEdicao($edicaoId, null, 'andamento') : [];
$proximasPartidas = $edicaoId > 0 ? $partidaModel->listarPorEdicao($edicaoId, null, 'agendada') : [];
$ultimasPartidas  = $edicaoId > 0 ? $partidaModel->listarPorEdicao($edicaoId, null, 'encerrada') : [];

// Líderes Grupo A e B
$lideresGrupoA = [];
$lideresGrupoB = [];
if ($edicaoId > 0) {
    $stmtA = $db->prepare("SELECT * FROM vw_classificacao WHERE edicao_id = :id AND grupo = 'A' ORDER BY P DESC, SG DESC, GP DESC, equipe ASC LIMIT 3");
    $stmtA->execute([':id' => $edicaoId]);
    $lideresGrupoA = $stmtA->fetchAll();

    $stmtB = $db->prepare("SELECT * FROM vw_classificacao WHERE edicao_id = :id AND grupo = 'B' ORDER BY P DESC, SG DESC, GP DESC, equipe ASC LIMIT 3");
    $stmtB->execute([':id' => $edicaoId]);
    $lideresGrupoB = $stmtB->fetchAll();
}

// Top Artilheiros
$topArtilheiros = [];
if ($edicaoId > 0) {
    $sqlArt = "SELECT j.nome AS jogador_nome, j.numero_camisa, e.nome AS equipe_nome, COUNT(es.id) AS total_gols
               FROM eventos_sumula es
               INNER JOIN jogadores j ON es.jogador_id = j.id
               INNER JOIN equipes e ON j.equipe_id = e.id
               WHERE es.tipo = 'gol' AND e.edicao_id = :id
               GROUP BY j.id, j.nome, j.numero_camisa, e.nome
               ORDER BY total_gols DESC, j.nome ASC
               LIMIT 3";
    $stmtArt = $db->prepare($sqlArt);
    $stmtArt->execute([':id' => $edicaoId]);
    $topArtilheiros = $stmtArt->fetchAll();
}
?>

<!-- Hero Banner Principal -->
<div class="p-5 mb-4 bg-primary text-white rounded-3 shadow-sm position-relative overflow-hidden">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark text-uppercase fw-bold px-3 py-2 mb-3">
                <i class="bi bi-trophy-fill me-1"></i>Competição Oficial Escolar
            </span>
            <h1 class="display-5 fw-bold mb-2">
                <?= $edicaoAtiva ? htmlspecialchars($edicaoAtiva['nome'], ENT_QUOTES, 'UTF-8') : "Copa Interclasse de Handebol" ?>
            </h1>
            <p class="lead mb-4 text-white-50">
                Acompanhe em tempo real as tabelas de classificação, os resultados dos jogos, estatísticas de atletas e a súmula eletrônica lance a lance.
            </p>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= $baseUrl ?>/classificacao.php" class="btn btn-warning btn-lg fw-semibold shadow-sm">
                    <i class="bi bi-bar-chart-fill me-1"></i>Ver Classificação
                </a>
                <a href="<?= $baseUrl ?>/partidas.php" class="btn btn-outline-light btn-lg fw-semibold">
                    <i class="bi bi-calendar-event me-1"></i>Calendário de Jogos
                </a>
                <?php if (!AuthMiddleware::isAuthenticated()): ?>
                    <a href="<?= $baseUrl ?>/login.php" class="btn btn-light btn-lg fw-semibold text-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Acesso Restrito
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4 text-center d-none d-lg-block">
            <i class="bi bi-trophy display-1 text-warning opacity-75"></i>
        </div>
    </div>
</div>

<!-- Destaque: Partida Ao Vivo -->
<?php if (!empty($partidasAoVivo)): ?>
    <div class="card border-danger shadow-sm mb-4">
        <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">
                <span class="spinner-grow spinner-grow-sm me-2" role="status"></span>
                JOGO EM ANDAMENTO &bull; AO VIVO
            </h5>
            <span class="badge bg-white text-danger fw-bold">Súmula Ativa</span>
        </div>
        <div class="card-body p-4 text-center">
            <?php foreach ($partidasAoVivo as $aovivo): ?>
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($aovivo['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <small class="text-muted"><?= htmlspecialchars($aovivo['equipe_casa_turma'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                    <div class="col-md-2 my-3 my-md-0">
                        <span class="display-4 fw-bold text-danger">
                            <?= $aovivo['gols_casa'] ?> &times; <?= $aovivo['gols_visitante'] ?>
                        </span>
                        <div class="badge bg-light text-dark border d-block mt-1">Quadra <?= $aovivo['quadra'] ?></div>
                    </div>
                    <div class="col-md-5">
                        <h4 class="fw-bold text-dark mb-0"><?= htmlspecialchars($aovivo['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <small class="text-muted"><?= htmlspecialchars($aovivo['equipe_visitante_turma'], ENT_QUOTES, 'UTF-8') ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Próximos Confrontos e Resultados Recentes -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-calendar-week me-2 text-primary"></i>Próximos Confrontos
                </h5>
                <a href="<?= $baseUrl ?>/partidas.php" class="small text-decoration-none">Ver Calendário &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($proximasPartidas)): ?>
                    <p class="text-muted p-4 text-center mb-0">Nenhuma partida agendada no momento.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($proximasPartidas, 0, 4) as $p): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <span class="badge bg-light text-dark border me-2">
                                        <i class="bi bi-clock me-1"></i><?= date('d/m', strtotime($p['data'])) ?> às <?= substr($p['horario'], 0, 5) ?>
                                    </span>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-muted small mx-1">x</span>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="badge bg-primary text-uppercase"><?= ucfirst($p['fase']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Últimos Resultados -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-check-circle-fill me-2 text-success"></i>Últimos Resultados
                </h5>
                <a href="<?= $baseUrl ?>/partidas.php" class="small text-decoration-none">Ver Todos &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ultimasPartidas)): ?>
                    <p class="text-muted p-4 text-center mb-0">Nenhuma partida encerrada ainda.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice(array_reverse($ultimasPartidas), 0, 4) as $p): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center p-3">
                                <div>
                                    <span class="small text-muted me-2"><?= date('d/m', strtotime($p['data'])) ?></span>
                                    <span class="fw-semibold"><?= htmlspecialchars($p['equipe_casa_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="badge bg-dark mx-2"><?= $p['gols_casa'] ?> - <?= $p['gols_visitante'] ?></span>
                                    <span class="fw-semibold"><?= htmlspecialchars($p['equipe_visitante_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="badge bg-success">Finalizada</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Coluna Direita: Líderes da Classificação e Artilheiros -->
    <div class="col-lg-5">
        <!-- Grupo A -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-warning me-2"></i>Líderes Grupo A</h6>
                <a href="<?= $baseUrl ?>/classificacao.php" class="small text-decoration-none">Tabela Completa &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lideresGrupoA)): ?>
                    <p class="text-muted p-3 text-center mb-0 small">Sem jogos computados ainda.</p>
                <?php else: ?>
                    <table class="table table-sm table-hover align-middle mb-0 text-center small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-3">Pos. Equipe</th>
                                <th>J</th>
                                <th>V</th>
                                <th>SG</th>
                                <th>Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lideresGrupoA as $idx => $eq): ?>
                                <tr>
                                    <td class="text-start ps-3 fw-semibold">
                                        <span class="badge <?= $idx < 2 ? 'bg-primary' : 'bg-secondary' ?> me-1"><?= $idx + 1 ?>º</span>
                                        <?= htmlspecialchars($eq['equipe'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><?= $eq['J'] ?></td>
                                    <td><?= $eq['V'] ?></td>
                                    <td><?= $eq['SG'] > 0 ? "+{$eq['SG']}" : $eq['SG'] ?></td>
                                    <td class="fw-bold text-primary"><?= $eq['P'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grupo B -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-trophy text-info me-2"></i>Líderes Grupo B</h6>
                <a href="<?= $baseUrl ?>/classificacao.php" class="small text-decoration-none">Tabela Completa &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($lideresGrupoB)): ?>
                    <p class="text-muted p-3 text-center mb-0 small">Sem jogos computados ainda.</p>
                <?php else: ?>
                    <table class="table table-sm table-hover align-middle mb-0 text-center small">
                        <thead class="table-light">
                            <tr>
                                <th class="text-start ps-3">Pos. Equipe</th>
                                <th>J</th>
                                <th>V</th>
                                <th>SG</th>
                                <th>Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lideresGrupoB as $idx => $eq): ?>
                                <tr>
                                    <td class="text-start ps-3 fw-semibold">
                                        <span class="badge <?= $idx < 2 ? 'bg-primary' : 'bg-secondary' ?> me-1"><?= $idx + 1 ?>º</span>
                                        <?= htmlspecialchars($eq['equipe'], ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><?= $eq['J'] ?></td>
                                    <td><?= $eq['V'] ?></td>
                                    <td><?= $eq['SG'] > 0 ? "+{$eq['SG']}" : $eq['SG'] ?></td>
                                    <td class="fw-bold text-primary"><?= $eq['P'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Destaque Artilharia -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-fire text-danger me-2"></i>Principais Artilheiros</h6>
                <a href="<?= $baseUrl ?>/artilharia.php" class="small text-decoration-none">Ranking &raquo;</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topArtilheiros)): ?>
                    <p class="text-muted p-3 text-center mb-0 small">Nenhum gol registrado até o momento.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush small">
                        <?php foreach ($topArtilheiros as $idx => $art): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-warning text-dark me-1"><?= $idx + 1 ?>º</span>
                                    <strong>#<?= $art['numero_camisa'] ?> <?= htmlspecialchars($art['jogador_nome'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($art['equipe_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <span class="badge bg-danger rounded-pill fs-6"><?= $art['total_gols'] ?> gol(s)</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
