<?php
require_once 'db_connect.php';

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_lojas':
            $stmt = $pdo->query("SELECT DISTINCT l.* FROM lojas l 
                                INNER JOIN estoque_lojas el ON l.id = el.loja_id 
                                WHERE el.quantidade > 0 
                                ORDER BY l.nome");
            $lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'lojas' => $lojas]);
            exit;
            
        case 'get_produtos_loja':
            $loja_id = (int)($_POST['loja_id'] ?? 0);
            
            $sql = "SELECT el.*, p.nome, p.categoria 
                    FROM estoque_lojas el
                    LEFT JOIN produtos p ON el.produto_id = p.id
                    WHERE el.loja_id = ? AND el.quantidade > 0
                    ORDER BY p.nome";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$loja_id]);
            $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'produtos' => $produtos]);
            exit;
            
        case 'processar_venda':
            $loja_id = (int)($_POST['loja_id'] ?? 0);
            $produto_id = (int)($_POST['produto_id'] ?? 0);
            $quantidade = (int)($_POST['quantidade'] ?? 0);
            $preco_unitario = (float)($_POST['preco_unitario'] ?? 0);
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? '';
            
            try {
                $pdo->beginTransaction();
                
                // Verificar estoque da loja
                $stmt = $pdo->prepare("SELECT quantidade FROM estoque_lojas WHERE loja_id = ? AND produto_id = ?");
                $stmt->execute([$loja_id, $produto_id]);
                $estoque_loja = $stmt->fetchColumn();
                
                if ($estoque_loja < $quantidade) {
                    throw new Exception('Estoque insuficiente na loja');
                }
                
                // Registrar venda
                $total = $quantidade * $preco_unitario;
                $stmt = $pdo->prepare("INSERT INTO vendas (loja_id, produto_id, quantidade, preco_unitario, total, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$loja_id, $produto_id, $quantidade, $preco_unitario, $total, $metodo_pagamento]);
                
                // Reduzir estoque da loja
                $stmt = $pdo->prepare("UPDATE estoque_lojas SET quantidade = quantidade - ? WHERE loja_id = ? AND produto_id = ?");
                $stmt->execute([$quantidade, $loja_id, $produto_id]);
                
                // Remover produto se quantidade for zero
                $stmt = $pdo->prepare("DELETE FROM estoque_lojas WHERE loja_id = ? AND produto_id = ? AND quantidade = 0");
                $stmt->execute([$loja_id, $produto_id]);
                
                $pdo->commit();
                echo json_encode(['success' => true, 'venda_id' => $pdo->lastInsertId()]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit;
        case 'processar_solicitacao':
            $itens = json_decode($_POST['itens'] ?? '[]', true);
            $metodo_pagamento = $_POST['metodo_pagamento'] ?? 'Solicitação';
            $pdo->beginTransaction();
            try {
                foreach ($itens as $item) {
                    $loja_id = (int)($item['loja_id'] ?? 0);
                    $produto_id = (int)($item['produto_id'] ?? 0);
                    $quantidade = (int)($item['quantidade'] ?? 0);
                    $preco_unitario = (float)($item['preco_unitario'] ?? 0);
                    
                    // Verificar estoque da loja
                    $stmt = $pdo->prepare("SELECT quantidade FROM estoque_lojas WHERE loja_id = ? AND produto_id = ?");
                    $stmt->execute([$loja_id, $produto_id]);
                    $estoque_loja = $stmt->fetchColumn();
                    
                    if ($estoque_loja < $quantidade) {
                        throw new Exception("Estoque insuficiente na loja para o produto ID $produto_id");
                    }
                    
                    // Registrar venda como solicitação
                    $total = $quantidade * $preco_unitario;
                    $stmt = $pdo->prepare("INSERT INTO vendas (loja_id, produto_id, quantidade, preco_unitario, total, metodo_pagamento) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$loja_id, $produto_id, $quantidade, $preco_unitario, $total, $metodo_pagamento]);
                    
                    // Reduzir estoque da loja
                    $stmt = $pdo->prepare("UPDATE estoque_lojas SET quantidade = quantidade - ? WHERE loja_id = ? AND produto_id = ?");
                    $stmt->execute([$quantidade, $loja_id, $produto_id]);
                    
                    // Remover produto se quantidade for zero
                    $stmt = $pdo->prepare("DELETE FROM estoque_lojas WHERE loja_id = ? AND produto_id = ? AND quantidade = 0");
                    $stmt->execute([$loja_id, $produto_id]);
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Solicitação processada com sucesso']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_vendas':
            $loja_id = $_POST['loja_id'] ?? '';
            $data_inicio = $_POST['data_inicio'] ?? '';
            $data_fim = $_POST['data_fim'] ?? '';
            
            $sql = "SELECT v.*, l.nome as loja_nome, p.nome as produto_nome, p.categoria
                    FROM vendas v
                    LEFT JOIN lojas l ON v.loja_id = l.id
                    LEFT JOIN produtos p ON v.produto_id = p.id
                    WHERE 1=1";
            $params = [];
            
            if ($loja_id && $loja_id !== 'todas') {
                $sql .= " AND v.loja_id = ?";
                $params[] = $loja_id;
            }
            
            if ($data_inicio) {
                $sql .= " AND DATE(v.created_at) >= ?";
                $params[] = $data_inicio;
            }
            
            if ($data_fim) {
                $sql .= " AND DATE(v.created_at) <= ?";
                $params[] = $data_fim;
            }
            
            $sql .= " ORDER BY v.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'vendas' => $vendas]);
            exit;
            
        case 'get_estatisticas_vendas':
            $loja_id = $_POST['loja_id'] ?? '';
            $data_inicio = $_POST['data_inicio'] ?? '';
            $data_fim = $_POST['data_fim'] ?? '';
            
            $where_conditions = ["1=1"];
            $params = [];
            
            if ($loja_id && $loja_id !== 'todas') {
                $where_conditions[] = "loja_id = ?";
                $params[] = $loja_id;
            }
            
            if ($data_inicio) {
                $where_conditions[] = "DATE(created_at) >= ?";
                $params[] = $data_inicio;
            }
            
            if ($data_fim) {
                $where_conditions[] = "DATE(created_at) <= ?";
                $params[] = $data_fim;
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            
            // Total de vendas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE $where_clause");
            $stmt->execute($params);
            $total_vendas = $stmt->fetchColumn();
            
            // Faturamento total
            $stmt = $pdo->prepare("SELECT SUM(total) FROM vendas WHERE $where_clause");
            $stmt->execute($params);
            $faturamento_total = $stmt->fetchColumn() ?: 0;
            
            // Produtos vendidos
            $stmt = $pdo->prepare("SELECT SUM(quantidade) FROM vendas WHERE $where_clause");
            $stmt->execute($params);
            $produtos_vendidos = $stmt->fetchColumn() ?: 0;
            
            // Ticket médio
            $ticket_medio = $total_vendas > 0 ? $faturamento_total / $total_vendas : 0;
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_vendas' => $total_vendas,
                    'faturamento_total' => $faturamento_total,
                    'produtos_vendidos' => $produtos_vendidos,
                    'ticket_medio' => $ticket_medio
                ]
            ]);
            exit;
    }
}

// Buscar dados para exibição inicial
$stmt = $pdo->query("SELECT DISTINCT l.* FROM lojas l 
                    INNER JOIN estoque_lojas el ON l.id = el.loja_id 
                    WHERE el.quantidade > 0 
                    ORDER BY l.nome");
$lojas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Vendas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendas.css">
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <button class="btn-back" onclick="window.location.href='Estoque.php'">
                    <i class="fas fa-arrow-left"></i>
                    Voltar
                </button>
                <div class="divider"></div>
                <div class="header-title">
                    <h1>Sistema de Vendas</h1>
                    <p>Gerenciamento de vendas por loja</p>
                </div>
            </div>
            <div class="header-right">
                <span class="contador-carrinho" id="contador-carrinho">0 itens no carrinho</span>
                <a href="Estoque.php" class="btn btn-secondary">
                    <i class="fas fa-boxes"></i>
                    Estoque
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid" id="stats-container">
            <!-- Stats serão carregados via JavaScript -->
        </div>

        <div class="main-grid">
            <!-- Produtos Disponíveis -->
            <div class="produtos-section">
                <div class="card">
                    <div class="card-header">
                        <h2>Produtos Disponíveis</h2>
                        <div class="filtros">
                            <div class="busca-container">
                                <i class="fas fa-search busca-icon"></i>
                                <input type="text" id="busca-produto" class="input-busca" placeholder="Buscar produtos...">
                            </div>
                            <div class="categoria-container">
                                <select id="loja-filtro" class="select-categoria">
                                    <option value="">Selecione uma loja</option>
                                    <?php foreach ($lojas as $loja): ?>
                                        <option value="<?php echo $loja['id']; ?>"><?php echo htmlspecialchars($loja['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="produtos-grid" id="produtos-grid">
                        <div class="empty-state">
                            <i class="fas fa-store"></i>
                            <h3>Selecione uma loja</h3>
                            <p>Escolha uma loja para ver os produtos disponíveis</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Carrinho -->
            <div class="carrinho-section">
                <div class="card carrinho-card">
                    <div class="card-header">
                        <h2 class="carrinho-titulo">
                            <i class="fas fa-shopping-cart"></i>
                            Carrinho de Vendas
                        </h2>
                    </div>
                    <div class="carrinho-content">
                        <div id="carrinho-vazio" class="carrinho-vazio">
                            <i class="fas fa-shopping-cart" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                            <p>Carrinho vazio</p>
                            <small>Adicione produtos para começar a venda</small>
                        </div>
                        
                        <div id="carrinho-itens" class="carrinho-itens hidden">
                            <!-- Itens do carrinho serão adicionados via JavaScript -->
                        </div>
                        
                        <div id="carrinho-total" class="carrinho-total hidden">
                            <div class="total-linha">
                                <span>Total (<span id="total-itens">0</span> itens)</span>
                                <span id="valor-total">R$ 0,00</span>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer" style="padding: 1rem; border-top: 1px solid #e2e8f0;">
                        <button id="btn-finalizar" class="btn btn-primary" style="width: 100%;" disabled>
                            <i class="fas fa-shopping-cart"></i>
                            Finalizar Venda (R$ 0,00)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Histórico de Vendas -->
        <div class="card" style="margin-top: 2rem;">
            <div class="card-header">
                <h2>Histórico de Vendas</h2>
                <div class="filtros">
                    <div class="busca-container">
                        <select id="loja-historico" class="select-categoria">
                            <option value="todas">Todas as lojas</option>
                            <?php foreach ($lojas as $loja): ?>
                                <option value="<?php echo $loja['id']; ?>"><?php echo htmlspecialchars($loja['nome']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="categoria-container">
                        <input type="date" id="data-inicio" class="input-busca" style="padding-left: 1rem;">
                    </div>
                    <div class="categoria-container">
                        <input type="date" id="data-fim" class="input-busca" style="padding-left: 1rem;">
                    </div>
                </div>
            </div>
            <div class="content-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Loja</th>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Preço Unitário</th>
                                <th>Total</th>
                                <th>Pagamento</th>
                            </tr>
                        </thead>
                        <tbody id="vendas-tbody">
                            <!-- Vendas serão carregadas via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Checkout -->
    <div id="modal-checkout" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3>Finalizar Venda</h3>
                <p>Confirme os detalhes da sua venda</p>
            </div>
            <div class="modal-content">
                <div class="resumo-compra">
                    <h4>Resumo da Venda</h4>
                    <div id="resumo-itens" class="resumo-itens">
                        <!-- Itens serão adicionados via JavaScript -->
                    </div>
                    <div class="resumo-total">
                        <span>Total:</span>
                        <span id="resumo-valor-total">R$ 0,00</span>
                    </div>
                </div>
                
                <div class="pagamento-opcoes">
                    <h4>Forma de Pagamento</h4>
                    <div class="opcoes-grid">
                        <button class="opcao-pagamento" data-metodo="Dinheiro">
                            <i class="fas fa-money-bill-wave"></i>
                            <span>Dinheiro</span>
                        </button>
                        <button class="opcao-pagamento" data-metodo="Cartão de Débito">
                            <i class="fas fa-credit-card"></i>
                            <span>Cartão de Débito</span>
                        </button>
                        <button class="opcao-pagamento" data-metodo="Cartão de Crédito">
                            <i class="fas fa-credit-card"></i>
                            <span>Cartão de Crédito</span>
                        </button>
                        <button class="opcao-pagamento" data-metodo="PIX">
                            <i class="fas fa-qrcode"></i>
                            <span>PIX</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btn-cancelar-checkout" class="btn btn-secondary">Cancelar</button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação -->
    <div id="modal-confirmacao" class="modal-overlay">
        <div class="modal modal-confirmacao">
            <div class="confirmacao-content">
                <div class="checkmark-container">
                    <div class="checkmark">
                        <i class="fas fa-check"></i>
                    </div>
                </div>
                <h3>Venda Realizada!</h3>
                <p>Pagamento via <span id="metodo-selecionado">-</span></p>
                <div class="valor-confirmacao">
                    Total: <span id="valor-confirmacao">R$ 0,00</span>
                </div>
                <button id="btn-nova-venda" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Nova Venda
                </button>
            </div>
        </div>
    </div>

    <script src="vendas.js"></script>

    <!-- Estilos adicionais para integração com o estoque -->
    <style>
        .content-body {
            padding: 1.5rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .table td {
            font-size: 0.875rem;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .card-footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .stat-title {
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
        }

        .stat-icon {
            padding: 0.5rem;
            border-radius: 0.5rem;
            color: white;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-description {
            font-size: 0.75rem;
            color: #64748b;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #64748b;
            padding: 3rem 1rem;
            grid-column: 1 / -1;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 0.875rem;
        }
    </style>
</body>
</html>
