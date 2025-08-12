<?php
require_once 'db_connect.php';

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $acao = $_POST['acao'] ?? '';
    
    switch ($acao) {
        case 'buscar_produto':
            $id = $_POST['id'] ?? 0;
            $produto = buscarProdutoPorId($pdo, $id);
            echo json_encode($produto);
            exit;
            
        case 'adicionar_produto':
            try {
                $dados = [
                    'nome' => $_POST['nome'],
                    'categoria' => $_POST['categoria'],
                    'localizacao' => $_POST['localizacao'],
                    'zona' => $_POST['zona'],
                    'prateleira' => $_POST['prateleira'],
                    'quantidade' => intval($_POST['quantidade']),
                    'estoque_minimo' => intval($_POST['estoque_minimo']),
                    'estoque_maximo' => intval($_POST['estoque_maximo']),
                    'unidade' => $_POST['unidade'],
                    'custo' => floatval($_POST['custo']),
                    'preco_venda' => floatval($_POST['preco_venda']),
                    'fornecedor' => $_POST['fornecedor']
                ];
                
                if (inserirProduto($pdo, $dados)) {
                    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto adicionado com sucesso!']);
                } else {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao adicionar produto.']);
                }
            } catch (Exception $e) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
            }
            exit;
            
        case 'editar_produto':
            try {
                $id = $_POST['id'];
                $dados = [
                    'nome' => $_POST['nome'],
                    'categoria' => $_POST['categoria'],
                    'localizacao' => $_POST['localizacao'],
                    'zona' => $_POST['zona'],
                    'prateleira' => $_POST['prateleira'],
                    'quantidade' => intval($_POST['quantidade']),
                    'estoque_minimo' => intval($_POST['estoque_minimo']),
                    'estoque_maximo' => intval($_POST['estoque_maximo']),
                    'unidade' => $_POST['unidade'],
                    'custo' => floatval($_POST['custo']),
                    'preco_venda' => floatval($_POST['preco_venda']),
                    'fornecedor' => $_POST['fornecedor']
                ];
                
                if (atualizarProduto($pdo, $id, $dados)) {
                    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto atualizado com sucesso!']);
                } else {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao atualizar produto.']);
                }
            } catch (Exception $e) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
            }
            exit;
            
        case 'excluir_produto':
            try {
                $id = $_POST['id'];
                if (deletarProduto($pdo, $id)) {
                    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto excluído com sucesso!']);
                } else {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao excluir produto.']);
                }
            } catch (Exception $e) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
            }
            exit;

        case 'enviar_solicitacao':
            try {
                $itens = json_decode($_POST['itens'] ?? '[]', true);

                if (empty($itens)) {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Itens são obrigatórios.']);
                    exit;
                }

                $solicitacao_id = inserirSolicitacao($pdo, '', $itens);

                echo json_encode(['sucesso' => true, 'mensagem' => 'Solicitação enviada com sucesso!', 'id' => $solicitacao_id]);
            } catch (Exception $e) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao enviar solicitação: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Buscar dados para exibição
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'categoria' => $_GET['categoria'] ?? 'all',
    'status' => $_GET['status'] ?? 'all'
];

