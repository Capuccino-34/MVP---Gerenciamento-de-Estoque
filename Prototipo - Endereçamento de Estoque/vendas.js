// Variáveis globais
let carrinho = [];
let produtos = [];
let lojaAtual = null;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
    carregarVendas();
});

// Eventos
function inicializarEventos() {
    // Filtro de loja
    document.getElementById('loja-filtro').addEventListener('change', function() {
        const loja = this.value;
        if (loja && loja !== 'todas') {
            lojaAtual = loja;
            carregarProdutosDaLoja(loja);
        } else {
            mostrarEstadoVazio();
            produtos = [];
        }
    });
    
    // Botão finalizar compra
    document.getElementById('btn-finalizar').addEventListener('click', abrirCheckout);
    
    // Botões do modal de checkout
    document.getElementById('btn-cancelar-checkout').addEventListener('click', fecharCheckout);
    
    // Opções de pagamento
    document.querySelectorAll('.opcao-pagamento').forEach(btn => {
        btn.addEventListener('click', function() {
            const metodo = this.dataset.metodo;
            processarCompra(metodo);
        });
    });
    
    // Nova compra
    document.getElementById('btn-nova-compra').addEventListener('click', novaCompra);
    
    // Fechar modais clicando fora
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                fecharModais();
            }
        });
    });
    
    // Tecla ESC para fechar modais
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            fecharModais();
        }
    });
}

// Carregar produtos da loja selecionada
async function carregarProdutosDaLoja(loja) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_produtos_distribuidos');
        formData.append('loja', loja);

        const response = await fetch('vendas.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            produtos = data.produtos;
            renderizarProdutos();
        }
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        mostrarNotificacao('Erro ao carregar produtos', 'error');
    }
}

// Renderizar produtos
function renderizarProdutos() {
    const grid = document.getElementById('produtos-grid');
    
    if (produtos.length === 0) {
        grid.innerHTML = `
            <div class="empty-state">
                <svg class="icon-lg" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="16" rx="2" ry="2"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                    <line x1="9" y1="4" x2="9" y2="20"/>
                </svg>
                <h3>Nenhum produto distribuído</h3>
                <p>Esta loja não possui produtos distribuídos</p>
            </div>
        `;
        return;
    }

    // Agrupar produtos por nome (somar quantidades)
    const produtosAgrupados = {};
    produtos.forEach(produto => {
        const key = produto.produto_id;
        if (produtosAgrupados[key]) {
            produtosAgrupados[key].quantidade += parseInt(produto.quantidade);
        } else {
            produtosAgrupados[key] = { ...produto };
            produtosAgrupados[key].quantidade = parseInt(produto.quantidade);
        }
    });

    grid.innerHTML = Object.values(produtosAgrupados).map(produto => {
        return `
            <div class="produto-card">
                <div class="produto-info">
                    <h4 class="produto-nome">${produto.produto_nome || 'Produto ID: ' + produto.produto_id}</h4>
                    <div class="produto-meta">
                        <span class="produto-categoria">${produto.categoria || 'Categoria não informada'}</span>
                        <span class="produto-loja">${produto.loja}</span>
                    </div>
                    <p class="produto-disponivel">${produto.quantidade} disponível</p>
                </div>
                <div class="produto-compra">
                    <div class="preco-container">
                        <span class="preco">R$ ${parseFloat(produto.preco_venda).toFixed(2).replace('.', ',')}</span>
                        <span class="unidade">por ${produto.unidade || 'unidade'}</span>
                    </div>
                    <button class="btn-adicionar-carrinho" 
                            data-id="${produto.produto_id}"
                            data-nome="${produto.produto_nome || 'Produto ID: ' + produto.produto_id}"
                            data-preco="${produto.preco_venda}"
                            data-disponivel="${produto.quantidade}"
                            data-loja="${produto.loja}"
                            ${produto.quantidade === 0 ? 'disabled' : ''}>
                        <svg class="icon" viewBox="0 0 24 24">
                            <line x1="12" y1="5" x2="12" y2="19"/>
                            <line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Adicionar
                    </button>
                </div>
            </div>
        `;
    }).join('');

    // Adicionar eventos aos botões
    document.querySelectorAll('.btn-adicionar-carrinho').forEach(btn => {
        btn.addEventListener('click', function() {
            const produtoData = {
                id: parseInt(this.dataset.id),
                nome: this.dataset.nome,
                preco: parseFloat(this.dataset.preco),
                disponivel: parseInt(this.dataset.disponivel),
                loja: this.dataset.loja
            };
            adicionarAoCarrinho(produtoData);
        });
    });
}

