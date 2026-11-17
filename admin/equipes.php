<?php
/**
 * admin/equipes.php
 * Gestão de Equipes (Cadastro, Edição, Inativação RN12 e Verificação de Aptidão RN05)
 * Projeto: Copa Interclasse Handebol
 */

require_once __DIR__ . '/../includes/protege.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/models/EquipeModel.php';

$tituloPagina = 'Gerenciamento de Equipes';
require_once __DIR__ . '/../includes/header.php';

$db = Database::getConnection();
$equipeModel = new EquipeModel($db);

// Carrega todas as edições para o filtro
$stmtEdicoes = $db->query("SELECT id, ano, nome, status FROM edicoes ORDER BY ano DESC");
$edicoes = $stmtEdicoes->fetchAll();

// Edição selecionada (padrão: primeira ativa ou primeira da lista)
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

// Filtro de grupo opcional
$filtroGrupo = !empty($_GET['grupo']) && in_array(strtoupper($_GET['grupo']), ['A', 'B']) ? strtoupper($_GET['grupo']) : null;

// Lista as equipes da edição selecionada
$equipes = $edicaoSelecionadaId > 0 ? $equipeModel->listarPorEdicao($edicaoSelecionadaId, $filtroGrupo, false) : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h3 fw-bold text-dark mb-1">
            <i class="bi bi-people-fill text-primary me-2"></i>Gerenciamento de Equipes
        </h2>
        <p class="text-muted mb-0">Controle de inscrições, grupos, uniformes e conformidade do elenco (RN05 e RN12).</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNovaEquipe">
            <i class="bi bi-plus-circle me-1"></i>Cadastrar Nova Equipe
        </button>
    </div>
</div>

