// Variáveis globais
let modoEdicao = false;
let produtoEdicaoId = null;
let produtosSolicitados = [];

// Utility functions
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor);
}

function formatarData(data) {
    return new Date(data).toLocaleDateString('pt-BR');
}

// Modal functions
function abrirModal(modalId) {
    document.getElementById(modalId).classList.add('active');
    document.body.style.overflow = 'hidden'; // Prevenir scroll do body
}

function fecharModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
    document.body.style.overflow = 'auto'; // Restaurar scroll do body
    if (modalId === 'modalFormulario') {
        limparFormulario();
    }
    if (modalId === 'modalAdicionarSolicitacao') {
        limparFormularioSolicitacao();
    }
}

function abrirModalAdicionar() {
    modoEdicao = false;
    produtoEdicaoId = null;
    document.getElementById('tituloFormulario').textContent = 'Adicionar Novo Item';
    document.getElementById('btnSalvar').textContent = 'Adicionar Item';
    limparFormulario();
    abrirModal('modalFormulario');
}

function limparFormulario() {
    document.getElementById('formProduto').reset();
    document.getElementById('produtoId').value = '';
}

// Visualizar produto
function visualizarProduto(id) {
    showLoader('Carregando produto...');
    
    fetch('estoque.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=buscar_produto&id=' + id
    })
    .then(response => response.json())
    .then(produto => {
        hideLoader();
        if (produto) {
            const conteudo = document.getElementById('conteudoVisualizacao');
            conteudo.innerHTML = `
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Especificação do Produto</label>
                    <p style="font-size: 1.125rem; font-weight: 600;">${produto.nome}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Categoria</label>
                    <p>${produto.categoria}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Fornecedor</label>
                    <p>${produto.fornecedor}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Localização Completa</label>
                    <p class="location">
                        <svg class="location-icon" viewBox="0 0 24 24">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        ${produto.localizacao} - ${produto.zona} - ${produto.prateleira}
                    </p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Quantidade Atual</label>
                    <p style="font-size: 1.25rem; font-weight: bold;">${produto.quantidade}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Informação ADC.</label>
                    <p style="font-size: 1.125rem; font-weight: 600;">${produto.unidade}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #f97316;">Estoque Mínimo</label>
                    <p style="color: #f97316;">${produto.estoque_minimo}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #22c55e;">Estoque Máximo</label>
                    <p style="color: #22c55e;">${produto.estoque_maximo}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Custo Unitário</label>
                    <p style="font-size: 1.125rem; font-weight: 600;">${formatarMoeda(parseFloat(produto.custo))}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Preço de Venda</label>
                    <p style="font-size: 1.125rem; font-weight: 600;">${formatarMoeda(parseFloat(produto.preco_venda))}</p>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Status</label>
                    <div style="margin-top: 0.25rem;">
                        ${obterBadgeStatus(produto.status)}
                    </div>
                </div>
                <div class="form-group">
                    <label style="font-size: 0.875rem; font-weight: 500; color: #64748b;">Última Atualização</label>
                    <p>${formatarData(produto.data_atualizacao)}</p>
                </div>
            `;
            abrirModal('modalVisualizar');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Erro:', error);
        showNotification('Erro ao carregar dados do produto', 'error');
    });
}

function obterBadgeStatus(status) {
    switch (status) {
        case 'in_stock':
            return '<span class="badge badge-success">Em Estoque</span>';
        case 'low_stock':
            return '<span class="badge badge-warning">Estoque Baixo</span>';
        case 'out_of_stock':
            return '<span class="badge badge-danger">Sem Estoque</span>';
        default:
            return '<span class="badge">' + status + '</span>';
    }
}