// Adicionar produto ao carrinho
function adicionarAoCarrinho(produto) {
    if (!lojaAtual) {
        mostrarNotificacao('Selecione uma loja primeiro', 'warning');
        return;
    }

    const itemExistente = carrinho.find(item => item.produto.id === produto.id);
    
    if (itemExistente) {
        if (itemExistente.quantidade < produto.disponivel) {
            itemExistente.quantidade++;
        } else {
            mostrarNotificacao('Quantidade máxima disponível atingida!', 'warning');
            return;
        }
    } else {
        carrinho.push({
            produto: produto,
            quantidade: 1
        });
    }
    
    atualizarCarrinho();
    
    // Feedback visual
    const btn = document.querySelector(`[data-id="${produto.id}"]`);
    const textoOriginal = btn.innerHTML;
    btn.innerHTML = '<svg class="icon" viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg> Adicionado!';
    btn.style.background = '#16a34a';
    
    setTimeout(() => {
        btn.innerHTML = textoOriginal;
        btn.style.background = '#2563eb';
    }, 800);
}

// Remover item do carrinho
function removerDoCarrinho(produtoId) {
    carrinho = carrinho.filter(item => item.produto.id !== produtoId);
    atualizarCarrinho();
}

// Alterar quantidade no carrinho
function alterarQuantidade(produtoId, novaQuantidade) {
    if (novaQuantidade <= 0) {
        removerDoCarrinho(produtoId);
        return;
    }
    
    const item = carrinho.find(item => item.produto.id === produtoId);
    if (item && novaQuantidade <= item.produto.disponivel) {
        item.quantidade = novaQuantidade;
        atualizarCarrinho();
    }
}

// Atualizar interface do carrinho
function atualizarCarrinho() {
    const carrinhoVazio = document.getElementById('carrinho-vazio');
    const carrinhoItens = document.getElementById('carrinho-itens');
    const carrinhoTotal = document.getElementById('carrinho-total');
    const totalItens = document.getElementById('total-itens');
    const valorTotal = document.getElementById('valor-total');
    const contadorCarrinho = document.getElementById('contador-carrinho');
    const btnFinalizar = document.getElementById('btn-finalizar');
    
    if (carrinho.length === 0) {
        carrinhoVazio.classList.remove('hidden');
        carrinhoItens.classList.add('hidden');
        carrinhoTotal.classList.add('hidden');
        btnFinalizar.disabled = true;
        contadorCarrinho.textContent = '0 itens no carrinho';
        btnFinalizar.innerHTML = 'Finalizar Compra (R$ 0,00)';
    } else {
        carrinhoVazio.classList.add('hidden');
        carrinhoItens.classList.remove('hidden');
        carrinhoTotal.classList.remove('hidden');
        btnFinalizar.disabled = false;
        
        // Atualizar contadores
        const quantidadeTotal = carrinho.reduce((total, item) => total + item.quantidade, 0);
        const valorTotalCalculado = calcularTotal();
        
        totalItens.textContent = quantidadeTotal;
        valorTotal.textContent = formatarMoeda(valorTotalCalculado);
        contadorCarrinho.textContent = `${quantidadeTotal} itens no carrinho`;
        btnFinalizar.innerHTML = `Finalizar Compra (${formatarMoeda(valorTotalCalculado)})`;
        
        // Renderizar itens
        renderizarItensCarrinho();
    }
}

