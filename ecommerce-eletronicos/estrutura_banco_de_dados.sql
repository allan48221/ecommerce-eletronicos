-- =====================================================
-- SCHEMA DO BANCO: ecommerce_eletronicos_fk2k
-- Apenas estrutura (sem dados)
-- Gerado em: 2026-04-23
-- =====================================================

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

-- ---------------------------------------------------
-- FUNÇÃO: fn_updated_at
-- ---------------------------------------------------
CREATE FUNCTION public.fn_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.atualizado_em = NOW();
    RETURN NEW;
END;
$$;

SET default_tablespace = '';
SET default_table_access_method = heap;

-- ---------------------------------------------------
-- TABELAS
-- ---------------------------------------------------

CREATE TABLE public.adicionais (
    id_adicional integer NOT NULL,
    nome character varying(100) NOT NULL,
    preco numeric(10,2) DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    id_tenant integer
);

CREATE SEQUENCE public.adicionais_id_adicional_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.adicionais_id_adicional_seq OWNED BY public.adicionais.id_adicional;

-- ---------------------------------------------------

CREATE TABLE public.administradores (
    id_admin integer NOT NULL,
    nome character varying(150) NOT NULL,
    usuario character varying(100) NOT NULL,
    email character varying(150) NOT NULL,
    senha character varying(255) NOT NULL,
    ativo boolean DEFAULT true,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE SEQUENCE public.administradores_id_admin_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.administradores_id_admin_seq OWNED BY public.administradores.id_admin;

-- ---------------------------------------------------

CREATE TABLE public.admins (
    id_admin integer NOT NULL,
    nome character varying(150) NOT NULL,
    email character varying(150) NOT NULL,
    senha character varying(255) NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    usuario character varying(100),
    id_tenant integer
);

CREATE SEQUENCE public.admins_id_admin_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.admins_id_admin_seq OWNED BY public.admins.id_admin;

-- ---------------------------------------------------

CREATE TABLE public.caixa_movimentacoes (
    id_mov integer NOT NULL,
    id_sessao integer NOT NULL,
    tipo character varying(20) NOT NULL,
    descricao character varying(255),
    valor numeric(10,2) NOT NULL,
    operador character varying(120),
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);

CREATE SEQUENCE public.caixa_movimentacoes_id_mov_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.caixa_movimentacoes_id_mov_seq OWNED BY public.caixa_movimentacoes.id_mov;

-- ---------------------------------------------------

CREATE TABLE public.caixa_sessoes (
    id_sessao integer NOT NULL,
    aberto_por character varying(120) NOT NULL,
    fechado_por character varying(120),
    valor_inicial numeric(10,2) DEFAULT 0 NOT NULL,
    valor_contado numeric(10,2),
    diferenca numeric(10,2),
    justificativa text,
    status character varying(20) DEFAULT 'aberto'::character varying NOT NULL,
    aberto_em timestamp without time zone DEFAULT now() NOT NULL,
    fechado_em timestamp without time zone,
    observacao text
);

CREATE SEQUENCE public.caixa_sessoes_id_sessao_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.caixa_sessoes_id_sessao_seq OWNED BY public.caixa_sessoes.id_sessao;

-- ---------------------------------------------------

CREATE TABLE public.cancelamentos (
    id integer NOT NULL,
    id_pedido integer NOT NULL,
    motivo text NOT NULL,
    id_admin integer,
    data_cancelamento timestamp with time zone DEFAULT now() NOT NULL
);

CREATE SEQUENCE public.cancelamentos_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.cancelamentos_id_seq OWNED BY public.cancelamentos.id;

-- ---------------------------------------------------

CREATE TABLE public.categorias (
    id_categoria integer NOT NULL,
    nome character varying(100) NOT NULL,
    descricao text,
    ativo boolean DEFAULT true,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_tenant integer
);

CREATE SEQUENCE public.categorias_id_categoria_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.categorias_id_categoria_seq OWNED BY public.categorias.id_categoria;

-- ---------------------------------------------------

CREATE TABLE public.clientes (
    id_cliente integer NOT NULL,
    nome character varying(150) NOT NULL,
    email character varying(150) NOT NULL,
    senha character varying(255) NOT NULL,
    cpf character varying(14),
    telefone character varying(20),
    cep character varying(9),
    endereco character varying(200),
    numero character varying(20),
    complemento character varying(100),
    bairro character varying(100),
    cidade character varying(100),
    estado character(2),
    ativo boolean DEFAULT true,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_tenant integer
);

CREATE SEQUENCE public.clientes_id_cliente_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.clientes_id_cliente_seq OWNED BY public.clientes.id_cliente;

-- ---------------------------------------------------

CREATE TABLE public.comanda_itens (
    id_item integer NOT NULL,
    id_comanda integer NOT NULL,
    id_produto integer,
    nome_produto character varying(255) NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    quantidade integer DEFAULT 1 NOT NULL,
    subtotal numeric(10,2) NOT NULL,
    adicionais text,
    observacao text,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    lancado_por character varying(100)
);

CREATE SEQUENCE public.comanda_itens_id_item_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.comanda_itens_id_item_seq OWNED BY public.comanda_itens.id_item;

-- ---------------------------------------------------

CREATE TABLE public.comanda_itens_removidos (
    id integer NOT NULL,
    id_comanda integer NOT NULL,
    numero_comanda character varying(50),
    nome_produto character varying(255),
    quantidade integer DEFAULT 1,
    subtotal numeric(10,2) DEFAULT 0,
    removido_por character varying(255),
    removido_em timestamp without time zone DEFAULT now(),
    id_tenant integer
);

CREATE SEQUENCE public.comanda_itens_removidos_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.comanda_itens_removidos_id_seq OWNED BY public.comanda_itens_removidos.id;

-- ---------------------------------------------------

CREATE TABLE public.comandas (
    id_comanda integer NOT NULL,
    numero_comanda character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'aberta'::character varying NOT NULL,
    observacao text,
    valor_total numeric(10,2) DEFAULT 0 NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    fechado_em timestamp without time zone,
    id_admin integer,
    lancado_por character varying(100),
    id_tenant integer,
    CONSTRAINT status_check CHECK (((status)::text = ANY (ARRAY[
        ('aberta'::character varying)::text,
        ('fechada'::character varying)::text,
        ('cancelada'::character varying)::text
    ])))
);

CREATE SEQUENCE public.comandas_id_comanda_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.comandas_id_comanda_seq OWNED BY public.comandas.id_comanda;

-- ---------------------------------------------------

CREATE TABLE public.compra_itens (
    id_item integer NOT NULL,
    id_compra integer NOT NULL,
    id_produto integer,
    nome_produto character varying(200) NOT NULL,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL
);

CREATE SEQUENCE public.compra_itens_id_item_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.compra_itens_id_item_seq OWNED BY public.compra_itens.id_item;

-- ---------------------------------------------------

CREATE TABLE public.compras (
    id_compra integer NOT NULL,
    id_cliente integer,
    valor_produtos numeric(10,2) DEFAULT 0.00,
    valor_frete numeric(10,2) DEFAULT 0.00,
    valor_total numeric(10,2) DEFAULT 0.00,
    cep_entrega character varying(9),
    endereco_entrega character varying(200),
    numero_entrega character varying(20),
    complemento_entrega character varying(100),
    bairro_entrega character varying(100),
    cidade_entrega character varying(100),
    estado_entrega character(2),
    forma_pagamento character varying(80),
    observacoes text,
    status character varying(30) DEFAULT 'pendente'::character varying,
    data_compra timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_tenant integer
);

CREATE SEQUENCE public.compras_id_compra_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.compras_id_compra_seq OWNED BY public.compras.id_compra;

-- ---------------------------------------------------

CREATE TABLE public.configuracoes (
    id integer NOT NULL,
    chave character varying(100) NOT NULL,
    valor character varying(255) NOT NULL,
    id_tenant integer
);

CREATE SEQUENCE public.configuracoes_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.configuracoes_id_seq OWNED BY public.configuracoes.id;

-- ---------------------------------------------------

CREATE TABLE public.empresa (
    id integer NOT NULL,
    nome_empresa character varying(200) DEFAULT ''::character varying NOT NULL,
    nome_fantasia character varying(200) DEFAULT ''::character varying NOT NULL,
    cnpj character varying(20) DEFAULT ''::character varying NOT NULL,
    telefone character varying(20) DEFAULT ''::character varying NOT NULL,
    celular character varying(20) DEFAULT ''::character varying NOT NULL,
    email character varying(200) DEFAULT ''::character varying NOT NULL,
    nome_responsavel character varying(200) DEFAULT ''::character varying NOT NULL,
    cep character varying(10) DEFAULT ''::character varying NOT NULL,
    endereco character varying(200) DEFAULT ''::character varying NOT NULL,
    numero character varying(20) DEFAULT ''::character varying NOT NULL,
    complemento character varying(100) DEFAULT ''::character varying NOT NULL,
    bairro character varying(100) DEFAULT ''::character varying NOT NULL,
    cidade character varying(100) DEFAULT ''::character varying NOT NULL,
    uf character varying(2) DEFAULT ''::character varying NOT NULL,
    site character varying(200) DEFAULT ''::character varying NOT NULL,
    atualizado_em timestamp with time zone DEFAULT now() NOT NULL,
    instagram character varying(200) DEFAULT ''::character varying NOT NULL,
    whatsapp character varying(20) DEFAULT ''::character varying NOT NULL,
    horario_atendimento character varying(200) DEFAULT ''::character varying NOT NULL,
    formas_pagamento character varying(200) DEFAULT ''::character varying NOT NULL,
    descricao_loja text DEFAULT ''::text NOT NULL,
    id_tenant integer,
    logo character varying(600) DEFAULT ''::character varying NOT NULL
);

CREATE SEQUENCE public.empresa_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.empresa_id_seq OWNED BY public.empresa.id;

-- ---------------------------------------------------

CREATE TABLE public.empresas_tenants (
    id_tenant integer NOT NULL,
    nome character varying(150) NOT NULL,
    subdominio character varying(80) NOT NULL,
    cnpj character varying(20),
    email character varying(150),
    telefone character varying(20),
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    atualizado_em timestamp without time zone DEFAULT now() NOT NULL
);

CREATE SEQUENCE public.empresas_tenants_id_tenant_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.empresas_tenants_id_tenant_seq OWNED BY public.empresas_tenants.id_tenant;

-- ---------------------------------------------------

CREATE TABLE public.impressoras (
    id integer NOT NULL,
    nome character varying(100) NOT NULL,
    ip character varying(45) NOT NULL,
    porta character varying(10) DEFAULT '9100'::character varying NOT NULL,
    categorias text[] DEFAULT '{}'::text[] NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp with time zone DEFAULT now() NOT NULL,
    atualizado_em timestamp with time zone DEFAULT now() NOT NULL,
    id_tenant integer
);

CREATE SEQUENCE public.impressoras_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.impressoras_id_seq OWNED BY public.impressoras.id;

-- ---------------------------------------------------

CREATE TABLE public.itens_compra (
    id integer NOT NULL,
    id_compra integer NOT NULL,
    id_produto integer,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL
);

CREATE SEQUENCE public.itens_compra_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.itens_compra_id_seq OWNED BY public.itens_compra.id;

-- ---------------------------------------------------

CREATE TABLE public.itens_pedido (
    id integer NOT NULL,
    id_pedido integer NOT NULL,
    id_produto integer,
    nome_produto character varying(200) NOT NULL,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL,
    adicionais_json text
);

CREATE SEQUENCE public.itens_pedido_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.itens_pedido_id_seq OWNED BY public.itens_pedido.id;

-- ---------------------------------------------------

CREATE TABLE public.licencas (
    id_licenca integer NOT NULL,
    id_tenant integer NOT NULL,
    id_plano integer NOT NULL,
    data_inicio date DEFAULT CURRENT_DATE NOT NULL,
    data_vencimento date NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    observacao text,
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);

CREATE SEQUENCE public.licencas_id_licenca_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.licencas_id_licenca_seq OWNED BY public.licencas.id_licenca;

-- ---------------------------------------------------

CREATE TABLE public.locais_permitidos (
    id integer NOT NULL,
    id_tenant integer NOT NULL,
    nome character varying(100) NOT NULL,
    lat numeric(10,8) NOT NULL,
    lng numeric(11,8) NOT NULL,
    raio_metros integer DEFAULT 30,
    ativo boolean DEFAULT true,
    criado_em timestamp with time zone DEFAULT now()
);

CREATE SEQUENCE public.locais_permitidos_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.locais_permitidos_id_seq OWNED BY public.locais_permitidos.id;

-- ---------------------------------------------------

CREATE TABLE public.logs (
    id integer NOT NULL,
    tipo character varying(100) NOT NULL,
    titulo character varying(255) NOT NULL,
    detalhe text DEFAULT ''::text,
    valor numeric(10,2) DEFAULT 0,
    ref_id integer,
    feito_por character varying(255) DEFAULT NULL::character varying,
    ip character varying(45) DEFAULT NULL::character varying,
    criado_em timestamp without time zone DEFAULT now(),
    id_tenant integer
);

CREATE SEQUENCE public.logs_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.logs_id_seq OWNED BY public.logs.id;

-- ---------------------------------------------------

CREATE TABLE public.pedido_itens (
    id_item integer NOT NULL,
    id_pedido integer NOT NULL,
    id_produto integer,
    nome_produto character varying(200) NOT NULL,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL
);

CREATE SEQUENCE public.pedido_itens_id_item_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.pedido_itens_id_item_seq OWNED BY public.pedido_itens.id_item;

-- ---------------------------------------------------

CREATE TABLE public.pedidos (
    id_pedido integer NOT NULL,
    nome_cliente character varying(150) NOT NULL,
    email_cliente character varying(150),
    cpf_cliente character varying(14),
    telefone_cliente character varying(20),
    endereco character varying(200),
    numero character varying(20),
    complemento character varying(100),
    bairro character varying(100),
    cidade character varying(100),
    estado character(2),
    cep character varying(9),
    forma_pagamento character varying(80),
    observacoes text,
    valor_produtos numeric(10,2) DEFAULT 0.00,
    valor_frete numeric(10,2) DEFAULT 0.00,
    valor_total numeric(10,2) DEFAULT 0.00,
    status character varying(30) DEFAULT 'pendente'::character varying,
    data_pedido timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    data_acao timestamp without time zone,
    tipo character varying(20) DEFAULT 'online'::character varying,
    kds_status character varying(20) DEFAULT 'novo'::character varying,
    kds_tipo character varying(20) DEFAULT 'online'::character varying,
    id_tenant integer
);

CREATE SEQUENCE public.pedidos_id_pedido_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.pedidos_id_pedido_seq OWNED BY public.pedidos.id_pedido;

-- ---------------------------------------------------

CREATE TABLE public.php_sessions (
    id character varying(255) NOT NULL,
    data text NOT NULL,
    last_access bigint NOT NULL
);

-- ---------------------------------------------------

CREATE TABLE public.plano_telas (
    id_plano integer NOT NULL,
    id_tela integer NOT NULL
);

-- ---------------------------------------------------

CREATE TABLE public.planos (
    id_plano integer NOT NULL,
    nome character varying(80) NOT NULL,
    descricao text,
    preco numeric(10,2) DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);

CREATE SEQUENCE public.planos_id_plano_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.planos_id_plano_seq OWNED BY public.planos.id_plano;

-- ---------------------------------------------------

CREATE TABLE public.produto_adicionais (
    id integer NOT NULL,
    id_produto integer NOT NULL,
    id_adicional integer NOT NULL
);

CREATE SEQUENCE public.produto_adicionais_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.produto_adicionais_id_seq OWNED BY public.produto_adicionais.id;

-- ---------------------------------------------------

CREATE TABLE public.produto_imagens (
    id integer NOT NULL,
    id_produto integer NOT NULL,
    imagem character varying(255) NOT NULL,
    ordem integer DEFAULT 0
);

CREATE SEQUENCE public.produto_imagens_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.produto_imagens_id_seq OWNED BY public.produto_imagens.id;

-- ---------------------------------------------------

CREATE TABLE public.produto_views (
    id integer NOT NULL,
    id_produto integer NOT NULL,
    session_id character varying(200),
    data_view timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip character varying(45)
);

CREATE SEQUENCE public.produto_views_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.produto_views_id_seq OWNED BY public.produto_views.id;

-- ---------------------------------------------------

CREATE TABLE public.produtos (
    id_produto integer NOT NULL,
    id_categoria integer,
    nome character varying(200) NOT NULL,
    descricao text,
    marca character varying(100),
    modelo character varying(100),
    preco numeric(10,2) DEFAULT 0.00 NOT NULL,
    preco_promocional numeric(10,2) DEFAULT 0.00,
    estoque integer DEFAULT 0 NOT NULL,
    imagem character varying(255),
    destaque boolean DEFAULT false,
    ativo boolean DEFAULT true,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    codigo_barras character varying(50) DEFAULT NULL::character varying,
    id_tenant integer
);

CREATE SEQUENCE public.produtos_id_produto_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.produtos_id_produto_seq OWNED BY public.produtos.id_produto;

-- ---------------------------------------------------

CREATE TABLE public.staff (
    id_staff integer NOT NULL,
    nome character varying(100) NOT NULL,
    usuario character varying(60) NOT NULL,
    senha character varying(255) NOT NULL,
    tipo character varying(20) DEFAULT 'vendedor'::character varying NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    id_tenant integer,
    CONSTRAINT tipo_check CHECK (((tipo)::text = ANY (ARRAY[
        ('vendedor'::character varying)::text,
        ('atendente'::character varying)::text,
        ('caixa'::character varying)::text,
        ('admin'::character varying)::text
    ])))
);

CREATE SEQUENCE public.staff_id_staff_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.staff_id_staff_seq OWNED BY public.staff.id_staff;

-- ---------------------------------------------------

CREATE TABLE public.telas (
    id_tela integer NOT NULL,
    chave character varying(80) NOT NULL,
    nome character varying(120) NOT NULL,
    descricao text,
    ativo boolean DEFAULT true NOT NULL
);

CREATE SEQUENCE public.telas_id_tela_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.telas_id_tela_seq OWNED BY public.telas.id_tela;

-- ---------------------------------------------------

CREATE TABLE public.vendas_presenciais (
    id integer NOT NULL,
    id_pedido integer NOT NULL,
    id_produto integer NOT NULL,
    quantidade integer NOT NULL,
    id_admin integer,
    criado_em timestamp without time zone DEFAULT now(),
    id_vendedor integer
);

CREATE SEQUENCE public.vendas_presenciais_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.vendas_presenciais_id_seq OWNED BY public.vendas_presenciais.id;

-- ---------------------------------------------------

CREATE TABLE public.vendedores (
    id_vendedor integer NOT NULL,
    nome character varying(100) NOT NULL,
    usuario character varying(50) NOT NULL,
    senha character varying(255) NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);

CREATE SEQUENCE public.vendedores_id_vendedor_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.vendedores_id_vendedor_seq OWNED BY public.vendedores.id_vendedor;

-- ---------------------------------------------------

CREATE TABLE public.visitas (
    id integer NOT NULL,
    session_id character varying(200) NOT NULL,
    ip character varying(50),
    data_visita timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_tenant integer
);

CREATE SEQUENCE public.visitas_id_seq
    AS integer START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.visitas_id_seq OWNED BY public.visitas.id;

-- ---------------------------------------------------
-- ALTER COLUMN DEFAULTS (vincular sequences)
-- ---------------------------------------------------

ALTER TABLE ONLY public.adicionais ALTER COLUMN id_adicional SET DEFAULT nextval('public.adicionais_id_adicional_seq'::regclass);
ALTER TABLE ONLY public.administradores ALTER COLUMN id_admin SET DEFAULT nextval('public.administradores_id_admin_seq'::regclass);
ALTER TABLE ONLY public.admins ALTER COLUMN id_admin SET DEFAULT nextval('public.admins_id_admin_seq'::regclass);
ALTER TABLE ONLY public.caixa_movimentacoes ALTER COLUMN id_mov SET DEFAULT nextval('public.caixa_movimentacoes_id_mov_seq'::regclass);
ALTER TABLE ONLY public.caixa_sessoes ALTER COLUMN id_sessao SET DEFAULT nextval('public.caixa_sessoes_id_sessao_seq'::regclass);
ALTER TABLE ONLY public.cancelamentos ALTER COLUMN id SET DEFAULT nextval('public.cancelamentos_id_seq'::regclass);
ALTER TABLE ONLY public.categorias ALTER COLUMN id_categoria SET DEFAULT nextval('public.categorias_id_categoria_seq'::regclass);
ALTER TABLE ONLY public.clientes ALTER COLUMN id_cliente SET DEFAULT nextval('public.clientes_id_cliente_seq'::regclass);
ALTER TABLE ONLY public.comanda_itens ALTER COLUMN id_item SET DEFAULT nextval('public.comanda_itens_id_item_seq'::regclass);
ALTER TABLE ONLY public.comanda_itens_removidos ALTER COLUMN id SET DEFAULT nextval('public.comanda_itens_removidos_id_seq'::regclass);
ALTER TABLE ONLY public.comandas ALTER COLUMN id_comanda SET DEFAULT nextval('public.comandas_id_comanda_seq'::regclass);
ALTER TABLE ONLY public.compra_itens ALTER COLUMN id_item SET DEFAULT nextval('public.compra_itens_id_item_seq'::regclass);
ALTER TABLE ONLY public.compras ALTER COLUMN id_compra SET DEFAULT nextval('public.compras_id_compra_seq'::regclass);
ALTER TABLE ONLY public.configuracoes ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_id_seq'::regclass);
ALTER TABLE ONLY public.empresa ALTER COLUMN id SET DEFAULT nextval('public.empresa_id_seq'::regclass);
ALTER TABLE ONLY public.empresas_tenants ALTER COLUMN id_tenant SET DEFAULT nextval('public.empresas_tenants_id_tenant_seq'::regclass);
ALTER TABLE ONLY public.impressoras ALTER COLUMN id SET DEFAULT nextval('public.impressoras_id_seq'::regclass);
ALTER TABLE ONLY public.itens_compra ALTER COLUMN id SET DEFAULT nextval('public.itens_compra_id_seq'::regclass);
ALTER TABLE ONLY public.itens_pedido ALTER COLUMN id SET DEFAULT nextval('public.itens_pedido_id_seq'::regclass);
ALTER TABLE ONLY public.licencas ALTER COLUMN id_licenca SET DEFAULT nextval('public.licencas_id_licenca_seq'::regclass);
ALTER TABLE ONLY public.locais_permitidos ALTER COLUMN id SET DEFAULT nextval('public.locais_permitidos_id_seq'::regclass);
ALTER TABLE ONLY public.logs ALTER COLUMN id SET DEFAULT nextval('public.logs_id_seq'::regclass);
ALTER TABLE ONLY public.pedido_itens ALTER COLUMN id_item SET DEFAULT nextval('public.pedido_itens_id_item_seq'::regclass);
ALTER TABLE ONLY public.pedidos ALTER COLUMN id_pedido SET DEFAULT nextval('public.pedidos_id_pedido_seq'::regclass);
ALTER TABLE ONLY public.planos ALTER COLUMN id_plano SET DEFAULT nextval('public.planos_id_plano_seq'::regclass);
ALTER TABLE ONLY public.produto_adicionais ALTER COLUMN id SET DEFAULT nextval('public.produto_adicionais_id_seq'::regclass);
ALTER TABLE ONLY public.produto_imagens ALTER COLUMN id SET DEFAULT nextval('public.produto_imagens_id_seq'::regclass);
ALTER TABLE ONLY public.produto_views ALTER COLUMN id SET DEFAULT nextval('public.produto_views_id_seq'::regclass);
ALTER TABLE ONLY public.produtos ALTER COLUMN id_produto SET DEFAULT nextval('public.produtos_id_produto_seq'::regclass);
ALTER TABLE ONLY public.staff ALTER COLUMN id_staff SET DEFAULT nextval('public.staff_id_staff_seq'::regclass);
ALTER TABLE ONLY public.telas ALTER COLUMN id_tela SET DEFAULT nextval('public.telas_id_tela_seq'::regclass);
ALTER TABLE ONLY public.vendas_presenciais ALTER COLUMN id SET DEFAULT nextval('public.vendas_presenciais_id_seq'::regclass);
ALTER TABLE ONLY public.vendedores ALTER COLUMN id_vendedor SET DEFAULT nextval('public.vendedores_id_vendedor_seq'::regclass);
ALTER TABLE ONLY public.visitas ALTER COLUMN id SET DEFAULT nextval('public.visitas_id_seq'::regclass);

-- ---------------------------------------------------
-- PRIMARY KEYS E CONSTRAINTS
-- ---------------------------------------------------

ALTER TABLE ONLY public.adicionais ADD CONSTRAINT adicionais_pkey PRIMARY KEY (id_adicional);
ALTER TABLE ONLY public.administradores ADD CONSTRAINT administradores_email_key UNIQUE (email);
ALTER TABLE ONLY public.administradores ADD CONSTRAINT administradores_pkey PRIMARY KEY (id_admin);
ALTER TABLE ONLY public.administradores ADD CONSTRAINT administradores_usuario_key UNIQUE (usuario);
ALTER TABLE ONLY public.admins ADD CONSTRAINT admins_email_key UNIQUE (email);
ALTER TABLE ONLY public.admins ADD CONSTRAINT admins_pkey PRIMARY KEY (id_admin);
ALTER TABLE ONLY public.caixa_movimentacoes ADD CONSTRAINT caixa_movimentacoes_pkey PRIMARY KEY (id_mov);
ALTER TABLE ONLY public.caixa_sessoes ADD CONSTRAINT caixa_sessoes_pkey PRIMARY KEY (id_sessao);
ALTER TABLE ONLY public.cancelamentos ADD CONSTRAINT cancelamentos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.categorias ADD CONSTRAINT categorias_pkey PRIMARY KEY (id_categoria);
ALTER TABLE ONLY public.clientes ADD CONSTRAINT clientes_cpf_key UNIQUE (cpf);
ALTER TABLE ONLY public.clientes ADD CONSTRAINT clientes_email_key UNIQUE (email);
ALTER TABLE ONLY public.clientes ADD CONSTRAINT clientes_pkey PRIMARY KEY (id_cliente);
ALTER TABLE ONLY public.comanda_itens ADD CONSTRAINT comanda_itens_pkey PRIMARY KEY (id_item);
ALTER TABLE ONLY public.comanda_itens_removidos ADD CONSTRAINT comanda_itens_removidos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.comandas ADD CONSTRAINT comandas_pkey PRIMARY KEY (id_comanda);
ALTER TABLE ONLY public.compra_itens ADD CONSTRAINT compra_itens_pkey PRIMARY KEY (id_item);
ALTER TABLE ONLY public.compras ADD CONSTRAINT compras_pkey PRIMARY KEY (id_compra);
ALTER TABLE ONLY public.configuracoes ADD CONSTRAINT configuracoes_chave_tenant_unique UNIQUE (chave, id_tenant);
ALTER TABLE ONLY public.configuracoes ADD CONSTRAINT configuracoes_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.empresa ADD CONSTRAINT empresa_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.empresas_tenants ADD CONSTRAINT empresas_tenants_pkey PRIMARY KEY (id_tenant);
ALTER TABLE ONLY public.empresas_tenants ADD CONSTRAINT empresas_tenants_subdominio_key UNIQUE (subdominio);
ALTER TABLE ONLY public.impressoras ADD CONSTRAINT impressoras_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.itens_compra ADD CONSTRAINT itens_compra_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.itens_pedido ADD CONSTRAINT itens_pedido_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.locais_permitidos ADD CONSTRAINT locais_permitidos_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.php_sessions ADD CONSTRAINT php_sessions_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.visitas ADD CONSTRAINT visitas_session_id_unique UNIQUE (session_id);

-- ---------------------------------------------------
-- ROW LEVEL SECURITY
-- ---------------------------------------------------

ALTER TABLE public.categorias ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.clientes ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.pedidos ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.produtos ENABLE ROW LEVEL SECURITY;
