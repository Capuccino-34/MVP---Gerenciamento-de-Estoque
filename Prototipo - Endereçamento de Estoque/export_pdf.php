<?php
require_once 'db_connect.php';

// Buscar filtros se enviados via GET
$filtros = [
    'busca' => $_GET['busca'] ?? '',
    'categoria' => $_GET['categoria'] ?? 'all',
    'status' => $_GET['status'] ?? 'all'
];

$produtos = buscarProdutos($pdo, $filtros);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <title>Exportar PDF - Estoque</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
    <script>
        function exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4'); // Landscape para mais colunas

            // Título do relatório
            doc.setFontSize(18);
            doc.text('Relatório de Estoque', 20, 20);
            
            // Data de geração
            doc.setFontSize(10);
            doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 20, 30);

            const head = [['ID', 'Produto', 'Categoria', 'Localização', 'Quantidade', 'Unidade', 'Status', 'Custo Unit.', 'Valor Total', 'Fornecedor']];
            const body = [
                <?php foreach ($produtos as $produto): ?>
                [
                    '<?php echo $produto['id']; ?>',
                    '<?php echo addslashes($produto['nome']); ?>',
                    '<?php echo addslashes($produto['categoria']); ?>',
                    '<?php echo addslashes($produto['localizacao'] . '-' . $produto['zona'] . '-' . $produto['prateleira']); ?>',
                    '<?php echo $produto['quantidade']; ?>',
                    '<?php echo addslashes($produto['unidade']); ?>',
                    '<?php 
                        $status = '';
                        switch($produto['status']) {
                            case 'in_stock': $status = 'Em Estoque'; break;
                            case 'low_stock': $status = 'Estoque Baixo'; break;
                            case 'out_of_stock': $status = 'Sem Estoque'; break;
                            default: $status = $produto['status'];
                        }
                        echo addslashes($status);
                    ?>',
                    'R$ <?php echo number_format($produto['custo'], 2, ',', '.'); ?>',
                    'R$ <?php echo number_format($produto['quantidade'] * $produto['custo'], 2, ',', '.'); ?>',
                    '<?php echo addslashes($produto['fornecedor']); ?>'
                ],
                <?php endforeach; ?>
            ];

            // Configurações da tabela
            doc.autoTable({
                head: head,
                body: body,
                startY: 40,
                styles: {
                    fontSize: 8,
                    cellPadding: 2
                },
                headStyles: {
                    fillColor: [37, 99, 235], // Azul
                    textColor: [255, 255, 255]
                },
                columnStyles: {
                    0: { cellWidth: 15 }, // ID
                    1: { cellWidth: 35 }, // Produto
                    2: { cellWidth: 25 }, // Categoria
                    3: { cellWidth: 30 }, // Localização
                    4: { cellWidth: 20 }, // Quantidade
                    5: { cellWidth: 15 }, // Unidade
                    6: { cellWidth: 25 }, // Status
                    7: { cellWidth: 25 }, // Custo
                    8: { cellWidth: 25 }, // Valor Total
                    9: { cellWidth: 35 }  // Fornecedor
                }
            });

            // Resumo estatístico
            const finalY = doc.lastAutoTable.finalY + 10;
            doc.setFontSize(12);
            doc.text('Resumo:', 20, finalY);
            
            doc.setFontSize(10);
            doc.text('Total de produtos: <?php echo count($produtos); ?>', 20, finalY + 8);
            
            <?php
            $valorTotal = 0;
            $emEstoque = 0;
            $estoqueBaixo = 0;
            $semEstoque = 0;
            
            foreach ($produtos as $produto) {
                $valorTotal += $produto['quantidade'] * $produto['custo'];
                switch($produto['status']) {
                    case 'in_stock': $emEstoque++; break;
                    case 'low_stock': $estoqueBaixo++; break;
                    case 'out_of_stock': $semEstoque++; break;
                }
            }
            ?>
            
            doc.text('Valor total do inventário: R$ <?php echo number_format($valorTotal, 2, ',', '.'); ?>', 20, finalY + 16);
            doc.text('Em estoque: <?php echo $emEstoque; ?> | Estoque baixo: <?php echo $estoqueBaixo; ?> | Sem estoque: <?php echo $semEstoque; ?>', 20, finalY + 24);

            const filename = 'relatorio_estoque_' + new Date().toISOString().slice(0, 10) + '.pdf';
            doc.save(filename);
        }

        window.onload = function() {
            exportPDF();
        };
    </script>
</head>
<body>
    <div style="text-align: center; margin-top: 100px; font-family: Arial, sans-serif;">
        <h1>Gerando Relatório PDF...</h1>
        <p>O download iniciará automaticamente.</p>
        <p><a href="javascript:history.back()">Voltar ao sistema</a></p>
    </div>
</body>
</html>