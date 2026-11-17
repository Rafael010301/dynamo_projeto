<?php
/**
 * equipes.php
 * Listagem Pública de Equipes Participantes e Seus Respectivos Elencos
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/AuthMiddleware.php';
require_once __DIR__ . '/app/models/EquipeModel.php';

$tituloPagina = 'Equipes & Atletas';
require_once __DIR__ . '/includes/header.php';

$db = Database::getConnection();
$equipeModel = new EquipeModel($db);

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

// Filtro por grupo
$filtroGrupo = !empty($_GET['grupo']) && in_array(strtoupper($_GET['grupo']), ['A', 'B']) ? strtoupper($_GET['grupo']) : null;

// Lista de equipes ativas da edição
$equipes = $edicaoId > 0 ? $equipeModel->listarPorEdicao($edicaoId, $filtroGrupo, true) : [];

// Consulta atletas de todas as equipes da edição
$jogadoresPorEquipe = [];
$golsPorJogador = [];

if ($edicaoId > 0) {
    // Gols por jogador
    $stmtGols = $db->query("SELECT jogador_id, COUNT(*) AS total_gols FROM eventos_sumula WHERE tipo = 'gol' GROUP BY jogador_id");
    foreach ($stmtGols->fetchAll() as $g) {
        $golsPorJogador[(int)$g['jogador_id']] = (int)$g['total_gols'];
    }

    // Atletas ativos
    $sqlJog = "SELECT id, equipe_id, rm, nome, numero_camisa 
               FROM jogadores 
               WHERE edicao_id = :id AND situacao = 'ativo' 
               ORDER BY numero_camisa ASC, nome ASC";
    $stmtJog = $db->prepare($sqlJog);
    $stmtJog->execute([':id' => $edicaoId]);
    foreach ($stmtJog->fetchAll() as $j) {
        $jogadoresPorEquipe[(int)$j['equipe_id']][] = $j;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-shield-shaded text-primary me-2"></i>Equipes & Elencos Oficiais
        </h2>
        <p class="text-muted mb-0">Conheça as turmas participantes e a escalação completa de cada time.</p>
    </div>

    <!-- Seletor de Edição e Grupo -->
    <div class="d-flex gap-2 align-items-center">
        <form method="GET" action="<?= $baseUrl ?>/equipes.php" class="d-flex align-items-center gap-2">
            <?php if (count($edicoes) > 1): ?>
                <select name="edicao_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($edicoes as $ed): ?>
                        <option value="<?= $ed['id'] ?>" <?= $ed['id'] == $edicaoId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ed['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <select name="grupo" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="">Todos os Grupos</option>
                <option value="A" <?= $filtroGrupo === 'A' ? 'selected' : '' ?>>Grupo A</option>
                <option value="B" <?= $filtroGrupo === 'B' ? 'selected' : '' ?>>Grupo B</option>
            </select>
        </form>
    </div>
</div>

<?php if (empty($equipes)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-5 text-center text-muted">
            <i class="bi bi-shield-slash fs-1 d-block mb-2 text-secondary"></i>
            Nenhuma equipe ativa cadastrada para a seleção atual.
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($equipes as $eq): ?>
            <?php $elenco = $jogadoresPorEquipe[(int)$eq['id']] ?? []; ?>
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100 overflow-hidden">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($eq['nome'], ENT_QUOTES, 'UTF-8') ?></h5>
                            <small class="text-muted"><i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($eq['turma'], ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                        <div class="text-end">
                            <?php if ($eq['grupo']): ?>
                                <span class="badge <?= $eq['grupo'] === 'A' ? 'bg-primary' : 'bg-info text-dark' ?> fs-6">
                                    Grupo <?= $eq['grupo'] ?>
                                </span>
                            <?php endif; ?>
                            <div class="small text-muted mt-1">
                                <i class="bi bi-palette-fill me-1"></i><?= htmlspecialchars($eq['cor_uniforme'], ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center small">
                            <span class="fw-semibold text-muted text-uppercase">Escalação de Atletas:</span>
                            <span class="badge bg-secondary"><?= count($elenco) ?> atletas inscritos</span>
                        </div>

                        <?php if (empty($elenco)): ?>
                            <p class="text-muted p-4 text-center mb-0 small">Nenhum atleta cadastrado nesta equipe.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush small" style="max-height: 280px; overflow-y: auto;">
                                <?php foreach ($elenco as $atleta): ?>
                                    <?php $gols = $golsPorJogador[(int)$atleta['id']] ?? 0; ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-3">
                                        <div>
                                            <span class="badge bg-dark me-2">#<?= $atleta['numero_camisa'] ?></span>
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($atleta['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="text-muted small ms-2">(RM: <?= htmlspecialchars($atleta['rm'], ENT_QUOTES, 'UTF-8') ?>)</span>
                                        </div>
                                        <?php if ($gols > 0): ?>
                                            <span class="badge bg-success rounded-pill" title="Gols marcados na copa">
                                                ⚽ <?= $gols ?> <?= $gols === 1 ? 'gol' : 'gols' ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
