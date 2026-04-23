# ClickZapp — Sistema de Gestão para Negócios

Plataforma completa de e-commerce e gestão presencial com suporte a múltiplos tenants, modo restaurante com comandas, caixa, PDV e painel administrativo.

---

## Índice

- [Sobre o Projeto](#sobre-o-projeto)
- [Funcionalidades](#funcionalidades)
- [Tecnologias](#tecnologias)
- [Estrutura de Pastas](#estrutura-de-pastas)
- [Pré-requisitos](#pré-requisitos)
- [Instalação e Execução](#instalação-e-execução)
  - [Com Docker](#com-docker)
  - [Sem Docker](#sem-docker)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Banco de Dados](#banco-de-dados)
- [Módulos do Sistema](#módulos-do-sistema)
- [Controle de Acesso](#controle-de-acesso)
- [Deploy (Render)](#deploy-render)

---

## Sobre o Projeto

O **ClickZapp** é um sistema web multi-tenant desenvolvido em PHP com banco de dados PostgreSQL. Ele oferece tanto uma loja virtual para clientes quanto um robusto painel de gestão para administradores, atendentes e operadores de caixa — tudo em uma única plataforma.

Ideal para restaurantes, lanchonetes, lojas físicas e negócios que precisam de controle de estoque, gestão de comandas, ponto de venda (PDV) e relatórios de vendas.

---

## Funcionalidades

### Loja Virtual (Cliente)
- Catálogo de produtos por categoria
- Carrinho de compras
- Checkout com múltiplas formas de pagamento
- Rastreamento de pedidos
- Cadastro de clientes

### Painel Administrativo
- Dashboard com métricas em tempo real (clientes, produtos, pedidos, faturamento)
- Gerenciamento de produtos, categorias e estoque
- Gestão de pedidos online com notificações push e sonoras
- Personalização de tema (cores) em tempo real
- Controle de usuários e permissões por tela
- Logs de atividades
- Dashboard de vendas

### Modo Restaurante
- **Lançamento de Comandas** por atendentes (mobile-first)
  - Busca de produtos por categoria ou nome
  - Adicionais por produto ou avulsos
  - Envio automático para impressora de cozinha
- **Caixa Completo**
  - Abertura/fechamento de sessão com valor inicial
  - Sangrias, suprimentos e despesas
  - Fechamento de comandas com forma de pagamento
  - Relatório financeiro por sessão
  - Comprovante imprimível (80mm)

### Venda Presencial (PDV)
- Interface desktop e mobile
- Leitura de código de barras via pistola USB
- Busca de produtos por nome
- Histórico de vendas da sessão
- Comprovante imprimível

### Impressão
- Integração com impressoras térmicas via rede (TCP/IP)
- Despacho automático por categoria de produto
- Teste e configuração de impressoras pelo painel

---

## Tecnologias

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP 8.2 |
| Banco de Dados | PostgreSQL |
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Containerização | Docker + Docker Compose |
| Deploy | Render.com |
| Upload de Imagens | Cloudinary |
| Sessões | Armazenadas no banco (tabela `php_sessions`) |

---

## Estrutura de Pastas

```
/
├── config/
│   ├── database.php          # Conexão PDO + session handler no banco
│   ├── cloudinary.php        # Configuração de upload de imagens
│   ├── session_handler.php   # Handler de sessão customizado
│   ├── tema.php              # Aplicação de tema dinâmico (CSS vars)
│   ├── tenant.php            # Resolução de tenant por subdomínio
│   └── verifica_plano.php    # Controle de acesso por plano
│
├── api/
│   ├── adicionais.php        # API de adicionais de produtos
│   ├── adicionar_carrinho.php
│   ├── buscar_produtos.php
│   ├── salvar_cliente.php
│   └── salvar_produto.php
│
├── css/
│   └── style.css             # Estilos globais
│
├── js/
│   └── main.js               # Scripts globais
│
├── admin.php                 # Painel do administrador (dashboard)
├── atendente.php             # Interface de lançamento de comandas
├── caixa.php                 # PDV e gestão de caixa
├── venda_presencial.php      # PDV balcão com leitor de código de barras
├── modo_restaurante.php      # Hub do modo restaurante
├── index.php                 # Loja virtual (vitrine)
├── login.php                 # Login admin
├── login_vendedor.php        # Login atendente/caixa
│
├── cadastro_produto.php      # Cadastro de produtos
├── editar_produto.php        # Edição de produtos
├── categorias.php            # Gestão de categorias
├── adicionais_admin.php      # Gestão de adicionais
├── cadastrar_vendedor.php    # Cadastro de usuários staff
│
├── dashboard_vendas.php      # Relatórios e gráficos de vendas
├── historico_comandas.php    # Histórico de comandas
├── fechamento_caixa.php      # Relatório de fechamento
├── cancelamentos.php         # Gestão de cancelamentos
├── logs.php                  # Visualização de logs
│
├── impressoras.php           # Configuração de impressoras
├── impressoras_ajax.php      # AJAX para impressoras
├── printer_dispatch.php      # Despacho de impressão
├── printer_save.php          # Salvar config de impressora
├── printer_test.php          # Teste de impressora
│
├── empresa.php               # Dados da empresa
├── empresa_helper.php        # Helper para dados da empresa
├── admin_licencas.php        # Gestão de licenças (master)
│
├── estrutura_banco_de_dados.sql  # Script de criação do banco
├── Dockerfile
├── docker-compose.yml
└── render.yaml               # Configuração de deploy no Render
```

---

## Pré-requisitos

- [Docker](https://www.docker.com/) e Docker Compose, **ou**
- PHP 8.2+ com extensões `pdo_pgsql`, `pgsql`, `zip`
- PostgreSQL 14+

---

## Instalação e Execução

### Com Docker

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/seu-repositorio.git
cd seu-repositorio

# 2. Configure as variáveis de ambiente
cp .env.example .env
# Edite o .env com suas credenciais

# 3. Suba os containers
docker-compose up -d

# 4. Acesse em http://localhost
```

### Sem Docker

```bash
# 1. Clone o repositório
git clone https://github.com/seu-usuario/seu-repositorio.git
cd seu-repositorio

# 2. Configure as variáveis de ambiente no seu servidor

# 3. Crie o banco de dados e execute o script
psql -U postgres -c "CREATE DATABASE ecommerce_eletronicos;"
psql -U postgres -d ecommerce_eletronicos -f estrutura_banco_de_dados.sql

# 4. Aponte o servidor web para a raiz do projeto
```

---

## Variáveis de Ambiente

Configure as seguintes variáveis no seu ambiente (`.env`, painel do Render ou `docker-compose.yml`):

| Variável | Descrição | Padrão |
|----------|-----------|--------|
| `DB_HOST` | Host do PostgreSQL | `localhost` |
| `DB_PORT` | Porta do PostgreSQL | `5432` |
| `DB_NAME` | Nome do banco | `ecommerce_eletronicos` |
| `DB_USER` | Usuário do banco | `postgres` |
| `DB_PASSWORD` | Senha do banco | — |

> **Atenção:** nunca commite senhas no repositório. Use variáveis de ambiente ou um arquivo `.env` listado no `.gitignore`.

---

## Banco de Dados

O arquivo `estrutura_banco_de_dados.sql` contém toda a estrutura necessária. As principais tabelas são:

| Tabela | Descrição |
|--------|-----------|
| `admins` | Administradores do sistema |
| `staff` | Atendentes, caixas e outros usuários |
| `empresas_tenants` | Dados dos tenants (multi-tenant) |
| `produtos` | Catálogo de produtos |
| `categorias` | Categorias de produtos |
| `adicionais` | Adicionais configuráveis por produto |
| `clientes` | Clientes da loja |
| `pedidos` / `itens_pedido` | Pedidos online |
| `comandas` / `comanda_itens` | Comandas do modo restaurante |
| `caixa_sessoes` | Sessões de abertura/fechamento de caixa |
| `caixa_movimentacoes` | Sangrias, suprimentos e despesas |
| `compras` | Vendas presenciais e via PDV |
| `logs` | Registro de ações do sistema |
| `php_sessions` | Sessões PHP armazenadas no banco |
| `configuracoes` | Configurações de tema e sistema |

---

## Módulos do Sistema

### Multi-Tenant

O sistema suporta múltiplos negócios em uma única instalação. Cada tenant é identificado pelo subdomínio de acesso e possui seus próprios produtos, pedidos, comandas e configurações.

### Modo Restaurante

Fluxo completo:

```
Atendente lança comanda -> Impressora de cozinha recebe -> Caixa fecha a comanda -> Comprovante gerado
```

### Controle de Planos

O acesso a funcionalidades é controlado pelo plano contratado (`verifica_plano.php`), permitindo liberar ou bloquear telas por tenant.

---

## Controle de Acesso

| Perfil | Acesso |
|--------|--------|
| Master | Acesso total a todos os tenants |
| Admin | Painel completo do seu tenant |
| Caixa | Tela de caixa e fechamento de comandas |
| Atendente | Apenas lançamento de comandas |

---

## Deploy (Render)

O projeto está configurado para deploy automático no [Render.com](https://render.com) via `render.yaml`.

1. Faça o fork/push do repositório no GitHub
2. Conecte o repositório no Render
3. Configure as variáveis de ambiente no painel do Render
4. O deploy é feito automaticamente via Docker

---

## Licença

Este projeto é proprietário. Todos os direitos reservados.
