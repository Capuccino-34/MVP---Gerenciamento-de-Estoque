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

        case 'salvar_distribuicao':
            try {
                $loja = $_POST['loja'] ?? '';
                $produto_id = intval($_POST['produto_id'] ?? 0);
                $quantidade = intval($_POST['quantidade'] ?? 0);
                $preco_venda = floatval($_POST['preco_venda'] ?? 0);

                if (empty($loja) || $produto_id <= 0 || $quantidade <= 0 || $preco_venda <= 0) {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Todos os campos são obrigatórios e devem ser válidos.']);
                    exit;
                }

                // Iniciar transação
                $pdo->beginTransaction();

                // Verificar se o produto existe e tem estoque suficiente
                $produto = buscarProdutoPorId($pdo, $produto_id);
                if (!$produto) {
                    $pdo->rollBack();
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado.']);
                    exit;
                }

                if ($produto['quantidade'] < $quantidade) {
                    $pdo->rollBack();
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Estoque insuficiente. Disponível: ' . $produto['quantidade']]);
                    exit;
                }

                // Diminuir estoque do produto principal
                $nova_quantidade = $produto['quantidade'] - $quantidade;

                // Determinar novo status baseado na quantidade atualizada
                $novo_status = 'in_stock';
                if ($nova_quantidade == 0) {
                    $novo_status = 'out_of_stock';
                } elseif ($nova_quantidade <= $produto['estoque_minimo']) {
                    $novo_status = 'low_stock';
                }

                // Atualizar estoque e status do produto
                $stmt = $pdo->prepare("UPDATE produtos SET quantidade = ?, status = ? WHERE id = ?");
                $stmt->execute([$nova_quantidade, $novo_status, $produto_id]);

                // Inserir na tabela distribuir
                if (inserirProdutoDistribuido($pdo, $loja, $produto_id, $quantidade, $preco_venda)) {
                    $pdo->commit();
                    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto distribuído com sucesso! Estoque atualizado.']);
                } else {
                    $pdo->rollBack();
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao salvar distribuição.']);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
            }
            exit;

        case 'verificar_estoque':
            try {
                $produto_id = intval($_POST['produto_id'] ?? 0);
                $quantidade = intval($_POST['quantidade'] ?? 0);

                if ($produto_id <= 0 || $quantidade <= 0) {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos']);
                    exit;
                }

                $produto = buscarProdutoPorId($pdo, $produto_id);
                if (!$produto) {
                    echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado']);
                    exit;
                }

                $disponivel = $produto['quantidade'];
                $suficiente = $disponivel >= $quantidade;

                // Calcular qual seria o novo status após a distribuição
                $nova_quantidade = $disponivel - $quantidade;
                $novo_status = 'in_stock';
                if ($nova_quantidade == 0) {
                    $novo_status = 'out_of_stock';
                } elseif ($nova_quantidade <= $produto['estoque_minimo']) {
                    $novo_status = 'low_stock';
                }

                echo json_encode([
                    'sucesso' => true,
                    'disponivel' => $disponivel,
                    'suficiente' => $suficiente,
                    'nova_quantidade' => $nova_quantidade,
                    'novo_status' => $novo_status,
                    'estoque_minimo' => $produto['estoque_minimo'],
                    'mensagem' => $suficiente ? 'Estoque disponível' : 'Estoque insuficiente. Disponível: ' . $disponivel
                ]);
            } catch (Exception $e) {
                echo json_encode(['sucesso' => false, 'mensagem' => 'Erro: ' . $e->getMessage()]);
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

$lojas = [
    "Americanas",
    "Amazon",
    "Shein",
    "Shopee",
    "AliExpress",
    "Mercado Livre"
];

$produtosDistribuidosBanco = buscarProdutosDistribuidos($pdo);
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
            <div class="header-title" style="display: flex; align-items: center;">
                <img src="img/Logo (PNG).png" alt="Logo" style="height: 50px; margin-right: 15px;">
                <div>
                    <h1>
                        Sistema de Estoque
                    </h1>
                    <p>Gerenciamento e endereçamento de inventário</p>
                </div>
            </div>
                <!-- Botão movido para a seção de ações do card -->
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
                <div>
                    <h3 class="card-title">Inventário</h3>
                    <p class="card-description"><?= count($produtos) ?> de <?= $estatisticas['total_produtos'] ?> itens</p>
                </div>
                <div class="card-header-actions">
                    <button class="btn btn-primary" onclick="abrirModalAdicionar()">
                        <svg class="icon" viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Adicionar Item
                    </button>
                    <a href="export_xls.php" class="btn btn-secondary">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 1.5V5.5C4 6.32843 4.67157 7 5.5 7H18.5C19.3284 7 20 6.32843 20 5.5V1.5C20 0.671573 19.3284 0 18.5 0H5.5C4.67157 0 4 0.671573 4 1.5Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M4 18.5V22.5C4 23.3284 4.67157 24 5.5 24H18.5C19.3284 24 20 23.3284 20 22.5V18.5C20 17.6716 19.3284 17 18.5 17H5.5C4.67157 17 4 17.6716 4 18.5Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M4 10.5V14.5C4 15.3284 4.67157 16 5.5 16H18.5C19.3284 16 20 15.3284 20 14.5V10.5C20 9.67157 19.3284 9 18.5 9H5.5C4.67157 9 4 9.67157 4 10.5Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        Exportar Excel
                    </a>
                    <a href="export_pdf.php" class="btn btn-secondary">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6C4.89543 2 4 2.89543 4 4V20C4 21.1046 4.89543 22 6 22H18C19.1046 22 20 21.1046 20 20V8L14 2Z" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M14 2V8H20" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10.5 17H13.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M12 17V11" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10.5 11H12.5C13.0523 11 13.5 11.4477 13.5 12V12C13.5 12.5523 13.0523 13 12.5 13H10.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                        Exportar PDF
                    </a>
                    <button class="btn btn-secondary" onclick="abrirModalEtiqueta()">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <path d="M20.4 14.5L16 10 4 20"/>
                        </svg>
                        Gerar Etiqueta
                    </button>
                </div>
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
                                    <td colspan="8" class="text-center" style="padding: 2rem; color: #64748b;">
                                        <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

    <!-- Seção de Distribuição de Produtos -->
    <section class="solicitacao-produtos card" style="margin: 2rem auto; max-width: 1200px; padding: 1rem;">
        <h2>Distribuir Produtos para Loja</h2>
        <p>Gerencie suas distribuições de produtos</p>
        
        <div style="margin-bottom: 1rem;">
            <button type="button" class="btn btn-primary" onclick="abrirModalDistribuirProduto()">
                <svg class="icon" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Adicionar Produto
            </button>
        </div>

        <div id="listaProdutosDistribuidos" style="margin-top: 1rem;">
            <h4>Produtos Distribuídos</h4>
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Loja</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Preço de Venda (R$)</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody id="corpoTabelaProdutosDistribuidos">
                    <?php if (empty($produtosDistribuidosBanco)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">
                                Nenhum produto distribuído
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($produtosDistribuidosBanco as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['loja']) ?></td>
                                <td><?= htmlspecialchars($item['produto_nome'] ?? 'Produto ID: ' . $item['produto']) ?></td>
                                <td><?= (int)$item['quantidade'] ?></td>
                                <td>R$ <?= number_format($item['preco_venda'], 2, ',', '.') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($item['data_distribuicao'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Modal Distribuir Produto -->
    <div id="modalDistribuirProduto" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Distribuir Produto para Loja</h2>
                <p class="modal-description">Preencha os dados para distribuir o produto</p>
            </div>
            <div class="modal-content">
                <form id="formModalDistribuirProduto" class="form-grid">
                    <div class="form-group">
                        <label for="loja_id">Loja</label>
                        <select id="loja_id" name="loja_id" class="select" required>
                            <option value="">Selecione uma loja</option>
                            <?php foreach ($lojas as $loja): ?>
                                <option value="<?= htmlspecialchars($loja) ?>"><?= htmlspecialchars($loja) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="produto_id">Produto</label>
                        <select id="produto_id" name="produto_id" class="select" required>
                            <option value="">Selecione um produto</option>
                            <?php foreach ($produtos as $produto): ?>
                                <option value="<?= (int)$produto['id'] ?>" data-stock="<?= (int)$produto['quantidade'] ?>"><?= htmlspecialchars($produto['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="quantidade_dist">Quantidade</label>
                        <input type="number" id="quantidade_dist" name="quantidade_dist" class="input" min="1" required>
                        <small id="estoque-info" style="color: #64748b; font-size: 0.75rem;"></small>
                    </div>
                    <div class="form-group">
                        <label for="preco_venda_dist">Preço de Venda (R$)</label>
                        <input type="number" id="preco_venda_dist" name="preco_venda_dist" class="input" step="0.01" min="0" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModal('modalDistribuirProduto')">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="salvarDistribuicao()">Salvar</button>
            </div>
        </div>
    </div>

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

    <!-- Modal Etiqueta -->
    <div id="modalEtiqueta" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Gerar Etiqueta</h2>
                <p class="modal-description">Gere etiquetas para identificação de produtos</p>
            </div>
            <div class="modal-content">
                <form id="formEtiqueta" class="form-grid">
                    <div class="form-group">
                        <label for="etiquetaLetra">Letra:</label>
                        <select id="etiquetaLetra" class="select" required>
                            <option value="L">L</option>
                            <option value="Z">Z</option>
                            <option value="P">P</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="etiquetaNumero">Número:</label>
                        <input type="number" id="etiquetaNumero" class="input" placeholder="Digite apenas números" required>
                    </div>
                    
                    <div class="form-group">
                        <svg width="250" height="120" id="etiquetaPreview">
                            <path d="M15,20 L170,20 Q180,20 180,30 L180,90 Q180,100 170,100 L15,100 Q5,100 5,90 L5,30 Q5,20 15,20 Z" 
                                  fill="black" stroke="white" stroke-width="3"/>
                            <circle cx="155" cy="60" r="8" fill="white"/>
                            <text x="95" y="70" fill="white" font-family="Arial, sans-serif" font-size="22" font-weight="bold" text-anchor="middle" id="textoEtiqueta">P-11</text>
                        </svg>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="fecharModal('modalEtiqueta')">Cancelar</button>
                <button class="btn btn-primary" onclick="gerarEtiquetaPDF()">Gerar PDF</button>
            </div>
        </div>
    </div>

    <!-- Modal Etiqueta -->
    <div id="modalEtiqueta" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Gerar Etiqueta</h2>
                <p class="modal-description">Gere etiquetas para identificação de produtos</p>
            </div>
            <div class="modal-content">
                <form id="formEtiqueta" class="form-grid">
                    <div class="form-group">
                        <label for="etiquetaLetra">Letra:</label>
                        <select id="etiquetaLetra" name="etiquetaLetra" class="select" required>
                            <option value="L">L</option>
                            <option value="Z">Z</option>
                            <option value="P">P</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="etiquetaNumero">Número:</label>
                        <input type="number" id="etiquetaNumero" name="etiquetaNumero" class="input" placeholder="Digite apenas números" required>
                    </div>
                    
                    <div class="form-group text-center">
                        <h3>Preview da Etiqueta:</h3>
                        <svg width="250" height="120" id="etiquetaPreview">
                            <!-- Etiqueta completa com bordas arredondadas -->
                            <path d="M15,20 L170,20 Q180,20 180,30 L180,90 Q180,100 170,100 L15,100 Q5,100 5,90 L5,30 Q5,20 15,20 Z" 
                                  fill="black" stroke="white" stroke-width="3"/>
                            <!-- Círculo do buraco -->
                            <circle cx="185" cy="30" r="8" fill="white"/>
                            <!-- Texto -->
                            <text x="95" y="65" fill="white" font-family="Arial, sans-serif" font-size="22" font-weight="bold" text-anchor="middle" id="textoEtiqueta">P-11</text>
                        </svg>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="document.getElementById('modalEtiqueta').classList.remove('active'); document.body.style.overflow = 'auto';">Cancelar</button>
                <button class="btn btn-primary" onclick="gerarEtiquetaPDF()">Gerar PDF</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script>
        // Garantir que o jsPDF esteja disponível globalmente
        window.jspdf = window.jspdf || {};
    </script>
    <script src="estoque.js"></script>
    <script src="etiquetas.js"></script>
</body>
</html>
