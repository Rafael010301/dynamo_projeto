<?php
/**
 * classificacao.php
 * Tabela Oficial de Classificação por Grupos
 * Consome a VIEW 'vw_classificacao' ordenada por Pontos, Saldo de Gols e Gols Pró (RN01 a RN04)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AuthMiddleware.php';

$tituloPagina = 'Classificação Oficial';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();

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

// Busca classificação da VIEW para o Grupo A e Grupo B
$classificacaoGrupoA = [];
$classificacaoGrupoB = [];

if ($edicaoId > 0) {
    // RN01 a RN04: Ordenação rigorosa por Pontos (P DESC), Saldo de Gols (SG DESC), Gols Pró (GP DESC) e Nome da Equipe
    $sqlA = "SELECT * FROM vw_classificacao 
             WHERE edicao_id = :id AND grupo = 'A' 
             ORDER BY P DESC, SG DESC, GP DESC, equipe ASC";
    $stmtA = $db->prepare($sqlA);
    $stmtA->execute([':id' => $edicaoId]);
    $classificacaoGrupoA = $stmtA->fetchAll();

    $sqlB = "SELECT * FROM vw_classificacao 
             WHERE edicao_id = :id AND grupo = 'B' 
             ORDER BY P DESC, SG DESC, GP DESC, equipe ASC";
    $stmtB = $db->prepare($sqlB);
    $stmtB->execute([':id' => $edicaoId]);
    $classificacaoGrupoB = $stmtB->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Tabela de Classificação
        </h2>
        <p class="text-muted mb-0">Pontuação oficial da Fase de Grupos com critérios de desempate automáticos.</p>
    </div>

    <!-- Seletor de Edição -->
    <?php if (count($edicoes) > 1): ?>
        <div>
            <form method="GET" action="<?= $baseUrl ?>/classificacao.php" class="d-flex align-items-center gap-2">
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

<div class="row g-4 mb-4">
    <!-- Tabela do Grupo A -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-shield-fill text-warning me-2"></i>Grupo A</h5>
                <span class="badge bg-white text-primary fw-bold"><?= count($classificacaoGrupoA) ?> equipes</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classificacaoGrupoA)): ?>
                    <p class="text-muted p-4 text-center mb-0">Nenhuma equipe pontuou ainda no Grupo A.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="text-start ps-3" style="width: 45%;">Equipe</th>
                                    <th title="Jogos Disputados">J</th>
                                    <th title="Vitórias (2 pts)">V</th>
                                    <th title="Empates (1 pt)">E</th>
                                    <th title="Derrotas (0 pt)">D</th>
                                    <th title="Gols Pró (Marcados)">GP</th>
                                    <th title="Gols Contra (Sofridos)">GC</th>
                                    <th title="Saldo de Gols">SG</th>
                                    <th title="Pontos Conquistados" class="bg-primary bg-opacity-10 text-primary fw-bold">Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classificacaoGrupoA as $idx => $row): ?>
                                    <tr class="<?= $idx < 2 ? 'table-success table-opacity-25' : '' ?>">
                                        <td class="text-start ps-3 fw-bold">
                                            <span class="badge <?= $idx < 2 ? 'bg-success' : 'bg-secondary' ?> me-2"><?= $idx + 1 ?>º</span>
                                            <?= htmlspecialchars($row['equipe'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($idx < 2): ?>
                                                <i class="bi bi-check-circle-fill text-success ms-1" title="Zona de classificação para semifinal"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $row['J'] ?></td>
                                        <td><?= $row['V'] ?></td>
                                        <td><?= $row['E'] ?></td>
                                        <td><?= $row['D'] ?></td>
                                        <td><?= $row['GP'] ?></td>
                                        <td><?= $row['GC'] ?></td>
                                        <td class="fw-semibold <?= $row['SG'] > 0 ? 'text-success' : ($row['SG'] < 0 ? 'text-danger' : 'text-muted') ?>">
                                            <?= $row['SG'] > 0 ? "+{$row['SG']}" : $row['SG'] ?>
                                        </td>
                                        <td class="bg-primary bg-opacity-10 fw-bold fs-6 text-primary">
                                            <?= $row['P'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light p-2 small text-muted ps-3">
                <span class="badge bg-success me-1">&bull;</span> Verde: Classificação para a Fase Semifinal (Top 2)
            </div>
        </div>
    </div>

    <!-- Tabela do Grupo B -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0"><i class="bi bi-shield-fill text-info me-2"></i>Grupo B</h5>
                <span class="badge bg-white text-primary fw-bold"><?= count($classificacaoGrupoB) ?> equipes</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($classificacaoGrupoB)): ?>
                    <p class="text-muted p-4 text-center mb-0">Nenhuma equipe pontuou ainda no Grupo B.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 text-center">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th class="text-start ps-3" style="width: 45%;">Equipe</th>
                                    <th title="Jogos Disputados">J</th>
                                    <th title="Vitórias (2 pts)">V</th>
                                    <th title="Empates (1 pt)">E</th>
                                    <th title="Derrotas (0 pt)">D</th>
                                    <th title="Gols Pró (Marcados)">GP</th>
                                    <th title="Gols Contra (Sofridos)">GC</th>
                                    <th title="Saldo de Gols">SG</th>
                                    <th title="Pontos Conquistados" class="bg-primary bg-opacity-10 text-primary fw-bold">Pts</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classificacaoGrupoB as $idx => $row): ?>
                                    <tr class="<?= $idx < 2 ? 'table-success table-opacity-25' : '' ?>">
                                        <td class="text-start ps-3 fw-bold">
                                            <span class="badge <?= $idx < 2 ? 'bg-success' : 'bg-secondary' ?> me-2"><?= $idx + 1 ?>º</span>
                                            <?= htmlspecialchars($row['equipe'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php if ($idx < 2): ?>
                                                <i class="bi bi-check-circle-fill text-success ms-1" title="Zona de classificação para semifinal"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $row['J'] ?></td>
                                        <td><?= $row['V'] ?></td>
                                        <td><?= $row['E'] ?></td>
                                        <td><?= $row['D'] ?></td>
                                        <td><?= $row['GP'] ?></td>
                                        <td><?= $row['GC'] ?></td>
                                        <td class="fw-semibold <?= $row['SG'] > 0 ? 'text-success' : ($row['SG'] < 0 ? 'text-danger' : 'text-muted') ?>">
                                            <?= $row['SG'] > 0 ? "+{$row['SG']}" : $row['SG'] ?>
                                        </td>
                                        <td class="bg-primary bg-opacity-10 fw-bold fs-6 text-primary">
                                            <?= $row['P'] ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-light p-2 small text-muted ps-3">
                <span class="badge bg-success me-1">&bull;</span> Verde: Classificação para a Fase Semifinal (Top 2)
            </div>
        </div>
    </div>
</div>

<!-- Card de Regulamento e Critérios de Desempate -->
<div class="card border-0 shadow-sm bg-white">
    <div class="card-body p-4">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-journal-text text-primary me-2"></i>Regras de Pontuação e Critérios de Desempate (RN01 a RN04)</h6>
        <div class="row g-3 small text-muted">
            <div class="col-md-4">
                <strong><i class="bi bi-check2 text-success me-1"></i>Pontuação por Partida:</strong>
                <ul class="mb-0 mt-1">
                    <li>Vitória: <strong>2 pontos</strong></li>
                    <li>Empate: <strong>1 ponto</strong></li>
                    <li>Derrota: <strong>0 pontos</strong></li>
                </ul>
            </div>
            <div class="col-md-4">
                <strong><i class="bi bi-sort-numeric-down text-primary me-1"></i>Critérios de Desempate:</strong>
                <ol class="mb-0 mt-1">
                    <li>Maior número de pontos (P)</li>
                    <li>Melhor saldo de gols (SG = GP - GC)</li>
                    <li>Maior número de gols marcados (GP)</li>
                    <li>Ordem alfabética do nome da equipe</li>
                </ol>
            </div>
            <div class="col-md-4">
                <strong><i class="bi bi-arrow-right-circle text-warning me-1"></i>Avanço de Fase:</strong>
                <p class="mb-0 mt-1">
                    Apenas os 2 melhores colocados de cada grupo (1º e 2º lugares) garantem vaga nas semifinais do torneio mata-mata.
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
