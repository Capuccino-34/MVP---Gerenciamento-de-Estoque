// Variáveis globais
let carrinho = [];
let produtos = [];
let lojaAtual = null;

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    inicializarEventos();
    carregarEstatisticas();
    carregarHistoricoVendas();
});

// Eventos
function inicializarEventos() {
    // Filtro de loja
    document.getElementById('loja-filtro').addEventListener('change', function() {
        const lojaId = this.value;
        if (lojaId) {
            lojaAtual = lojaId;
            carregarProdutosLoja(lojaId);
            carregarEstatisticas();
        } else {
            mostrarEstadoVazio();
        }
    });
    
    // Busca de produtos
    document.getElementById('busca-produto').addEventListener('input', filtrarProdutos);
    
    // Botão finalizar venda
    document.getElementById('btn-finalizar').addEventListener('click', abrirCheckout);
    
    // Botões do modal de checkout
    document.getElementById('btn-cancelar-checkout').addEventListener('click', fecharCheckout);
    
    // Opções de pagamento
    document.querySelectorAll('.opcao-pagamento').forEach(btn => {
        btn.addEventListener('click', function() {
            const metodo = this.dataset.metodo;
            processarVenda(metodo);
        });
    });
    
    // Nova venda
    document.getElementById('btn-nova-venda').addEventListener('click', novaVenda);
    
    // Filtros do histórico
    document.getElementById('loja-historico').addEventListener('change', carregarHistoricoVendas);
    document.getElementById('data-inicio').addEventListener('change', carregarHistoricoVendas);
    document.getElementById('data-fim').addEventListener('change', carregarHistoricoVendas);
    
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
async function carregarProdutosLoja(lojaId) {
    try {
        const formData = new FormData();
        formData.append('action', 'get_produtos_loja');
        formData.append('loja_id', lojaId);

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
                <i class="fas fa-box-open"></i>
                <h3>Nenhum produto disponível</h3>
                <p>Esta loja não possui produtos em estoque</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = produtos.map(produto => {
        const status = getStatusProduto(produto.quantidade);
        return `
            <div class="produto-card" data-nome="${produto.nome.toLowerCase()}" data-categoria="${produto.categoria}">
                <div class="produto-info">
                    <h3 class="produto-nome">${produto.nome}</h3>
                    <div class="produto-meta">
                        <span class="produto-categoria">${produto.categoria}</span>
                        <span class="status-badge ${status.class}">${status.text}</span>
                    </div>
                    <p class="produto-disponivel">${produto.quantidade} disponível</p>
                </div>
                <div class="produto-compra">
                    <div class="preco-container">
                        <span class="preco">R$ ${parseFloat(produto.preco_venda).toFixed(2)}</span>
                        <span class="unidade">por unidade</span>
                    </div>
                    <button class="btn-adicionar-carrinho" 
                            data-id="${produto.produto_id}"
                            data-nome="${produto.nome}"
                            data-preco="${produto.preco_venda}"
                            data-disponivel="${produto.quantidade}"
                            data-categoria="${produto.categoria}"
                            ${produto.quantidade === 0 ? 'disabled' : ''}>
                        <i class="fas fa-plus"></i>
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
                categoria: this.dataset.categoria
            };
            adicionarAoCarrinho(produtoData);
        });
    });
}

function getStatusProduto(quantidade) {
    if (quantidade === 0) {
        return { class: 'status-esgotado', text: 'Esgotado' };
    } else if (quantidade < 5) {
        return { class: 'status-baixo', text: 'Baixo' };
    } else {
        return { class: 'status-disponivel', text: 'Disponível' };
    }
}

// Filtrar produtos
function filtrarProdutos() {
    const busca = document.getElementById('busca-produto').value.toLowerCase();
    const produtoCards = document.querySelectorAll('.produto-card');
    
    produtoCards.forEach(card => {
        const nome = card.dataset.nome;
        const categoria = card.dataset.categoria.toLowerCase();
        
        const matchBusca = nome.includes(busca) || categoria.includes(busca);
        
        if (matchBusca) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
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
    btn.innerHTML = '<i class="fas fa-check"></i> Adicionado!';
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
        btnFinalizar.innerHTML = '<i class="fas fa-shopping-cart"></i> Finalizar Venda (R$ 0,00)';
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
        btnFinalizar.innerHTML = `<i class="fas fa-shopping-cart"></i> Finalizar Venda (${formatarMoeda(valorTotalCalculado)})`;
        
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

// Processar venda
async function processarVenda(metodo) {
    if (carrinho.length === 0 || !lojaAtual) return;
    
    try {
        // Processar cada item do carrinho
        for (const item of carrinho) {
            const formData = new FormData();
            formData.append('action', 'processar_venda');
            formData.append('loja_id', lojaAtual);
            formData.append('produto_id', item.produto.id);
            formData.append('quantidade', item.quantidade);
            formData.append('preco_unitario', item.produto.preco);
            formData.append('metodo_pagamento', metodo);

            const response = await fetch('vendas.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Erro ao processar venda');
            }
        }
        
        const total = calcularTotal();
        
        // Fechar modal de checkout
        fecharCheckout();
        
        // Mostrar modal de confirmação
        setTimeout(() => {
            document.getElementById('metodo-selecionado').textContent = metodo;
            document.getElementById('valor-confirmacao').textContent = formatarMoeda(total);
            document.getElementById('modal-confirmacao').classList.add('active');
        }, 300);
        
        // Atualizar dados
        carregarProdutosLoja(lojaAtual);
        carregarEstatisticas();
        carregarHistoricoVendas();
        
    } catch (error) {
        console.error('Erro ao processar venda:', error);
        mostrarNotificacao('Erro ao processar venda: ' + error.message, 'error');
    }
}

// Nova venda
function novaVenda() {
    carrinho = [];
    atualizarCarrinho();
    document.getElementById('modal-confirmacao').classList.remove('active');
    
    // Reset dos filtros
    document.getElementById('busca-produto').value = '';
    filtrarProdutos();
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
            <i class="fas fa-store"></i>
            <h3>Selecione uma loja</h3>
            <p>Escolha uma loja para ver os produtos disponíveis</p>
        </div>
    `;
}

// Carregar estatísticas
async function carregarEstatisticas() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_estatisticas_vendas');
        
        if (lojaAtual) {
            formData.append('loja_id', lojaAtual);
        }

        const response = await fetch('vendas.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            renderizarEstatisticas(data.stats);
        }
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
    }
}

// Renderizar estatísticas
function renderizarEstatisticas(stats) {
    const container = document.getElementById('stats-container');
    
    container.innerHTML = `
        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">Total de Vendas</span>
                <div class="stat-icon" style="background: #3b82f6;">
                    <i class="fas fa-shopping-cart"></i>
                </div>
            </div>
            <div class="stat-value">${stats.total_vendas}</div>
            <div class="stat-description">vendas realizadas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">Faturamento</span>
                <div class="stat-icon" style="background: #10b981;">
                    <i class="fas fa-dollar-sign"></i>
                </div>
            </div>
            <div class="stat-value">R$ ${parseFloat(stats.faturamento_total).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
            <div class="stat-description">receita total</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">Produtos Vendidos</span>
                <div class="stat-icon" style="background: #f59e0b;">
                    <i class="fas fa-box"></i>
                </div>
            </div>
            <div class="stat-value">${stats.produtos_vendidos}</div>
            <div class="stat-description">unidades vendidas</div>
        </div>

        <div class="stat-card">
            <div class="stat-header">
                <span class="stat-title">Ticket Médio</span>
                <div class="stat-icon" style="background: #8b5cf6;">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-value">R$ ${parseFloat(stats.ticket_medio).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
            <div class="stat-description">valor médio por venda</div>
        </div>
    `;
}

// Carregar histórico de vendas
async function carregarHistoricoVendas() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_vendas');
        
        const lojaHistorico = document.getElementById('loja-historico').value;
        const dataInicio = document.getElementById('data-inicio').value;
        const dataFim = document.getElementById('data-fim').value;
        
        if (lojaHistorico && lojaHistorico !== 'todas') {
            formData.append('loja_id', lojaHistorico);
        }
        
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
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">Nenhuma venda encontrada</td></tr>';
        return;
    }

    tbody.innerHTML = vendas.map(venda => {
        const data = new Date(venda.created_at);
        const dataFormatada = data.toLocaleDateString('pt-BR');
        const horaFormatada = data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        
        return `
            <tr>
                <td>
                    <div>
                        <strong>${dataFormatada}</strong>
                        <div style="font-size: 0.75rem; color: #64748b;">${horaFormatada}</div>
                    </div>
                </td>
                <td><strong>${venda.loja_nome}</strong></td>
                <td>
                    <div>
                        <strong>${venda.produto_nome}</strong>
                        <div style="font-size: 0.75rem; color: #64748b;">${venda.categoria}</div>
                    </div>
                </td>
                <td><strong>${venda.quantidade}</strong> un</td>
                <td>R$ ${parseFloat(venda.preco_unitario).toFixed(2)}</td>
                <td><strong>R$ ${parseFloat(venda.total).toFixed(2)}</strong></td>
                <td>
                    <span class="status-badge status-disponivel">
                        ${getIconePagamento(venda.metodo_pagamento)}
                        ${venda.metodo_pagamento}
                    </span>
                </td>
            </tr>
        `;
    }).join('');
}

