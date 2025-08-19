<?php
require_once 'db_connect.php';

// Buscar filtros se enviados via GET
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'categoria' => $_GET['categoria'] ?? 'all',
    'status' => $_GET['status'] ?? 'all'
];

$produtos = buscarProdutos($pdo, $filtros);

$filename = "relatorio_estoque_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Output UTF-8 BOM to help Excel recognize UTF-8 encoding
echo "\xEF\xBB\xBF";

echo "<table border='1'>";

// Cabeçalho
echo "<tr style='background-color: #2563eb; color: white; font-weight: bold;'>";
echo "<th>ID</th>";
echo "<th>Produto</th>";
echo "<th>Categoria</th>";
echo "<th>Localização</th>";
echo "<th>Zona</th>";
echo "<th>Prateleira</th>";
echo "<th>Quantidade</th>";
echo "<th>Unidade</th>";
echo "<th>Estoque Mínimo</th>";
echo "<th>Estoque Máximo</th>";
echo "<th>Status</th>";
echo "<th>Custo Unitário</th>";
echo "<th>Preço de Venda</th>";
echo "<th>Valor Total Custo</th>";
echo "<th>Valor Total Venda</th>";
echo "<th>Fornecedor</th>";
echo "<th>Data Criação</th>";
echo "<th>Última Atualização</th>";
echo "</tr>";

// Dados dos produtos
foreach ($produtos as $produto) {
    // Determinar cor da linha baseada no status
    $corLinha = '';
    switch($produto['status']) {
        case 'in_stock':
            $corLinha = 'background-color: #dcfce7;'; // Verde claro
            break;
        case 'low_stock':
            $corLinha = 'background-color: #fef3c7;'; // Amarelo claro
            break;
        case 'out_of_stock':
            $corLinha = 'background-color: #fee2e2;'; // Vermelho claro
            break;
    }
    
    echo "<tr style='$corLinha'>";
    echo "<td>" . htmlspecialchars($produto['id']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['nome']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['categoria']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['localizacao']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['zona']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['prateleira']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['quantidade']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['unidade']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['estoque_minimo']) . "</td>";
    echo "<td>" . htmlspecialchars($produto['estoque_maximo']) . "</td>";
    
    // Status em texto
    $statusTexto = '';
    switch($produto['status']) {
        case 'in_stock': $statusTexto = 'Em Estoque'; break;
        case 'low_stock': $statusTexto = 'Estoque Baixo'; break;
        case 'out_of_stock': $statusTexto = 'Sem Estoque'; break;
        default: $statusTexto = $produto['status'];
    }
    echo "<td>" . htmlspecialchars($statusTexto) . "</td>";
    
    echo "<td>R$ " . number_format($produto['custo'], 2, ',', '.') . "</td>";
    echo "<td>R$ " . number_format($produto['preco_venda'], 2, ',', '.') . "</td>";
    echo "<td>R$ " . number_format($produto['quantidade'] * $produto['custo'], 2, ',', '.') . "</td>";
    echo "<td>R$ " . number_format($produto['quantidade'] * $produto['preco_venda'], 2, ',', '.') . "</td>";
    echo "<td>" . htmlspecialchars($produto['fornecedor']) . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($produto['data_criacao'])) . "</td>";
    echo "<td>" . date('d/m/Y H:i', strtotime($produto['data_atualizacao'])) . "</td>";
    echo "</tr>";
}

// Linha de resumo
echo "<tr style='background-color: #f1f5f9; font-weight: bold;'>";
echo "<td colspan='6'>TOTAIS:</td>";

// Calcular totais
$totalQuantidade = 0;
$totalValorCusto = 0;
$totalValorVenda = 0;
$totalEmEstoque = 0;
$totalEstoqueBaixo = 0;
$totalSemEstoque = 0;

foreach ($produtos as $produto) {
    $totalQuantidade += $produto['quantidade'];
    $totalValorCusto += $produto['quantidade'] * $produto['custo'];
    $totalValorVenda += $produto['quantidade'] * $produto['preco_venda'];
    
    switch($produto['status']) {
        case 'in_stock': $totalEmEstoque++; break;
        case 'low_stock': $totalEstoqueBaixo++; break;
        case 'out_of_stock': $totalSemEstoque++; break;
    }
}

echo "<td>" . $totalQuantidade . "</td>";
echo "<td>-</td>";
echo "<td>-</td>";
echo "<td>-</td>";
echo "<td>Em: $totalEmEstoque | Baixo: $totalEstoqueBaixo | Sem: $totalSemEstoque</td>";
echo "<td>-</td>";
echo "<td>-</td>";
echo "<td>R$ " . number_format($totalValorCusto, 2, ',', '.') . "</td>";
echo "<td>R$ " . number_format($totalValorVenda, 2, ',', '.') . "</td>";
echo "<td>-</td>";
echo "<td>-</td>";
echo "<td>-</td>";
echo "</tr>";

// Informações do relatório
echo "<tr style='background-color: #e2e8f0;'>";
echo "<td colspan='18'>";
echo "Relatório gerado em: " . date('d/m/Y H:i:s') . " | ";
echo "Total de produtos: " . count($produtos) . " | ";
echo "Filtros aplicados: ";
if (!empty($filtros['busca'])) echo "Busca: '" . htmlspecialchars($filtros['busca']) . "' ";
if ($filtros['categoria'] !== 'all') echo "Categoria: '" . htmlspecialchars($filtros['categoria']) . "' ";
if ($filtros['status'] !== 'all') echo "Status: '" . htmlspecialchars($filtros['status']) . "' ";
echo "</td>";
echo "</tr>";

echo "</table>";
exit;
?>