// Renderizar itens do carrinho
function renderizarItensCarrinho() {
    const carrinhoItens = document.getElementById('carrinho-itens');
    
    carrinhoItens.innerHTML = carrinho.map(item => `
        <div class="carrinho-item">
            <div class="item-header">
                <span class="item-nome">${item.produto.nome}</span>
                <button class="btn-remover" onclick="removerDoCarrinho(${item.produto.id})">
                    Remover
                </button>
            </div>
            <div class="item-preco">
                ${formatarMoeda(item.produto.preco)} x ${item.quantidade}
            </div>
            <div class="item-controles">
                <div class="quantidade-controles">
                    <button class="btn-quantidade" onclick="alterarQuantidade(${item.produto.id}, ${item.quantidade - 1})">
                        -
                    </button>
                    <span class="quantidade-valor">${item.quantidade}</span>
                    <button class="btn-quantidade" 
                            onclick="alterarQuantidade(${item.produto.id}, ${item.quantidade + 1})"
                            ${item.quantidade >= item.produto.disponivel ? 'disabled' : ''}>
                        +
                    </button>
                </div>
                <span class="item-total">${formatarMoeda(item.produto.preco * item.quantidade)}</span>
            </div>
        </div>
    `).join('');
}

// Calcular total do carrinho
function calcularTotal() {
    return carrinho.reduce((total, item) => total + (item.produto.preco * item.quantidade), 0);
}

// Abrir modal de checkout
function abrirCheckout() {
    if (carrinho.length === 0) return;
    
    const resumoItens = document.getElementById('resumo-itens');
    const resumoValorTotal = document.getElementById('resumo-valor-total');
    
    // Renderizar resumo
    resumoItens.innerHTML = carrinho.map(item => `
        <div class="resumo-item">
            <span>${item.produto.nome} x${item.quantidade}</span>
            <span>${formatarMoeda(item.produto.preco * item.quantidade)}</span>
        </div>
    `).join('');
    
    resumoValorTotal.textContent = formatarMoeda(calcularTotal());
    
    // Mostrar modal
    document.getElementById('modal-checkout').classList.add('active');
}

// Fechar modal de checkout
function fecharCheckout() {
    document.getElementById('modal-checkout').classList.remove('active');
}

// Processar compra
async function processarCompra(metodo) {
    if (carrinho.length === 0 || !lojaAtual) return;
    
    try {
        const itens = carrinho.map(item => ({
            produto_id: item.produto.id,
            quantidade: item.quantidade,
            preco_unitario: item.produto.preco
        }));
        
        const formData = new FormData();
        formData.append('action', 'processar_compra');
        formData.append('loja', lojaAtual);
        formData.append('itens', JSON.stringify(itens));
        formData.append('forma_pagamento', metodo);
        formData.append('total', calcularTotal().toFixed(2));

        const response = await fetch('vendas.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            const total = calcularTotal();
            
            // Fechar modal de checkout
            fecharCheckout();
            
            // Mostrar modal de confirmação
            setTimeout(() => {
                document.getElementById('metodo-selecionado').textContent = metodo;
                document.getElementById('valor-confirmacao').textContent = formatarMoeda(total);
                document.getElementById('modal-confirmacao').classList.add('active');
            }, 300);
            
            // Recarregar dados
            carregarProdutosDaLoja(lojaAtual);
            carregarVendas();
            
        } else {
            mostrarNotificacao('Erro ao processar compra: ' + data.message, 'error');
        }
        
    } catch (error) {
        console.error('Erro ao processar compra:', error);
        mostrarNotificacao('Erro ao processar compra', 'error');
    }
}

// Nova compra
function novaCompra() {
    carrinho = [];
    atualizarCarrinho();
    document.getElementById('modal-confirmacao').classList.remove('active');
}

// Fechar todos os modais
function fecharModais() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.classList.remove('active');
    });
}

// Mostrar estado vazio
function mostrarEstadoVazio() {
    const grid = document.getElementById('produtos-grid');
    grid.innerHTML = `
        <div class="empty-state">
            <svg class="icon-lg" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="16" rx="2" ry="2"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
                <line x1="9" y1="4" x2="9" y2="20"/>
            </svg>
            <h3>Selecione uma loja</h3>
            <p>Escolha uma loja para ver os produtos distribuídos</p>
        </div>
    `;
}