// Editar produto
function editarProduto(id) {
    showLoader('Carregando dados para edição...');
    
    fetch('estoque.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'acao=buscar_produto&id=' + id
    })
    .then(response => response.json())
    .then(produto => {
        hideLoader();
        if (produto) {
            modoEdicao = true;
            produtoEdicaoId = id;
            
            // Preencher formulário
            document.getElementById('produtoId').value = produto.id;
            document.getElementById('nome').value = produto.nome;
            document.getElementById('categoria').value = produto.categoria;
            document.getElementById('fornecedor').value = produto.fornecedor;
            document.getElementById('localizacao').value = produto.localizacao;
            document.getElementById('zona').value = produto.zona;
            document.getElementById('prateleira').value = produto.prateleira;
            document.getElementById('quantidade').value = produto.quantidade;
            document.getElementById('unidade').value = produto.unidade;
            document.getElementById('estoque_minimo').value = produto.estoque_minimo;
            document.getElementById('estoque_maximo').value = produto.estoque_maximo;
            document.getElementById('custo').value = produto.custo;
            document.getElementById('preco_venda').value = produto.preco_venda;
            
            // Atualizar título e botão
            document.getElementById('tituloFormulario').textContent = 'Editar Item';
            document.getElementById('btnSalvar').textContent = 'Salvar Alterações';
            
            abrirModal('modalFormulario');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Erro:', error);
        showNotification('Erro ao carregar dados do produto', 'error');
    });
}

// Excluir produto
function excluirProduto(id, nome) {
    if (confirm(`Tem certeza que deseja excluir o produto "${nome}"?\n\nEsta ação não pode ser desfeita.`)) {
        showLoader('Excluindo produto...');
        
        fetch('estoque.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'acao=excluir_produto&id=' + id
        })
        .then(response => response.json())
        .then(data => {
            hideLoader();
            if (data.sucesso) {
                showNotification(data.mensagem, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('Erro: ' + data.mensagem, 'error');
            }
        })
        .catch(error => {
            hideLoader();
            console.error('Erro:', error);
            showNotification('Erro ao excluir produto', 'error');
        });
    }
}

// Salvar produto (adicionar ou editar)
function salvarProduto() {
    const form = document.getElementById('formProduto');

    // Validate required fields
    if (!form.checkValidity()) {
        showNotification('Por favor, preencha todos os campos obrigatórios.', 'warning');
        return;
    }

    const formData = new FormData(form);
    formData.set('unidade', formData.get('unidade') || 'un');
    
    // Determinar ação
    const acao = modoEdicao ? 'editar_produto' : 'adicionar_produto';
    formData.append('acao', acao);
    
    if (modoEdicao) {
        formData.append('id', produtoEdicaoId);
    }
    
    const loadingText = modoEdicao ? 'Salvando alterações...' : 'Adicionando produto...';
    showLoader(loadingText);
    
    // Enviar dados
    fetch('estoque.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        if (data.sucesso) {
            showNotification(data.mensagem, 'success');
            fecharModal('modalFormulario');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification('Erro: ' + data.mensagem, 'error');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Erro:', error);
        showNotification('Erro ao salvar produto', 'error');
    });
}

// ===== FUNÇÕES DE SOLICITAÇÃO DE PRODUTOS =====

// Abrir modal para adicionar produto solicitado
function abrirModalAdicionarSolicitacao() {
    limparFormularioSolicitacao();
    abrirModal('modalAdicionarSolicitacao');
}

// Limpar formulário do modal de solicitação
function limparFormularioSolicitacao() {
    document.getElementById('formAdicionarSolicitacao').reset();
    document.getElementById('lojaSolicitadaModal').value = '';
    document.getElementById('unidadeSolicitadaModal').value = '';
    document.getElementById('precoSolicitadoModal').value = '';
}

function atualizarDadosProdutoModal() {
    const select = document.getElementById('produtoSolicitadoModal');
    const option = select.options[select.selectedIndex];
    const inputUnidade = document.getElementById('unidadeSolicitadaModal');
    const inputPreco = document.getElementById('precoSolicitadoModal');
    const inputQuantidade = document.getElementById('quantidadeSolicitadaModal');

    if (option && option.value) {
        inputUnidade.value = option.getAttribute('data-unidade') || 'un';
        const preco = option.getAttribute('data-preco') || '0';
        inputPreco.value = formatarMoeda(parseFloat(preco));

        // Set max quantity based on stock
        const stock = parseInt(option.getAttribute('data-stock')) || 0;
        inputQuantidade.max = stock;
        if (inputQuantidade.value > stock) {
            inputQuantidade.value = stock;
        }
    } else {
        inputUnidade.value = '';
        inputPreco.value = '';
        inputQuantidade.removeAttribute('max');
    }
}