$produtos = buscarProdutos($pdo, $filtros);
$estatisticas = obterEstatisticas($pdo);
$categorias = [
    "Amortecedores",
    "Anéis de Pistão",
    "Árvores de Cames",
    "Barras de Torção",
    "Bomba de Água",
    "Bomba de Óleo",
    "Braços de Controle",
    "Cabeçote",
    "Capô",
    "Cárter",
    "Cilindro de Roda",
    "Cilindro Mestre",
    "Discos de Freio",
    "Embreagem",
    "Espelhos Retrovisores",
    "Faróis",
    "Junta Homocinética",
    "Juntas Esféricas",
    "Lanternas",
    "Mangueira de Freio",
    "Molas",
    "Pastilhas de Freio",
    "Pistões",
    "Pivôs",
    "Portas",
    "Rolamentos de Roda",
    "Sapatas de Freio",
    "Tambor de Freio",
    "Transmissão Automática",
    "Transmissão Manual",
    "Tubo de Freio",
    "Válvulas",
    "Vidros"
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Estoque</title>
    <link rel="stylesheet" href="Estoque.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-title">
                <h1>
                    Sistema de Estoque
                </h1>
                <p>Gerenciamento e endereçamento de inventário</p>
            </div>
            <div class="header-actions">
                <a href="Vendas.php" class="btn btn-success">
                    <svg class="icon" viewBox="0 0 24 24">
                        <circle cx="8" cy="21" r="1"/>
                        <circle cx="19" cy="21" r="1"/>
                        <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57L23 6H6"/>
                    </svg>
                    Sistema de Vendas
                </a>
                <button class="btn btn-primary" onclick="abrirModalAdicionar()">
                    <svg class="icon" viewBox="0 0 24 24">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Adicionar Item
                </button>
            </div>
        </div>
    </header>

    <main class="main">
        <!-- Cards de Estatísticas -->
        <div class="stats-grid">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Total de Itens</h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="2" ry="2" stroke="#6b7280" fill="none"></rect>
                        <line x1="3" y1="10" x2="21" y2="10" stroke="#6b7280"></line>
                        <line x1="9" y1="4" x2="9" y2="20" stroke="#6b7280"></line>
                    </svg>
                </div>
                <div class="card-content">
                    <div class="card-value"><?= $estatisticas['total_produtos'] ?></div>
                    <p class="card-description">produtos cadastrados</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Estoque Baixo</h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="12 2 2 22 22 22 12 2" stroke="#f97316" fill="none"></polygon>
                        <line x1="12" y1="16" x2="12" y2="12" stroke="#f97316"></line>
                        <line x1="12" y1="8" x2="12" y2="8" stroke="#f97316"></line>
                    </svg>
                </div>
                <div class="card-content">
                    <div class="card-value text-yellow-600"><?= $estatisticas['estoque_baixo'] ?></div>
                    <p class="card-description">itens precisam reposição</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Sem Estoque</h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10" stroke="#dc2626" fill="none"></circle>
                        <line x1="15" y1="9" x2="9" y2="15" stroke="#dc2626"></line>
                        <line x1="9" y1="9" x2="15" y2="15" stroke="#dc2626"></line>
                    </svg>
                </div>
                <div class="card-content">
                    <div class="card-value text-red-600"><?= $estatisticas['sem_estoque'] ?></div>
                    <p class="card-description">itens esgotados</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Valor Total</h3>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1v22" stroke="#22c55e"></path>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H16a3.5 3.5 0 0 1 0 7H7" stroke="#22c55e"></path>
                    </svg>
                </div>
                <div class="card-content">
                    <div class="card-value text-green-600">R$ <?= number_format($estatisticas['valor_total'], 2, ',', '.') ?></div>
                    <p class="card-description">valor do inventário</p>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <svg class="icon" viewBox="0 0 24 24">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                    </svg>
                    Filtros e Busca
                </h3>
            </div>
            <div class="card-content">
                <form method="GET" class="filters-form">
                    <div class="filter-group">
                        <label for="busca">Buscar</label>
                        <div class="search-container">
                            <svg class="search-icon" viewBox="0 0 24 24">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="M21 21l-4.35-4.35"/>
                            </svg>
                            <input type="text" id="busca" name="busca" class="input with-icon"
                                   placeholder="Buscar por nome ou localização..."
                                   value="<?= htmlspecialchars($filtros['busca']) ?>">
                        </div>
                    </div>
                    
                    <div class="filter-group narrow">
                        <label for="categoria">Categoria</label>
                        <select id="categoria" name="categoria" class="select">
                            <option value="all">Todas as categorias</option>
                            <?php if (!empty($categorias)): ?>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= htmlspecialchars($categoria) ?>"
                                            <?= $filtros['categoria'] === $categoria ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($categoria) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group narrow">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="select">
                            <option value="all">Todos os status</option>
                            <option value="in_stock" <?= $filtros['status'] === 'in_stock' ? 'selected' : '' ?>>Em Estoque</option>
                            <option value="low_stock" <?= $filtros['status'] === 'low_stock' ? 'selected' : '' ?>>Estoque Baixo</option>
                            <option value="out_of_stock" <?= $filtros['status'] === 'out_of_stock' ? 'selected' : '' ?>>Sem Estoque</option>
                        </select>
                    </div>
                    
                    <!-- Botão Filtrar removido - busca automática ao digitar -->
                </form>
            </div>
        </div>

        <!-- Tabela de Produtos -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Inventário</h3>
                <p class="card-description"><?= count($produtos) ?> de <?= $estatisticas['total_produtos'] ?> itens</p>
            </div>
            <div class="card-content">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Categoria</th>
                                <th>Localização</th>
<th>Quantidade</th>
<th>Informações ADC</th>
<th>Status</th>
<th>Valor</th>
<th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($produtos)): ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 2rem; color: #64748b;">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                                            <svg class="icon-lg" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="16" rx="2" ry="2" stroke="#6b7280" fill="none"></rect>
                                            <line x1="3" y1="10" x2="21" y2="10" stroke="#6b7280"></line>
                                            <line x1="9" y1="4" x2="9" y2="20" stroke="#6b7280"></line>
                                            </svg>
                                            <div>
                                                <h3 style="font-weight: 600; margin-bottom: 0.5rem;">Nenhum produto cadastrado</h3>
                                                <p>Comece adicionando seu primeiro produto ao estoque</p>
                                                <button class="btn btn-primary" onclick="abrirModalAdicionar()" style="margin-top: 1rem;">
                                                    <svg class="icon" viewBox="0 0 24 24">
                                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                                    </svg>
                                                    Adicionar Primeiro Produto
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($produtos as $produto): ?>
                                    <tr>
                                        <td class="font-medium"><?= htmlspecialchars($produto['nome']) ?></td>
                                        <td><?= htmlspecialchars($produto['categoria']) ?></td>
                                        <td>
                                            <div class="location">
                                                <svg class="location-icon" viewBox="0 0 24 24">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                                <span><?= htmlspecialchars($produto['localizacao'] . ' - ' . $produto['zona'] . ' - ' . $produto['prateleira']) ?></span>
                                            </div>
                                        </td>