// Carregar histórico de vendas
async function carregarVendas() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_vendas');
        
        const dataInicio = document.getElementById('data-inicio').value;
        const dataFim = document.getElementById('data-fim').value;
        
        if (dataInicio) {
            formData.append('data_inicio', dataInicio);
        }
        
        if (dataFim) {
            formData.append('data_fim', dataFim);
        }

        const response = await fetch('vendas.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            renderizarHistoricoVendas(data.vendas);
        }
    } catch (error) {
        console.error('Erro ao carregar histórico:', error);
    }
}

// Renderizar histórico de vendas
function renderizarHistoricoVendas(vendas) {
    const tbody = document.getElementById('vendas-tbody');
    
    if (vendas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem; color: #64748b;">Nenhuma venda encontrada</td></tr>';
        return;
    }

    tbody.innerHTML = vendas.map(venda => {
        const data = new Date(venda.data_venda);
        const dataFormatada = data.toLocaleDateString('pt-BR');
        const horaFormatada = data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        
        // Processar itens da venda
        let produtosTexto = 'Sem produtos';
        if (venda.itens) {
            const itens = venda.itens.split('||').map(item => {
                const [nome, quantidade, preco] = item.split('|');
                return `${nome} (${quantidade}x)`;
            });
            produtosTexto = itens.join(', ');
        }
        
        return `
            <tr>
                <td>
                    <div>
                        <strong>${dataFormatada}</strong>
                        <div style="font-size: 0.75rem; color: #64748b;">${horaFormatada}</div>
                    </div>
                </td>
                <td>
                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${produtosTexto}
                    </div>
                </td>
                <td><strong>R$ ${parseFloat(venda.total).toFixed(2).replace('.', ',')}</strong></td>
                <td>
                    <span class="badge badge-success">
                        ${getIconePagamento(venda.forma_pagamento)}
                        ${venda.forma_pagamento}
                    </span>
                </td>
                <td>
                    <span class="badge badge-success">
                        ${venda.status_pagamento === 'pago' ? 'Pago' : venda.status_pagamento}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function getIconePagamento(metodo) {
    const icones = {
        'Dinheiro': '<svg class="icon-sm" viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H16a3.5 3.5 0 0 1 0 7H7"/></svg>',
        'Cartão de Débito': '<svg class="icon-sm" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'Cartão de Crédito': '<svg class="icon-sm" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'PIX': '<svg class="icon-sm" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><rect x="7" y="7" width="10" height="10"/></svg>'
    };
    return icones[metodo] || '<svg class="icon-sm" viewBox="0 0 24 24"><path d="M12 1v22"/></svg>';
}

// Utilitários
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

// Sistema de notificações
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Remover notificações anteriores
    const notificacoesExistentes = document.querySelectorAll('.notificacao');
    notificacoesExistentes.forEach(notificacao => notificacao.remove());

    const notificacao = document.createElement('div');
    notificacao.className = `notificacao notificacao-${tipo}`;
    notificacao.innerHTML = `
        <div class="notificacao-content">
            <span class="notificacao-message">${mensagem}</span>
            <button class="notificacao-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    document.body.appendChild(notificacao);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notificacao.parentElement) {
            notificacao.remove();
        }
    }, 5000);
}

