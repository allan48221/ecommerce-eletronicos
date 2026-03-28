let searchTimeout;

function buscarProdutos(termo) {
    clearTimeout(searchTimeout);
    
    const searchResults = document.getElementById('search-results');
    
    if (termo.length < 2) {
        searchResults.classList.remove('active');
        searchResults.innerHTML = '';
        return;
    }
    
    searchResults.innerHTML = '<div class="loading-results">Buscando produtos...</div>';
    searchResults.classList.add('active');
    
    searchTimeout = setTimeout(() => {
        fetch(`api/buscar_produtos.php?q=${encodeURIComponent(termo)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.produtos && data.produtos.length > 0) {
                    searchResults.innerHTML = data.produtos.map(produto => {
                        const preco = parseFloat(produto.preco_atual).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: 'BRL'
                        });
                        
                        return `
<div class="search-result-item" onclick="verProduto(${produto.id_produto})">
    <img class="search-result-img"
         src="uploads/${produto.imagem || 'placeholder.jpg'}"
         alt="${produto.nome}"
         onerror="this.src='https://via.placeholder.com/80'">
    <div class="search-result-info">
        <div class="search-result-name">${produto.nome}</div>
        <div class="search-result-price">${preco}</div>
        ${produto.categoria ? `<div class="search-result-category">${produto.categoria}</div>` : ''}
    </div>
    <button class="result-btn-cart"
        onclick="event.stopPropagation(); adicionarAoCarrinho(${produto.id_produto}, this);">
        Adicionar
    </button>
</div>
                        `;
                    }).join('');
                    searchResults.classList.add('active');
                } else {
                    searchResults.innerHTML = '<div class="no-results">Nenhum produto encontrado</div>';
                    searchResults.classList.add('active');
                }
            })
            .catch(error => {
                console.error('Erro na busca:', error);
                searchResults.innerHTML = '<div class="no-results">Erro ao buscar. Tente novamente.</div>';
                searchResults.classList.add('active');
            });
    }, 300);
}

function executarBusca() {
    const termo = document.getElementById('search-input').value.trim();
    
    if (termo.length < 2) {
        alert('Digite pelo menos 2 caracteres');
        return;
    }
    
    document.getElementById('search-results').classList.remove('active');
    exibirResultadosBusca(termo);
}

function exibirResultadosBusca(termo) {
    let divResultados = document.getElementById('area-resultados-busca');
    
    if (!divResultados) {
        divResultados = document.createElement('div');
        divResultados.id = 'area-resultados-busca';
        
        const cards = document.querySelectorAll('.card');
        let cardDestaque = null;
        
        cards.forEach(card => {
            if (card.textContent.includes('Produtos em Destaque')) {
                cardDestaque = card;
            }
        });
        
        if (cardDestaque) {
            cardDestaque.style.display = 'none';
            cardDestaque.setAttribute('data-hidden-by-search', 'true');
            cardDestaque.parentNode.insertBefore(divResultados, cardDestaque);
        } else {
            const container = document.querySelector('.container');
            const primeiroCard = container ? container.querySelector('.card') : null;
            
            if (primeiroCard && primeiroCard.nextSibling) {
                primeiroCard.parentNode.insertBefore(divResultados, primeiroCard.nextSibling);
            } else if (container) {
                container.appendChild(divResultados);
            }
        }
    }

    divResultados.innerHTML = `
    <div class="card">
        <h2 class="card-title">Resultados para: "${termo}"</h2>
        <div class="produtos-grid" id="grid-resultados">
            <div style="text-align:center; padding:2rem;">Buscando produtos...</div>
        </div>
    </div>
    `;
    
    divResultados.scrollIntoView({ behavior: 'smooth', block: 'start' });
    
    fetch(`api/buscar_produtos.php?q=${encodeURIComponent(termo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.produtos && data.produtos.length > 0) {
                renderizarProdutos(divResultados, termo, data.produtos);
            } else {
                mostrarSemResultados(divResultados, termo);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarErroNaBusca(divResultados);
        });
}

function renderizarProdutos(div, termo, produtos) {
    const htmlProdutos = produtos.map(p => {
        const preco = parseFloat(p.preco_atual).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });

        return `
            <div class="produto-card">
                ${p.destaque ? '<span class="badge-destaque">DESTAQUE</span>' : ''}
                <img class="produto-imagem"
                     src="uploads/${p.imagem || 'placeholder.jpg'}"
                     alt="${p.nome}"
                     onclick="verProduto(${p.id_produto})"
                     onerror="this.src='https://via.placeholder.com/300x300'">
                <div class="produto-info">
                    <h3 class="produto-nome">${p.nome}</h3>
                    <div class="produto-preco">
                        <span class="preco-atual">${preco}</span>
                    </div>
                    ${p.estoque ? `<div class="produto-estoque">✓ ${p.estoque} em estoque</div>` : ''}
                    <button class="btn btn-primary btn-block"
                        onclick="event.stopPropagation(); adicionarAoCarrinho(${p.id_produto}, this);">
                        Adicionar ao Carrinho
                    </button>
                </div>
            </div>
        `;
    }).join('');

    div.innerHTML = `
        <div class="card">
            <h2 class="card-title">Resultados para: "${termo}"</h2>
            <div class="produtos-grid">${htmlProdutos}</div>
        </div>
    `;
}

function mostrarSemResultados(div, termo) {
    div.innerHTML = `
        <div style="max-width:1200px;margin:2rem auto;padding:2rem;background:white;border-radius:1rem;box-shadow:0 4px 20px rgba(0,0,0,0.1);text-align:center;">
            <h2 style="color:#333;">Nenhum produto encontrado</h2>
            <p style="color:#666;">Não encontramos resultados para "<strong>${termo}</strong>"</p>
            <button onclick="fecharBusca()" style="margin-top:1rem;padding:0.75rem 2rem;background:#2563eb;color:white;border:none;border-radius:0.5rem;cursor:pointer;font-size:1rem;">
                Voltar
            </button>
        </div>
    `;
}

function mostrarErroNaBusca(div) {
    div.innerHTML = `
        <div style="max-width:1200px;margin:2rem auto;padding:2rem;background:white;border-radius:1rem;box-shadow:0 4px 20px rgba(0,0,0,0.1);text-align:center;">
            <h2 style="color:#333;">Erro ao buscar produtos</h2>
            <p style="color:#666;">Tente novamente em alguns instantes</p>
            <button onclick="fecharBusca()" style="margin-top:1rem;padding:0.75rem 2rem;background:#2563eb;color:white;border:none;border-radius:0.5rem;cursor:pointer;font-size:1rem;">
                Voltar
            </button>
        </div>
    `;
}

function fecharBusca() {
    const div = document.getElementById('area-resultados-busca');
    if (div) div.remove();
    
    const cardEscondido = document.querySelector('[data-hidden-by-search="true"]');
    if (cardEscondido) {
        cardEscondido.style.display = '';
        cardEscondido.removeAttribute('data-hidden-by-search');
    }
    
    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.value = '';
}

function verProduto(id) {
    window.location.href = `produto.php?id=${id}`;
}

// ── ADICIONAR AO CARRINHO — botão muda para "✓ Adicionado" ──
function adicionarAoCarrinho(idProduto, btn) {
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Adicionando...';
    }

    fetch('api/adicionar_carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_produto=${idProduto}&quantidade=1&acao=adicionar`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (btn) {
                btn.textContent = '✓ Adicionado';
                btn.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                btn.style.cursor = 'default';
            }

          // Atualiza badge desktop
const badge = document.querySelector('.cart-badge');
if (badge) {
    badge.textContent = parseInt(badge.textContent || 0) + 1;
} else {
    const btnCarrinho = document.querySelector('.btn-carrinho');
    if (btnCarrinho) {
        const newBadge = document.createElement('span');
        newBadge.className = 'cart-badge';
        newBadge.textContent = '1';
        btnCarrinho.appendChild(newBadge);
    }
}

// Atualiza badge mobile
const badgeMobile = document.getElementById('carrinho-mobile-badge');
if (badgeMobile) {
    const atual = parseInt(badgeMobile.textContent || 0);
    badgeMobile.textContent = atual + 1;
    badgeMobile.style.display = 'flex';
}
        } else {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Adicionar ao Carrinho';
            }
            alert(data.message || 'Erro ao adicionar produto');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Adicionar ao Carrinho';
        }
    });
}