// Adicionar produto à lista de solicitados a partir do modal
function adicionarProdutoSolicitadoModal() {
    const lojaInput = document.getElementById('lojaSolicitadaModal');
    const selectProduto = document.getElementById('produtoSolicitadoModal');
    const quantidadeInput = document.getElementById('quantidadeSolicitadaModal');
    const unidadeInput = document.getElementById('unidadeSolicitadaModal');
    const precoInput = document.getElementById('precoSolicitadoModal');

    const loja = lojaInput.value.trim();
    const produtoId = selectProduto.value;
    const produtoNome = selectProduto.options[selectProduto.selectedIndex].text;
    const quantidade = parseInt(quantidadeInput.value);
    const unidade = unidadeInput.value;
    const precoTexto = precoInput.value;

    // Validações
    if (!loja) {
        showNotification('Informe a loja.', 'warning');
        lojaInput.focus();
        return;
    }
    if (!produtoId) {
        showNotification('Selecione um produto.', 'warning');
        selectProduto.focus();
        return;
    }
    if (quantidade <= 0 || isNaN(quantidade)) {
        showNotification('Informe uma quantidade válida.', 'warning');
        quantidadeInput.focus();
        return;
    }

    // Extrair valor numérico do preço
    const precoNumerico = parseFloat(precoTexto.replace(/[^\d,.-]/g, '').replace(',', '.'));

    // Verificar se produto já está na lista para a mesma loja
    const existenteIndex = produtosSolicitados.findIndex(p => 
        p.produto_id === produtoId && p.loja.toLowerCase() === loja.toLowerCase()
    );
    
    if (existenteIndex !== -1) {
        // Atualizar quantidade do produto existente
        produtosSolicitados[existenteIndex].quantidade += quantidade;
        showNotification('Quantidade atualizada para produto existente na lista', 'info');
    } else {
        // Adicionar novo produto
        produtosSolicitados.push({
            loja: loja,
            produto_id: produtoId,
            nome: produtoNome,
            quantidade: quantidade,
            unidade: unidade,
            preco_venda: precoNumerico
        });
        showNotification('Produto adicionado à solicitação', 'success');
    }

    atualizarTabelaProdutosSolicitados();
    fecharModal('modalAdicionarSolicitacao');
}

// Atualizar tabela de produtos solicitados
function atualizarTabelaProdutosSolicitados() {
    const tbody = document.getElementById('corpoTabelaProdutosSolicitados');
    
    if (!tbody) return;
    
    tbody.innerHTML = '';

    if (produtosSolicitados.length === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                    <svg class="icon" viewBox="0 0 24 24" style="width: 48px; height: 48px; opacity: 0.5;">
                        <rect x="3" y="4" width="18" height="16" rx="2" ry="2"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                        <line x1="9" y1="4" x2="9" y2="20"/>
                    </svg>
                    <div>
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Nenhum produto solicitado</h4>
                        <p>Clique em "Adicionar Produto" para começar</p>
                    </div>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
        return;
    }

    produtosSolicitados.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${item.loja}</strong></td>
            <td>
                <div>
                    <strong>${item.nome}</strong>
                    <div style="font-size: 0.75rem; color: #64748b;">ID: ${item.produto_id}</div>
                </div>
            </td>
            <td><strong>${item.quantidade}</strong></td>
            <td>${item.unidade}</td>
            <td><strong>${formatarMoeda(item.preco_venda)}</strong></td>
            <td>
                <div class="actions">
                    <button class="btn btn-ghost btn-sm" onclick="visualizarProdutoSolicitado(${index})" title="Visualizar">
                        <svg class="icon-sm" viewBox="0 0 24 24">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                    <button class="btn btn-ghost btn-sm" onclick="editarProdutoSolicitado(${index})" title="Editar">
                        <svg class="icon-sm" viewBox="0 0 24 24">
                            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>
                        </svg>
                    </button>
                    <button class="btn btn-ghost btn-danger btn-sm" onclick="excluirProdutoSolicitado(${index})" title="Excluir">
                        <svg class="icon-sm" viewBox="0 0 24 24">
                            <polyline points="3,6 5,6 21,6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Mostrar/esconder botão de envio
    const btnEnviar = document.getElementById('btnEnviarSolicitacao');
    if (btnEnviar) {
        btnEnviar.style.display = produtosSolicitados.length > 0 ? 'inline-flex' : 'none';
    }
}