function getIconePagamento(metodo) {
    const icones = {
        'Dinheiro': '<i class="fas fa-money-bill-wave"></i>',
        'Cartão de Débito': '<i class="fas fa-credit-card"></i>',
        'Cartão de Crédito': '<i class="fas fa-credit-card"></i>',
        'PIX': '<i class="fas fa-qrcode"></i>'
    };
    return icones[metodo] || '<i class="fas fa-money-bill"></i>';
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

// Animação de sucesso personalizada
function animarSucesso() {
    const checkmark = document.querySelector('.checkmark');
    const checkIcon = document.querySelector('.checkmark i');
    
    // Reset da animação
    checkmark.style.animation = 'none';
    checkIcon.style.animation = 'none';
    
    // Força reflow
    checkmark.offsetHeight;
    
    // Aplicar animação novamente
    checkmark.style.animation = 'checkmarkBounce 0.6s ease-in-out';
    checkIcon.style.animation = 'checkmarkCheck 0.3s ease-in-out 0.3s both';
}

// Executar animação quando o modal de confirmação aparecer
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
            const modal = mutation.target;
            if (modal.id === 'modal-confirmacao' && modal.classList.contains('active')) {
                setTimeout(animarSucesso, 100);
            }
        }
    });
});

// Observar mudanças no modal de confirmação se existir
const modalConfirmacao = document.getElementById('modal-confirmacao');
if (modalConfirmacao) {
    observer.observe(modalConfirmacao, {
        attributes: true,
        attributeFilter: ['class']
    });
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
`;

document.head.appendChild(style);
