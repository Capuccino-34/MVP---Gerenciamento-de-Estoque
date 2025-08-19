<?php
// Configurações do banco de dados
$servername = "localhost";
$username = "";
$password = "";
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
                produto INT NOT NULL,
                quantidade INT NOT NULL,
                preco_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
                FOREIGN KEY (produto) REFERENCES produtos(id) ON DELETE CASCADE
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
                produto INT,
                quantidade INT DEFAULT 0,
                preco_venda DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE CASCADE,
                FOREIGN KEY (produto) REFERENCES produtos(id) ON DELETE CASCADE,
                UNIQUE KEY unique_loja_produto (loja_id, produto)
            )
        ");

        // Criar tabela transferencias para controle de transferências
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS transferencias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loja_id INT,
                produto INT,
                quantidade_solicitada INT,
                quantidade_enviada INT DEFAULT 0,
                preco_venda DECIMAL(10,2),
                status ENUM('pendente', 'aprovada', 'enviada', 'concluida') DEFAULT 'pendente',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (loja_id) REFERENCES lojas(id) ON DELETE CASCADE,
                FOREIGN KEY (produto) REFERENCES produtos(id) ON DELETE CASCADE
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
                produto INT NOT NULL,
                quantidade INT NOT NULL,
                preco_venda DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id) ON DELETE CASCADE,
                FOREIGN KEY (produto) REFERENCES produtos(id) ON DELETE CASCADE
            )
        ");

        // Criar tabela distribuir para armazenar dados de distribuição de produtos para lojas
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS distribuir (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loja VARCHAR(255) NOT NULL,
                produto INT NOT NULL,
                quantidade INT NOT NULL,
                preco_venda DECIMAL(10,2) NOT NULL,
                data_distribuicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (produto) REFERENCES produtos(id) ON DELETE CASCADE
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
function atualizarEstoque($pdo, $produto, $quantidade_vendida) {
    $produto = buscarProdutoPorId($pdo, $produto);
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
    return $stmt->execute([$nova_quantidade, $status, $produto]);
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
            $stmt = $pdo->prepare("INSERT INTO itens_venda (venda_id, produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
            $subtotal = $item['quantidade'] * $item['preco_unitario'];
            $stmt->execute([$venda_id, $item['produto'], $item['quantidade'], $item['preco_unitario'], $subtotal]);
            
            // Atualizar estoque
            atualizarEstoque($pdo, $item['produto'], $item['quantidade']);
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

// Função para buscar produtos distribuídos
function buscarProdutosDistribuidos($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT d.*, p.nome as produto_nome 
            FROM distribuir d 
            LEFT JOIN produtos p ON d.produto_id = p.id 
            ORDER BY d.data_distribuicao DESC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Se houver erro na consulta, vamos recriar a tabela
        $pdo->exec("DROP TABLE IF EXISTS distribuir");
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS distribuir (
                id INT AUTO_INCREMENT PRIMARY KEY,
                loja VARCHAR(255) NOT NULL,
                produto_id INT NOT NULL,
                quantidade INT NOT NULL,
                preco_venda DECIMAL(10,2) NOT NULL,
                data_distribuicao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
            )
        ");
        // Retorna array vazio após recriar a tabela
        return [];
    }
}

// Função para inserir produto distribuído
function inserirProdutoDistribuido($pdo, $loja, $produto_id, $quantidade, $preco_venda) {
    $stmt = $pdo->prepare("INSERT INTO distribuir (loja, produto_id, quantidade, preco_venda) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$loja, $produto_id, $quantidade, $preco_venda]);
}

// Função para inserir uma nova solicitação
function inserirSolicitacao($pdo, $loja, $itens) {
    try {
        $pdo->beginTransaction();

        // Verificar e reduzir estoque para cada item
        foreach ($itens as $item) {
            // Verificar se há estoque suficiente
            $stmt = $pdo->prepare("SELECT quantidade FROM produtos WHERE id = ?");
            $stmt->execute([$item['produto']]);
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
            $stmt = $pdo->prepare("INSERT INTO transferencias (loja_id, produto, quantidade_solicitada, preco_venda, status) VALUES (?, ?, ?, ?, 'aprovada')");
            $stmt->execute([$loja_id, $item['produto'], $item['quantidade'], $item['preco_venda']]);

            // Reduzir estoque do produto principal
            $stmt = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ?");
            $stmt->execute([$item['quantidade'], $item['produto']]);

            // Adicionar ao estoque da loja
            $stmt = $pdo->prepare("INSERT INTO estoque_lojas (loja_id, produto, quantidade, preco_venda)
                                  VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE
                                  quantidade = quantidade + VALUES(quantidade),
                                  preco_venda = VALUES(preco_venda)");
            $stmt->execute([$loja_id, $item['produto'], $item['quantidade'], $item['preco_venda']]);

            // Atualizar status do produto principal se necessário
            $stmt = $pdo->prepare("SELECT quantidade, estoque_minimo FROM produtos WHERE id = ?");
            $stmt->execute([$item['produto']]);
            $produto = $stmt->fetch();
            
            $status = 'in_stock';
            if ($produto['quantidade'] == 0) {
                $status = 'out_of_stock';
            } elseif ($produto['quantidade'] <= $produto['estoque_minimo']) {
                $status = 'low_stock';
            }
            
            $stmt = $pdo->prepare("UPDATE produtos SET status = ? WHERE id = ?");
            $stmt->execute([$status, $item['produto']]);
        }

        // Inserir solicitação principal
        $stmt = $pdo->prepare("INSERT INTO solicitacoes (loja) VALUES (?)");
        $stmt->execute([$loja]);
        $solicitacao_id = $pdo->lastInsertId();

        // Inserir itens da solicitação
        $stmtItem = $pdo->prepare("INSERT INTO itens_solicitacao (solicitacao_id, produto, quantidade, preco_venda) VALUES (?, ?, ?, ?)");
        foreach ($itens as $item) {
            $stmtItem->execute([
                $solicitacao_id,
                $item['produto'],
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