// Visualizar produto solicitado
function visualizarProdutoSolicitado(index) {
    const item = produtosSolicitados[index];
    const detalhes = `
        Loja: ${item.loja}
        Produto: ${item.nome}
        Quantidade: ${item.quantidade}
        Unidade: ${item.unidade}
        Preço de Venda: ${formatarMoeda(item.preco_venda)}
        Total: ${formatarMoeda(item.quantidade * item.preco_venda)}
    `;
    alert(detalhes);
}

// Editar produto solicitado
function editarProdutoSolicitado(index) {
    const item = produtosSolicitados[index];
    
    // Preencher formulário do modal com dados do item
    document.getElementById('lojaSolicitadaModal').value = item.loja;
    document.getElementById('produtoSolicitadoModal').value = item.produto_id;
    document.getElementById('quantidadeSolicitadaModal').value = item.quantidade;
    document.getElementById('unidadeSolicitadaModal').value = item.unidade;
    document.getElementById('precoSolicitadoModal').value = formatarMoeda(item.preco_venda);

    // Remover o item antigo para evitar duplicação ao salvar
    produtosSolicitados.splice(index, 1);
    atualizarTabelaProdutosSolicitados();
    
    abrirModal('modalAdicionarSolicitacao');
}

// Excluir produto solicitado
function excluirProdutoSolicitado(index) {
    if (confirm('Tem certeza que deseja remover este produto da solicitação?')) {
        produtosSolicitados.splice(index, 1);
        atualizarTabelaProdutosSolicitados();
        showNotification('Produto removido da solicitação', 'info');
    }
}

function enviarSolicitacao() {
    if (produtosSolicitados.length === 0) {
        showNotification('Adicione pelo menos um produto à solicitação.', 'warning');
        return;
    }

    showLoader('Enviando solicitação...');

    // Prepare data for vendas.php
    const vendasData = produtosSolicitados.map(item => ({
        loja_id: item.loja,
        produto_id: item.produto_id,
        quantidade: item.quantidade,
        preco_unitario: item.preco_venda
    }));

    fetch('vendas.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=processar_solicitacao&itens=' + encodeURIComponent(JSON.stringify(vendasData))
    })
    .then(response => response.json())
    .then(data => {
        hideLoader();
        if (data.success) {
            showNotification('Solicitação enviada com sucesso!', 'success');
            // Resetar lista
            produtosSolicitados = [];
            atualizarTabelaProdutosSolicitados();
        } else {
            showNotification('Erro: ' + data.message, 'error');
        }
    })
    .catch(error => {
        hideLoader();
        console.error('Erro ao enviar solicitação:', error);
        showNotification('Erro ao enviar solicitação.', 'error');
    });
}

// ===== SISTEMA DE NOTIFICAÇÕES =====