<td>
    <span class="font-medium"><?= $produto['quantidade'] ?></span>
</td>
<td>
    <?= htmlspecialchars($produto['unidade']) ?>
</td>
<td>
    <?php
    $badgeClass = '';
    $statusText = '';
    switch ($produto['status']) {
        case 'in_stock':
            $badgeClass = 'badge-success';
            $statusText = 'Em Estoque';
            break;
        case 'low_stock':
            $badgeClass = 'badge-warning';
            $statusText = 'Estoque Baixo';
            break;
        case 'out_of_stock':
            $badgeClass = 'badge-danger';
            $statusText = 'Sem Estoque';
            break;
    }
    ?>
    <span class="badge <?= $badgeClass ?>"><?= $statusText ?></span>
</td>
                                        <td>R$ <?= number_format($produto['quantidade'] * $produto['custo'], 2, ',', '.') ?></td>
                                        <td class="text-right">
                                            <div class="actions">
                                                <button class="btn btn-ghost" onclick="visualizarProduto(<?= $produto['id'] ?>)">
                                                    <svg class="icon-sm" viewBox="0 0 24 24">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                                        <circle cx="12" cy="12" r="3"/>
                                                    </svg>
                                                </button>
                                                <button class="btn btn-ghost" onclick="editarProduto(<?= $produto['id'] ?>)">
                                                    <svg class="icon-sm" viewBox="0 0 24 24">
                                                        <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
                                                    </svg>
                                                </button>
                                                <button class="btn btn-ghost btn-danger" onclick="excluirProduto(<?= $produto['id'] ?>, '<?= htmlspecialchars($produto['nome']) ?>')">
                                                    <svg class="icon-sm" viewBox="0 0 24 24">
                                                        <polyline points="3,6 5,6 21,6"/>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                        <line x1="10" y1="11" x2="10" y2="17"/>
                                                        <line x1="14" y1="11" x2="14" y2="17"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Seção de Solicitação de Produtos -->
    <section class="solicitacao-produtos card" style="margin: 2rem auto; max-width: 900px; padding: 1rem;">
        <h3>Solicitar Produtos do Estoque</h3>
        <p>Gerencie suas solicitações</p>
        <form id="formSolicitacao" onsubmit="return false;" style="margin-bottom: 1rem;">
            <div class="form-group" style="width: fit-content;">
                <button type="button" class="btn btn-primary" onclick="abrirModalAdicionarSolicitacao()">
                    <svg class="icon" viewBox="0 0 24 24">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Adicionar Produto
                </button>
            </div>
        </form>

        <div id="listaProdutosSolicitados" style="margin-top: 1rem;">
            <h4>Produtos Solicitados</h4>
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Loja</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Unidade</th>
                        <th>Preço de Venda</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="corpoTabelaProdutosSolicitados">
                    <!-- Produtos adicionados aparecerão aqui -->
                </tbody>
            </table>
        </div>

        <button type="button" class="btn btn-success" style="margin-top: 1rem;" onclick="enviarSolicitacao()">Enviar Solicitação</button>
    </section>

    <!-- Modal Adicionar Produto Solicitado -->
    <div id="modalAdicionarSolicitacao" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Adicionar Produto à Solicitação</h2>
                <p class="modal-description">Adicione um produto à lista de produtos solicitados</p>
            </div>
            <div class="modal-content">
                <form id="formAdicionarSolicitacao" class="form-grid">
                    <div class="form-group">
                        <label for="lojaSolicitadaModal">Loja</label>
                        <input type="text" id="lojaSolicitadaModal" name="lojaSolicitadaModal" class="input" placeholder="Nome da loja" required>
                    </div>
                    <div class="form-group">
                        <label for="produtoSolicitadoModal">Produto</label>
                        <select id="produtoSolicitadoModal" name="produtoSolicitadoModal" class="select" required>
                            <option value="">Selecione um produto</option>
                            <?php foreach ($produtos as $produto): ?>
                                <option value="<?= $produto['id'] ?>" data-unidade="<?= htmlspecialchars($produto['unidade']) ?>" data-preco="<?= $produto['preco_venda'] ?>" data-stock="<?= $produto['quantidade'] ?>">
                                    <?= htmlspecialchars($produto['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantidadeSolicitadaModal">Quantidade</label>
                        <input type="number" id="quantidadeSolicitadaModal" name="quantidadeSolicitadaModal" class="input" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <input type="text" id="unidadeSolicitadaModal" class="input" readonly>
                    </div>
                    <div class="form-group">
                        <label>Preço de Venda</label>
                        <input type="text" id="precoSolicitadoModal" class="input" readonly>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModal('modalAdicionarSolicitacao')">Cancelar</button>
                <button class="btn btn-primary" onclick="adicionarProdutoSolicitadoModal()">Adicionar Produto</button>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar -->
    <div id="modalVisualizar" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Detalhes do Item</h2>
                <p class="modal-description">Informações completas do produto no estoque</p>
            </div>
            <div class="modal-content">
                <div id="conteudoVisualizacao" class="form-grid">
                    <!-- Conteúdo será preenchido pelo JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModal('modalVisualizar')">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Modal Adicionar/Editar -->
    <div id="modalFormulario" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="tituloFormulario">Adicionar Novo Item</h2>
                <p class="modal-description">Cadastre um novo produto no sistema de estoque</p>
            </div>
            <div class="modal-content">
                <form id="formProduto" class="form-grid">
                    <input type="hidden" id="produtoId" name="id">
                    <div class="form-group">
                        <label for="nome">Especificação do Produto</label>
<input type="text" id="nome" name="nome" class="input" placeholder="Ex: Disco Freio Traseiro" required>
                    </div>
                    <div class="form-group">
                        <label for="categoria">Categoria</label>
<select id="categoria" name="categoria" class="select" required>
                            <option value="">Selecione uma categoria</option>
                            <?php if (!empty($categorias)): ?>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= htmlspecialchars($categoria) ?>"><?= htmlspecialchars($categoria) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fornecedor">Fornecedor</label>
<input type="text" id="fornecedor" name="fornecedor" class="input" placeholder="Nome do fornecedor" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Localização</label>
                        <div class="form-group-3">
                            <div>
                                <label for="localizacao">Local</label>
<input type="text" id="localizacao" name="localizacao" class="input" placeholder="Galpão A" required>
                            </div>
                            <div>
                                <label for="zona">Zona</label>
<input type="text" id="zona" name="zona" class="input" placeholder="A1" required>
                            </div>
                            <div>
                                <label for="prateleira">Prateleira</label>
<input type="text" id="prateleira" name="prateleira" class="input" placeholder="A1-03" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantidades</label>
                        <div class="form-group-2">
                            <div>
                                <label for="quantidade">Quantidade</label>
<input type="number" id="quantidade" name="quantidade" class="input" placeholder="100" required>
                            </div>
                            <div>
<label for="unidade">Informação ADC.</label>
<input type="text" id="unidade" name="unidade" class="input" placeholder="un" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Estoque Mín./Máx.</label>
                        <div class="form-group-2">
                            <div>
                                <label for="estoque_minimo">Estoque Mín.</label>
<input type="number" id="estoque_minimo" name="estoque_minimo" class="input" placeholder="50" required>
                            </div>
                            <div>
                                <label for="estoque_maximo">Estoque Máx.</label>
<input type="number" id="estoque_maximo" name="estoque_maximo" class="input" placeholder="500" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Custos e Preços</label>
                        <div class="form-group-2">
                            <div>
                                <label for="custo">Custo Unitário (R$)</label>
<input type="number" id="custo" name="custo" class="input" step="0.01" placeholder="0.25" required>
                            </div>
                            <div>
                                <label for="preco_venda">Preço de Venda (R$)</label>
<input type="number" id="preco_venda" name="preco_venda" class="input" step="0.01" placeholder="0.50" required>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModal('modalFormulario')">Cancelar</button>
                <button class="btn btn-primary" onclick="salvarProduto()" id="btnSalvar">Adicionar Item</button>
            </div>
        </div>
    </div>

    <script src="estoque.js"></script>
</body>
</html>