// Adicionar estilos para notificações
const style = document.createElement('style');
style.textContent = `
    .notificacao {
        position: fixed;
        top: 20px;
        right: 20px;
        min-width: 300px;
        padding: 16px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 9999;
        animation: slideInRight 0.3s ease-out;
    }

    .notificacao-info {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        color: #1e40af;
    }

    .notificacao-success {
        background: #dcfce7;
        border-left: 4px solid #16a34a;
        color: #166534;
    }

    .notificacao-warning {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        color: #92400e;
    }

    .notificacao-error {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }

    .notificacao-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notificacao-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        margin-left: 12px;
        opacity: 0.7;
    }

    .notificacao-close:hover {
        opacity: 1;
    }

    .hidden {
        display: none !important;
    }

    .produtos-vendas-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .produtos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1rem;
    }

    .produto-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 1rem;
        background: white;
        transition: all 0.2s;
    }

    .produto-card:hover {
        border-color: #3b82f6;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.1);
    }

    .produto-nome {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: #1f2937;
    }

    .produto-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
        color: #64748b;
    }

    .produto-disponivel {
        font-size: 0.875rem;
        color: #16a34a;
        margin-bottom: 1rem;
    }

    .preco-container {
        margin-bottom: 1rem;
    }

    .preco {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
    }

    .unidade {
        font-size: 0.75rem;
        color: #64748b;
    }

    .btn-adicionar-carrinho {
        width: 100%;
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 0.75rem;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        transition: all 0.2s;
    }

    .btn-adicionar-carrinho:hover:not(:disabled) {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .btn-adicionar-carrinho:disabled {
        background: #9ca3af;
        cursor: not-allowed;
    }

    .empty-state {
        grid-column: 1 / -1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #64748b;
        padding: 3rem 1rem;
    }

    .empty-state .icon-lg {
        width: 3rem;
        height: 3rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .carrinho-vazio {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #64748b;
        padding: 2rem 1rem;
    }

    .carrinho-vazio .icon-lg {
        width: 2rem;
        height: 2rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .carrinho-item {
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 0;
    }

    .carrinho-item:last-child {
        border-bottom: none;
    }

    .item-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .item-nome {
        font-weight: 500;
        color: #1f2937;
    }

    .btn-remover {
        background: none;
        border: none;
        color: #ef4444;
        font-size: 0.875rem;
        cursor: pointer;
    }

    .item-preco {
        font-size: 0.875rem;
        color: #64748b;
        margin-bottom: 0.5rem;
    }

    .item-controles {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .quantidade-controles {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-quantidade {
        width: 24px;
        height: 24px;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.875rem;
    }

    .btn-quantidade:hover:not(:disabled) {
        background: #f3f4f6;
    }

    .btn-quantidade:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .quantidade-valor {
        font-weight: 500;
        min-width: 24px;
        text-align: center;
    }

    .item-total {
        font-weight: 600;
        color: #1f2937;
    }

    .carrinho-total {
        border-top: 1px solid #e2e8f0;
        padding-top: 1rem;
        margin-top: 1rem;
    }

    .total-linha {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
        color: #1f2937;
    }

    .contador-carrinho {
        font-size: 0.875rem;
        color: #64748b;
        font-weight: 500;
    }

    .filtros-vendas {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .opcoes-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-top: 1rem;
    }

    .opcao-pagamento {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }

    .opcao-pagamento:hover {
        border-color: #3b82f6;
        background: #f8fafc;
    }

    .opcao-pagamento .icon {
        width: 24px;
        height: 24px;
    }

    .resumo-compra {
        margin-bottom: 2rem;
    }

    .resumo-itens {
        margin: 1rem 0;
    }

    .resumo-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }

    .resumo-total {
        display: flex;
        justify-content: space-between;
        font-weight: 600;
        font-size: 1.125rem;
        border-top: 1px solid #e2e8f0;
        padding-top: 1rem;
        margin-top: 1rem;
    }

    .modal-confirmacao {
        max-width: 400px;
    }

    .confirmacao-content {
        text-align: center;
        padding: 2rem;
    }

    .checkmark-container {
        margin-bottom: 1rem;
    }

    .checkmark {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #16a34a;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        color: white;
    }

    .checkmark .icon {
        width: 24px;
        height: 24px;
    }

    .valor-confirmacao {
        font-size: 1.25rem;
        font-weight: 600;
        color: #16a34a;
        margin: 1rem 0;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .badge-success {
        background: #dcfce7;
        color: #166534;
    }

    .icon-sm {
        width: 12px;
        height: 12px;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @media (max-width: 768px) {
        .produtos-vendas-grid {
            grid-template-columns: 1fr;
        }
        
        .produtos-grid {
            grid-template-columns: 1fr;
        }
        
        .opcoes-grid {
            grid-template-columns: 1fr;
        }
        
        .filtros-vendas {
            flex-direction: column;
            align-items: stretch;
        }
    }
`;

document.head.appendChild(style);