<!-- Filtros de Edição e Grupo -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="GET" action="<?= $baseUrl ?>/admin/equipes.php" class="row g-2 align-items-center">
            <div class="col-md-5">
                <label class="form-label small fw-semibold text-muted mb-1">Selecione a Edição:</label>
                <select name="edicao_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php if (empty($edicoes)): ?>
                        <option value="0">Nenhuma edição cadastrada</option>
                    <?php else: ?>
                        <?php foreach ($edicoes as $ed): ?>
                            <option value="<?= $ed['id'] ?>" <?= $ed['id'] == $edicaoSelecionadaId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ed['nome'], ENT_QUOTES, 'UTF-8') ?> (<?= $ed['ano'] ?>) - <?= ucfirst($ed['status']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted mb-1">Filtrar por Grupo:</label>
                <select name="grupo" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Todos os Grupos (A e B)</option>
                    <option value="A" <?= $filtroGrupo === 'A' ? 'selected' : '' ?>>Grupo A</option>
                    <option value="B" <?= $filtroGrupo === 'B' ? 'selected' : '' ?>>Grupo B</option>
                </select>
            </div>
            <div class="col-md-3 text-md-end mt-md-4">
                <span class="badge bg-light text-dark border p-2">
                    <i class="bi bi-list-check me-1"></i>Total: <strong><?= count($equipes) ?></strong> equipes
                </span>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Equipes -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($equipes)): ?>
            <div class="p-5 text-center text-muted">
                <i class="bi bi-shield-slash fs-1 d-block mb-2 text-secondary"></i>
                Nenhuma equipe cadastrada para a edição selecionada.
                <div class="mt-3">
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaEquipe">
                        Cadastrar Equipe Agora
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Turma / Equipe</th>
                            <th class="text-center">Grupo</th>
                            <th>Uniforme</th>
                            <th class="text-center">Elenco (RN05)</th>
                            <th class="text-center">Situação</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($equipes as $eq): ?>
                            <?php 
                                $aptidao = $equipeModel->verificarAptidao((int)$eq['id']);
                                $isAtiva = $eq['situacao'] === 'ativo';
                            ?>
                            <tr class="<?= !$isAtiva ? 'table-light opacity-75' : '' ?>">
                                <td class="ps-3 fw-bold text-muted">#<?= $eq['id'] ?></td>
                                <td>
                                    <span class="fw-bold text-dark d-block"><?= htmlspecialchars($eq['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <small class="text-muted"><i class="bi bi-mortarboard me-1"></i><?= htmlspecialchars($eq['turma'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td class="text-center">
                                    <?php if ($eq['grupo']): ?>
                                        <span class="badge <?= $eq['grupo'] === 'A' ? 'bg-primary' : 'bg-info text-dark' ?> fs-6">
                                            Gr. <?= $eq['grupo'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <i class="bi bi-palette-fill me-1 text-secondary"></i>
                                    <?= htmlspecialchars($eq['cor_uniforme'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($aptidao['apta']): ?>
                                        <span class="badge bg-success" title="<?= $aptidao['motivo'] ?>">
                                            <i class="bi bi-check2-circle me-1"></i><?= $aptidao['total_atletas'] ?> Atletas (Apta)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" title="<?= $aptidao['motivo'] ?>">
                                            <i class="bi bi-exclamation-triangle me-1"></i><?= $aptidao['total_atletas'] ?> / Mín: 7 (Incompleta)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isAtiva): ?>
                                        <span class="badge bg-success">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativa (RN12)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Botão Editar -->
                                        <button type="button" class="btn btn-outline-secondary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalEditarEquipe"
                                                data-id="<?= $eq['id'] ?>"
                                                data-turma="<?= htmlspecialchars($eq['turma'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-nome="<?= htmlspecialchars($eq['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-grupo="<?= $eq['grupo'] ?>"
                                                data-cor="<?= htmlspecialchars($eq['cor_uniforme'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-situacao="<?= $eq['situacao'] ?>"
                                                title="Editar Equipe">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>

                                        <!-- Botão Inativar / Reativar (RN12) -->
                                        <?php if ($isAtiva): ?>
                                            <form method="POST" action="<?= $baseUrl ?>/app/controllers/EquipeController.php?action=inativar" class="d-inline" onsubmit="return confirm('RN12: Deseja inativar esta equipe? Ela será mantida no banco para preservar o histórico de partidas.');">
                                                <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                                                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                                                <button type="submit" class="btn btn-outline-warning" title="Inativar Equipe (RN12)">
                                                    <i class="bi bi-pause-circle"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="<?= $baseUrl ?>/app/controllers/EquipeController.php?action=reativar" class="d-inline">
                                                <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                                                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                                                <button type="submit" class="btn btn-outline-success" title="Reativar Equipe">
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Botão Excluir (apenas se sem vínculos) -->
                                        <?php 
                                            $podeExcluir = $equipeModel->podeExcluirFisicamente((int)$eq['id']);
                                            if ($podeExcluir['pode']):
                                        ?>
                                            <form method="POST" action="<?= $baseUrl ?>/app/controllers/EquipeController.php?action=excluir" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir esta equipe permanentemente?');">
                                                <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                                                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Excluir Permanentemente">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
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

<!-- Modal: Cadastrar Nova Equipe -->
<div class="modal fade" id="modalNovaEquipe" tabindex="-1" aria-labelledby="modalNovaEquipeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= $baseUrl ?>/app/controllers/EquipeController.php?action=cadastrar">
                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="modalNovaEquipeLabel"><i class="bi bi-shield-plus me-2"></i>Nova Equipe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome da Equipe <span class="text-danger">*</span></label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Trovão Handebol" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Turma / Sala <span class="text-danger">*</span></label>
                        <input type="text" name="turma" class="form-control" placeholder="Ex: 3º Ensino Médio A" required maxlength="50">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Grupo da Copa <span class="text-danger">*</span></label>
                            <select name="grupo" class="form-select" required>
                                <option value="A">Grupo A</option>
                                <option value="B">Grupo B</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cor do Uniforme <span class="text-danger">*</span></label>
                            <input type="text" name="cor_uniforme" class="form-control" placeholder="Ex: Azul e Branco" required maxlength="50">
                        </div>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i>Regra RN05: Cada equipe cadastrada deverá inscrever entre 7 e 14 atletas aptos para disputar as partidas.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2 me-1"></i>Salvar Equipe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Editar Equipe -->
<div class="modal fade" id="modalEditarEquipe" tabindex="-1" aria-labelledby="modalEditarEquipeLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?= $baseUrl ?>/app/controllers/EquipeController.php?action=editar">
                <input type="hidden" name="id" id="edit_equipe_id">
                <input type="hidden" name="edicao_id" value="<?= $edicaoSelecionadaId ?>">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalEditarEquipeLabel"><i class="bi bi-pencil-square me-2"></i>Editar Equipe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome da Equipe <span class="text-danger">*</span></label>
                        <input type="text" name="nome" id="edit_equipe_nome" class="form-control" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Turma / Sala <span class="text-danger">*</span></label>
                        <input type="text" name="turma" id="edit_equipe_turma" class="form-control" required maxlength="50">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Grupo da Copa <span class="text-danger">*</span></label>
                            <select name="grupo" id="edit_equipe_grupo" class="form-select" required>
                                <option value="A">Grupo A</option>
                                <option value="B">Grupo B</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cor do Uniforme <span class="text-danger">*</span></label>
                            <input type="text" name="cor_uniforme" id="edit_equipe_cor" class="form-control" required maxlength="50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Situação da Equipe</label>
                        <select name="situacao" id="edit_equipe_situacao" class="form-select">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo (RN12)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark btn-sm"><i class="bi bi-save me-1"></i>Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEditar = document.getElementById('modalEditarEquipe');
    if (modalEditar) {
        modalEditar.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit_equipe_id').value = button.getAttribute('data-id');
            document.getElementById('edit_equipe_nome').value = button.getAttribute('data-nome');
            document.getElementById('edit_equipe_turma').value = button.getAttribute('data-turma');
            document.getElementById('edit_equipe_grupo').value = button.getAttribute('data-grupo');
            document.getElementById('edit_equipe_cor').value = button.getAttribute('data-cor');
            document.getElementById('edit_equipe_situacao').value = button.getAttribute('data-situacao');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
