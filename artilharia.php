<?php
/**
 * artilharia.php
 * Ranking Oficial de Artilharia da Copa Interclasse de Handebol
 * Baseado na contagem estrita de lances de gol registrados em 'eventos_sumula'
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AuthMiddleware.php';

$tituloPagina = 'Ranking de Artilharia';
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

// Consulta os artilheiros da edição
$artilheiros = [];
$totalGolsTorneio = 0;

if ($edicaoId > 0) {
    $sql = "SELECT 
                j.id, j.nome AS jogador_nome, j.numero_camisa, j.rm,
                e.id AS equipe_id, e.nome AS equipe_nome, e.turma, e.grupo,
                COUNT(es.id) AS total_gols
            FROM eventos_sumula es
            INNER JOIN jogadores j ON es.jogador_id = j.id
            INNER JOIN equipes e ON j.equipe_id = e.id
            WHERE es.tipo = 'gol' AND e.edicao_id = :id
            GROUP BY j.id, j.nome, j.numero_camisa, j.rm, e.id, e.nome, e.turma, e.grupo
            ORDER BY total_gols DESC, j.nome ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $edicaoId]);
    $artilheiros = $stmt->fetchAll();

    foreach ($artilheiros as $a) {
        $totalGolsTorneio += (int)$a['total_gols'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-fire text-danger me-2"></i>Ranking de Artilharia
        </h2>
        <p class="text-muted mb-0">Contagem de gols computada automaticamente pelas súmulas eletrônicas.</p>
    </div>

    <!-- Seletor de Edição -->
    <?php if (count($edicoes) > 1): ?>
        <div>
            <form method="GET" action="<?= $baseUrl ?>/artilharia.php" class="d-flex align-items-center gap-2">
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

<!-- Cards de Destaque do Pódio (Top 3) -->
<?php if (!empty($artilheiros)): ?>
    <div class="row g-3 mb-4">
        <!-- 1º Lugar -->
        <?php if (isset($artilheiros[0])): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center bg-white h-100 position-relative overflow-hidden">
                    <div class="card-body p-4">
                        <div class="display-5 text-warning mb-2">🥇</div>
                        <span class="badge bg-warning text-dark text-uppercase fw-bold px-3 py-1 mb-2">Artilheiro(a)</span>
                        <h4 class="fw-bold text-dark mb-0">
                            #<?= $artilheiros[0]['numero_camisa'] ?> <?= htmlspecialchars($artilheiros[0]['jogador_nome'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($artilheiros[0]['equipe_nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $artilheiros[0]['turma'] ?>)</p>
                        <span class="badge bg-danger fs-5 px-3 py-2 shadow-sm">
                            <?= $artilheiros[0]['total_gols'] ?> <?= $artilheiros[0]['total_gols'] == 1 ? 'gol' : 'gols' ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2º Lugar -->
        <?php if (isset($artilheiros[1])): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center bg-white h-100">
                    <div class="card-body p-4">
                        <div class="display-5 text-secondary mb-2">🥈</div>
                        <span class="badge bg-secondary text-uppercase fw-bold px-3 py-1 mb-2">Vice-Artilheiro(a)</span>
                        <h4 class="fw-bold text-dark mb-0">
                            #<?= $artilheiros[1]['numero_camisa'] ?> <?= htmlspecialchars($artilheiros[1]['jogador_nome'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($artilheiros[1]['equipe_nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $artilheiros[1]['turma'] ?>)</p>
                        <span class="badge bg-danger fs-5 px-3 py-2 shadow-sm">
                            <?= $artilheiros[1]['total_gols'] ?> <?= $artilheiros[1]['total_gols'] == 1 ? 'gol' : 'gols' ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 3º Lugar -->
        <?php if (isset($artilheiros[2])): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm text-center bg-white h-100">
                    <div class="card-body p-4">
                        <div class="display-5 mb-2" style="color: #cd7f32;">🥉</div>
                        <span class="badge text-white text-uppercase fw-bold px-3 py-1 mb-2" style="background-color: #cd7f32;">3º Lugar</span>
                        <h4 class="fw-bold text-dark mb-0">
                            #<?= $artilheiros[2]['numero_camisa'] ?> <?= htmlspecialchars($artilheiros[2]['jogador_nome'], ENT_QUOTES, 'UTF-8') ?>
                        </h4>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($artilheiros[2]['equipe_nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $artilheiros[2]['turma'] ?>)</p>
                        <span class="badge bg-danger fs-5 px-3 py-2 shadow-sm">
                            <?= $artilheiros[2]['total_gols'] ?> <?= $artilheiros[2]['total_gols'] == 1 ? 'gol' : 'gols' ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Tabela Geral de Artilharia -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="fw-bold mb-0 text-dark">
            <i class="bi bi-list-ol me-2 text-primary"></i>Lista Completa de Marcadores
        </h5>
        <span class="badge bg-light text-dark border">
            Total de Gols na Competição: <strong><?= $totalGolsTorneio ?></strong>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($artilheiros)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-balloon fs-1 d-block mb-2 text-secondary"></i>
                Nenhum gol registrado na edição selecionada até o momento.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 text-center">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 80px;">Posição</th>
                            <th class="text-start">Atleta</th>
                            <th class="text-start">Equipe / Turma</th>
                            <th class="text-center">Grupo</th>
                            <th class="text-end pe-4" style="width: 150px;">Gols Marcados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($artilheiros as $idx => $art): ?>
                            <tr>
                                <td class="ps-3 fw-bold">
                                    <?php if ($idx === 0): ?>
                                        <span class="badge bg-warning text-dark fs-6">🥇 1º</span>
                                    <?php elseif ($idx === 1): ?>
                                        <span class="badge bg-secondary fs-6">🥈 2º</span>
                                    <?php elseif ($idx === 2): ?>
                                        <span class="badge text-white fs-6" style="background-color: #cd7f32;">🥉 3º</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= $idx + 1 ?>º</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-start">
                                    <span class="badge bg-dark me-2">#<?= $art['numero_camisa'] ?></span>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($art['jogador_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <small class="text-muted ms-2">(RM: <?= htmlspecialchars($art['rm'], ENT_QUOTES, 'UTF-8') ?>)</small>
                                </td>
                                <td class="text-start">
                                    <span class="fw-semibold text-primary"><?= htmlspecialchars($art['equipe_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <small class="text-muted d-block"><?= htmlspecialchars($art['turma'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $art['grupo'] === 'A' ? 'bg-primary' : 'bg-info text-dark' ?>">
                                        Grupo <?= $art['grupo'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-danger rounded-pill fs-6 px-3 py-2">
                                        ⚽ <?= $art['total_gols'] ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