function showNotification(message, type = 'info') {
    // Remover notificações anteriores
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;

    document.body.appendChild(notification);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// ===== SISTEMA DE LOADING =====

function showLoader(message = 'Carregando...') {
    let loader = document.getElementById('globalLoader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'globalLoader';
        loader.className = 'global-loader';
        loader.innerHTML = `
            <div class="loader-content">
                <div class="loader-spinner"></div>
                <div class="loader-message">${message}</div>
            </div>
        `;
        document.body.appendChild(loader);
    } else {
        loader.querySelector('.loader-message').textContent = message;
        loader.style.display = 'flex';
    }
}

function hideLoader() {
    const loader = document.getElementById('globalLoader');
    if (loader) {
        loader.style.display = 'none';
    }
}

// ===== EVENT LISTENERS =====

document.addEventListener('DOMContentLoaded', function() {
    
    // Fechar modais clicando fora
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            const modalId = e.target.id;
            fecharModal(modalId);
        }
    });
    
    // Tecla ESC para fechar modais
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modais = document.querySelectorAll('.modal-overlay.active');
            modais.forEach(modal => {
                fecharModal(modal.id);
            });
        }
    });
    
    // Validação do formulário principal
    const form = document.getElementById('formProduto');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            salvarProduto();
        });
    }
    
    // Atualizar dados do produto no modal de solicitação
    const selectProdutoModal = document.getElementById('produtoSolicitadoModal');
    if (selectProdutoModal) {
        selectProdutoModal.addEventListener('change', atualizarDadosProdutoModal);
    }
    
    // Formulário de adição de solicitação
    const formSolicitacao = document.getElementById('formAdicionarSolicitacao');
    if (formSolicitacao) {
        formSolicitacao.addEventListener('submit', function(e) {
            e.preventDefault();
            adicionarProdutoSolicitadoModal();
        });
    }
    
    // Validação de números
    const inputsNumericos = ['quantidade', 'estoque_minimo', 'estoque_maximo', 'custo', 'preco_venda', 'quantidadeSolicitadaModal'];
    inputsNumericos.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        }
    });

    // Filtros com debounce
    const inputBusca = document.getElementById('busca');
    const selectCategoria = document.getElementById('categoria');
    const selectStatus = document.getElementById('status');

    let timeoutId;
    
    function aplicarFiltros() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            const form = document.querySelector('.filters-form');
            if (form) {
                form.submit();
            }
        }, 500);
    }

    if (inputBusca) {
        inputBusca.addEventListener('input', aplicarFiltros);
    }
    
    if (selectCategoria) {
        selectCategoria.addEventListener('change', aplicarFiltros);
    }
    
    if (selectStatus) {
        selectStatus.addEventListener('change', aplicarFiltros);
    }

    // Inicializar tabela de produtos solicitados
    atualizarTabelaProdutosSolicitados();
    
    // Verificar se há produtos para mostrar/ocultar filtros
    const formFiltros = document.querySelector('.filters-form');
    const totalProdutos = parseInt(document.querySelector('.card-value')?.textContent || '0');

    if (totalProdutos === 0 && formFiltros) {
        formFiltros.style.display = 'none';
    }
});

// Funções auxiliares para formatação
function formatarMoedaInput(input) {
    let valor = input.value.replace(/[^\d,]/g, '');
    valor = valor.replace(',', '.');
    input.value = valor;
}

// Função para animação suave dos elementos
function animateElement(element, animation = 'fadeIn') {
    element.style.animation = `${animation} 0.3s ease-in-out`;
}

// Adicionar estilos dinâmicos para notificações e loader
const style = document.createElement('style');
style.textContent = `
    /* Notificações */
    .notification {
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

    .notification-info {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        color: #1e40af;
    }

    .notification-success {
        background: #dcfce7;
        border-left: 4px solid #16a34a;
        color: #166534;
    }

    .notification-warning {
        background: #fef3c7;
        border-left: 4px solid #f59e0b;
        color: #92400e;
    }

    .notification-error {
        background: #fee2e2;
        border-left: 4px solid #ef4444;
        color: #991b1b;
    }

    .notification-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        margin-left: 12px;
        opacity: 0.7;
    }

    .notification-close:hover {
        opacity: 1;
    }

    /* Loader global */
    .global-loader {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9998;
    }

    .loader-content {
        background: white;
        padding: 2rem;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .loader-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f4f6;
        border-top: 4px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
    }

    .loader-message {
        color: #374151;
        font-weight: 500;
    }

    /* Animações */
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

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    /* Melhorias visuais */
    .form-group {
        transition: all 0.2s ease;
    }

    .input:focus, .select:focus {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
    }

    .btn {
        transition: all 0.2s ease;
    }

    .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .table tr {
        transition: background-color 0.2s ease;
    }
`;

document.head.appendChild(style);
