<?php
// Configurações do banco de dados
$servername = "localhost";
$username = "SakyHell";
$password = "Luc@$123.+inv";
$port = 3306;
$dbname = "sistema_estoque";

try {
    // Primeiro conectar sem especificar o banco para criar se necessário
    $pdo_temp = new PDO("mysql:host=$servername;port=$port;charset=utf8", $username, $password);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Criar banco se não existir
    $pdo_temp->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");

    // Conectar ao banco específico
    $pdo = new PDO("mysql:host=$servername;port=$port;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Criar tabelas automaticamente se não existirem
    criarTabelasAutomaticamente($pdo);

} catch(PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// Função para criar todas as tabelas automaticamente
function criarTabelasAutomaticamente($pdo) {
    try {
        // Criar tabela produtos
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS produtos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                categoria VARCHAR(100) NOT NULL,
                localizacao VARCHAR(100) NOT NULL,
                zona VARCHAR(50) NOT NULL,
                prateleira VARCHAR(50) NOT NULL,
                quantidade INT NOT NULL DEFAULT 0,
                estoque_minimo INT NOT NULL DEFAULT 0,
                estoque_maximo INT NOT NULL DEFAULT 0,
                unidade VARCHAR(20) NOT NULL,
                custo DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                preco_venda DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                fornecedor VARCHAR(255),
                status ENUM('in_stock', 'low_stock', 'out_of_stock') DEFAULT 'in_stock',
                data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Criar tabela vendas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS vendas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                total DECIMAL(10,2) NOT NULL,
                forma_pagamento ENUM('pix', 'card', 'cash') NOT NULL,
                codigo_pagamento VARCHAR(255),
                status_pagamento ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
                data_venda TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Criar tabela itens_venda
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS itens_venda (
                id INT AUTO_INCREMENT PRIMARY KEY,
                venda_id INT NOT NULL,
                produto_id INT NOT NULL,
                quantidade INT NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
                FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
            )
        ");

        // Criar tabela lojas para integração com o sistema de vendas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lojas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL UNIQUE,
                endereco TEXT,
                telefone VARCHAR(20),
                email VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Criar tabela estoque_lojas para controle de estoque por loja
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS estoque_lojas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loja_id INT,
                produto_id INT,
                quantidade INT DEFAULT 0,
                preco_venda DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE CASCADE,
                FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
                UNIQUE KEY unique_loja_produto (loja_id, produto_id)
            )
        ");

        // Criar tabela transferencias para controle de transferências
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transferencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loja_id INT,
                produto_id INT,
                quantidade_solicitada INT,
                quantidade_enviada INT DEFAULT 0,
                preco_venda DECIMAL(10,2),
                status ENUM('pendente', 'aprovada', 'enviada', 'concluida') DEFAULT 'pendente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE CASCADE,
                FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
            )
        ");

        // Criar tabela solicitacoes para pedidos de produtos por lojas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS solicitacoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loja VARCHAR(255) NOT NULL,
                data_solicitacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                status ENUM('pendente', 'enviado', 'cancelado') DEFAULT 'pendente'
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS itens_solicitacao (
                id INT AUTO_INCREMENT PRIMARY KEY,
                solicitacao_id INT NOT NULL,
                produto_id INT NOT NULL,
                quantidade INT NOT NULL,
                preco_venda DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
                FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
            )
        ");

        // Remover coluna SKU se ela existir (para bancos existentes)
        try {
            $pdo->exec("ALTER TABLE produtos DROP COLUMN sku");
        } catch(PDOException $e) {
            // Ignora erro se a coluna não existir
        }

        // Inserir produtos de exemplo se a tabela estiver vazia
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos");
        if ($stmt->fetchColumn() == 0) {
            $produtos_exemplo = [
                ['Disco de freio traseiro 15 cm', 'Discos de Freio', 'Galpão A', 'A5', 'A5-02', 150, 10, 200, 'un', 45.00, 85.00, 'AutoPeças Inc'],
                ['Pastilha de freio dianteira', 'Pastilhas de Freio', 'Galpão B', 'B2', 'B2-01', 85, 5, 150, 'un', 25.00, 55.00, 'Frasle SA'],
                ['Óleo motor 5W30 sintético', 'Óleos', 'Galpão C', 'C1', 'C1-15', 200, 20, 300, 'litro', 15.00, 35.00, 'Castrol Brasil'],
                ['Filtro de ar esportivo K&N', 'Filtros', 'Galpão D', 'D3', 'D3-08', 45, 5, 80, 'un', 75.00, 150.00, 'K&N Filters'],
                ['Bateria automotiva 60Ah', 'Baterias', 'Galpão E', 'E1', 'E1-03', 25, 3, 50, 'un', 120.00, 280.00, 'Moura Baterias']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO produtos (nome, categoria, localizacao, zona, prateleira, quantidade, estoque_minimo, estoque_maximo, unidade, custo, preco_venda, fornecedor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($produtos_exemplo as $produto) {
                $stmt->execute($produto);
            }
        }

        // Inserir lojas de exemplo se a tabela estiver vazia
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM lojas");
        if ($stmt->fetchColumn() == 0) {
            $lojas_exemplo = [
                ['Americanas', 'Shopping Center Norte', '(11) 9999-0001', 'americanas@email.com'],
                ['Atacadão', 'Av. Paulista, 1000', '(11) 9999-0002', 'atacadao@email.com'],
                ['Mercado Livre', 'Centro de Distribuição SP', '(11) 9999-0003', 'ml@email.com'],
                ['Amazon', 'Fulfillment Center', '(11) 9999-0004', 'amazon@email.com'],
                ['Casas Bahia', 'Loja Centro', '(11) 9999-0005', 'casasbahia@email.com'],
                ['Magazine Luiza', 'Loja Shopping', '(11) 9999-0006', 'magalu@email.com']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO lojas (nome, endereco, telefone, email) VALUES (?, ?, ?, ?)");
            foreach ($lojas_exemplo as $loja) {
                $stmt->execute($loja);
            }
        }

    } catch(PDOException $e) {
        // Ignora erros de criação se as tabelas já existem
    }
}

// ✅ Banco e tabelas criados automaticamente

// Função para buscar todos os produtos
function buscarProdutos($pdo, $filtros = []) {
    $sql = "SELECT * FROM produtos WHERE 1=1";
    $params = [];
    
    if (!empty($filtros['busca'])) {
        $sql .= " AND (nome LIKE ? OR localizacao LIKE ?)";
        $busca = '%' . $filtros['busca'] . '%';
        $params[] = $busca;
        $params[] = $busca;
    }
    
    if (!empty($filtros['categoria']) && $filtros['categoria'] !== 'all') {
        $sql .= " AND categoria = ?";
        $params[] = $filtros['categoria'];
    }
    
    if (!empty($filtros['status']) && $filtros['status'] !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $filtros['status'];
    }
    
    $sql .= " ORDER BY nome ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Função para buscar produto por ID
function buscarProdutoPorId($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Função para inserir produto
function inserirProduto($pdo, $dados) {
    $sql = "INSERT INTO produtos (nome, categoria, localizacao, zona, prateleira, quantidade, estoque_minimo, estoque_maximo, unidade, custo, preco_venda, fornecedor, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Determinar status baseado na quantidade
    $status = 'in_stock';
    if ($dados['quantidade'] == 0) {
        $status = 'out_of_stock';
    } elseif ($dados['quantidade'] <= $dados['estoque_minimo']) {
        $status = 'low_stock';
    }

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $dados['nome'],
        $dados['categoria'],
        $dados['localizacao'],
        $dados['zona'],
        $dados['prateleira'],
        $dados['quantidade'],
        $dados['estoque_minimo'],
        $dados['estoque_maximo'],
        $dados['unidade'],
        $dados['custo'],
        $dados['preco_venda'],
        $dados['fornecedor'],
        $status
    ]);
}

// Função para atualizar produto
function atualizarProduto($pdo, $id, $dados) {
    // Determinar status baseado na quantidade
    $status = 'in_stock';
    if ($dados['quantidade'] == 0) {
        $status = 'out_of_stock';
    } elseif ($dados['quantidade'] <= $dados['estoque_minimo']) {
        $status = 'low_stock';
    }

    $sql = "UPDATE produtos SET nome = ?, categoria = ?, localizacao = ?, zona = ?, prateleira = ?, quantidade = ?, estoque_minimo = ?, estoque_maximo = ?, unidade = ?, custo = ?, preco_venda = ?, fornecedor = ?, status = ? WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $dados['nome'],
        $dados['categoria'],
        $dados['localizacao'],
        $dados['zona'],
        $dados['prateleira'],
        $dados['quantidade'],
        $dados['estoque_minimo'],
        $dados['estoque_maximo'],
        $dados['unidade'],
        $dados['custo'],
        $dados['preco_venda'],
        $dados['fornecedor'],
        $status,
        $id
    ]);
}

// Função para deletar produto
function deletarProduto($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
    return $stmt->execute([$id]);
}

// Função para atualizar estoque após venda
function atualizarEstoque($pdo, $produto_id, $quantidade_vendida) {
    $produto = buscarProdutoPorId($pdo, $produto_id);
    if (!$produto) return false;
    
    $nova_quantidade = $produto['quantidade'] - $quantidade_vendida;
    if ($nova_quantidade < 0) return false;
    
    // Determinar novo status
    $status = 'in_stock';
    if ($nova_quantidade == 0) {
        $status = 'out_of_stock';
    } elseif ($nova_quantidade <= $produto['estoque_minimo']) {
        $status = 'low_stock';
    }
    
    $stmt = $pdo->prepare("UPDATE produtos SET quantidade = ?, status = ? WHERE id = ?");
    return $stmt->execute([$nova_quantidade, $status, $produto_id]);
}

// Função para registrar venda
function registrarVenda($pdo, $total, $forma_pagamento, $codigo_pagamento, $itens) {
    try {
        $pdo->beginTransaction();
        
        // Inserir venda
        $stmt = $pdo->prepare("INSERT INTO vendas (total, forma_pagamento, codigo_pagamento, status_pagamento) VALUES (?, ?, ?, 'pago')");
        $stmt->execute([$total, $forma_pagamento, $codigo_pagamento]);
        $venda_id = $pdo->lastInsertId();
        
        // Inserir itens da venda e atualizar estoque
        foreach ($itens as $item) {
            // Inserir item da venda
            $stmt = $pdo->prepare("INSERT INTO itens_venda (venda_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $subtotal = $item['quantidade'] * $item['preco_unitario'];
            $stmt->execute([$venda_id, $item['produto_id'], $item['quantidade'], $item['preco_unitario'], $subtotal]);
            
            // Atualizar estoque
            atualizarEstoque($pdo, $item['produto_id'], $item['quantidade']);
        }
        
        $pdo->commit();
        return $venda_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Função para obter estatísticas do estoque
function obterEstatisticas($pdo) {
    $stats = [];

    // Total de produtos
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos");
    $stats['total_produtos'] = (int)$stmt->fetchColumn();

    // Produtos com estoque baixo
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos WHERE status = 'low_stock'");
    $stats['estoque_baixo'] = (int)$stmt->fetchColumn();

    // Produtos sem estoque
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produtos WHERE status = 'out_of_stock'");
    $stats['sem_estoque'] = (int)$stmt->fetchColumn();

    // Valor total do inventário
    $stmt = $pdo->query("SELECT COALESCE(SUM(quantidade * custo), 0) as valor_total FROM produtos");
    $stats['valor_total'] = (float)$stmt->fetchColumn();

    return $stats;
}

// Função para obter categorias únicas
function obterCategorias($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT categoria FROM produtos ORDER BY categoria");
    return array_column($stmt->fetchAll(), 'categoria');
}

// Função para inserir uma nova solicitação
function inserirSolicitacao($pdo, $loja, $itens) {
    try {
        $pdo->beginTransaction();

        // Verificar e reduzir estoque para cada item
        foreach ($itens as $item) {
            // Verificar se há estoque suficiente
            $stmt = $pdo->prepare("SELECT quantidade FROM produtos WHERE id = ?");
            $stmt->execute([$item['produto_id']]);
            $estoque_atual = $stmt->fetchColumn();

            if ($estoque_atual < $item['quantidade']) {
                throw new Exception("Estoque insuficiente para o produto: " . $item['nome']);
            }

            // Buscar ou criar loja
            $stmt = $pdo->prepare("SELECT id FROM lojas WHERE nome = ?");
            $stmt->execute([$item['loja']]);
            $loja_id = $stmt->fetchColumn();

            if (!$loja_id) {
                // Criar nova loja se não existir
                $stmt = $pdo->prepare("INSERT INTO lojas (nome) VALUES (?)");
                $stmt->execute([$item['loja']]);
                $loja_id = $pdo->lastInsertId();
            }

            // Inserir transferência
            $stmt = $pdo->prepare("INSERT INTO transferencias (loja_id, produto_id, quantidade_solicitada, preco_venda, status) VALUES (?, ?, ?, ?, 'aprovada')");
            $stmt->execute([$loja_id, $item['produto_id'], $item['quantidade'], $item['preco_venda']]);

            // Reduzir estoque do produto principal
            $stmt = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");
            $stmt->execute([$item['quantidade'], $item['produto_id']]);

            // Adicionar ao estoque da loja
            $stmt = $pdo->prepare("INSERT INTO estoque_lojas (loja_id, produto_id, quantidade, preco_venda)
                                  VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE
                                  quantidade = quantidade + VALUES(quantidade),
                                  preco_venda = VALUES(preco_venda)");
            $stmt->execute([$loja_id, $item['produto_id'], $item['quantidade'], $item['preco_venda']]);

            // Atualizar status do produto principal se necessário
            $stmt = $pdo->prepare("SELECT quantidade, estoque_minimo FROM produtos WHERE id = ?");
            $stmt->execute([$item['produto_id']]);
            $produto = $stmt->fetch();
            
            $status = 'in_stock';
            if ($produto['quantidade'] == 0) {
                $status = 'out_of_stock';
            } elseif ($produto['quantidade'] <= $produto['estoque_minimo']) {
                $status = 'low_stock';
            }
            
            $stmt = $pdo->prepare("UPDATE produtos SET status = ? WHERE id = ?");
            $stmt->execute([$status, $item['produto_id']]);
        }

        // Inserir solicitação principal
        $stmt = $pdo->prepare("INSERT INTO solicitacoes (loja) VALUES (?)");
        $stmt->execute([$loja]);
        $solicitacao_id = $pdo->lastInsertId();

        // Inserir itens da solicitação
        $stmtItem = $pdo->prepare("INSERT INTO itens_solicitacao (solicitacao_id, produto_id, quantidade, preco_venda) VALUES (?, ?, ?, ?)");
        foreach ($itens as $item) {
            $stmtItem->execute([
                $solicitacao_id,
                $item['produto_id'],
                $item['quantidade'],
                $item['preco_venda']
            ]);
        }

        $pdo->commit();
        return $solicitacao_id;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

?>