// ── SIDEBAR ──
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (sidebar) sidebar.classList.toggle('active');
    if (overlay) overlay.classList.toggle('active');
    
    document.body.style.overflow = sidebar?.classList.contains('active') ? 'hidden' : '';
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    if (sidebar) sidebar.classList.remove('active');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
}

// ── CEP / FRETE ──
async function buscarCEP(cep) {
    const cepLimpo = cep.replace(/\D/g, '');
    
    if (cepLimpo.length !== 8) {
        mostrarAlerta('CEP inválido! Digite 8 dígitos.', 'danger');
        return;
    }
    
    const elementoFrete = document.getElementById('valor-frete');
    if (elementoFrete) {
        elementoFrete.innerHTML = `<div style="color:#667eea;">Calculando frete...</div>`;
    }
    
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cepLimpo}/json/`);
        const data = await response.json();
        
        if (data.erro) {
            mostrarAlerta('CEP não encontrado! Verifique e tente novamente.', 'danger');
            if (elementoFrete) elementoFrete.innerHTML = '<span style="color:#dc3545;">CEP inválido</span>';
            return;
        }
        
        preencherEndereco(data);
        
        const freteCalculado = calcularFreteDeBelem(data.uf, data.localidade, cepLimpo);
        exibirInformacoesFrete(freteCalculado, data);
        
        if (document.getElementById('frete-input')) {
            document.getElementById('frete-input').value = freteCalculado.valor;
        }
        atualizarTotal();
        
        mostrarAlerta(`✓ Frete calculado para ${data.localidade}/${data.uf}`, 'success');
        
    } catch (error) {
        console.error('Erro ao buscar CEP:', error);
        mostrarAlerta('Erro ao consultar CEP. Verifique sua conexão.', 'danger');
        if (elementoFrete) elementoFrete.innerHTML = '<span style="color:#dc3545;">Erro na consulta</span>';
    }
}

function preencherEndereco(data) {
    const campos = {
        'endereco': data.logradouro || '',
        'bairro': data.bairro || '',
        'cidade': data.localidade || '',
        'estado': data.uf || '',
        'complemento': data.complemento || ''
    };
    
    Object.keys(campos).forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) elemento.value = campos[id];
    });
}

function calcularFreteDeBelem(uf, cidade, cep) {
    const tabelaFrete = {
        'PA': { base: 12, prazo: '1-3', regiao: 'Norte' },
        'AP': { base: 28, prazo: '3-5', regiao: 'Norte' },
        'AM': { base: 45, prazo: '5-8', regiao: 'Norte' },
        'RR': { base: 65, prazo: '8-12', regiao: 'Norte' },
        'AC': { base: 70, prazo: '9-14', regiao: 'Norte' },
        'RO': { base: 58, prazo: '7-11', regiao: 'Norte' },
        'TO': { base: 38, prazo: '4-7', regiao: 'Norte' },
        'MA': { base: 22, prazo: '2-4', regiao: 'Nordeste' },
        'PI': { base: 32, prazo: '4-6', regiao: 'Nordeste' },
        'CE': { base: 38, prazo: '4-7', regiao: 'Nordeste' },
        'RN': { base: 42, prazo: '5-7', regiao: 'Nordeste' },
        'PB': { base: 44, prazo: '5-8', regiao: 'Nordeste' },
        'PE': { base: 45, prazo: '5-8', regiao: 'Nordeste' },
        'AL': { base: 48, prazo: '6-9', regiao: 'Nordeste' },
        'SE': { base: 50, prazo: '6-9', regiao: 'Nordeste' },
        'BA': { base: 52, prazo: '6-9', regiao: 'Nordeste' },
        'MT': { base: 55, prazo: '7-10', regiao: 'Centro-Oeste' },
        'MS': { base: 62, prazo: '8-11', regiao: 'Centro-Oeste' },
        'GO': { base: 58, prazo: '7-10', regiao: 'Centro-Oeste' },
        'DF': { base: 56, prazo: '7-10', regiao: 'Centro-Oeste' },
        'MG': { base: 65, prazo: '8-12', regiao: 'Sudeste' },
        'ES': { base: 68, prazo: '8-12', regiao: 'Sudeste' },
        'RJ': { base: 72, prazo: '9-13', regiao: 'Sudeste' },
        'SP': { base: 75, prazo: '9-14', regiao: 'Sudeste' },
        'PR': { base: 78, prazo: '10-15', regiao: 'Sul' },
        'SC': { base: 82, prazo: '11-16', regiao: 'Sul' },
        'RS': { base: 88, prazo: '12-18', regiao: 'Sul' }
    };
    
    const dadosFrete = tabelaFrete[uf] || { base: 60, prazo: '7-12', regiao: 'Brasil' };
    let valorFrete = dadosFrete.base;
    let prazo = dadosFrete.prazo;

    const capitais = ['Belém','Macapá','Manaus','Boa Vista','Rio Branco','Porto Velho','Palmas','São Luís','Teresina','Fortaleza','Natal','João Pessoa','Recife','Maceió','Aracaju','Salvador','Cuiabá','Campo Grande','Goiânia','Brasília','Belo Horizonte','Vitória','Rio de Janeiro','São Paulo','Curitiba','Florianópolis','Porto Alegre'];
    const cidadesGrandes = ['Ananindeua','Marituba','Castanhal','Santarém','Marabá','Parauapebas','Parintins','Itacoatiara','Manacapuru','São José de Ribamar','Imperatriz','Campinas','Guarulhos','São Bernardo do Campo','Santos','Ribeirão Preto','Niterói','Duque de Caxias','Uberlândia','Contagem','Londrina','Maringá'];
    
    let tipoLocalidade = '';
    let descontoAcrescimo = 0;
    
    if (uf === 'PA' && cidade === 'Belém') {
        valorFrete = 8.50; prazo = '1-2'; tipoLocalidade = 'Centro de Distribuição';
    } else if (capitais.includes(cidade)) {
        tipoLocalidade = 'Capital'; descontoAcrescimo = -0.05;
    } else if (cidadesGrandes.includes(cidade)) {
        tipoLocalidade = 'Região Metropolitana'; descontoAcrescimo = 0.08;
    } else {
        tipoLocalidade = 'Interior'; descontoAcrescimo = 0.18;
    }
    
    valorFrete = valorFrete * (1 + descontoAcrescimo);
    
    const p1 = parseInt(cep.substring(0, 1));
    const p2 = parseInt(cep.substring(1, 2));
    valorFrete += ((p1 + p2) % 6) * 0.8;
    
    const r = Math.random();
    if (r > 0.7) valorFrete *= 1.15;
    else if (r < 0.3) valorFrete *= 0.92;
    
    valorFrete = Math.ceil(valorFrete);
    const opcoes = [0.50, 0.90];
    valorFrete = Math.floor(valorFrete) + opcoes[Math.floor(Math.random() * opcoes.length)];
    if (valorFrete < 8.50) valorFrete = 8.50;
    
    const distancias = { 'PA':50,'AP':350,'AM':1300,'RR':2100,'AC':2800,'RO':2400,'TO':900,'MA':550,'PI':1100,'CE':1600,'RN':2000,'PB':2200,'PE':2300,'AL':2600,'SE':2700,'BA':2100,'MT':1800,'MS':2800,'GO':2000,'DF':2100,'MG':2800,'ES':3000,'RJ':3200,'SP':3300,'PR':3600,'SC':3900,'RS':4300 };
    
    return {
        valor: parseFloat(valorFrete.toFixed(2)),
        prazo,
        tipoLocalidade,
        regiao: dadosFrete.regiao,
        distanciaKm: distancias[uf] || 2000,
        modalidade: valorFrete < 30 ? 'Econômico' : valorFrete < 60 ? 'Padrão' : 'Expresso'
    };
}

function exibirInformacoesFrete(frete, endereco) {
    const elementoFrete = document.getElementById('valor-frete');
    if (!elementoFrete) return;
    
    elementoFrete.innerHTML = `
        <div style="background:#f8f9fa;padding:1rem;border-radius:0.5rem;border-left:4px solid #28a745;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.75rem;">
                <strong style="font-size:1.25rem;color:#28a745;">${formatarMoeda(frete.valor)}</strong>
                <span style="background:#28a745;color:white;padding:0.25rem 0.75rem;border-radius:1rem;font-size:0.85rem;">${frete.modalidade}</span>
            </div>
            <div style="display:grid;gap:0.5rem;font-size:0.9rem;color:#495057;">
                <div>Entrega em <strong>${frete.prazo} dias úteis</strong></div>
                <div>${frete.tipoLocalidade} • ${endereco.localidade}/${endereco.uf}</div>
                <div>~${frete.distanciaKm}km do CD Belém/PA</div>
                <div>Região ${frete.regiao}</div>
            </div>
        </div>
    `;
}

function atualizarTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal-input')?.value || 0);
    const frete    = parseFloat(document.getElementById('frete-input')?.value || 0);
    const total    = subtotal + frete;
    
    if (document.getElementById('valor-total')) {
        document.getElementById('valor-total').textContent = formatarMoeda(total);
        if (document.getElementById('total-input')) {
            document.getElementById('total-input').value = total;
        }
    }
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor);
}

function validarCPF(cpf) {
    cpf = cpf.replace(/\D/g, '');
    if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
    
    let soma = 0;
    for (let i = 1; i <= 9; i++) soma += parseInt(cpf[i - 1]) * (11 - i);
    let resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    if (resto !== parseInt(cpf[9])) return false;
    
    soma = 0;
    for (let i = 1; i <= 10; i++) soma += parseInt(cpf[i - 1]) * (12 - i);
    resto = (soma * 10) % 11;
    if (resto === 10 || resto === 11) resto = 0;
    return resto === parseInt(cpf[10]);
}

function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function aplicarMascaras() {
    document.querySelectorAll('input[name="cpf"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d)/, '$1.$2');
            v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = v;
        });
    });
    
    document.querySelectorAll('input[name="cep"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            v = v.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = v;
        });
    });
    
    document.querySelectorAll('input[name="telefone"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length <= 10) {
                v = v.replace(/(\d{2})(\d)/, '($1) $2');
                v = v.replace(/(\d{4})(\d)/, '$1-$2');
            } else {
                v = v.replace(/(\d{2})(\d)/, '($1) $2');
                v = v.replace(/(\d{5})(\d)/, '$1-$2');
            }
            e.target.value = v;
        });
    });
}

function setupImagePreview() {
    const inputImagem = document.getElementById('imagem');
    if (!inputImagem) return;
    
    inputImagem.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (!file.type.match('image.*')) {
            mostrarAlerta('Por favor, selecione apenas imagens!', 'danger');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) {
            mostrarAlerta('A imagem deve ter no máximo 5MB!', 'danger');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(event) {
            let preview = document.getElementById('preview-imagem');
            if (!preview) {
                preview = document.createElement('img');
                preview.id = 'preview-imagem';
                preview.className = 'preview-imagem';
                inputImagem.parentElement.appendChild(preview);
            }
            preview.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// Alerta simples (usado apenas para CEP/formulários, NÃO para carrinho)
function mostrarAlerta(mensagem, tipo = 'info') {
    const alertaDiv = document.createElement('div');
    alertaDiv.className = `alert alert-${tipo} fade-in`;
    alertaDiv.innerHTML = `<span>${mensagem}</span>`;
    
    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertaDiv, container.firstChild);
    } else {
        document.body.insertBefore(alertaDiv, document.body.firstChild);
    }
    
    setTimeout(() => {
        alertaDiv.style.opacity = '0';
        setTimeout(() => alertaDiv.remove(), 300);
    }, 3000);
}

function atualizarQuantidade(idProduto, acao) {
    const quantidadeInput = document.querySelector(`input[data-produto="${idProduto}"]`);
    let quantidade = parseInt(quantidadeInput.value);
    
    if (acao === 'aumentar') quantidade++;
    else if (acao === 'diminuir' && quantidade > 1) quantidade--;
    
    quantidadeInput.value = quantidade;
    
    fetch('api/adicionar_carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_produto=${idProduto}&quantidade=${quantidade}&acao=atualizar`
    })
    .then(response => response.json())
    .then(data => { if (data.success) location.reload(); })
    .catch(error => console.error('Erro:', error));
}

function removerDoCarrinho(idProduto) {
    if (!confirm('Deseja realmente remover este item do carrinho?')) return;
    
    fetch('api/adicionar_carrinho.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_produto=${idProduto}&acao=remover`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) setTimeout(() => location.reload(), 500);
    })
    .catch(error => console.error('Erro:', error));
}

// Fecha dropdown de busca ao clicar fora
document.addEventListener('click', function(e) {
    const searchBar = document.querySelector('.search-bar');
    const searchResults = document.getElementById('search-results');
    if (searchBar && searchResults && !searchBar.contains(e.target)) {
        searchResults.classList.remove('active');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    aplicarMascaras();
    setupImagePreview();
    
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                executarBusca();
            }
        });
    }
    
    const cepInput = document.querySelector('input[name="cep"]');
    if (cepInput) {
        cepInput.addEventListener('blur', function() {
            if (this.value.replace(/\D/g, '').length === 8) buscarCEP(this.value);
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });
    
    document.querySelectorAll('#sidebar a').forEach(link => link.addEventListener('click', closeSidebar));
});