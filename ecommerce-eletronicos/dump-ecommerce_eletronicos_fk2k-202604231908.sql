--
-- PostgreSQL database dump
--

\restrict 98j0FpSpIs3IeNkhEnDfgKp4h8grpV2JgIvB2fgQb1JQPIaQYl98GLn46jfaOYk

-- Dumped from database version 18.3 (Debian 18.3-1.pgdg12+1)
-- Dumped by pg_dump version 18.3

-- Started on 2026-04-23 19:08:12

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 5 (class 2615 OID 2200)
-- Name: public; Type: SCHEMA; Schema: -; Owner: -
--

-- *not* creating schema, since initdb creates it


--
-- TOC entry 300 (class 1255 OID 16398)
-- Name: fn_updated_at(); Type: FUNCTION; Schema: public; Owner: -
--

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

--
-- TOC entry 219 (class 1259 OID 16399)
-- Name: adicionais; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.adicionais (
    id_adicional integer NOT NULL,
    nome character varying(100) NOT NULL,
    preco numeric(10,2) DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    id_tenant integer
);


--
-- TOC entry 220 (class 1259 OID 16410)
-- Name: adicionais_id_adicional_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.adicionais_id_adicional_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3825 (class 0 OID 0)
-- Dependencies: 220
-- Name: adicionais_id_adicional_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.adicionais_id_adicional_seq OWNED BY public.adicionais.id_adicional;


--
-- TOC entry 221 (class 1259 OID 16411)
-- Name: administradores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.administradores (
    id_admin integer NOT NULL,
    nome character varying(150) NOT NULL,
    usuario character varying(100) NOT NULL,
    email character varying(150) NOT NULL,
    senha character varying(255) NOT NULL,
    ativo boolean DEFAULT true,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


--
-- TOC entry 222 (class 1259 OID 16423)
-- Name: administradores_id_admin_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.administradores_id_admin_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3826 (class 0 OID 0)
-- Dependencies: 222
-- Name: administradores_id_admin_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.administradores_id_admin_seq OWNED BY public.administradores.id_admin;


--
-- TOC entry 223 (class 1259 OID 16424)
-- Name: admins; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 224 (class 1259 OID 16437)
-- Name: admins_id_admin_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.admins_id_admin_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3827 (class 0 OID 0)
-- Dependencies: 224
-- Name: admins_id_admin_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.admins_id_admin_seq OWNED BY public.admins.id_admin;


--
-- TOC entry 225 (class 1259 OID 16438)
-- Name: caixa_movimentacoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.caixa_movimentacoes (
    id_mov integer NOT NULL,
    id_sessao integer NOT NULL,
    tipo character varying(20) NOT NULL,
    descricao character varying(255),
    valor numeric(10,2) NOT NULL,
    operador character varying(120),
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);


--
-- TOC entry 226 (class 1259 OID 16447)
-- Name: caixa_movimentacoes_id_mov_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caixa_movimentacoes_id_mov_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3828 (class 0 OID 0)
-- Dependencies: 226
-- Name: caixa_movimentacoes_id_mov_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caixa_movimentacoes_id_mov_seq OWNED BY public.caixa_movimentacoes.id_mov;


--
-- TOC entry 227 (class 1259 OID 16448)
-- Name: caixa_sessoes; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 228 (class 1259 OID 16461)
-- Name: caixa_sessoes_id_sessao_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.caixa_sessoes_id_sessao_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3829 (class 0 OID 0)
-- Dependencies: 228
-- Name: caixa_sessoes_id_sessao_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.caixa_sessoes_id_sessao_seq OWNED BY public.caixa_sessoes.id_sessao;


--
-- TOC entry 229 (class 1259 OID 16462)
-- Name: cancelamentos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cancelamentos (
    id integer NOT NULL,
    id_pedido integer NOT NULL,
    motivo text NOT NULL,
    id_admin integer,
    data_cancelamento timestamp with time zone DEFAULT now() NOT NULL
);


--
-- TOC entry 230 (class 1259 OID 16472)
-- Name: cancelamentos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cancelamentos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3830 (class 0 OID 0)
-- Dependencies: 230
-- Name: cancelamentos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cancelamentos_id_seq OWNED BY public.cancelamentos.id;


--
-- TOC entry 231 (class 1259 OID 16473)
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id_categoria integer NOT NULL,
    nome character varying(100) NOT NULL,
    descricao text,
    ativo boolean DEFAULT true,
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_tenant integer
);


--
-- TOC entry 232 (class 1259 OID 16482)
-- Name: categorias_id_categoria_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.categorias_id_categoria_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3831 (class 0 OID 0)
-- Dependencies: 232
-- Name: categorias_id_categoria_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.categorias_id_categoria_seq OWNED BY public.categorias.id_categoria;


--
-- TOC entry 233 (class 1259 OID 16483)
-- Name: clientes; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 234 (class 1259 OID 16494)
-- Name: clientes_id_cliente_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.clientes_id_cliente_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3832 (class 0 OID 0)
-- Dependencies: 234
-- Name: clientes_id_cliente_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.clientes_id_cliente_seq OWNED BY public.clientes.id_cliente;


--
-- TOC entry 235 (class 1259 OID 16495)
-- Name: comanda_itens; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 236 (class 1259 OID 16509)
-- Name: comanda_itens_id_item_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comanda_itens_id_item_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3833 (class 0 OID 0)
-- Dependencies: 236
-- Name: comanda_itens_id_item_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comanda_itens_id_item_seq OWNED BY public.comanda_itens.id_item;


--
-- TOC entry 237 (class 1259 OID 16510)
-- Name: comanda_itens_removidos; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 238 (class 1259 OID 16520)
-- Name: comanda_itens_removidos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comanda_itens_removidos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3834 (class 0 OID 0)
-- Dependencies: 238
-- Name: comanda_itens_removidos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comanda_itens_removidos_id_seq OWNED BY public.comanda_itens_removidos.id;


--
-- TOC entry 239 (class 1259 OID 16521)
-- Name: comandas; Type: TABLE; Schema: public; Owner: -
--

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
    CONSTRAINT status_check CHECK (((status)::text = ANY (ARRAY[('aberta'::character varying)::text, ('fechada'::character varying)::text, ('cancelada'::character varying)::text])))
);


--
-- TOC entry 240 (class 1259 OID 16535)
-- Name: comandas_id_comanda_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comandas_id_comanda_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3835 (class 0 OID 0)
-- Dependencies: 240
-- Name: comandas_id_comanda_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comandas_id_comanda_seq OWNED BY public.comandas.id_comanda;


--
-- TOC entry 241 (class 1259 OID 16536)
-- Name: compra_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.compra_itens (
    id_item integer NOT NULL,
    id_compra integer NOT NULL,
    id_produto integer,
    nome_produto character varying(200) NOT NULL,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL
);


--
-- TOC entry 242 (class 1259 OID 16546)
-- Name: compra_itens_id_item_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compra_itens_id_item_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3836 (class 0 OID 0)
-- Dependencies: 242
-- Name: compra_itens_id_item_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compra_itens_id_item_seq OWNED BY public.compra_itens.id_item;


--
-- TOC entry 243 (class 1259 OID 16547)
-- Name: compras; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 244 (class 1259 OID 16558)
-- Name: compras_id_compra_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.compras_id_compra_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3837 (class 0 OID 0)
-- Dependencies: 244
-- Name: compras_id_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.compras_id_compra_seq OWNED BY public.compras.id_compra;


--
-- TOC entry 245 (class 1259 OID 16559)
-- Name: configuracoes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.configuracoes (
    id integer NOT NULL,
    chave character varying(100) NOT NULL,
    valor character varying(255) NOT NULL,
    id_tenant integer
);


--
-- TOC entry 246 (class 1259 OID 16565)
-- Name: configuracoes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.configuracoes_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3838 (class 0 OID 0)
-- Dependencies: 246
-- Name: configuracoes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.configuracoes_id_seq OWNED BY public.configuracoes.id;


--
-- TOC entry 247 (class 1259 OID 16566)
-- Name: empresa; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 248 (class 1259 OID 16614)
-- Name: empresa_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresa_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3839 (class 0 OID 0)
-- Dependencies: 248
-- Name: empresa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresa_id_seq OWNED BY public.empresa.id;


--
-- TOC entry 249 (class 1259 OID 16615)
-- Name: empresas_tenants; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 250 (class 1259 OID 16627)
-- Name: empresas_tenants_id_tenant_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.empresas_tenants_id_tenant_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3840 (class 0 OID 0)
-- Dependencies: 250
-- Name: empresas_tenants_id_tenant_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.empresas_tenants_id_tenant_seq OWNED BY public.empresas_tenants.id_tenant;


--
-- TOC entry 251 (class 1259 OID 16628)
-- Name: impressoras; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 252 (class 1259 OID 16646)
-- Name: impressoras_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.impressoras_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3841 (class 0 OID 0)
-- Dependencies: 252
-- Name: impressoras_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.impressoras_id_seq OWNED BY public.impressoras.id;


--
-- TOC entry 253 (class 1259 OID 16647)
-- Name: itens_compra; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.itens_compra (
    id integer NOT NULL,
    id_compra integer NOT NULL,
    id_produto integer,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL
);


--
-- TOC entry 254 (class 1259 OID 16656)
-- Name: itens_compra_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.itens_compra_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3842 (class 0 OID 0)
-- Dependencies: 254
-- Name: itens_compra_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.itens_compra_id_seq OWNED BY public.itens_compra.id;


--
-- TOC entry 255 (class 1259 OID 16658)
-- Name: itens_pedido; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 256 (class 1259 OID 16670)
-- Name: itens_pedido_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.itens_pedido_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3843 (class 0 OID 0)
-- Dependencies: 256
-- Name: itens_pedido_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.itens_pedido_id_seq OWNED BY public.itens_pedido.id;


--
-- TOC entry 257 (class 1259 OID 16671)
-- Name: licencas; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 258 (class 1259 OID 16686)
-- Name: licencas_id_licenca_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.licencas_id_licenca_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3844 (class 0 OID 0)
-- Dependencies: 258
-- Name: licencas_id_licenca_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.licencas_id_licenca_seq OWNED BY public.licencas.id_licenca;


--
-- TOC entry 288 (class 1259 OID 24591)
-- Name: locais_permitidos; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 287 (class 1259 OID 24590)
-- Name: locais_permitidos_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.locais_permitidos_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3845 (class 0 OID 0)
-- Dependencies: 287
-- Name: locais_permitidos_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.locais_permitidos_id_seq OWNED BY public.locais_permitidos.id;


--
-- TOC entry 259 (class 1259 OID 16687)
-- Name: logs; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 260 (class 1259 OID 16700)
-- Name: logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3846 (class 0 OID 0)
-- Dependencies: 260
-- Name: logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.logs_id_seq OWNED BY public.logs.id;


--
-- TOC entry 261 (class 1259 OID 16701)
-- Name: pedido_itens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.pedido_itens (
    id_item integer NOT NULL,
    id_pedido integer NOT NULL,
    id_produto integer,
    nome_produto character varying(200) NOT NULL,
    quantidade integer DEFAULT 1 NOT NULL,
    preco_unitario numeric(10,2) NOT NULL,
    subtotal numeric(10,2) NOT NULL
);


--
-- TOC entry 262 (class 1259 OID 16711)
-- Name: pedido_itens_id_item_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pedido_itens_id_item_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3847 (class 0 OID 0)
-- Dependencies: 262
-- Name: pedido_itens_id_item_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pedido_itens_id_item_seq OWNED BY public.pedido_itens.id_item;


--
-- TOC entry 263 (class 1259 OID 16712)
-- Name: pedidos; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 264 (class 1259 OID 16727)
-- Name: pedidos_id_pedido_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.pedidos_id_pedido_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3848 (class 0 OID 0)
-- Dependencies: 264
-- Name: pedidos_id_pedido_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.pedidos_id_pedido_seq OWNED BY public.pedidos.id_pedido;


--
-- TOC entry 286 (class 1259 OID 16930)
-- Name: php_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.php_sessions (
    id character varying(255) NOT NULL,
    data text NOT NULL,
    last_access bigint NOT NULL
);


--
-- TOC entry 265 (class 1259 OID 16728)
-- Name: plano_telas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plano_telas (
    id_plano integer NOT NULL,
    id_tela integer NOT NULL
);


--
-- TOC entry 266 (class 1259 OID 16733)
-- Name: planos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.planos (
    id_plano integer NOT NULL,
    nome character varying(80) NOT NULL,
    descricao text,
    preco numeric(10,2) DEFAULT 0 NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);


--
-- TOC entry 267 (class 1259 OID 16746)
-- Name: planos_id_plano_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.planos_id_plano_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3849 (class 0 OID 0)
-- Dependencies: 267
-- Name: planos_id_plano_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.planos_id_plano_seq OWNED BY public.planos.id_plano;


--
-- TOC entry 268 (class 1259 OID 16747)
-- Name: produto_adicionais; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produto_adicionais (
    id integer NOT NULL,
    id_produto integer NOT NULL,
    id_adicional integer NOT NULL
);


--
-- TOC entry 269 (class 1259 OID 16753)
-- Name: produto_adicionais_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produto_adicionais_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3850 (class 0 OID 0)
-- Dependencies: 269
-- Name: produto_adicionais_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produto_adicionais_id_seq OWNED BY public.produto_adicionais.id;


--
-- TOC entry 270 (class 1259 OID 16754)
-- Name: produto_imagens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produto_imagens (
    id integer NOT NULL,
    id_produto integer NOT NULL,
    imagem character varying(255) NOT NULL,
    ordem integer DEFAULT 0
);


--
-- TOC entry 271 (class 1259 OID 16761)
-- Name: produto_imagens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produto_imagens_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3851 (class 0 OID 0)
-- Dependencies: 271
-- Name: produto_imagens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produto_imagens_id_seq OWNED BY public.produto_imagens.id;


--
-- TOC entry 272 (class 1259 OID 16762)
-- Name: produto_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.produto_views (
    id integer NOT NULL,
    id_produto integer NOT NULL,
    session_id character varying(200),
    data_view timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip character varying(45)
);


--
-- TOC entry 273 (class 1259 OID 16768)
-- Name: produto_views_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produto_views_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3852 (class 0 OID 0)
-- Dependencies: 273
-- Name: produto_views_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produto_views_id_seq OWNED BY public.produto_views.id;


--
-- TOC entry 274 (class 1259 OID 16769)
-- Name: produtos; Type: TABLE; Schema: public; Owner: -
--

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


--
-- TOC entry 275 (class 1259 OID 16785)
-- Name: produtos_id_produto_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.produtos_id_produto_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3853 (class 0 OID 0)
-- Dependencies: 275
-- Name: produtos_id_produto_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.produtos_id_produto_seq OWNED BY public.produtos.id_produto;


--
-- TOC entry 276 (class 1259 OID 16786)
-- Name: staff; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff (
    id_staff integer NOT NULL,
    nome character varying(100) NOT NULL,
    usuario character varying(60) NOT NULL,
    senha character varying(255) NOT NULL,
    tipo character varying(20) DEFAULT 'vendedor'::character varying NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL,
    id_tenant integer,
    CONSTRAINT tipo_check CHECK (((tipo)::text = ANY (ARRAY[('vendedor'::character varying)::text, ('atendente'::character varying)::text, ('caixa'::character varying)::text, ('admin'::character varying)::text])))
);


--
-- TOC entry 277 (class 1259 OID 16800)
-- Name: staff_id_staff_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.staff_id_staff_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3854 (class 0 OID 0)
-- Dependencies: 277
-- Name: staff_id_staff_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.staff_id_staff_seq OWNED BY public.staff.id_staff;


--
-- TOC entry 278 (class 1259 OID 16801)
-- Name: telas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.telas (
    id_tela integer NOT NULL,
    chave character varying(80) NOT NULL,
    nome character varying(120) NOT NULL,
    descricao text,
    ativo boolean DEFAULT true NOT NULL
);


--
-- TOC entry 279 (class 1259 OID 16811)
-- Name: telas_id_tela_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.telas_id_tela_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3855 (class 0 OID 0)
-- Dependencies: 279
-- Name: telas_id_tela_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.telas_id_tela_seq OWNED BY public.telas.id_tela;


--
-- TOC entry 280 (class 1259 OID 16812)
-- Name: vendas_presenciais; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendas_presenciais (
    id integer NOT NULL,
    id_pedido integer NOT NULL,
    id_produto integer NOT NULL,
    quantidade integer NOT NULL,
    id_admin integer,
    criado_em timestamp without time zone DEFAULT now(),
    id_vendedor integer
);


--
-- TOC entry 281 (class 1259 OID 16820)
-- Name: vendas_presenciais_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendas_presenciais_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3856 (class 0 OID 0)
-- Dependencies: 281
-- Name: vendas_presenciais_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendas_presenciais_id_seq OWNED BY public.vendas_presenciais.id;


--
-- TOC entry 282 (class 1259 OID 16821)
-- Name: vendedores; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vendedores (
    id_vendedor integer NOT NULL,
    nome character varying(100) NOT NULL,
    usuario character varying(50) NOT NULL,
    senha character varying(255) NOT NULL,
    ativo boolean DEFAULT true NOT NULL,
    criado_em timestamp without time zone DEFAULT now() NOT NULL
);


--
-- TOC entry 283 (class 1259 OID 16832)
-- Name: vendedores_id_vendedor_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vendedores_id_vendedor_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3857 (class 0 OID 0)
-- Dependencies: 283
-- Name: vendedores_id_vendedor_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vendedores_id_vendedor_seq OWNED BY public.vendedores.id_vendedor;


--
-- TOC entry 284 (class 1259 OID 16833)
-- Name: visitas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.visitas (
    id integer NOT NULL,
    session_id character varying(200) NOT NULL,
    ip character varying(50),
    data_visita timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    id_tenant integer
);


--
-- TOC entry 285 (class 1259 OID 16839)
-- Name: visitas_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.visitas_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- TOC entry 3858 (class 0 OID 0)
-- Dependencies: 285
-- Name: visitas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.visitas_id_seq OWNED BY public.visitas.id;


--
-- TOC entry 3403 (class 2604 OID 16840)
-- Name: adicionais id_adicional; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adicionais ALTER COLUMN id_adicional SET DEFAULT nextval('public.adicionais_id_adicional_seq'::regclass);


--
-- TOC entry 3407 (class 2604 OID 16841)
-- Name: administradores id_admin; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administradores ALTER COLUMN id_admin SET DEFAULT nextval('public.administradores_id_admin_seq'::regclass);


--
-- TOC entry 3410 (class 2604 OID 16842)
-- Name: admins id_admin; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins ALTER COLUMN id_admin SET DEFAULT nextval('public.admins_id_admin_seq'::regclass);


--
-- TOC entry 3413 (class 2604 OID 16843)
-- Name: caixa_movimentacoes id_mov; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_movimentacoes ALTER COLUMN id_mov SET DEFAULT nextval('public.caixa_movimentacoes_id_mov_seq'::regclass);


--
-- TOC entry 3415 (class 2604 OID 16844)
-- Name: caixa_sessoes id_sessao; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_sessoes ALTER COLUMN id_sessao SET DEFAULT nextval('public.caixa_sessoes_id_sessao_seq'::regclass);


--
-- TOC entry 3419 (class 2604 OID 16845)
-- Name: cancelamentos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cancelamentos ALTER COLUMN id SET DEFAULT nextval('public.cancelamentos_id_seq'::regclass);


--
-- TOC entry 3421 (class 2604 OID 16846)
-- Name: categorias id_categoria; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias ALTER COLUMN id_categoria SET DEFAULT nextval('public.categorias_id_categoria_seq'::regclass);


--
-- TOC entry 3424 (class 2604 OID 16847)
-- Name: clientes id_cliente; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes ALTER COLUMN id_cliente SET DEFAULT nextval('public.clientes_id_cliente_seq'::regclass);


--
-- TOC entry 3427 (class 2604 OID 16848)
-- Name: comanda_itens id_item; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comanda_itens ALTER COLUMN id_item SET DEFAULT nextval('public.comanda_itens_id_item_seq'::regclass);


--
-- TOC entry 3430 (class 2604 OID 16849)
-- Name: comanda_itens_removidos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comanda_itens_removidos ALTER COLUMN id SET DEFAULT nextval('public.comanda_itens_removidos_id_seq'::regclass);


--
-- TOC entry 3434 (class 2604 OID 16850)
-- Name: comandas id_comanda; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comandas ALTER COLUMN id_comanda SET DEFAULT nextval('public.comandas_id_comanda_seq'::regclass);


--
-- TOC entry 3438 (class 2604 OID 16851)
-- Name: compra_itens id_item; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compra_itens ALTER COLUMN id_item SET DEFAULT nextval('public.compra_itens_id_item_seq'::regclass);


--
-- TOC entry 3440 (class 2604 OID 16852)
-- Name: compras id_compra; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras ALTER COLUMN id_compra SET DEFAULT nextval('public.compras_id_compra_seq'::regclass);


--
-- TOC entry 3446 (class 2604 OID 16853)
-- Name: configuracoes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes ALTER COLUMN id SET DEFAULT nextval('public.configuracoes_id_seq'::regclass);


--
-- TOC entry 3447 (class 2604 OID 16854)
-- Name: empresa id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa ALTER COLUMN id SET DEFAULT nextval('public.empresa_id_seq'::regclass);


--
-- TOC entry 3470 (class 2604 OID 16855)
-- Name: empresas_tenants id_tenant; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas_tenants ALTER COLUMN id_tenant SET DEFAULT nextval('public.empresas_tenants_id_tenant_seq'::regclass);


--
-- TOC entry 3474 (class 2604 OID 16856)
-- Name: impressoras id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.impressoras ALTER COLUMN id SET DEFAULT nextval('public.impressoras_id_seq'::regclass);


--
-- TOC entry 3480 (class 2604 OID 16857)
-- Name: itens_compra id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_compra ALTER COLUMN id SET DEFAULT nextval('public.itens_compra_id_seq'::regclass);


--
-- TOC entry 3482 (class 2604 OID 16858)
-- Name: itens_pedido id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_pedido ALTER COLUMN id SET DEFAULT nextval('public.itens_pedido_id_seq'::regclass);


--
-- TOC entry 3484 (class 2604 OID 16859)
-- Name: licencas id_licenca; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.licencas ALTER COLUMN id_licenca SET DEFAULT nextval('public.licencas_id_licenca_seq'::regclass);


--
-- TOC entry 3535 (class 2604 OID 24594)
-- Name: locais_permitidos id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locais_permitidos ALTER COLUMN id SET DEFAULT nextval('public.locais_permitidos_id_seq'::regclass);


--
-- TOC entry 3488 (class 2604 OID 16860)
-- Name: logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.logs ALTER COLUMN id SET DEFAULT nextval('public.logs_id_seq'::regclass);


--
-- TOC entry 3494 (class 2604 OID 16861)
-- Name: pedido_itens id_item; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedido_itens ALTER COLUMN id_item SET DEFAULT nextval('public.pedido_itens_id_item_seq'::regclass);


--
-- TOC entry 3496 (class 2604 OID 16862)
-- Name: pedidos id_pedido; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pedidos ALTER COLUMN id_pedido SET DEFAULT nextval('public.pedidos_id_pedido_seq'::regclass);


--
-- TOC entry 3505 (class 2604 OID 16863)
-- Name: planos id_plano; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.planos ALTER COLUMN id_plano SET DEFAULT nextval('public.planos_id_plano_seq'::regclass);


--
-- TOC entry 3509 (class 2604 OID 16864)
-- Name: produto_adicionais id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_adicionais ALTER COLUMN id SET DEFAULT nextval('public.produto_adicionais_id_seq'::regclass);


--
-- TOC entry 3510 (class 2604 OID 16865)
-- Name: produto_imagens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_imagens ALTER COLUMN id SET DEFAULT nextval('public.produto_imagens_id_seq'::regclass);


--
-- TOC entry 3512 (class 2604 OID 16866)
-- Name: produto_views id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produto_views ALTER COLUMN id SET DEFAULT nextval('public.produto_views_id_seq'::regclass);


--
-- TOC entry 3514 (class 2604 OID 16867)
-- Name: produtos id_produto; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.produtos ALTER COLUMN id_produto SET DEFAULT nextval('public.produtos_id_produto_seq'::regclass);


--
-- TOC entry 3522 (class 2604 OID 16868)
-- Name: staff id_staff; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff ALTER COLUMN id_staff SET DEFAULT nextval('public.staff_id_staff_seq'::regclass);


--
-- TOC entry 3526 (class 2604 OID 16869)
-- Name: telas id_tela; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.telas ALTER COLUMN id_tela SET DEFAULT nextval('public.telas_id_tela_seq'::regclass);


--
-- TOC entry 3528 (class 2604 OID 16870)
-- Name: vendas_presenciais id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendas_presenciais ALTER COLUMN id SET DEFAULT nextval('public.vendas_presenciais_id_seq'::regclass);


--
-- TOC entry 3530 (class 2604 OID 16871)
-- Name: vendedores id_vendedor; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vendedores ALTER COLUMN id_vendedor SET DEFAULT nextval('public.vendedores_id_vendedor_seq'::regclass);


--
-- TOC entry 3533 (class 2604 OID 16872)
-- Name: visitas id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.visitas ALTER COLUMN id SET DEFAULT nextval('public.visitas_id_seq'::regclass);


--
-- TOC entry 3750 (class 0 OID 16399)
-- Dependencies: 219
-- Data for Name: adicionais; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.adicionais VALUES (1, 'bacon', 5.00, true, '2026-03-09 22:54:11.616389', NULL);
INSERT INTO public.adicionais VALUES (4, 'queijo', 8.00, true, '2026-03-16 19:43:50.746954', NULL);
INSERT INTO public.adicionais VALUES (5, 'peperone', 10.00, true, '2026-03-16 19:44:07.370668', NULL);
INSERT INTO public.adicionais VALUES (6, 'ovo', 3.50, true, '2026-03-16 19:44:49.482191', NULL);
INSERT INTO public.adicionais VALUES (7, 'queijo cheddar', 10.00, true, '2026-03-16 19:45:40.697795', NULL);
INSERT INTO public.adicionais VALUES (8, 'bacon', 5.00, true, '2026-04-05 22:15:23.287065', 9);


--
-- TOC entry 3752 (class 0 OID 16411)
-- Dependencies: 221
-- Data for Name: administradores; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.administradores VALUES (1, 'Administrador', 'admin', 'admin@techstore.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', true, '2026-03-02 23:20:40.889173');


--
-- TOC entry 3754 (class 0 OID 16424)
-- Dependencies: 223
-- Data for Name: admins; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.admins VALUES (1, 'Administrador', 'admin@techstore.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-03-03 20:50:45.631639', true, NULL, NULL);
INSERT INTO public.admins VALUES (2, 'Administrador', 'admin@ecommerce.com', '$2y$10$vz1nHljBX0epLh8ILd4THekGq4ySZceMILIVQTOsQQP/g75wopN2C', '2026-03-03 22:19:22.423442', true, NULL, NULL);
INSERT INTO public.admins VALUES (9, 'benjamim', 'benjamim@gmail.com', '$2y$10$gb5Sg9yB5R44Wm7UnHGHMu0EYSwH1XlXQ1Nwuh925GWxXV9hkK5Ju', '2026-03-31 11:23:34.283531', true, 'benjamim', 4);
INSERT INTO public.admins VALUES (8, 'gg@gmail.com', 'gg@gmail.com', '$2y$10$4bXvTKyfZ6nZFgyjszheuu0PJttg6NbSM87mgWKDeuvNOeqj21VfO', '2026-04-03 22:30:08.854241', true, 'gg@gmail.com', 8);
INSERT INTO public.admins VALUES (14, 'hh@gmail.com', 'hh@gmail.com', '$2y$10$dCMQfBJkHi/o/ILeuR9axunWBsae6IK4VS5wXOkeTUAcI3hfFP3ni', '2026-04-04 13:56:13.420984', true, 'hh@gmail.com', 8);
INSERT INTO public.admins VALUES (15, 'pp@gmail.com', 'pp@gmail.com', '$2y$10$Ae5iVB.mTfkMT3vwD7pKmuGzLLk2xfMogc7xccCGtDW7kBjWeMvDW', '2026-04-04 14:27:00.274582', true, 'pp@gmail.com', 9);
INSERT INTO public.admins VALUES (16, 'vv@gmail.com', 'vv@gmail.com', '$2y$10$OpMawJUV/iyjlyqs1835mO2XVkOB.xQnVv.9efAQZ7oHE1rm313G.', '2026-04-05 17:48:18.448769', true, 'vv@gmail.com', 9);
INSERT INTO public.admins VALUES (17, 'll@gmail.com', 'll@gmail.com', '$2y$10$CPNwJSR.usJLZiRQdBaeH.YxTpI6sRxwSO5uMQ92Bu0WASFVUUP/K', '2026-04-05 18:00:01.763691', true, 'll@gmail.com', 9);


--
-- TOC entry 3756 (class 0 OID 16438)
-- Dependencies: 225
-- Data for Name: caixa_movimentacoes; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.caixa_movimentacoes VALUES (1, 1, 'sangria', 'compra gelo', 2500.00, 'teste caixa (Caixa)', '2026-03-18 20:29:20.990028');
INSERT INTO public.caixa_movimentacoes VALUES (2, 2, 'sangria', '22', 25.00, 'teste caixa (Caixa)', '2026-03-18 20:38:58.377484');
INSERT INTO public.caixa_movimentacoes VALUES (3, 4, 'sangria', 'gelo', 33.33, 'delon', '2026-03-21 00:16:25.599015');


--
-- TOC entry 3758 (class 0 OID 16448)
-- Dependencies: 227
-- Data for Name: caixa_sessoes; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.caixa_sessoes VALUES (1, 'teste caixa (Caixa)', 'teste caixa (Caixa)', 0.00, 5562200.00, 5453257.50, '', 'fechado', '2026-03-17 21:15:04.146545', '2026-03-18 20:30:43.696853', 'turno noite');
INSERT INTO public.caixa_sessoes VALUES (2, 'teste caixa (Caixa)', 'Administrador', 0.00, 56.00, 0.00, '', 'fechado', '2026-03-18 20:38:21.355989', '2026-03-20 23:38:05.497512', '');
INSERT INTO public.caixa_sessoes VALUES (3, 'Administrador', 'teste caixa', 0.00, 0.00, 0.00, '', 'fechado', '2026-03-20 23:38:21.172711', '2026-03-20 23:51:05.641243', '');
INSERT INTO public.caixa_sessoes VALUES (4, 'teste caixa', 'delon', 0.00, 22.67, 0.00, '', 'fechado', '2026-03-20 23:51:18.299753', '2026-03-21 00:31:10.12398', '');
INSERT INTO public.caixa_sessoes VALUES (5, 'teste caixa', 'teste caixa', 0.00, 25.00, 0.00, '', 'fechado', '2026-03-21 00:32:08.424579', '2026-03-21 00:33:30.843086', '');
INSERT INTO public.caixa_sessoes VALUES (6, 'teste caixa', NULL, 0.00, NULL, NULL, NULL, 'aberto', '2026-03-21 01:08:09.209088', NULL, '');


--
-- TOC entry 3760 (class 0 OID 16462)
-- Dependencies: 229
-- Data for Name: cancelamentos; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.cancelamentos VALUES (1, 20, 'teste teste', 2, '2026-03-10 01:27:47.767342+00');
INSERT INTO public.cancelamentos VALUES (2, 106, 'teste', 17, '2026-04-22 12:29:07.342675+00');


--
-- TOC entry 3762 (class 0 OID 16473)
-- Dependencies: 231
-- Data for Name: categorias; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.categorias VALUES (1, 'Smartphones', 'Celulares e acessórios', true, '2026-03-02 23:20:40.889173', 4);
INSERT INTO public.categorias VALUES (16, 'Acessórios', 'Cabos, capas e periféricos', true, '2026-03-02 23:22:28.912603', 4);
INSERT INTO public.categorias VALUES (26, 'Acessórios', NULL, true, '2026-03-03 20:50:45.631639', 4);
INSERT INTO public.categorias VALUES (27, 'pizzas', '', true, '2026-03-16 19:31:10.254531', 4);
INSERT INTO public.categorias VALUES (28, 'hamburgues', '', true, '2026-03-16 19:31:21.884119', 4);
INSERT INTO public.categorias VALUES (29, 'hamburgue', '', true, '2026-04-05 22:13:07.660033', 9);
INSERT INTO public.categorias VALUES (30, 'pizzas', '', true, '2026-04-06 23:51:22.239001', 9);


--
-- TOC entry 3764 (class 0 OID 16483)
-- Dependencies: 233
-- Data for Name: clientes; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- TOC entry 3766 (class 0 OID 16495)
-- Dependencies: 235
-- Data for Name: comanda_itens; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.comanda_itens VALUES (1, 1, 3, 'teste teste', 12000.00, 1, 12035.00, '[{"id_adicional":1,"nome":"bacon","preco":5},{"id_adicional":3,"nome":"teste add","preco":30}]', 'bacon bem frito', '2026-03-15 13:41:05.908605', NULL);
INSERT INTO public.comanda_itens VALUES (2, 2, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', '', '2026-03-15 13:56:16.578149', NULL);
INSERT INTO public.comanda_itens VALUES (3, 3, 4, 'Abbavash', 12234.00, 1, 12239.00, '[{"id_adicional":1,"nome":"bacon","preco":5}]', 'ovo bem frito', '2026-03-15 14:10:16.685524', NULL);
INSERT INTO public.comanda_itens VALUES (5, 5, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', 'ASFASFSF', '2026-03-15 14:47:57.304595', NULL);
INSERT INTO public.comanda_itens VALUES (6, 6, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', 'Bem frito', '2026-03-15 14:53:34.970844', NULL);
INSERT INTO public.comanda_itens VALUES (7, 7, 2, '3535352', 55555.00, 1, 55555.00, NULL, 'bem frito', '2026-03-15 18:50:44.785532', NULL);
INSERT INTO public.comanda_itens VALUES (8, 8, 4, 'Abbavash', 12234.00, 1, 12234.00, NULL, '', '2026-03-15 18:51:35.5252', NULL);
INSERT INTO public.comanda_itens VALUES (9, 9, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', '', '2026-03-15 19:16:24.817939', NULL);
INSERT INTO public.comanda_itens VALUES (10, 9, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', '', '2026-03-15 19:17:03.912167', NULL);
INSERT INTO public.comanda_itens VALUES (11, 7, 4, 'Abbavash', 12234.00, 1, 12239.00, '[{"id_adicional":1,"nome":"bacon","preco":5}]', '', '2026-03-15 19:20:20.83934', NULL);
INSERT INTO public.comanda_itens VALUES (12, 10, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', 'kkk', '2026-03-15 19:48:22.85961', 'teste atendente (Atendente)');
INSERT INTO public.comanda_itens VALUES (13, 10, 4, 'Abbavash', 12234.00, 1, 12239.00, '[{"id_adicional":1,"nome":"bacon","preco":5}]', 'teste teste', '2026-03-15 19:56:58.237863', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (14, 11, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', 'ddddddddd', '2026-03-15 20:28:03.453655', 'teste atendente (Atendente)');
INSERT INTO public.comanda_itens VALUES (15, 12, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', 'bem frito', '2026-03-15 20:42:01.247871', 'teste atendente (Atendente)');
INSERT INTO public.comanda_itens VALUES (16, 13, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', 'Não esquentar muito', '2026-03-15 20:56:23.104398', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (17, 14, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', '', '2026-03-16 12:47:26.985437', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (18, 15, 2, '3535352', 55555.00, 1, 55585.00, '[{"id_adicional":3,"nome":"teste add","preco":30}]', '', '2026-03-16 13:00:04.240898', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (19, 16, 5, 'pizza de calabresa', 76000.00, 1, 76025.00, '[{"id_adicional":1,"nome":"bacon","preco":5},{"id_adicional":5,"nome":"peperone","preco":10},{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', '', '2026-03-16 19:51:01.164027', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (20, 17, 5, 'pizza de calabresa', 76.00, 1, 84.50, '[{"id_adicional":1,"nome":"bacon","preco":5},{"id_adicional":6,"nome":"ovo","preco":3.5}]', '', '2026-03-16 20:02:25.51973', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (21, 18, 6, 'x calabresa', 27.00, 1, 37.00, '[{"id_adicional":5,"nome":"peperone","preco":10}]', '', '2026-03-16 20:23:09.347296', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (22, 18, 5, 'pizza de calabresa', 76.00, 1, 91.00, '[{"id_adicional":1,"nome":"bacon","preco":5},{"id_adicional":5,"nome":"peperone","preco":10}]', 'Bem frito', '2026-03-16 20:23:09.347296', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (23, 19, 2, '3535352', 25.00, 1, 25.00, NULL, '', '2026-03-17 20:17:24.812617', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (24, 19, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":5,"nome":"peperone","preco":10}]', 'dddddd', '2026-03-17 20:17:24.812617', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (25, 20, 6, 'x calabresa', 27.00, 1, 35.00, '[{"id_adicional":4,"nome":"queijo","preco":8}]', '', '2026-03-17 21:30:59.271758', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (26, 21, 2, '3535352', 25.00, 2, 50.00, NULL, '', '2026-03-17 21:33:39.81461', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (27, 22, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', '', '2026-03-17 21:36:09.119801', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (28, 23, 2, '3535352', 25.00, 2, 50.00, NULL, '', '2026-03-18 20:12:25.839999', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (29, 24, 6, 'x calabresa', 27.00, 1, 37.00, '[{"id_adicional":5,"nome":"peperone","preco":10}]', 'kkkkkkkkk', '2026-03-18 20:26:01.598029', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (30, 25, 5, 'pizza de calabresa', 56.00, 1, 56.00, NULL, '', '2026-03-18 21:18:19.691921', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (33, 26, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', '', '2026-03-18 21:40:03.545753', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (34, 26, NULL, '[AVULSO] bacon', 5.00, 1, 5.00, NULL, 'a parte', '2026-03-18 21:40:03.545753', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (35, 27, NULL, '[AVULSO] bacon', 5.00, 1, 5.00, NULL, 'teste', '2026-03-18 21:44:18.96215', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (36, 27, NULL, '[AVULSO] bacon', 5.00, 1, 5.00, NULL, 'teste', '2026-03-18 21:46:53.097858', 'teste caixa (Caixa)');
INSERT INTO public.comanda_itens VALUES (37, 28, 5, 'pizza de calabresa', 56.00, 1, 59.50, '[{"id_adicional":6,"nome":"ovo","preco":3.5}]', '', '2026-03-18 21:58:40.899573', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (38, 29, 5, 'pizza de calabresa', 56.00, 1, 56.00, NULL, '', '2026-03-18 23:13:47.25512', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (39, 30, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', '', '2026-03-18 23:16:37.517293', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (44, 31, 2, '3535352', 25.00, 1, 25.00, NULL, '', '2026-03-21 00:32:39.105644', 'teste caixa');
INSERT INTO public.comanda_itens VALUES (45, 32, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":5,"nome":"peperone","preco":10}]', 'Crocante', '2026-03-21 01:10:32.902511', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (46, 33, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":5,"nome":"peperone","preco":10}]', 'Bem assada', '2026-03-21 14:27:16.82576', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (47, 34, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":5,"nome":"peperone","preco":10}]', 'Bem assada', '2026-03-21 14:34:42.878268', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (48, 35, 5, 'pizza de calabresa', 56.00, 1, 61.00, '[{"id_adicional":1,"nome":"bacon","preco":5}]', 'Teste tes', '2026-03-22 11:58:51.78407', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (49, 35, 5, 'pizza de calabresa', 56.00, 1, 61.00, '[{"id_adicional":1,"nome":"bacon","preco":5}]', 'Teste tes', '2026-03-22 12:22:45.806269', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (50, 32, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', 'Por cima é bem assada a pizza', '2026-03-22 12:23:22.786217', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (51, 36, 5, 'pizza de calabresa', 56.00, 1, 61.00, '[{"id_adicional":1,"nome":"bacon","preco":5}]', 'Bem assada', '2026-03-22 12:24:41.495405', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (52, 37, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', 'Sbzvz', '2026-03-22 12:27:04.714273', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (53, 38, 5, 'pizza de calabresa', 56.00, 1, 64.00, '[{"id_adicional":4,"nome":"queijo","preco":8}]', 'Bem assada', '2026-03-22 12:29:19.241053', 'teste caixa');
INSERT INTO public.comanda_itens VALUES (54, 39, 5, 'pizza de calabresa', 56.00, 1, 66.00, '[{"id_adicional":7,"nome":"queijo cheddar","preco":10}]', 'Ok', '2026-03-22 12:33:21.239247', 'teste caixa');
INSERT INTO public.comanda_itens VALUES (55, 40, 5, 'pizza de calabresa', 56.00, 1, 64.00, '[{"id_adicional":4,"nome":"queijo","preco":8}]', 'Add queijo', '2026-03-22 12:35:15.920943', 'teste caixa');
INSERT INTO public.comanda_itens VALUES (56, 40, 8, 'teste pizza', 29.90, 1, 29.90, NULL, '', '2026-03-22 12:35:15.920943', 'teste caixa');
INSERT INTO public.comanda_itens VALUES (57, 41, 5, 'pizza de calabresa', 56.00, 1, 64.00, '[{"id_adicional":4,"nome":"queijo","preco":8}]', 'Add queijo', '2026-03-22 12:44:19.555963', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (58, 41, NULL, '[AVULSO] ovo', 3.50, 1, 3.50, NULL, '', '2026-03-22 12:44:19.555963', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (59, 41, 6, 'x calabresa', 27.00, 1, 27.00, NULL, '', '2026-03-22 12:44:19.555963', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (60, 42, 5, 'pizza de calabresa', 56.00, 1, 59.50, '[{"id_adicional":6,"nome":"ovo","preco":3.5}]', 'Add ovo', '2026-03-22 13:10:27.292007', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (61, 42, NULL, '[AVULSO] bacon', 5.00, 1, 5.00, NULL, 'A parte', '2026-03-22 13:10:27.292007', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (62, 43, 6, 'x calabresa', 27.00, 1, 35.50, '[{"id_adicional":1,"nome":"bacon","preco":5},{"id_adicional":6,"nome":"ovo","preco":3.5}]', '', '2026-04-04 22:20:28.456225', 'Allan (Atendente)');
INSERT INTO public.comanda_itens VALUES (63, 44, 11, 'x calabresa', 20.00, 1, 25.00, '[{"id_adicional":8,"nome":"bacon","preco":5}]', 'Te amo', '2026-04-05 22:31:35.291891', 'rita123 (Atendente)');
INSERT INTO public.comanda_itens VALUES (64, 45, 13, 'pizza de marguerita', 44.44, 1, 49.44, '[{"id_adicional":8,"nome":"bacon","preco":5}]', '', '2026-04-07 11:46:37.525993', 'eduardo (Atendente)');
INSERT INTO public.comanda_itens VALUES (65, 45, 13, 'pizza de marguerita', 44.44, 1, 44.44, NULL, '', '2026-04-19 17:10:32.536174', 'Rita (Atendente)');
INSERT INTO public.comanda_itens VALUES (66, 45, 14, 'teclado', 10.00, 1, 10.00, NULL, '', '2026-04-19 17:10:32.536174', 'Rita (Atendente)');
INSERT INTO public.comanda_itens VALUES (67, 46, 14, 'teclado', 10.00, 1, 10.00, NULL, '', '2026-04-22 00:26:35.334406', 'll@gmail.com');
INSERT INTO public.comanda_itens VALUES (68, 47, 13, 'pizza de marguerita', 44.44, 1, 44.44, NULL, '', '2026-04-22 12:08:46.630182', 'll@gmail.com');


--
-- TOC entry 3768 (class 0 OID 16510)
-- Dependencies: 237
-- Data for Name: comanda_itens_removidos; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.comanda_itens_removidos VALUES (1, 28, '33', '[AVULSO] peperone', 1, 10.00, 'teste caixa (Caixa)', '2026-03-19 21:57:21.325566', NULL);


--
-- TOC entry 3770 (class 0 OID 16521)
-- Dependencies: 239
-- Data for Name: comandas; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.comandas VALUES (33, '40', 'fechada', '50', 66.00, '2026-03-21 14:27:16.82576', '2026-03-21 14:27:47.806836', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (8, '30', 'fechada', '', 12234.00, '2026-03-15 18:51:35.5252', '2026-03-15 19:23:18.261737', NULL, 'teste atendente (Atendente)', NULL);
INSERT INTO public.comandas VALUES (6, '10', 'fechada', 'Mesa 20', 55585.00, '2026-03-15 14:53:34.970844', '2026-03-15 19:23:40.932685', NULL, 'Desconhecido', NULL);
INSERT INTO public.comandas VALUES (10, '10', 'fechada', 'mesa 10', 67824.00, '2026-03-15 19:48:22.85961', '2026-03-15 20:09:01.802103', NULL, 'teste atendente (Atendente)', NULL);
INSERT INTO public.comandas VALUES (3, '50', 'fechada', 'mesa 56', 12239.00, '2026-03-15 14:10:16.685524', '2026-03-15 20:31:19.857213', NULL, NULL, NULL);
INSERT INTO public.comandas VALUES (5, '4', 'fechada', '4', 55585.00, '2026-03-15 14:47:57.304595', '2026-03-15 20:31:34.32589', NULL, 'Desconhecido', NULL);
INSERT INTO public.comandas VALUES (7, '30', 'fechada', 'mesa 32', 67794.00, '2026-03-15 18:50:44.785532', '2026-03-15 20:31:44.970124', NULL, 'teste atendente (Atendente)', NULL);
INSERT INTO public.comandas VALUES (9, '33', 'fechada', '33', 111170.00, '2026-03-15 19:16:24.817939', '2026-03-15 20:31:55.717793', NULL, 'teste atendente (Atendente)', NULL);
INSERT INTO public.comandas VALUES (11, '39', 'fechada', 'mesa 39', 55585.00, '2026-03-15 20:28:03.453655', '2026-03-15 20:37:41.033528', NULL, 'teste atendente (Atendente)', NULL);
INSERT INTO public.comandas VALUES (15, '47', 'cancelada', 'mesa 46', 55585.00, '2026-03-16 13:00:04.240898', '2026-03-16 13:00:21.000207', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (16, '20', 'fechada', 'mesa 25', 76025.00, '2026-03-16 19:51:01.164027', '2026-03-16 20:00:43.300824', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (18, '23', 'fechada', 'Mesa 44', 128.00, '2026-03-16 20:23:09.347296', '2026-03-17 20:26:16.215751', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (12, '31', 'fechada', '', 55585.00, '2026-03-15 20:42:01.247871', '2026-03-18 20:26:25.921852', NULL, 'teste atendente (Atendente)', NULL);
INSERT INTO public.comandas VALUES (13, '49', 'fechada', 'Mesa 80', 55585.00, '2026-03-15 20:56:23.104398', '2026-03-18 20:26:39.421996', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (17, '59', 'fechada', '59', 84.50, '2026-03-16 20:02:25.51973', '2026-03-18 20:27:00.443978', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (19, '35', 'cancelada', '32', 91.00, '2026-03-17 20:17:24.812617', '2026-03-18 20:27:06.858055', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (20, '44', 'fechada', '44', 35.00, '2026-03-17 21:30:59.271758', '2026-03-18 20:27:26.241672', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (21, '66', 'cancelada', '66', 50.00, '2026-03-17 21:33:39.81461', '2026-03-18 20:27:41.424469', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (22, '77', 'fechada', '77', 66.00, '2026-03-17 21:36:09.119801', '2026-03-18 20:27:53.024014', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (23, '40', 'fechada', 'g', 50.00, '2026-03-18 20:12:25.839999', '2026-03-18 20:28:08.244911', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (24, '88', 'fechada', '88', 37.00, '2026-03-18 20:26:01.598029', '2026-03-18 20:28:15.967771', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (26, '99', 'fechada', '99', 71.00, '2026-03-18 21:40:03.545753', '2026-03-18 21:42:19.931052', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (27, '98', 'fechada', '98', 10.00, '2026-03-18 21:44:18.96215', '2026-03-18 21:48:05.602503', NULL, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (35, '14', 'aberta', '10', 122.00, '2026-03-22 11:58:51.78407', NULL, NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (30, '55', 'cancelada', '55', 66.00, '2026-03-18 23:16:37.517293', '2026-03-19 21:17:11.565339', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (36, '2', 'aberta', '9', 61.00, '2026-03-22 12:24:41.495405', NULL, NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (37, '89', 'aberta', '3', 66.00, '2026-03-22 12:27:04.714273', NULL, NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (38, '1', 'aberta', 'Mesa 2', 64.00, '2026-03-22 12:29:19.241053', NULL, NULL, 'teste caixa', NULL);
INSERT INTO public.comandas VALUES (39, '44', 'aberta', 'Mesa 11', 66.00, '2026-03-22 12:33:21.239247', NULL, NULL, 'teste caixa', NULL);
INSERT INTO public.comandas VALUES (40, '13', 'aberta', 'Mesa 14', 93.90, '2026-03-22 12:35:15.920943', NULL, NULL, 'teste caixa', NULL);
INSERT INTO public.comandas VALUES (41, '16', 'aberta', '16', 94.50, '2026-03-22 12:44:19.555963', NULL, NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (42, '18', 'aberta', '21', 64.50, '2026-03-22 13:10:27.292007', NULL, NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (28, '33', 'cancelada', '33', 59.50, '2026-03-18 21:58:40.899573', '2026-03-19 21:58:10.053912', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (29, '44', 'cancelada', '44', 56.00, '2026-03-18 23:13:47.25512', '2026-03-20 23:43:10.706194', NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (25, '16', 'fechada', 'Mesa 29', 56.00, '2026-03-18 21:18:19.691921', '2026-03-21 00:30:31.660451', 7, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (31, '77', 'fechada', 'Mesa 77', 25.00, '2026-03-21 00:32:39.105644', '2026-03-21 00:32:45.083637', NULL, 'teste caixa', NULL);
INSERT INTO public.comandas VALUES (43, '67', 'aberta', '67', 35.50, '2026-04-04 22:20:28.456225', NULL, NULL, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (34, '40', 'fechada', '50', 66.00, '2026-03-21 14:34:42.878268', '2026-03-23 21:32:26.922047', 2, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (32, '56', 'fechada', '56', 132.00, '2026-03-21 01:10:32.902511', '2026-03-23 21:23:19.06893', 2, 'Allan (Atendente)', NULL);
INSERT INTO public.comandas VALUES (14, '25', 'fechada', 'mesa 25', 55585.00, '2026-03-16 12:47:26.985437', '2026-03-16 13:00:31.229026', 2, 'teste caixa (Caixa)', NULL);
INSERT INTO public.comandas VALUES (2, '40', 'fechada', 'mesa 30', 55585.00, '2026-03-15 13:56:16.578149', '2026-03-15 14:49:17.605577', 2, NULL, NULL);
INSERT INTO public.comandas VALUES (1, '43', 'fechada', 'mesa 20', 12035.00, '2026-03-15 13:41:05.908605', '2026-03-15 13:53:21.174074', 2, NULL, NULL);
INSERT INTO public.comandas VALUES (44, '30', 'fechada', '5', 25.00, '2026-04-05 22:31:35.291891', '2026-04-05 22:32:19.519238', 17, 'rita123 (Atendente)', 9);
INSERT INTO public.comandas VALUES (45, '10', 'aberta', '10', 103.88, '2026-04-07 11:46:37.525993', NULL, NULL, 'eduardo (Atendente)', 9);
INSERT INTO public.comandas VALUES (46, '55', 'fechada', 'Mesa 55', 10.00, '2026-04-22 00:26:35.334406', '2026-04-22 00:27:58.674265', 17, 'll@gmail.com', 9);
INSERT INTO public.comandas VALUES (47, '5', 'fechada', 'Mesa 73', 44.44, '2026-04-22 12:08:46.630182', '2026-04-22 12:09:00.897937', 17, 'll@gmail.com', 9);


--
-- TOC entry 3772 (class 0 OID 16536)
-- Dependencies: 241
-- Data for Name: compra_itens; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- TOC entry 3774 (class 0 OID 16547)
-- Dependencies: 243
-- Data for Name: compras; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.compras VALUES (1, NULL, 25.00, 0.00, 25.00, '', '', '', '', '', '', '  ', 'Cartao de Credito', 'fasfas', 'confirmado', '2026-04-05 22:54:56.840858', NULL);
INSERT INTO public.compras VALUES (2, NULL, 44.44, 0.00, 44.44, '', '', '', '', '', '', '  ', 'Cartao de Credito', 'sgsg', 'confirmado', '2026-04-07 01:13:45.256279', NULL);
INSERT INTO public.compras VALUES (3, NULL, 44.44, 0.00, 44.44, '', '', '', '', '', '', '  ', 'Boleto', 'sdgds', 'confirmado', '2026-04-07 01:45:45.928987', 9);
INSERT INTO public.compras VALUES (4, NULL, 66.66, 0.00, 66.66, '', '', '', '', '', '', '  ', 'Cartao de Credito', '24142', 'confirmado', '2026-04-07 01:52:21.403218', 9);
INSERT INTO public.compras VALUES (5, NULL, 44.44, 0.00, 44.44, '', '', '', '', '', '', '  ', 'Cartao de Credito', 'ADSADads', 'confirmado', '2026-04-07 02:16:42.172376', 9);
INSERT INTO public.compras VALUES (6, NULL, 66.66, 0.00, 66.66, '', '', '', '', '', '', '  ', 'Cartao de Debito', 'Ahahah', 'confirmado', '2026-04-07 02:30:55.703211', 9);


--
-- TOC entry 3776 (class 0 OID 16559)
-- Dependencies: 245
-- Data for Name: configuracoes; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.configuracoes VALUES (1, 'cor_primary', '#2563eb', NULL);
INSERT INTO public.configuracoes VALUES (2, 'cor_primary_dark', '#1e40af', NULL);
INSERT INTO public.configuracoes VALUES (3, 'cor_secondary', '#10b981', NULL);
INSERT INTO public.configuracoes VALUES (4, 'cor_danger', '#ef4444', NULL);
INSERT INTO public.configuracoes VALUES (5, 'cor_header_grad1', '#1e40af', NULL);
INSERT INTO public.configuracoes VALUES (6, 'cor_header_grad2', '#7c3aed', NULL);
INSERT INTO public.configuracoes VALUES (7, 'cor_fundo', '#3015b7', NULL);
INSERT INTO public.configuracoes VALUES (57, 'cor_primary', '#2563eb', 9);
INSERT INTO public.configuracoes VALUES (58, 'cor_primary_dark', '#1e40af', 9);
INSERT INTO public.configuracoes VALUES (59, 'cor_secondary', '#10b981', 9);
INSERT INTO public.configuracoes VALUES (60, 'cor_danger', '#ef4444', 9);
INSERT INTO public.configuracoes VALUES (61, 'cor_header_grad1', '#1e40af', 9);
INSERT INTO public.configuracoes VALUES (62, 'cor_header_grad2', '#291aff', 9);
INSERT INTO public.configuracoes VALUES (63, 'cor_fundo', '#3015b7', 9);


--
-- TOC entry 3778 (class 0 OID 16566)
-- Dependencies: 247
-- Data for Name: empresa; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.empresa VALUES (1, 'empresa teste', 'empresa testt', '33663868442224', '(21) 3132-123', '(12) 31231-332', 'allan@gmail.com', 'teste teste', '3323124', '124124', '124124', '12441', '242414', '4212', '24', 'allan@gmail.com', '2026-03-23 23:58:03.326094+00', '', '', '', '', '', NULL, '');
INSERT INTO public.empresa VALUES (2, 'pizza do fabio', 'pizza do fabio', '33333', '', '', '', 'delon', '', '', '', '', '', '', '', '', '2026-04-07 13:18:37.457817+00', '', '', '', '', 'somos os melhores', 9, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775567916/logos/s6l8nrpobdcv9jvcmhgg.png');


--
-- TOC entry 3780 (class 0 OID 16615)
-- Dependencies: 249
-- Data for Name: empresas_tenants; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.empresas_tenants VALUES (4, 'Minha Empresa', 'ecommerce-eletronicos-1', NULL, NULL, NULL, true, '2026-03-31 00:36:34.885698', '2026-03-31 00:36:34.885698');
INSERT INTO public.empresas_tenants VALUES (8, 'gg', 'gg', '401351352562565', 'gg@gmail.com', NULL, true, '2026-04-03 22:26:29.324004', '2026-04-03 22:26:29.324004');
INSERT INTO public.empresas_tenants VALUES (9, 'pizza do fabio', 'pizzadofabio', '231231231241414', 'pp@gmail.com', NULL, true, '2026-04-04 14:26:42.958533', '2026-04-04 14:26:42.958533');


--
-- TOC entry 3782 (class 0 OID 16628)
-- Dependencies: 251
-- Data for Name: impressoras; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.impressoras VALUES (2, 'teste cozinha', '192.168.0.16', '9100', '{28,27}', true, '2026-03-22 14:52:17.435886+00', '2026-03-22 14:52:17.435886+00', 4);


--
-- TOC entry 3784 (class 0 OID 16647)
-- Dependencies: 253
-- Data for Name: itens_compra; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- TOC entry 3786 (class 0 OID 16658)
-- Dependencies: 255
-- Data for Name: itens_pedido; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.itens_pedido VALUES (1, 1, 2, '3535352', 1, 25.00, 25.00, NULL);
INSERT INTO public.itens_pedido VALUES (2, 1, 3, 'teste teste', 1, 10000.00, 10000.00, NULL);
INSERT INTO public.itens_pedido VALUES (3, 2, 2, '3535352', 1, 25.00, 0.00, NULL);
INSERT INTO public.itens_pedido VALUES (4, 2, 3, 'teste teste', 1, 10000.00, 0.00, NULL);
INSERT INTO public.itens_pedido VALUES (5, 3, 1, 'teste', 1, 0.03, 0.00, NULL);
INSERT INTO public.itens_pedido VALUES (6, 3, 3, 'teste teste', 1, 10000.00, 0.00, NULL);
INSERT INTO public.itens_pedido VALUES (7, 4, 1, 'teste', 1, 0.03, 0.03, NULL);
INSERT INTO public.itens_pedido VALUES (8, 4, 3, 'teste teste', 1, 10000.00, 10000.00, NULL);
INSERT INTO public.itens_pedido VALUES (9, 5, 3, 'teste teste', 2, 10000.00, 20000.00, NULL);
INSERT INTO public.itens_pedido VALUES (10, 6, 3, 'teste teste', 1, 10000.00, 10000.00, NULL);
INSERT INTO public.itens_pedido VALUES (11, 7, 3, 'teste teste', 2, 10000.00, 20000.00, NULL);
INSERT INTO public.itens_pedido VALUES (12, 8, 3, 'teste teste', 1, 10000.00, 10000.00, NULL);
INSERT INTO public.itens_pedido VALUES (13, 9, 3, 'teste teste', 1, 10000.00, 10000.00, NULL);
INSERT INTO public.itens_pedido VALUES (14, 10, 3, 'teste teste', 1, 10000.00, 10000.00, NULL);
INSERT INTO public.itens_pedido VALUES (23, 19, 3, 'teste teste', 1, 12000.00, 12000.00, NULL);
INSERT INTO public.itens_pedido VALUES (24, 20, 4, 'Abbavash', 1, 1333.00, 1333.00, NULL);
INSERT INTO public.itens_pedido VALUES (25, 21, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (26, 22, 3, 'teste teste', 1, 10035.00, 10035.00, NULL);
INSERT INTO public.itens_pedido VALUES (27, 23, 4, 'Abbavash', 1, 1338.00, 1338.00, '[{"id":1,"nome":"bacon","preco":5}]');
INSERT INTO public.itens_pedido VALUES (28, 24, NULL, 'Batata Frita G', 3, 19.00, 57.00, NULL);
INSERT INTO public.itens_pedido VALUES (29, 25, NULL, 'Pastel de Queijo', 1, 14.50, 14.50, NULL);
INSERT INTO public.itens_pedido VALUES (30, 26, NULL, 'Batata Frita G', 3, 19.00, 57.00, NULL);
INSERT INTO public.itens_pedido VALUES (31, 27, NULL, 'Pastel de Queijo', 2, 14.50, 29.00, NULL);
INSERT INTO public.itens_pedido VALUES (32, 28, NULL, 'Batata Frita G', 3, 19.00, 57.00, NULL);
INSERT INTO public.itens_pedido VALUES (33, 29, 4, 'Abbavash', 1, 1338.00, 1338.00, '[{"id":1,"nome":"bacon","preco":5}]');
INSERT INTO public.itens_pedido VALUES (34, 30, 4, 'Abbavash', 2, 1333.00, 2666.00, NULL);
INSERT INTO public.itens_pedido VALUES (35, 31, 3, 'teste teste', 1, 10035.00, 10035.00, '[{"id":1,"nome":"bacon","preco":5},{"id":3,"nome":"teste add","preco":30}]');
INSERT INTO public.itens_pedido VALUES (36, 32, 4, 'Abbavash', 2, 1333.00, 2666.00, NULL);
INSERT INTO public.itens_pedido VALUES (37, 33, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (38, 34, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (39, 35, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (40, 36, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (41, 37, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (42, 38, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (43, 39, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (44, 40, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (45, 41, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (46, 41, 3, 'teste teste', 1, 12000.00, 12000.00, NULL);
INSERT INTO public.itens_pedido VALUES (47, 42, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (48, 43, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (49, 44, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (70, 65, 3, 'teste teste', 1, 12000.00, 12035.00, NULL);
INSERT INTO public.itens_pedido VALUES (71, 66, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (72, 67, 4, 'Abbavash', 1, 12234.00, 12234.00, NULL);
INSERT INTO public.itens_pedido VALUES (73, 68, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (74, 69, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (75, 69, 4, 'Abbavash', 1, 12234.00, 12239.00, NULL);
INSERT INTO public.itens_pedido VALUES (76, 70, 4, 'Abbavash', 1, 12234.00, 12239.00, NULL);
INSERT INTO public.itens_pedido VALUES (77, 71, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (78, 72, 2, '3535352', 1, 55555.00, 55555.00, NULL);
INSERT INTO public.itens_pedido VALUES (79, 72, 4, 'Abbavash', 1, 12234.00, 12239.00, NULL);
INSERT INTO public.itens_pedido VALUES (80, 73, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (81, 73, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (82, 74, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (83, 75, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (84, 76, 5, 'pizza de calabresa', 1, 76000.00, 76025.00, NULL);
INSERT INTO public.itens_pedido VALUES (85, 77, 6, 'x calabresa', 1, 27.00, 37.00, NULL);
INSERT INTO public.itens_pedido VALUES (86, 77, 5, 'pizza de calabresa', 1, 76.00, 91.00, NULL);
INSERT INTO public.itens_pedido VALUES (87, 78, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (88, 79, 2, '3535352', 1, 55555.00, 55585.00, NULL);
INSERT INTO public.itens_pedido VALUES (89, 80, 5, 'pizza de calabresa', 1, 76.00, 84.50, NULL);
INSERT INTO public.itens_pedido VALUES (90, 81, 6, 'x calabresa', 1, 27.00, 35.00, NULL);
INSERT INTO public.itens_pedido VALUES (91, 82, 5, 'pizza de calabresa', 1, 56.00, 66.00, NULL);
INSERT INTO public.itens_pedido VALUES (92, 83, 2, '3535352', 2, 25.00, 50.00, NULL);
INSERT INTO public.itens_pedido VALUES (93, 84, 6, 'x calabresa', 1, 27.00, 37.00, NULL);
INSERT INTO public.itens_pedido VALUES (94, 85, 5, 'pizza de calabresa', 1, 56.00, 66.00, NULL);
INSERT INTO public.itens_pedido VALUES (95, 85, NULL, '[AVULSO] bacon', 1, 5.00, 5.00, NULL);
INSERT INTO public.itens_pedido VALUES (96, 86, NULL, '[AVULSO] bacon', 1, 5.00, 5.00, NULL);
INSERT INTO public.itens_pedido VALUES (97, 86, NULL, '[AVULSO] bacon', 1, 5.00, 5.00, NULL);
INSERT INTO public.itens_pedido VALUES (98, 87, 5, 'pizza de calabresa', 1, 56.00, 56.00, NULL);
INSERT INTO public.itens_pedido VALUES (99, 88, 2, '3535352', 1, 25.00, 25.00, NULL);
INSERT INTO public.itens_pedido VALUES (100, 89, 5, 'pizza de calabresa', 1, 56.00, 66.00, NULL);
INSERT INTO public.itens_pedido VALUES (101, 90, 8, 'teste pizza', 1, 29.90, 29.90, NULL);
INSERT INTO public.itens_pedido VALUES (102, 91, 8, 'teste pizza', 1, 29.90, 29.90, NULL);
INSERT INTO public.itens_pedido VALUES (103, 92, 6, 'x calabresa', 1, 27.00, 27.00, NULL);
INSERT INTO public.itens_pedido VALUES (104, 93, 5, 'pizza de calabresa', 1, 56.00, 66.00, NULL);
INSERT INTO public.itens_pedido VALUES (105, 93, 5, 'pizza de calabresa', 1, 56.00, 66.00, NULL);
INSERT INTO public.itens_pedido VALUES (106, 94, 5, 'pizza de calabresa', 1, 56.00, 66.00, NULL);
INSERT INTO public.itens_pedido VALUES (107, 95, 11, 'x calabresa', 1, 25.00, 25.00, '[{"id":8,"nome":"bacon","preco":5}]');
INSERT INTO public.itens_pedido VALUES (108, 96, 11, 'x calabresa', 1, 20.00, 25.00, NULL);
INSERT INTO public.itens_pedido VALUES (109, 97, 11, 'x calabresa', 1, 20.00, 20.00, NULL);
INSERT INTO public.itens_pedido VALUES (110, 98, 11, 'x calabresa', 1, 25.00, 25.00, '[{"id":8,"nome":"bacon","preco":5}]');
INSERT INTO public.itens_pedido VALUES (111, 99, 13, 'pizza de marguerita', 1, 44.44, 44.44, NULL);
INSERT INTO public.itens_pedido VALUES (112, 100, 13, 'pizza de marguerita', 1, 44.44, 44.44, NULL);
INSERT INTO public.itens_pedido VALUES (113, 101, 12, 'fdfaff', 1, 66.66, 66.66, NULL);
INSERT INTO public.itens_pedido VALUES (114, 102, 13, 'pizza de marguerita', 1, 44.44, 44.44, NULL);
INSERT INTO public.itens_pedido VALUES (115, 103, 12, 'fdfaff', 1, 66.66, 66.66, NULL);
INSERT INTO public.itens_pedido VALUES (116, 104, 14, 'teclado', 1, 10.00, 10.00, NULL);
INSERT INTO public.itens_pedido VALUES (117, 105, 14, 'teclado', 1, 10.00, 10.00, NULL);
INSERT INTO public.itens_pedido VALUES (118, 106, 13, 'pizza de marguerita', 1, 44.44, 44.44, NULL);


--
-- TOC entry 3788 (class 0 OID 16671)
-- Dependencies: 257
-- Data for Name: licencas; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.licencas VALUES (4, 4, 3, '2026-03-31', '2027-03-31', true, NULL, '2026-03-31 00:40:55.62196');
INSERT INTO public.licencas VALUES (8, 8, 3, '2026-04-03', '2027-04-03', true, NULL, '2026-04-03 22:26:29.324004');
INSERT INTO public.licencas VALUES (9, 9, 3, '2026-04-04', '2027-04-04', true, NULL, '2026-04-04 14:26:42.958533');


--
-- TOC entry 3819 (class 0 OID 24591)
-- Dependencies: 288
-- Data for Name: locais_permitidos; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- TOC entry 3790 (class 0 OID 16687)
-- Dependencies: 259
-- Data for Name: logs; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.logs VALUES (1, 'item_removido', 'Item removido da comanda #28', 'Valor subtraído: R$ 8.00', 8.00, 42, 'teste caixa (Caixa)', '127.0.0.1', '2026-03-19 21:38:09.983098', NULL);
INSERT INTO public.logs VALUES (2, 'item_removido', 'Item removido da comanda #28', 'Valor subtraído: R$ 10.00', 10.00, 43, 'teste caixa (Caixa)', '127.0.0.1', '2026-03-19 21:57:21.338796', NULL);
INSERT INTO public.logs VALUES (3, 'comanda_cancelada', 'Comanda cancelada', 'ID comanda: 28', 0.00, 28, 'teste caixa (Caixa)', '127.0.0.1', '2026-03-19 21:58:10.058011', NULL);
INSERT INTO public.logs VALUES (4, 'caixa_fechado', 'Caixa fechado por Administrador', 'Total sistema: R$ 81,00 | Contado: R$ 56,00 | Diferenca: R$ 0,00', 81.00, NULL, 'Administrador', '127.0.0.1', '2026-03-20 23:38:05.502686', NULL);
INSERT INTO public.logs VALUES (5, 'caixa_aberto', 'Caixa aberto por Administrador', 'Valor inicial: R$ 0,00', 0.00, NULL, 'Administrador', '127.0.0.1', '2026-03-20 23:38:21.175618', NULL);
INSERT INTO public.logs VALUES (6, 'comanda_cancelada', 'Comanda cancelada', 'ID comanda: 29', 0.00, 29, 'teste caixa (Caixa)', '127.0.0.1', '2026-03-20 23:43:10.710476', NULL);
INSERT INTO public.logs VALUES (7, 'caixa_fechado', 'Caixa fechado por teste caixa', 'Total sistema: R$ 0,00 | Contado: R$ 0,00 | Diferenca: R$ 0,00', 0.00, NULL, 'teste caixa', '127.0.0.1', '2026-03-20 23:51:05.643068', NULL);
INSERT INTO public.logs VALUES (8, 'caixa_aberto', 'Caixa aberto por teste caixa', 'Valor inicial: R$ 0,00', 0.00, NULL, 'teste caixa', '127.0.0.1', '2026-03-20 23:51:18.303071', NULL);
INSERT INTO public.logs VALUES (9, 'caixa_mov_sangria', 'Sangria no caixa por delon', 'gelo — R$ 33,33', 33.33, NULL, 'delon', '127.0.0.1', '2026-03-21 00:16:25.612847', NULL);
INSERT INTO public.logs VALUES (10, 'comanda_fechada', 'Comanda #16 fechada', 'Pagamento: Cartao de Credito — Pedido #87 gerado', 56.00, 87, 'delon', '127.0.0.1', '2026-03-21 00:30:31.724253', NULL);
INSERT INTO public.logs VALUES (11, 'caixa_fechado', 'Caixa fechado por delon', 'Total sistema: R$ 56,00 | Contado: R$ 22,67 | Diferenca: R$ 0,00', 56.00, NULL, 'delon', '127.0.0.1', '2026-03-21 00:31:10.126164', NULL);
INSERT INTO public.logs VALUES (12, 'caixa_aberto', 'Caixa aberto por teste caixa', 'Valor inicial: R$ 0,00', 0.00, NULL, 'teste caixa', '127.0.0.1', '2026-03-21 00:32:08.426654', NULL);
INSERT INTO public.logs VALUES (13, 'comanda_fechada', 'Comanda #77 fechada', 'Pagamento: Dinheiro — Pedido #88 gerado', 25.00, 88, 'teste caixa', '127.0.0.1', '2026-03-21 00:32:45.100097', NULL);
INSERT INTO public.logs VALUES (14, 'caixa_fechado', 'Caixa fechado por teste caixa', 'Total sistema: R$ 25,00 | Contado: R$ 25,00 | Diferenca: R$ 0,00', 25.00, NULL, 'teste caixa', '127.0.0.1', '2026-03-21 00:33:30.845253', NULL);
INSERT INTO public.logs VALUES (15, 'caixa_aberto', 'Caixa aberto por teste caixa', 'Valor inicial: R$ 0,00', 0.00, NULL, 'teste caixa', '127.0.0.1', '2026-03-21 01:08:09.210935', NULL);
INSERT INTO public.logs VALUES (16, 'comanda_lancada', 'Comanda #56 lancada', '1 item(ns) — R$ 66,00', 66.00, 32, 'Allan (Atendente)', '127.0.0.1', '2026-03-21 01:10:32.911606', NULL);
INSERT INTO public.logs VALUES (17, 'comanda_lancada', 'Comanda #40 lancada', '1 item(ns) — R$ 66,00', 66.00, 33, 'Allan (Atendente)', '127.0.0.1', '2026-03-21 14:27:16.837447', NULL);
INSERT INTO public.logs VALUES (18, 'comanda_fechada', 'Comanda #40 fechada', 'Pagamento: PIX — Pedido #89 gerado', 66.00, 89, 'teste caixa', '127.0.0.1', '2026-03-21 14:27:47.850846', NULL);
INSERT INTO public.logs VALUES (19, 'comanda_lancada', 'Comanda #40 lancada', '1 item(ns) — R$ 66,00', 66.00, 34, 'Allan (Atendente)', '127.0.0.1', '2026-03-21 14:34:42.886825', NULL);
INSERT INTO public.logs VALUES (20, 'comanda_lancada', 'Comanda #14 lancada', '1 item(ns) — R$ 61,00', 61.00, 35, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 11:58:51.804871', NULL);
INSERT INTO public.logs VALUES (21, 'comanda_atualizada', 'Comanda #14 atualizada pelo atendente', '1 item(ns) — R$ 61,00', 61.00, 35, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 12:22:45.825822', NULL);
INSERT INTO public.logs VALUES (22, 'comanda_atualizada', 'Comanda #56 atualizada pelo atendente', '1 item(ns) — R$ 66,00', 66.00, 32, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 12:23:22.793048', NULL);
INSERT INTO public.logs VALUES (23, 'comanda_lancada', 'Comanda #2 lancada', '1 item(ns) — R$ 61,00', 61.00, 36, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 12:24:41.505488', NULL);
INSERT INTO public.logs VALUES (24, 'comanda_lancada', 'Comanda #89 lancada', '1 item(ns) — R$ 66,00', 66.00, 37, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 12:27:04.817374', NULL);
INSERT INTO public.logs VALUES (25, 'comanda_lancada', 'Comanda #16 lancada', '3 item(ns) — R$ 94,50', 94.50, 41, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 12:44:19.58127', NULL);
INSERT INTO public.logs VALUES (26, 'comanda_lancada', 'Comanda #18 lancada', '2 item(ns) — R$ 64,50', 64.50, 42, 'Allan (Atendente)', '127.0.0.1', '2026-03-22 13:10:27.307149', NULL);
INSERT INTO public.logs VALUES (27, 'comanda_fechada', 'Comanda #56 fechada', 'Pagamento: PIX — Pedido #93 gerado', 132.00, 93, 'Administrador', '127.0.0.1', '2026-03-23 21:23:19.094015', NULL);
INSERT INTO public.logs VALUES (28, 'comanda_fechada', 'Comanda #40 fechada', 'Pagamento: Cartao de Credito — Pedido #94 gerado', 66.00, 94, 'Administrador', '127.0.0.1', '2026-03-23 21:32:26.940874', NULL);
INSERT INTO public.logs VALUES (29, 'comanda_lancada', 'Comanda #67 lancada', '1 item(ns) — R$ 35,50', 35.50, 43, 'Allan (Atendente)', '::1', '2026-04-04 22:20:31.851319', NULL);
INSERT INTO public.logs VALUES (30, 'comanda_lancada', 'Comanda #30 lancada', '1 item(ns) - R$ 25,00', 25.00, 44, 'rita123 (Atendente)', '::1', '2026-04-05 22:31:35.865181', NULL);
INSERT INTO public.logs VALUES (31, 'comanda_fechada', 'Comanda #30 fechada', 'Pagamento: PIX - Pedido #96 gerado', 25.00, 96, 'rita123', '::1', '2026-04-05 22:32:20.131478', NULL);
INSERT INTO public.logs VALUES (32, 'comanda_lancada', 'Comanda #10 lancada', '1 item(ns) - R$ 49,44', 49.44, 45, 'eduardo (Atendente)', '::1', '2026-04-07 11:46:37.999994', NULL);
INSERT INTO public.logs VALUES (33, 'comanda_atualizada', 'Comanda #10 atualizada pelo atendente', '2 item(ns) - R$ 54,44', 54.44, 45, 'Rita (Atendente)', '::1', '2026-04-19 17:10:33.086396', NULL);
INSERT INTO public.logs VALUES (34, 'comanda_fechada', 'Comanda #55 fechada', 'Pagamento: Cartao de Credito - Pedido #105 gerado', 10.00, 105, 'll@gmail.com', '::1', '2026-04-22 00:27:59.218878', NULL);
INSERT INTO public.logs VALUES (35, 'comanda_fechada', 'Comanda #5 fechada', 'Pagamento: PIX - Pedido #106 gerado', 44.44, 106, 'll@gmail.com', '::1', '2026-04-22 12:09:01.44825', NULL);


--
-- TOC entry 3792 (class 0 OID 16701)
-- Dependencies: 261
-- Data for Name: pedido_itens; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- TOC entry 3794 (class 0 OID 16712)
-- Dependencies: 263
-- Data for Name: pedidos; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.pedidos VALUES (95, 'rtita', '', '0653582399', '9199354628', '', '', '', '', '', '  ', '', 'PIX', 'te amo', 25.00, 0.00, 25.00, 'confirmado', '2026-04-05 22:19:45.167773', '2026-04-05 22:21:09.8427', 'online', 'novo', 'online', NULL);
INSERT INTO public.pedidos VALUES (98, 'wwwra', '', '', 'asfasfas', '', '', '', '', '', '  ', '', 'Cartao de Credito', 'fasfas', 25.00, 0.00, 25.00, 'confirmado', '2026-04-05 22:54:41.82581', '2026-04-05 22:54:56.840858', 'online', 'novo', 'online', NULL);
INSERT INTO public.pedidos VALUES (101, '1241241', '', '12412412', '12412412', '', '', '', '', '', '  ', '', 'Cartao de Credito', '24142', 66.66, 0.00, 66.66, 'confirmado', '2026-04-07 01:51:59.310909', '2026-04-07 01:52:21.403218', 'online', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (104, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 10.00, 0.00, 10.00, 'aprovado', '2026-04-22 00:24:15.268353', NULL, 'presencial', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (96, 'Comanda #30', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #30 - 5', 25.00, 0.00, 25.00, 'aprovado', '2026-04-05 22:32:19.519238', NULL, 'presencial', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (99, 'sdggfgfg', '', '', 'sdgsgsg', '', '', '', '', '', '  ', '', 'Cartao de Credito', 'sgsg', 44.44, 0.00, 44.44, 'confirmado', '2026-04-07 01:10:18.423769', '2026-04-07 01:13:45.256279', 'online', 'novo', 'online', NULL);
INSERT INTO public.pedidos VALUES (102, 'asADSASFF', '', '', 'asdADSAda', '', '', '', '', '', '  ', '', 'Cartao de Credito', 'ADSADads', 44.44, 0.00, 44.44, 'confirmado', '2026-04-07 01:59:41.252112', '2026-04-07 02:16:42.172376', 'online', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (105, 'Comanda #55', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Comanda #55 - Mesa 55', 10.00, 0.00, 10.00, 'aprovado', '2026-04-22 00:27:58.674265', NULL, 'presencial', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (2, 'sfsf', '', '', 'sfsaf', '', '', '', '', '', '  ', '', 'Cartão de Crédito', 'sfsf', 0.00, 0.00, 10025.00, 'cancelado', '2026-03-04 23:13:27.529724', '2026-03-04 23:47:12.895267', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (1, 'wwr', '', '', 'wr', '', '', '', '', '', '  ', '', 'Cartão de Crédito', 'fsff', 10025.00, 0.00, 10025.00, 'cancelado', '2026-03-04 23:03:46.511073', '2026-03-04 23:47:18.072985', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (10, 'allan', '', '', 'awfrasf', '', '', '', '', '', '  ', '', 'Cartão de Crédito', 'aefQF', 10000.00, 0.00, 10000.00, 'cancelado', '2026-03-08 10:47:47.898817', '2026-03-08 11:36:38.975077', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (9, 'Rita de Cássia', '', '91847481928', '6788738392983', '', '', '', '', '', '  ', '', 'Cartão de Crédito', '2 vezes', 10000.00, 0.00, 10000.00, 'cancelado', '2026-03-08 10:46:23.933276', '2026-03-08 11:36:43.139436', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (8, 'Jahhahaa', '', '', 'Avsvaga', '', '', '', '', '', '  ', '', 'Cartão de Crédito', 'Ahaag', 10000.00, 0.00, 10000.00, 'cancelado', '2026-03-08 10:44:56.524594', '2026-03-08 11:36:46.908595', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (7, 'Rita de Cássia', '', '9174927293', '9274939393', '', '', '', '', '', '  ', '', 'Cartão de Crédito', '3 vezes', 20000.00, 0.00, 20000.00, 'cancelado', '2026-03-08 10:04:35.471245', '2026-03-08 11:36:51.008523', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (6, 'Rita de Cássia', '', '93749202749', '91749392838:', '', '', '', '', '', '  ', '', 'Cartão de Crédito', '3 vezes', 10000.00, 0.00, 10000.00, 'cancelado', '2026-03-08 10:03:54.695839', '2026-03-08 11:36:57.119434', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (19, 'delon', NULL, '837.173.713', '(97) 61396-9361', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 12000.00, 0.00, 12000.00, 'aprovado', '2026-03-08 14:40:10.134419', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (20, 'Allan', '', '02212236220', '91984305884', '', '', '', '', '', '  ', '', 'Boleto', 'Boleto', 1333.00, 0.00, 1333.00, 'cancelado', '2026-03-09 21:06:36.451231', '2026-03-09 22:27:47.767342', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (22, '828264801240821', '', '48712474012', '2412949148', '', '', '', '', '', '  ', '', 'Cartao de Credito', 'hdhfh', 10035.00, 0.00, 10035.00, 'expirado', '2026-03-10 13:06:32.775012', '2026-03-11 22:21:48.490199', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (23, 'hdhdihfiqwf', '', '2312414244', '4241242424', '', '', '', '', '', '  ', '', 'Boleto', 'fffff', 1338.00, 0.00, 1338.00, 'cancelado', '2026-03-10 23:18:31.029807', '2026-03-11 22:21:58.078013', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (3, 'SSFS', '', '', 'fSS', '', '', '', '', '', '  ', '', 'Cartão de Crédito', 'sf', 0.00, 0.00, 10000.03, 'entregue', '2026-03-04 23:15:50.013498', '2026-03-12 23:00:42.949581', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (21, 'benjamim', NULL, '232.241.242-14', '(24) 12412-4414', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 55555.00, 0.00, 55555.00, 'entregue', '2026-03-09 21:11:17.537379', '2026-03-12 23:00:54.31092', 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (24, 'Bruno Alves', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 57.00, 0.00, 57.00, 'cancelado', '2026-03-12 23:00:13.142819', '2026-03-12 23:24:55.975861', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (25, 'Fernanda Rocha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 14.50, 0.00, 14.50, 'cancelado', '2026-03-12 23:00:14.021939', '2026-03-12 23:24:58.445056', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (26, 'João Silva', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 57.00, 0.00, 57.00, 'cancelado', '2026-03-12 23:00:14.73293', '2026-03-12 23:25:00.93192', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (28, 'Fernanda Rocha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 57.00, 0.00, 57.00, 'cancelado', '2026-03-12 23:00:18.991478', '2026-03-12 23:25:04.672156', 'delivery', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (27, 'Bruno Alves', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 29.00, 0.00, 29.00, 'cancelado', '2026-03-12 23:00:15.930573', '2026-03-12 23:25:07.535766', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (5, 'Allan', '', '02212236220', '91984305884', '', '', '', '', '', '  ', '', 'PIX', '', 20000.00, 0.00, 20000.00, 'confirmado', '2026-03-07 01:51:54.947144', '2026-03-07 01:53:49.769966', 'online', 'preparo', 'online', 4);
INSERT INTO public.pedidos VALUES (31, 'E', '', '1221', '2E12E', '', '', '', '', '', '  ', '', 'Cartao de Credito', '21EE1E', 10035.00, 0.00, 10035.00, 'cancelado', '2026-03-12 23:57:11.093263', '2026-03-12 23:59:14.044041', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (4, 'DGSG', '', '', 'SSD', '', '', '', '', '', '  ', '', 'Cartão de Débito', 'SDSG', 0.00, 0.00, 10000.03, 'confirmado', '2026-03-04 23:20:06.97078', '2026-03-04 23:46:52.685863', 'online', 'preparo', 'online', 4);
INSERT INTO public.pedidos VALUES (29, 'dqwdww', '', '1212', '24124124', '', '', '', '', '', '  ', '', 'Cartao de Credito', '12414', 1338.00, 0.00, 1338.00, 'confirmado', '2026-03-12 23:46:59.27415', '2026-03-12 23:49:28.906099', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (30, 'axDDdD', '', '', 'EE2E21E21E12212', '', '', '', '', '', '  ', '', 'Cartao de Credito', '21E2E12', 2666.00, 0.00, 2666.00, 'cancelado', '2026-03-12 23:56:23.798896', '2026-03-12 23:59:16.877865', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (32, 'Allan', '', '0221223622', '91984305884', '', '', '', '', '', '  ', '', 'PIX', '', 2666.00, 0.00, 2666.00, 'confirmado', '2026-03-13 22:04:57.853066', '2026-03-13 22:07:57.125494', 'online', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (33, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:23:27.947588', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (34, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:26:12.963617', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (35, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:35:20.352897', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (36, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:37:46.825445', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (37, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:39:38.417184', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (38, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:41:51.432336', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (39, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:43:12.423176', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (40, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-13 23:45:24.691887', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (41, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Venda presencial', 67555.00, 0.00, 67555.00, 'aprovado', '2026-03-14 00:02:57.283663', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (42, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-14 14:09:16.92919', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (43, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-15 12:09:13.707862', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (44, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Venda presencial', 55555.00, 0.00, 55555.00, 'aprovado', '2026-03-15 12:14:13.247503', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (65, 'Comanda #43', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #43 - mesa 20', 12035.00, 0.00, 12035.00, 'aprovado', '2026-03-15 13:53:21.174074', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (66, 'Comanda #40', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #40 - mesa 30', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-15 14:49:17.605577', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (67, 'Comanda #30', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #30', 12234.00, 0.00, 12234.00, 'aprovado', '2026-03-15 19:23:18.261737', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (68, 'Comanda #10', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #10 - Mesa 20', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-15 19:23:40.932685', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (69, 'Comanda #10', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Comanda #10 - mesa 10', 67824.00, 0.00, 67824.00, 'aprovado', '2026-03-15 20:09:01.802103', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (70, 'Comanda #50', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Comanda #50 - mesa 56', 12239.00, 0.00, 12239.00, 'aprovado', '2026-03-15 20:31:19.857213', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (71, 'Comanda #4', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Comanda #4 - 4', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-15 20:31:34.32589', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (72, 'Comanda #30', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Comanda #30 - mesa 32', 67794.00, 0.00, 67794.00, 'aprovado', '2026-03-15 20:31:44.970124', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (73, 'Comanda #33', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Comanda #33 - 33', 111170.00, 0.00, 111170.00, 'aprovado', '2026-03-15 20:31:55.717793', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (74, 'Comanda #39', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Comanda #39 - mesa 39', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-15 20:37:41.033528', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (75, 'Comanda #25', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #25 - mesa 25', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-16 13:00:31.229026', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (76, 'Comanda #20', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Comanda #20 - mesa 25', 76025.00, 0.00, 76025.00, 'aprovado', '2026-03-16 20:00:43.300824', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (77, 'Comanda #23', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #23 - Mesa 44', 128.00, 0.00, 128.00, 'aprovado', '2026-03-17 20:26:16.215751', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (78, 'Comanda #31', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Comanda #31', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-18 20:26:25.921852', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (79, 'Comanda #49', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #49 - Mesa 80', 55585.00, 0.00, 55585.00, 'aprovado', '2026-03-18 20:26:39.421996', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (80, 'Comanda #59', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Comanda #59 - 59', 84.50, 0.00, 84.50, 'aprovado', '2026-03-18 20:27:00.443978', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (81, 'Comanda #44', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Comanda #44 - 44', 35.00, 0.00, 35.00, 'aprovado', '2026-03-18 20:27:26.241672', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (82, 'Comanda #77', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Comanda #77 - 77', 66.00, 0.00, 66.00, 'aprovado', '2026-03-18 20:27:53.024014', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (83, 'Comanda #40', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #40 - g', 50.00, 0.00, 50.00, 'aprovado', '2026-03-18 20:28:08.244911', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (84, 'Comanda #88', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Comanda #88 - 88', 37.00, 0.00, 37.00, 'aprovado', '2026-03-18 20:28:15.967771', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (85, 'Comanda #99', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #99 - 99', 71.00, 0.00, 71.00, 'aprovado', '2026-03-18 21:42:19.931052', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (86, 'Comanda #98', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #98 - 98', 10.00, 0.00, 10.00, 'aprovado', '2026-03-18 21:48:05.602503', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (87, 'Comanda #16', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Comanda #16 - Mesa 29', 56.00, 0.00, 56.00, 'aprovado', '2026-03-21 00:30:31.660451', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (88, 'Comanda #77', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Comanda #77 - Mesa 77', 25.00, 0.00, 25.00, 'aprovado', '2026-03-21 00:32:45.083637', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (89, 'Comanda #40', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #40 - 50', 66.00, 0.00, 66.00, 'aprovado', '2026-03-21 14:27:47.806836', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (90, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Dinheiro', 'Venda presencial', 29.90, 0.00, 29.90, 'aprovado', '2026-03-23 20:59:42.653824', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (91, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Venda presencial', 29.90, 0.00, 29.90, 'aprovado', '2026-03-23 21:04:08.903007', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (92, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Venda presencial', 27.00, 0.00, 27.00, 'aprovado', '2026-03-23 21:09:53.176887', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (93, 'Comanda #56', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #56 - 56', 132.00, 0.00, 132.00, 'aprovado', '2026-03-23 21:23:19.06893', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (94, 'Comanda #40', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Credito', 'Comanda #40 - 50', 66.00, 0.00, 66.00, 'aprovado', '2026-03-23 21:32:26.922047', NULL, 'presencial', 'novo', 'online', 4);
INSERT INTO public.pedidos VALUES (97, 'Cliente Balcao', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Cartao de Debito', 'Venda presencial', 20.00, 0.00, 20.00, 'aprovado', '2026-04-05 22:34:17.435182', NULL, 'presencial', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (100, 'fdd', '', '', 'sdgsdg', '', '', '', '', '', '  ', '', 'Boleto', 'sdgds', 44.44, 0.00, 44.44, 'confirmado', '2026-04-07 01:45:16.672777', '2026-04-07 01:45:45.928987', 'online', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (103, 'Hhhhh', '', '2726272', '177777', '', '', '', '', '', '  ', '', 'Cartao de Debito', 'Ahahah', 66.66, 0.00, 66.66, 'confirmado', '2026-04-07 02:29:56.560647', '2026-04-07 02:30:55.703211', 'online', 'novo', 'online', 9);
INSERT INTO public.pedidos VALUES (106, 'Comanda #5', NULL, '', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PIX', 'Comanda #5 - Mesa 73', 44.44, 0.00, 44.44, 'cancelado', '2026-04-22 12:09:00.897937', '2026-04-22 12:29:07.342675', 'presencial', 'novo', 'online', 9);


--
-- TOC entry 3817 (class 0 OID 16930)
-- Dependencies: 286
-- Data for Name: php_sessions; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.php_sessions VALUES ('c62c63609e6dcc7fe8f1a9b19de19c0d', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776814457);
INSERT INTO public.php_sessions VALUES ('9c4cab3fc26cc54b1be9bd60b82041f7', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776815040);
INSERT INTO public.php_sessions VALUES ('1deb0f752a2f750168f451716612d5b5', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776815397);
INSERT INTO public.php_sessions VALUES ('2d6b7cf52a0df85228a4facb8a0d1a91', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776815399);
INSERT INTO public.php_sessions VALUES ('b13d6b1980b6d409cb75c5660edc7c5d', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776815753);
INSERT INTO public.php_sessions VALUES ('b4d523b2d4c928149ca1db09211f8eb4', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776816323);
INSERT INTO public.php_sessions VALUES ('cf6bde2df32deb7d6eb21e84f4497130', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776814451);
INSERT INTO public.php_sessions VALUES ('00b839a8b4b157a574f6bcb1aeef3fef', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776815034);
INSERT INTO public.php_sessions VALUES ('d58edc78774d1bc648fbc5bc93a53a7f', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776815756);
INSERT INTO public.php_sessions VALUES ('884463f62ee332a4f65e9cf533e45e95', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776816327);
INSERT INTO public.php_sessions VALUES ('323321ea7438d728c415e70157ba7815', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;id_admin|i:17;nome_admin|s:12:"ll@gmail.com";email_admin|s:12:"ll@gmail.com";usuario_admin|s:12:"ll@gmail.com";', 1776817846);
INSERT INTO public.php_sessions VALUES ('7593d6eeb216d4b97d65a664006b6b85', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776816756);
INSERT INTO public.php_sessions VALUES ('9380e904d355b1b29cc59fac37d512bc', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776816756);
INSERT INTO public.php_sessions VALUES ('259f9174e7b256af3bd8f7dd96eab908', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776816965);
INSERT INTO public.php_sessions VALUES ('8e3613bcbc0f765d293d1c5584be2100', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776816970);
INSERT INTO public.php_sessions VALUES ('05b1217553bef92dd5e26945c77c02f7', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859208);
INSERT INTO public.php_sessions VALUES ('c3c2713ee101da70035572d46339ca99', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859208);
INSERT INTO public.php_sessions VALUES ('477c7684377d5851d1da8b025ae96e81', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859208);
INSERT INTO public.php_sessions VALUES ('7ea0491243caa644a281fb9a2c3745b6', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859208);
INSERT INTO public.php_sessions VALUES ('5091fabac294c5fa27cc1711b982a1e2', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859208);
INSERT INTO public.php_sessions VALUES ('62126e9dc863098e21cfe21a3cd6c118', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859208);
INSERT INTO public.php_sessions VALUES ('042f9cae9fefa62cb8c3e7f6da526088', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859209);
INSERT INTO public.php_sessions VALUES ('3af6d7b52d5c142817a40a045c93fb93', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859209);
INSERT INTO public.php_sessions VALUES ('8834536234f37b806d22e91b1a5184d4', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859209);
INSERT INTO public.php_sessions VALUES ('91a44e007bba99fd14544646fe17adb6', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859209);
INSERT INTO public.php_sessions VALUES ('b2cff1a04a6d00d719eb6e490ebac69c', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:0;', 1776859209);
INSERT INTO public.php_sessions VALUES ('a88d74da8c10b133a20aa12f1b47aac6', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776860599);
INSERT INTO public.php_sessions VALUES ('5bb75e63d3a983f54f0e9e2dd5a2763a', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776860599);
INSERT INTO public.php_sessions VALUES ('2a0c4e501fb5a6aea3bc91b57fe1f3b6', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776897958);
INSERT INTO public.php_sessions VALUES ('289deb4117e3a8c47768cfc400547cb9', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776898685);
INSERT INTO public.php_sessions VALUES ('1352ca9e7d0abea543b42861299429e2', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776898695);
INSERT INTO public.php_sessions VALUES ('295ab2eca96d0df16f293c37a89710c6', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776862035);
INSERT INTO public.php_sessions VALUES ('d9cb52d3c367802239f240fabfab8219', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776862037);
INSERT INTO public.php_sessions VALUES ('03294a819cc4aae7822cd5ece2c1c200', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776896404);
INSERT INTO public.php_sessions VALUES ('b46e52a0fcee433a0378975ea1eab3e5', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;id_admin|i:17;nome_admin|s:12:"ll@gmail.com";email_admin|s:12:"ll@gmail.com";usuario_admin|s:12:"ll@gmail.com";', 1776863091);
INSERT INTO public.php_sessions VALUES ('61a497182475bc08e530ea459ce5526a', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776862377);
INSERT INTO public.php_sessions VALUES ('b4a6c056d0bd96f7eced43b35bbebfe1', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776862379);
INSERT INTO public.php_sessions VALUES ('fd92498bff1ada54ddf75d7a02f3343f', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776896412);
INSERT INTO public.php_sessions VALUES ('801261db34d9706056c9d3026362dfd4', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776897958);
INSERT INTO public.php_sessions VALUES ('4585b29bf59156f96403a89ea1228ce4', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776899327);
INSERT INTO public.php_sessions VALUES ('813f45c8df611f78f85644260eb77a12', 'id_tenant|i:4;tenant_nome|s:13:"Minha Empresa";tenant_subdominio|s:23:"ecommerce-eletronicos-1";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-03-31";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}tenant_carregado|b:1;', 1776899331);
INSERT INTO public.php_sessions VALUES ('a1628b674c85bf2f2b19726c4d8b0bbb', 'tenant_subdominio_url|s:12:"pizzadofabio";id_tenant|i:9;tenant_carregado|b:1;tenant_nome|s:14:"pizza do fabio";tenant_subdominio|s:12:"pizzadofabio";id_plano|i:3;plano_nome|s:10:"Enterprise";plano_vencimento|s:10:"2027-04-04";telas_permitidas|a:21:{i:0;s:9:"dashboard";i:1;s:16:"venda_presencial";i:2;s:9:"atendente";i:3;s:5:"caixa";i:4;s:7:"pedidos";i:5;s:8:"produtos";i:6;s:10:"categorias";i:7;s:8:"clientes";i:8;s:10:"relatorios";i:9;s:5:"admin";i:10;s:13:"configuracoes";i:11;s:11:"impressoras";i:12;s:7:"empresa";i:13;s:10:"adicionais";i:14;s:16:"dashboard_vendas";i:15;s:13:"cancelamentos";i:16;s:4:"logs";i:17;s:9:"historico";i:18;s:16:"modo_restaurante";i:19;s:12:"novo_produto";i:20;s:8:"usuarios";}id_admin|i:17;nome_admin|s:12:"ll@gmail.com";email_admin|s:12:"ll@gmail.com";usuario_admin|s:12:"ll@gmail.com";', 1776899562);


--
-- TOC entry 3796 (class 0 OID 16728)
-- Dependencies: 265
-- Data for Name: plano_telas; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.plano_telas VALUES (1, 1);
INSERT INTO public.plano_telas VALUES (1, 4);
INSERT INTO public.plano_telas VALUES (1, 5);
INSERT INTO public.plano_telas VALUES (2, 1);
INSERT INTO public.plano_telas VALUES (2, 2);
INSERT INTO public.plano_telas VALUES (2, 3);
INSERT INTO public.plano_telas VALUES (2, 4);
INSERT INTO public.plano_telas VALUES (2, 5);
INSERT INTO public.plano_telas VALUES (2, 6);
INSERT INTO public.plano_telas VALUES (2, 7);
INSERT INTO public.plano_telas VALUES (2, 8);
INSERT INTO public.plano_telas VALUES (2, 9);
INSERT INTO public.plano_telas VALUES (3, 1);
INSERT INTO public.plano_telas VALUES (3, 2);
INSERT INTO public.plano_telas VALUES (3, 3);
INSERT INTO public.plano_telas VALUES (3, 4);
INSERT INTO public.plano_telas VALUES (3, 5);
INSERT INTO public.plano_telas VALUES (3, 6);
INSERT INTO public.plano_telas VALUES (3, 7);
INSERT INTO public.plano_telas VALUES (3, 8);
INSERT INTO public.plano_telas VALUES (3, 9);
INSERT INTO public.plano_telas VALUES (3, 10);
INSERT INTO public.plano_telas VALUES (3, 11);
INSERT INTO public.plano_telas VALUES (3, 12);
INSERT INTO public.plano_telas VALUES (1, 7);
INSERT INTO public.plano_telas VALUES (1, 17);
INSERT INTO public.plano_telas VALUES (1, 18);
INSERT INTO public.plano_telas VALUES (1, 19);
INSERT INTO public.plano_telas VALUES (1, 20);
INSERT INTO public.plano_telas VALUES (1, 24);
INSERT INTO public.plano_telas VALUES (1, 25);
INSERT INTO public.plano_telas VALUES (2, 17);
INSERT INTO public.plano_telas VALUES (2, 19);
INSERT INTO public.plano_telas VALUES (2, 20);
INSERT INTO public.plano_telas VALUES (2, 21);
INSERT INTO public.plano_telas VALUES (2, 24);
INSERT INTO public.plano_telas VALUES (2, 25);
INSERT INTO public.plano_telas VALUES (3, 17);
INSERT INTO public.plano_telas VALUES (3, 18);
INSERT INTO public.plano_telas VALUES (3, 19);
INSERT INTO public.plano_telas VALUES (3, 20);
INSERT INTO public.plano_telas VALUES (3, 21);
INSERT INTO public.plano_telas VALUES (3, 22);
INSERT INTO public.plano_telas VALUES (3, 23);
INSERT INTO public.plano_telas VALUES (3, 24);
INSERT INTO public.plano_telas VALUES (3, 25);


--
-- TOC entry 3797 (class 0 OID 16733)
-- Dependencies: 266
-- Data for Name: planos; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.planos VALUES (1, 'Básico', 'Acesso a vendas e caixa', 99.00, true, '2026-03-24 21:34:26.880066');
INSERT INTO public.planos VALUES (2, 'Pro', 'Básico + relatórios e comandas', 199.00, true, '2026-03-24 21:34:26.880066');
INSERT INTO public.planos VALUES (3, 'Enterprise', 'Acesso completo + white label', 399.00, true, '2026-03-24 21:34:26.880066');


--
-- TOC entry 3799 (class 0 OID 16747)
-- Dependencies: 268
-- Data for Name: produto_adicionais; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.produto_adicionais VALUES (6, 4, 1);
INSERT INTO public.produto_adicionais VALUES (7, 1, 1);
INSERT INTO public.produto_adicionais VALUES (9, 3, 1);
INSERT INTO public.produto_adicionais VALUES (10, 6, 7);
INSERT INTO public.produto_adicionais VALUES (11, 6, 6);
INSERT INTO public.produto_adicionais VALUES (12, 6, 5);
INSERT INTO public.produto_adicionais VALUES (13, 6, 4);
INSERT INTO public.produto_adicionais VALUES (14, 6, 1);
INSERT INTO public.produto_adicionais VALUES (15, 5, 7);
INSERT INTO public.produto_adicionais VALUES (16, 5, 6);
INSERT INTO public.produto_adicionais VALUES (17, 5, 5);
INSERT INTO public.produto_adicionais VALUES (18, 5, 4);
INSERT INTO public.produto_adicionais VALUES (19, 5, 1);
INSERT INTO public.produto_adicionais VALUES (20, 11, 8);
INSERT INTO public.produto_adicionais VALUES (21, 12, 8);
INSERT INTO public.produto_adicionais VALUES (22, 13, 8);


--
-- TOC entry 3801 (class 0 OID 16754)
-- Dependencies: 270
-- Data for Name: produto_imagens; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.produto_imagens VALUES (1, 1, '69a78ffd45289_1772589053.png', 0);
INSERT INTO public.produto_imagens VALUES (2, 1, '69a78ffd45c54_1772589053.png', 0);
INSERT INTO public.produto_imagens VALUES (3, 2, '69a7a4066c797_1772594182.png', 0);
INSERT INTO public.produto_imagens VALUES (4, 2, '69a7a4066cb29_1772594182.png', 0);
INSERT INTO public.produto_imagens VALUES (5, 2, '69a7a4066cdbf_1772594182.png', 0);
INSERT INTO public.produto_imagens VALUES (7, 3, '69a8c968ce02c_1772669288.png', 0);
INSERT INTO public.produto_imagens VALUES (8, 4, '69ad8c6b23d74_1772981355.png', 0);
INSERT INTO public.produto_imagens VALUES (9, 5, '69b886e9145ce_1773700841.jpg', 0);
INSERT INTO public.produto_imagens VALUES (10, 6, '69b887493b45f_1773700937.jpg', 0);
INSERT INTO public.produto_imagens VALUES (11, 7, '69b973d200992_1773761490.jpg', 0);
INSERT INTO public.produto_imagens VALUES (12, 10, '69c32502501f6_1774396674.png', 0);
INSERT INTO public.produto_imagens VALUES (13, 9, '69c325139663a_1774396691.png', 0);
INSERT INTO public.produto_imagens VALUES (14, 8, '69c32531c8c81_1774396721.png', 0);
INSERT INTO public.produto_imagens VALUES (15, 11, '69d2ded44ca49_1775427284.webp', 0);
INSERT INTO public.produto_imagens VALUES (16, 12, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775520905/produtos/jbq3fw3oihkz9hel4ql3.jpg', 0);
INSERT INTO public.produto_imagens VALUES (17, 13, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775521587/produtos/q5t8lssamuj2dbmnjydl.jpg', 0);
INSERT INTO public.produto_imagens VALUES (18, 12, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775565105/produtos/l9giqgtjbge28pdc5xci.jpg', 0);
INSERT INTO public.produto_imagens VALUES (19, 12, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775565115/produtos/ngxkir1m3fkjbhj90fi6.jpg', 0);


--
-- TOC entry 3803 (class 0 OID 16762)
-- Dependencies: 272
-- Data for Name: produto_views; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.produto_views VALUES (1, 3, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:19:39.563007', '127.0.0.1');
INSERT INTO public.produto_views VALUES (2, 1, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:19:43.568922', '127.0.0.1');
INSERT INTO public.produto_views VALUES (3, 2, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:19:48.503847', '127.0.0.1');
INSERT INTO public.produto_views VALUES (4, 3, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:20:00.313269', '127.0.0.1');
INSERT INTO public.produto_views VALUES (5, 3, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:51:46.799796', '127.0.0.1');
INSERT INTO public.produto_views VALUES (6, 3, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:54:57.512344', '127.0.0.1');
INSERT INTO public.produto_views VALUES (7, 1, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:56:20.025752', '127.0.0.1');
INSERT INTO public.produto_views VALUES (8, 1, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:57:10.186636', '127.0.0.1');
INSERT INTO public.produto_views VALUES (9, 1, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-04 21:57:47.282123', '127.0.0.1');
INSERT INTO public.produto_views VALUES (10, 3, 'qjhkvb3rgip661nog99v6j9cn0', '2026-03-05 00:34:24.225376', '127.0.0.1');
INSERT INTO public.produto_views VALUES (11, 2, '6qifcafq9s9qabr7vi0h7ratj8', '2026-03-07 17:07:55.189614', '127.0.0.1');
INSERT INTO public.produto_views VALUES (12, 2, '6qifcafq9s9qabr7vi0h7ratj8', '2026-03-07 17:08:33.564585', '127.0.0.1');
INSERT INTO public.produto_views VALUES (13, 3, 'i8i44bqnk0f3kuf7ouht9cft1p', '2026-03-08 10:47:25.215221', '127.0.0.1');
INSERT INTO public.produto_views VALUES (14, 3, 'i8i44bqnk0f3kuf7ouht9cft1p', '2026-03-08 10:53:12.838674', '127.0.0.1');
INSERT INTO public.produto_views VALUES (15, 1, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 10:53:42.022066', '192.168.0.22');
INSERT INTO public.produto_views VALUES (16, 1, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 10:59:23.499555', '192.168.0.22');
INSERT INTO public.produto_views VALUES (17, 1, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 10:59:31.723146', '192.168.0.22');
INSERT INTO public.produto_views VALUES (18, 1, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:02:32.91474', '192.168.0.22');
INSERT INTO public.produto_views VALUES (19, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:04:15.758124', '192.168.0.22');
INSERT INTO public.produto_views VALUES (20, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:08:49.071036', '192.168.0.22');
INSERT INTO public.produto_views VALUES (21, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:09:03.206121', '192.168.0.22');
INSERT INTO public.produto_views VALUES (22, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:10:01.901487', '192.168.0.22');
INSERT INTO public.produto_views VALUES (23, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:10:22.94', '192.168.0.22');
INSERT INTO public.produto_views VALUES (24, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:10:43.350234', '192.168.0.22');
INSERT INTO public.produto_views VALUES (25, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:11:03.469264', '192.168.0.22');
INSERT INTO public.produto_views VALUES (26, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:11:15.138471', '192.168.0.22');
INSERT INTO public.produto_views VALUES (27, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:11:39.544014', '192.168.0.22');
INSERT INTO public.produto_views VALUES (28, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:12:13.778765', '192.168.0.22');
INSERT INTO public.produto_views VALUES (29, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:12:29.533519', '192.168.0.22');
INSERT INTO public.produto_views VALUES (30, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:12:46.897608', '192.168.0.22');
INSERT INTO public.produto_views VALUES (31, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:13:07.338543', '192.168.0.22');
INSERT INTO public.produto_views VALUES (32, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:13:09.972646', '192.168.0.22');
INSERT INTO public.produto_views VALUES (33, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:13:28.696268', '192.168.0.22');
INSERT INTO public.produto_views VALUES (34, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:13:40.805217', '192.168.0.22');
INSERT INTO public.produto_views VALUES (35, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:14:44.924573', '192.168.0.22');
INSERT INTO public.produto_views VALUES (36, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:14:59.258946', '192.168.0.22');
INSERT INTO public.produto_views VALUES (37, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:15:16.418264', '192.168.0.22');
INSERT INTO public.produto_views VALUES (38, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:15:41.90504', '192.168.0.22');
INSERT INTO public.produto_views VALUES (39, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:15:59.62337', '192.168.0.22');
INSERT INTO public.produto_views VALUES (40, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:21:58.499819', '192.168.0.22');
INSERT INTO public.produto_views VALUES (41, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:23:33.203871', '192.168.0.22');
INSERT INTO public.produto_views VALUES (42, 1, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:24:28.749421', '192.168.0.22');
INSERT INTO public.produto_views VALUES (43, 3, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:27:06.988947', '192.168.0.22');
INSERT INTO public.produto_views VALUES (44, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:27:16.428605', '192.168.0.22');
INSERT INTO public.produto_views VALUES (45, 2, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:33:59.858084', '192.168.0.22');
INSERT INTO public.produto_views VALUES (46, 3, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:34:24.35857', '192.168.0.22');
INSERT INTO public.produto_views VALUES (47, 3, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:35:46.127145', '192.168.0.22');
INSERT INTO public.produto_views VALUES (48, 4, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 11:49:22.878663', '192.168.0.22');
INSERT INTO public.produto_views VALUES (49, 4, '1q3r6c9n3rcinrb32inp2mt0a0', '2026-03-08 12:18:25.96838', '192.168.0.22');
INSERT INTO public.produto_views VALUES (50, 4, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 22:24:19.431782', '192.168.0.25');
INSERT INTO public.produto_views VALUES (51, 3, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 22:24:24.568645', '192.168.0.25');
INSERT INTO public.produto_views VALUES (52, 2, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 22:24:29.154118', '192.168.0.25');
INSERT INTO public.produto_views VALUES (53, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:53:28.165893', '127.0.0.1');
INSERT INTO public.produto_views VALUES (54, 3, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:55:34.684016', '127.0.0.1');
INSERT INTO public.produto_views VALUES (55, 2, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:55:42.245798', '127.0.0.1');
INSERT INTO public.produto_views VALUES (56, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:56:00.602518', '127.0.0.1');
INSERT INTO public.produto_views VALUES (57, 3, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:56:04.601872', '127.0.0.1');
INSERT INTO public.produto_views VALUES (58, 1, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:56:07.453769', '127.0.0.1');
INSERT INTO public.produto_views VALUES (59, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 22:56:13.491148', '127.0.0.1');
INSERT INTO public.produto_views VALUES (60, 2, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 22:57:34.835338', '192.168.0.25');
INSERT INTO public.produto_views VALUES (61, 2, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:00:34.261143', '127.0.0.1');
INSERT INTO public.produto_views VALUES (62, 2, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:02:33.523042', '127.0.0.1');
INSERT INTO public.produto_views VALUES (63, 3, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:03:08.427061', '127.0.0.1');
INSERT INTO public.produto_views VALUES (64, 2, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:03:33.370591', '127.0.0.1');
INSERT INTO public.produto_views VALUES (65, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:05:25.589715', '127.0.0.1');
INSERT INTO public.produto_views VALUES (66, 4, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 23:05:40.765933', '192.168.0.25');
INSERT INTO public.produto_views VALUES (67, 3, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 23:06:21.265684', '192.168.0.25');
INSERT INTO public.produto_views VALUES (68, 3, 'qfl8g33aelfsq4gc9dugknmkn9', '2026-03-09 23:06:42.126893', '192.168.0.25');
INSERT INTO public.produto_views VALUES (69, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:07:57.261378', '127.0.0.1');
INSERT INTO public.produto_views VALUES (70, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:09:12.552122', '127.0.0.1');
INSERT INTO public.produto_views VALUES (71, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:09:41.658161', '127.0.0.1');
INSERT INTO public.produto_views VALUES (72, 3, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:17:11.386013', '127.0.0.1');
INSERT INTO public.produto_views VALUES (73, 3, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:20:03.450423', '127.0.0.1');
INSERT INTO public.produto_views VALUES (74, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:22:02.452683', '127.0.0.1');
INSERT INTO public.produto_views VALUES (75, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:23:25.554313', '127.0.0.1');
INSERT INTO public.produto_views VALUES (76, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:34:14.059222', '127.0.0.1');
INSERT INTO public.produto_views VALUES (77, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:37:58.613285', '127.0.0.1');
INSERT INTO public.produto_views VALUES (78, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:38:17.802776', '127.0.0.1');
INSERT INTO public.produto_views VALUES (79, 3, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:38:49.989372', '127.0.0.1');
INSERT INTO public.produto_views VALUES (80, 4, 'b3vi0mji8f7fs1jd86neh0ej5b', '2026-03-09 23:42:51.623229', '127.0.0.1');
INSERT INTO public.produto_views VALUES (81, 4, 'uglfp01ur9ssc06lvnu32p6q1c', '2026-03-10 13:05:18.670873', '127.0.0.1');
INSERT INTO public.produto_views VALUES (82, 3, 'uglfp01ur9ssc06lvnu32p6q1c', '2026-03-10 13:05:57.929571', '127.0.0.1');
INSERT INTO public.produto_views VALUES (83, 3, 'ftvlhc5nbkkpbclfslfc7codf8', '2026-03-10 21:52:23.899102', '127.0.0.1');
INSERT INTO public.produto_views VALUES (84, 4, 'ftvlhc5nbkkpbclfslfc7codf8', '2026-03-10 23:17:38.33761', '127.0.0.1');
INSERT INTO public.produto_views VALUES (85, 2, '5e0qvsl097r1ejbdtrr2ptn838', '2026-03-11 22:01:40.368861', '127.0.0.1');
INSERT INTO public.produto_views VALUES (86, 4, '5e0qvsl097r1ejbdtrr2ptn838', '2026-03-11 22:01:48.604885', '127.0.0.1');
INSERT INTO public.produto_views VALUES (87, 4, '5e0qvsl097r1ejbdtrr2ptn838', '2026-03-11 22:03:22.385925', '127.0.0.1');
INSERT INTO public.produto_views VALUES (88, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 21:52:15.072429', '127.0.0.1');
INSERT INTO public.produto_views VALUES (89, 1, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 21:53:20.920492', '127.0.0.1');
INSERT INTO public.produto_views VALUES (90, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 21:53:24.456588', '127.0.0.1');
INSERT INTO public.produto_views VALUES (91, 2, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 21:53:49.225847', '127.0.0.1');
INSERT INTO public.produto_views VALUES (92, 3, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 21:53:53.573513', '127.0.0.1');
INSERT INTO public.produto_views VALUES (93, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 21:54:30.349447', '127.0.0.1');
INSERT INTO public.produto_views VALUES (94, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:05:47.545038', '127.0.0.1');
INSERT INTO public.produto_views VALUES (95, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:10:53.064314', '127.0.0.1');
INSERT INTO public.produto_views VALUES (96, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:11:02.371426', '127.0.0.1');
INSERT INTO public.produto_views VALUES (97, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:11:22.354708', '127.0.0.1');
INSERT INTO public.produto_views VALUES (98, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:13:02.929158', '127.0.0.1');
INSERT INTO public.produto_views VALUES (99, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:14:25.57997', '127.0.0.1');
INSERT INTO public.produto_views VALUES (100, 4, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:15:47.644547', '127.0.0.1');
INSERT INTO public.produto_views VALUES (101, 3, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:16:04.098839', '127.0.0.1');
INSERT INTO public.produto_views VALUES (102, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:17:32.440713', '127.0.0.1');
INSERT INTO public.produto_views VALUES (103, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:19:48.029366', '127.0.0.1');
INSERT INTO public.produto_views VALUES (104, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:20:01.764419', '127.0.0.1');
INSERT INTO public.produto_views VALUES (105, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:21:31.209913', '127.0.0.1');
INSERT INTO public.produto_views VALUES (106, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:21:52.88', '127.0.0.1');
INSERT INTO public.produto_views VALUES (107, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:22:15.702275', '127.0.0.1');
INSERT INTO public.produto_views VALUES (108, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:23:32.351237', '127.0.0.1');
INSERT INTO public.produto_views VALUES (109, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:25:09.950798', '127.0.0.1');
INSERT INTO public.produto_views VALUES (110, 3, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:25:20.215483', '127.0.0.1');
INSERT INTO public.produto_views VALUES (111, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:26:20.506449', '127.0.0.1');
INSERT INTO public.produto_views VALUES (112, 3, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:26:32.328889', '127.0.0.1');
INSERT INTO public.produto_views VALUES (113, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:28:10.359757', '127.0.0.1');
INSERT INTO public.produto_views VALUES (114, 3, 'ftcgkpr8l1e6o85uni0ipj658d', '2026-03-12 22:28:12.241257', '127.0.0.1');
INSERT INTO public.produto_views VALUES (115, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 22:28:33.235126', '127.0.0.1');
INSERT INTO public.produto_views VALUES (116, 4, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 23:46:25.248438', '127.0.0.1');
INSERT INTO public.produto_views VALUES (117, 3, 'r4jsuhtkdmaa0bsftn3onoqevc', '2026-03-12 23:56:50.900152', '127.0.0.1');
INSERT INTO public.produto_views VALUES (118, 4, 'mbd1okp34phar6b7pne27n8u6s', '2026-03-13 22:03:06.441623', '127.0.0.1');
INSERT INTO public.produto_views VALUES (119, 6, 'ap7459b54de7i6bvr5fih27htj', '2026-03-16 20:38:23.987495', '127.0.0.1');
INSERT INTO public.produto_views VALUES (120, 9, '011958875b7ffb2e8b0f91346055c104', '2026-03-31 14:03:34.673992', '::1');
INSERT INTO public.produto_views VALUES (121, 9, '011958875b7ffb2e8b0f91346055c104', '2026-03-31 14:03:35.419428', '::1');
INSERT INTO public.produto_views VALUES (122, 9, '011958875b7ffb2e8b0f91346055c104', '2026-03-31 14:03:36.101914', '::1');
INSERT INTO public.produto_views VALUES (123, 9, '011958875b7ffb2e8b0f91346055c104', '2026-03-31 14:03:37.049731', '::1');
INSERT INTO public.produto_views VALUES (124, 9, '011958875b7ffb2e8b0f91346055c104', '2026-03-31 14:03:38.535638', '::1');
INSERT INTO public.produto_views VALUES (125, 9, '011958875b7ffb2e8b0f91346055c104', '2026-03-31 14:03:40.07964', '::1');
INSERT INTO public.produto_views VALUES (126, 7, '8dfdbc566032c78d695e23b994027b01', '2026-04-03 13:09:09.502621', '::1');
INSERT INTO public.produto_views VALUES (127, 7, '8dfdbc566032c78d695e23b994027b01', '2026-04-03 13:49:22.692616', '::1');
INSERT INTO public.produto_views VALUES (128, 11, 'cc31343edf68ca7287fa647ce472b2f2', '2026-04-05 22:18:36.062401', '::1');
INSERT INTO public.produto_views VALUES (129, 11, 'd638d7eecfa8a0b550e199f81ff85cd8', '2026-04-05 22:52:23.446311', '::1');
INSERT INTO public.produto_views VALUES (130, 11, 'd638d7eecfa8a0b550e199f81ff85cd8', '2026-04-05 22:52:32.188576', '::1');
INSERT INTO public.produto_views VALUES (131, 11, 'd638d7eecfa8a0b550e199f81ff85cd8', '2026-04-05 22:52:58.097294', '::1');
INSERT INTO public.produto_views VALUES (132, 11, 'd638d7eecfa8a0b550e199f81ff85cd8', '2026-04-05 22:54:02.039536', '::1');
INSERT INTO public.produto_views VALUES (133, 11, 'd638d7eecfa8a0b550e199f81ff85cd8', '2026-04-05 22:54:11.489385', '::1');
INSERT INTO public.produto_views VALUES (134, 11, '274752c72ee60d2856dff20c75e5a1f2', '2026-04-06 11:46:43.66639', '::1');
INSERT INTO public.produto_views VALUES (135, 11, '274752c72ee60d2856dff20c75e5a1f2', '2026-04-06 11:52:11.05257', '::1');
INSERT INTO public.produto_views VALUES (136, 11, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 00:15:24.071062', '::1');
INSERT INTO public.produto_views VALUES (137, 12, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 00:44:01.410284', '::1');
INSERT INTO public.produto_views VALUES (138, 13, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 00:44:06.200842', '::1');
INSERT INTO public.produto_views VALUES (139, 12, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 00:58:18.894539', '::1');
INSERT INTO public.produto_views VALUES (140, 12, '535bad2ff2cdb00d0e6ffc331ba240ee', '2026-04-07 02:28:40.855996', '::1');
INSERT INTO public.produto_views VALUES (141, 12, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 02:33:29.369067', '::1');
INSERT INTO public.produto_views VALUES (142, 12, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 02:34:48.560014', '::1');
INSERT INTO public.produto_views VALUES (143, 13, 'e52b0c6fcace781021fb44bb85964404', '2026-04-07 03:48:48.246105', '::1');
INSERT INTO public.produto_views VALUES (144, 13, '697086afc009f4e8844d1d58ca0b7342', '2026-04-07 13:18:06.504808', '::1');


--
-- TOC entry 3805 (class 0 OID 16769)
-- Dependencies: 274
-- Data for Name: produtos; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.produtos VALUES (7, 1, 'dasddd', 'sadasdsadsa', '', '', 7800.00, NULL, 12, '69b973d200992_1773761490.jpg', true, true, '2026-03-17 12:31:30.010284', NULL, 4);
INSERT INTO public.produtos VALUES (1, 16, 'teste', 'fqw', 'wfwf', 'ww', 0.02, 0.03, 0, '69a78ffd45289_1772589053.png', true, true, '2026-03-03 22:50:53.288605', NULL, 4);
INSERT INTO public.produtos VALUES (3, 1, 'teste teste', 'wsgsgsgsgsgss', 'eaDdAD', 'qadaDad', 12000.00, 10000.00, 0, '69a8c968ce02c_1772669288.png', true, true, '2026-03-04 21:08:08.84625', NULL, 4);
INSERT INTO public.produtos VALUES (4, 26, 'Abbavash', 'Ahsag', 'Ahaah', 'Ahshsh', 12234.00, 1333.00, 0, '69ad8c6b23d74_1772981355.png', true, true, '2026-03-08 11:49:15.149911', NULL, 4);
INSERT INTO public.produtos VALUES (2, 16, '3535352', '552525', 'dsds', '32535325', 55555.00, 25.00, 22, '69a7a4066c797_1772594182.png', false, true, '2026-03-04 00:16:22.448401', NULL, 4);
INSERT INTO public.produtos VALUES (6, 28, 'x calabresa', 'x calabresa com salada, carne e queijo e calabresa', '', '', 27.00, 0.00, 6, '69b887493b45f_1773700937.jpg', false, true, '2026-03-16 19:42:17.244391', NULL, 4);
INSERT INTO public.produtos VALUES (5, 27, 'pizza de calabresa', 'pizza de calabresa com molho de tomate e queijo', '', '', 76.00, 56.00, 0, '69b886e9145ce_1773700841.jpg', false, true, '2026-03-16 19:40:41.087479', NULL, 4);
INSERT INTO public.produtos VALUES (10, 16, 'fffff', 'fffff', '', '', 29.90, NULL, 8, '69c32502501f6_1774396674.png', false, true, '2026-03-21 11:37:01.366691', NULL, 4);
INSERT INTO public.produtos VALUES (9, 16, 'asfaf', 'asfasfsaf', '', '', 2990.00, NULL, 9, '69c325139663a_1774396691.png', false, true, '2026-03-21 11:33:15.581819', NULL, 4);
INSERT INTO public.produtos VALUES (8, 27, 'teste pizza', 'pizza de calabres com queijo e molho', '', '', 29.90, NULL, 8, '69c32531c8c81_1774396721.png', false, true, '2026-03-21 11:28:00.814607', NULL, 4);
INSERT INTO public.produtos VALUES (13, 30, 'pizza de marguerita', 'pizza marguerita com queijo e molho de tomate', '', '', 44.44, NULL, 7, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775521587/produtos/q5t8lssamuj2dbmnjydl.jpg', false, true, '2026-04-07 00:26:28.202996', NULL, 9);
INSERT INTO public.produtos VALUES (11, 29, 'x calabresa', 'x calabresa com queijo, salada  e carne', '', '', 20.00, NULL, 11, '69d2ded44ca49_1775427284.webp', false, true, '2026-04-05 22:14:44.376974', NULL, 9);
INSERT INTO public.produtos VALUES (12, 30, 'fdfaff', 'fsfadsf', '', '', 66.66, NULL, 5, 'https://res.cloudinary.com/dbmhzqykt/image/upload/v1775565105/produtos/l9giqgtjbge28pdc5xci.jpg', false, true, '2026-04-07 00:15:06.168469', NULL, 9);
INSERT INTO public.produtos VALUES (14, 29, 'teclado', '42414', '', '', 10.00, NULL, 5, NULL, false, true, '2026-04-07 19:06:37.272035', '7898615154064', 9);


--
-- TOC entry 3807 (class 0 OID 16786)
-- Dependencies: 276
-- Data for Name: staff; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.staff VALUES (1, 'teste', 'teste123', '$2y$10$HH1sLM2HYr4m6dNYpE3aX.sIiZBCq21A75WrbYYYgbevXe1eXDdeK', 'vendedor', true, '2026-03-14 13:11:49.727594', NULL);
INSERT INTO public.staff VALUES (2, 'teste atendente', 'testeatendente', '$2y$10$J9WXUD/jKexIjI4Qj4sqruTvIpdKTQM78wwIbea1iMH.IrGJmbLee', 'atendente', true, '2026-03-15 13:06:20.526736', NULL);
INSERT INTO public.staff VALUES (4, 'teste caixa', 'teste caixa', '$2y$10$gNkXlnjdT2JK9CnV6ov3re2YP2yFhV7mLgEaPwkWnVZTqAVrtvav.', 'caixa', true, '2026-03-15 13:08:37.299354', NULL);
INSERT INTO public.staff VALUES (7, 'delon', 'delon@123', '$2y$10$ld4CC4eQUzFfr6CtnpFhT.H/qNuCDpWRpqpsEp81UhvaCjHV7Wypy', 'admin', true, '2026-03-19 00:34:26.259491', NULL);
INSERT INTO public.staff VALUES (5, 'Allan', 'allan123', '$2y$10$Q9ZXRPu071ZvjcKiFZHsIeCJcXHjhRoRFSY8cMJ6e5lTOChcb/jZu', 'atendente', true, '2026-03-15 19:55:03.617261', NULL);
INSERT INTO public.staff VALUES (8, 'vv@gmail.com', 'vv@gmail.com', '$2y$10$OpMawJUV/iyjlyqs1835mO2XVkOB.xQnVv.9efAQZ7oHE1rm313G.', 'admin', true, '2026-04-05 17:48:18.595747', 9);
INSERT INTO public.staff VALUES (9, 'll@gmail.com', 'll@gmail.com', '$2y$10$CPNwJSR.usJLZiRQdBaeH.YxTpI6sRxwSO5uMQ92Bu0WASFVUUP/K', 'admin', true, '2026-04-05 18:00:01.906008', 9);
INSERT INTO public.staff VALUES (10, 'rita123', 'rita123', '$2y$10$U6ZGp/6ZSiKuVbpPztjGNObaav5aXSxAFsbS9SNuzkd0Gxci8Pcxa', 'atendente', true, '2026-04-05 19:33:17.00449', 9);
INSERT INTO public.staff VALUES (12, 'rr@gmail.com', 'rr@gmail.com', '$2y$10$zZeafmBuCu7TWs0HyPptdOSrbLCeUZ8fsby5BXSKsW8d1tFLSWUQ2', 'vendedor', true, '2026-04-17 00:08:00.366256', 9);
INSERT INTO public.staff VALUES (13, 'Rita', 'Rita', '$2y$10$HYBBszgt4keQT/i03IboqO/epU0tMQAzVEzmAwrfHeFYTag1UZY0m', 'atendente', true, '2026-04-19 17:07:46.027736', 9);


--
-- TOC entry 3809 (class 0 OID 16801)
-- Dependencies: 278
-- Data for Name: telas; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.telas VALUES (1, 'dashboard', 'Dashboard / Início', NULL, true);
INSERT INTO public.telas VALUES (2, 'venda_presencial', 'Venda Presencial', NULL, true);
INSERT INTO public.telas VALUES (3, 'atendente', 'Lançar Comanda', NULL, true);
INSERT INTO public.telas VALUES (4, 'caixa', 'Painel do Caixa', NULL, true);
INSERT INTO public.telas VALUES (5, 'pedidos', 'Pedidos', NULL, true);
INSERT INTO public.telas VALUES (6, 'produtos', 'Produtos', NULL, true);
INSERT INTO public.telas VALUES (7, 'categorias', 'Categorias', NULL, true);
INSERT INTO public.telas VALUES (8, 'clientes', 'Clientes', NULL, true);
INSERT INTO public.telas VALUES (9, 'relatorios', 'Relatórios', NULL, true);
INSERT INTO public.telas VALUES (10, 'admin', 'Painel Administrativo', NULL, true);
INSERT INTO public.telas VALUES (11, 'configuracoes', 'Configurações', NULL, true);
INSERT INTO public.telas VALUES (12, 'impressoras', 'Impressoras', NULL, true);
INSERT INTO public.telas VALUES (17, 'empresa', 'Empresa', NULL, true);
INSERT INTO public.telas VALUES (18, 'adicionais', 'Adicionais', NULL, true);
INSERT INTO public.telas VALUES (19, 'dashboard_vendas', 'Dashboard de Vendas', NULL, true);
INSERT INTO public.telas VALUES (20, 'cancelamentos', 'Cancelamentos', NULL, true);
INSERT INTO public.telas VALUES (21, 'logs', 'Logs', NULL, true);
INSERT INTO public.telas VALUES (22, 'historico', 'Histórico', NULL, true);
INSERT INTO public.telas VALUES (23, 'modo_restaurante', 'Modo Restaurante', NULL, true);
INSERT INTO public.telas VALUES (24, 'novo_produto', 'Novo Produto', NULL, true);
INSERT INTO public.telas VALUES (25, 'usuarios', 'Usuários', NULL, true);


--
-- TOC entry 3811 (class 0 OID 16812)
-- Dependencies: 280
-- Data for Name: vendas_presenciais; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.vendas_presenciais VALUES (1, 19, 3, 1, 2, '2026-03-08 14:40:10.134419', NULL);
INSERT INTO public.vendas_presenciais VALUES (2, 21, 2, 1, 2, '2026-03-09 21:11:17.537379', NULL);
INSERT INTO public.vendas_presenciais VALUES (3, 33, 2, 1, 2, '2026-03-13 23:23:27.947588', NULL);
INSERT INTO public.vendas_presenciais VALUES (4, 34, 2, 1, 2, '2026-03-13 23:26:12.963617', NULL);
INSERT INTO public.vendas_presenciais VALUES (5, 35, 2, 1, 2, '2026-03-13 23:35:20.352897', NULL);
INSERT INTO public.vendas_presenciais VALUES (6, 36, 2, 1, 2, '2026-03-13 23:37:46.825445', NULL);
INSERT INTO public.vendas_presenciais VALUES (7, 37, 2, 1, 2, '2026-03-13 23:39:38.417184', NULL);
INSERT INTO public.vendas_presenciais VALUES (8, 38, 2, 1, 2, '2026-03-13 23:41:51.432336', NULL);
INSERT INTO public.vendas_presenciais VALUES (9, 39, 2, 1, 2, '2026-03-13 23:43:12.423176', NULL);
INSERT INTO public.vendas_presenciais VALUES (10, 40, 2, 1, 2, '2026-03-13 23:45:24.691887', NULL);
INSERT INTO public.vendas_presenciais VALUES (11, 41, 2, 1, 2, '2026-03-14 00:02:57.283663', NULL);
INSERT INTO public.vendas_presenciais VALUES (12, 41, 3, 1, 2, '2026-03-14 00:02:57.283663', NULL);
INSERT INTO public.vendas_presenciais VALUES (13, 42, 2, 1, NULL, '2026-03-14 14:09:16.92919', 1);
INSERT INTO public.vendas_presenciais VALUES (14, 43, 2, 1, NULL, '2026-03-15 12:09:13.707862', 1);
INSERT INTO public.vendas_presenciais VALUES (15, 44, 2, 1, NULL, '2026-03-15 12:14:13.247503', 1);
INSERT INTO public.vendas_presenciais VALUES (16, 90, 8, 1, 2, '2026-03-23 20:59:42.653824', NULL);
INSERT INTO public.vendas_presenciais VALUES (17, 91, 8, 1, 2, '2026-03-23 21:04:08.903007', NULL);
INSERT INTO public.vendas_presenciais VALUES (18, 92, 6, 1, 2, '2026-03-23 21:09:53.176887', NULL);
INSERT INTO public.vendas_presenciais VALUES (19, 97, 11, 1, NULL, '2026-04-05 22:34:17.435182', 10);
INSERT INTO public.vendas_presenciais VALUES (20, 104, 14, 1, 17, '2026-04-22 00:24:15.268353', NULL);


--
-- TOC entry 3813 (class 0 OID 16821)
-- Dependencies: 282
-- Data for Name: vendedores; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.vendedores VALUES (1, 'teste', 'teste123', '$2y$10$/Rf00dwZp4WiasEXLRb29.pCyCMhasSQpgBSGDqcYMU.iz/fLzZEm', true, '2026-03-14 13:11:49.727594');


--
-- TOC entry 3815 (class 0 OID 16833)
-- Dependencies: 284
-- Data for Name: visitas; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.visitas VALUES (1, 'a9ub4qkaq7it06mmlbl230ubg9', '::1', '2026-03-03 22:23:49.826666', NULL);
INSERT INTO public.visitas VALUES (16, 'h6l8tbm7q3mc0dcp6sosl8uiqp', '127.0.0.1', '2026-03-04 00:13:19.441801', NULL);
INSERT INTO public.visitas VALUES (29, '8gu1log26s7hhfgathlpp1un7v', '192.168.0.13', '2026-03-04 00:28:31.326994', NULL);
INSERT INTO public.visitas VALUES (31, 'qjhkvb3rgip661nog99v6j9cn0', '127.0.0.1', '2026-03-04 20:54:29.871117', NULL);
INSERT INTO public.visitas VALUES (739, '17lcng342c8vuf8fucec7pfkk5', '127.0.0.1', '2026-03-06 22:42:38.389264', NULL);
INSERT INTO public.visitas VALUES (819, 'imspjdurbhe7lo9fqg9q2ht1hk', '192.168.0.22', '2026-03-07 01:10:27.957392', NULL);
INSERT INTO public.visitas VALUES (931, 't2r6232ao7h4q2vi7qkfb1vs4s', '192.168.0.22', '2026-03-07 17:05:47.835776', NULL);
INSERT INTO public.visitas VALUES (937, 'qd9893f52agrc2l8l08q22thp0', '127.0.0.1', '2026-03-08 09:58:15.77667', NULL);
INSERT INTO public.visitas VALUES (941, 'i8i44bqnk0f3kuf7ouht9cft1p', '127.0.0.1', '2026-03-08 11:36:20.336925', NULL);
INSERT INTO public.visitas VALUES (949, '1q3r6c9n3rcinrb32inp2mt0a0', '192.168.0.22', '2026-03-08 11:48:03.307484', NULL);
INSERT INTO public.visitas VALUES (1431, 'b3vi0mji8f7fs1jd86neh0ej5b', '127.0.0.1', '2026-03-09 20:57:26.87071', NULL);
INSERT INTO public.visitas VALUES (2111, '893mudlv8bcmbook76na0mt0j9', '192.168.0.22', '2026-03-10 21:53:22.551438', NULL);
INSERT INTO public.visitas VALUES (2121, 'ftvlhc5nbkkpbclfslfc7codf8', '127.0.0.1', '2026-03-10 23:19:15.598689', NULL);
INSERT INTO public.visitas VALUES (2132, 'ahofinsogsumdccjlomjf9t8m5', '127.0.0.1', '2026-03-11 22:21:49.014548', NULL);
INSERT INTO public.visitas VALUES (2138, 'i0ofg88uvb9chgd4ultffm8h1o', '127.0.0.1', '2026-03-11 22:25:53.205221', NULL);
INSERT INTO public.visitas VALUES (2340, 'r4jsuhtkdmaa0bsftn3onoqevc', '127.0.0.1', '2026-03-12 22:59:51.32034', NULL);
INSERT INTO public.visitas VALUES (2390, '290pr7v7timn2a35c8afiun91c', '127.0.0.1', '2026-03-12 23:48:30.685798', NULL);
INSERT INTO public.visitas VALUES (2474, 'mbd1okp34phar6b7pne27n8u6s', '127.0.0.1', '2026-03-13 22:05:28.374291', NULL);
INSERT INTO public.visitas VALUES (2494, 'q5p0gjd4lfnv9lcc8e7m2f5tlh', '127.0.0.1', '2026-03-13 22:19:11.724001', NULL);
INSERT INTO public.visitas VALUES (2762, 'ij8ulbcqcg05ktqtohhihj81jd', '127.0.0.1', '2026-03-13 23:25:35.715316', NULL);
INSERT INTO public.visitas VALUES (2799, 't4ta1dlh6i6tbsd6eu8v2rlo7g', '127.0.0.1', '2026-03-14 12:38:36.268477', NULL);
INSERT INTO public.visitas VALUES (3170, 'r4kg3271ctheie2ma7h32ftis3', '127.0.0.1', '2026-03-14 14:10:46.228002', NULL);
INSERT INTO public.visitas VALUES (3171, 'pjecoub45ksq5qcree8iqme5b6', '127.0.0.1', '2026-03-14 22:42:08.354439', NULL);
INSERT INTO public.visitas VALUES (3180, '33fsj0dlc8hlem2n41gpn5j4m7', '127.0.0.1', '2026-03-15 11:17:10.181813', NULL);
INSERT INTO public.visitas VALUES (3219, 'l28r896j0o80g19ft7an39k43b', '127.0.0.1', '2026-03-15 12:06:06.871725', NULL);
INSERT INTO public.visitas VALUES (3278, '2toioajjlepmhmjlqj7qd8r7t0', '127.0.0.1', '2026-03-15 18:53:01.21572', NULL);
INSERT INTO public.visitas VALUES (3280, 'd8d8h6a7efcsso2n3tu28hf9ki', '127.0.0.1', '2026-03-15 19:00:50.87367', NULL);
INSERT INTO public.visitas VALUES (3623, 'dh0u5q0g68sgrd60i109rhhmdf', '127.0.0.1', '2026-03-15 20:59:15.183228', NULL);
INSERT INTO public.visitas VALUES (3624, '6su8sccgk0e869ntn3fbnu3ffb', '127.0.0.1', '2026-03-16 12:22:12.067903', NULL);
INSERT INTO public.visitas VALUES (3645, 'ap7459b54de7i6bvr5fih27htj', '127.0.0.1', '2026-03-16 19:28:30.463364', NULL);
INSERT INTO public.visitas VALUES (3943, '1ponhmfotrq9d6mivoc0v914ct', '127.0.0.1', '2026-03-17 12:25:25.382959', NULL);
INSERT INTO public.visitas VALUES (4004, 'hjkmosuvn71hgohregj4c9uo3p', '127.0.0.1', '2026-03-17 19:24:23.515754', NULL);
INSERT INTO public.visitas VALUES (4901, 'hvdal0735kpf65bv0borausskg', '127.0.0.1', '2026-03-19 00:32:29.870584', NULL);
INSERT INTO public.visitas VALUES (4902, 'ke8s5srblmbfdqungmmt1v6vsv', '127.0.0.1', '2026-03-19 21:12:58.521059', NULL);
INSERT INTO public.visitas VALUES (4903, 'rr55p5c50antrnmgh7n4ulekj8', '127.0.0.1', '2026-03-20 22:51:08.182754', NULL);
INSERT INTO public.visitas VALUES (5061, 'dug3ci19ncqiamgu08ahobqhl3', '127.0.0.1', '2026-03-21 11:11:48.956161', NULL);
INSERT INTO public.visitas VALUES (5949, 'vdvns7vo7bbf7q9mmacko5vlu4', '127.0.0.1', '2026-03-22 11:17:59.632872', NULL);
INSERT INTO public.visitas VALUES (6006, 'qbuuemcjn69td6vh1brqpfgrqa', '127.0.0.1', '2026-03-23 20:51:36.984248', NULL);
INSERT INTO public.visitas VALUES (6031, '4cph9urqjsg8p06goqbucp33d5', '127.0.0.1', '2026-03-24 20:34:23.764342', NULL);
INSERT INTO public.visitas VALUES (6495, 'qph9t9qlgnvujdt2emchec40md', '127.0.0.1', '2026-03-25 23:27:06.192803', NULL);
INSERT INTO public.visitas VALUES (6649, 'd638d7eecfa8a0b550e199f81ff85cd8', '::1', '2026-04-05 19:49:26.922698', NULL);
INSERT INTO public.visitas VALUES (7333, '274752c72ee60d2856dff20c75e5a1f2', '::1', '2026-04-06 11:44:55.267468', 4);
INSERT INTO public.visitas VALUES (7344, 'e52b0c6fcace781021fb44bb85964404', '::1', '2026-04-06 23:49:43.867548', 4);
INSERT INTO public.visitas VALUES (7580, '697086afc009f4e8844d1d58ca0b7342', '::1', '2026-04-07 11:34:53.941597', 9);
INSERT INTO public.visitas VALUES (8001, '3f92fc76f7e80048444e0e590117d54d', '::1', '2026-04-12 21:41:30.285159', 9);
INSERT INTO public.visitas VALUES (8099, '45ef9c0a6985e7fcb0c174908d853b78', '::1', '2026-04-16 01:48:16.499263', 9);
INSERT INTO public.visitas VALUES (8122, '63f13efd5fbf65fe38e2b521a61aabd4', '::1', '2026-04-16 11:12:42.713299', 9);
INSERT INTO public.visitas VALUES (8143, '385930e9c95c97d82e30fdda53d4c15e', '::1', '2026-04-17 00:04:01.616699', 9);
INSERT INTO public.visitas VALUES (8152, '03039ef79a5880124f251cf56c13b590', '::1', '2026-04-17 11:24:15.175643', 9);
INSERT INTO public.visitas VALUES (10105, '0765cc01acb9f2f08c749682fad584cd', '::1', '2026-04-19 17:07:14.457535', 9);
INSERT INTO public.visitas VALUES (10110, 'ad019dfee5cf763910dcd9c7c7e61c2e', '::1', '2026-04-20 20:02:44.704976', 9);
INSERT INTO public.visitas VALUES (10111, '323321ea7438d728c415e70157ba7815', '::1', '2026-04-21 23:09:31.822269', 9);
INSERT INTO public.visitas VALUES (10180, 'b46e52a0fcee433a0378975ea1eab3e5', '::1', '2026-04-22 12:00:25.289249', 9);
INSERT INTO public.visitas VALUES (10402, 'a1628b674c85bf2f2b19726c4d8b0bbb', '::1', '2026-04-22 22:22:20.660155', 9);


--
-- TOC entry 3859 (class 0 OID 0)
-- Dependencies: 220
-- Name: adicionais_id_adicional_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.adicionais_id_adicional_seq', 8, true);


--
-- TOC entry 3860 (class 0 OID 0)
-- Dependencies: 222
-- Name: administradores_id_admin_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.administradores_id_admin_seq', 3, true);


--
-- TOC entry 3861 (class 0 OID 0)
-- Dependencies: 224
-- Name: admins_id_admin_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.admins_id_admin_seq', 17, true);


--
-- TOC entry 3862 (class 0 OID 0)
-- Dependencies: 226
-- Name: caixa_movimentacoes_id_mov_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.caixa_movimentacoes_id_mov_seq', 3, true);


--
-- TOC entry 3863 (class 0 OID 0)
-- Dependencies: 228
-- Name: caixa_sessoes_id_sessao_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.caixa_sessoes_id_sessao_seq', 6, true);


--
-- TOC entry 3864 (class 0 OID 0)
-- Dependencies: 230
-- Name: cancelamentos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.cancelamentos_id_seq', 2, true);


--
-- TOC entry 3865 (class 0 OID 0)
-- Dependencies: 232
-- Name: categorias_id_categoria_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.categorias_id_categoria_seq', 30, true);


--
-- TOC entry 3866 (class 0 OID 0)
-- Dependencies: 234
-- Name: clientes_id_cliente_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.clientes_id_cliente_seq', 1, false);


--
-- TOC entry 3867 (class 0 OID 0)
-- Dependencies: 236
-- Name: comanda_itens_id_item_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.comanda_itens_id_item_seq', 68, true);


--
-- TOC entry 3868 (class 0 OID 0)
-- Dependencies: 238
-- Name: comanda_itens_removidos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.comanda_itens_removidos_id_seq', 1, true);


--
-- TOC entry 3869 (class 0 OID 0)
-- Dependencies: 240
-- Name: comandas_id_comanda_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.comandas_id_comanda_seq', 47, true);


--
-- TOC entry 3870 (class 0 OID 0)
-- Dependencies: 242
-- Name: compra_itens_id_item_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compra_itens_id_item_seq', 1, false);


--
-- TOC entry 3871 (class 0 OID 0)
-- Dependencies: 244
-- Name: compras_id_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.compras_id_compra_seq', 6, true);


--
-- TOC entry 3872 (class 0 OID 0)
-- Dependencies: 246
-- Name: configuracoes_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.configuracoes_id_seq', 70, true);


--
-- TOC entry 3873 (class 0 OID 0)
-- Dependencies: 248
-- Name: empresa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresa_id_seq', 2, true);


--
-- TOC entry 3874 (class 0 OID 0)
-- Dependencies: 250
-- Name: empresas_tenants_id_tenant_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.empresas_tenants_id_tenant_seq', 9, true);


--
-- TOC entry 3875 (class 0 OID 0)
-- Dependencies: 252
-- Name: impressoras_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.impressoras_id_seq', 3, false);


--
-- TOC entry 3876 (class 0 OID 0)
-- Dependencies: 254
-- Name: itens_compra_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.itens_compra_id_seq', 1, false);


--
-- TOC entry 3877 (class 0 OID 0)
-- Dependencies: 256
-- Name: itens_pedido_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.itens_pedido_id_seq', 118, true);


--
-- TOC entry 3878 (class 0 OID 0)
-- Dependencies: 258
-- Name: licencas_id_licenca_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.licencas_id_licenca_seq', 9, true);


--
-- TOC entry 3879 (class 0 OID 0)
-- Dependencies: 287
-- Name: locais_permitidos_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.locais_permitidos_id_seq', 1, true);


--
-- TOC entry 3880 (class 0 OID 0)
-- Dependencies: 260
-- Name: logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.logs_id_seq', 35, true);


--
-- TOC entry 3881 (class 0 OID 0)
-- Dependencies: 262
-- Name: pedido_itens_id_item_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pedido_itens_id_item_seq', 1, false);


--
-- TOC entry 3882 (class 0 OID 0)
-- Dependencies: 264
-- Name: pedidos_id_pedido_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.pedidos_id_pedido_seq', 106, true);


--
-- TOC entry 3883 (class 0 OID 0)
-- Dependencies: 267
-- Name: planos_id_plano_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.planos_id_plano_seq', 4, true);


--
-- TOC entry 3884 (class 0 OID 0)
-- Dependencies: 269
-- Name: produto_adicionais_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.produto_adicionais_id_seq', 22, true);


--
-- TOC entry 3885 (class 0 OID 0)
-- Dependencies: 271
-- Name: produto_imagens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.produto_imagens_id_seq', 19, true);


--
-- TOC entry 3886 (class 0 OID 0)
-- Dependencies: 273
-- Name: produto_views_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.produto_views_id_seq', 144, true);


--
-- TOC entry 3887 (class 0 OID 0)
-- Dependencies: 275
-- Name: produtos_id_produto_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.produtos_id_produto_seq', 14, true);


--
-- TOC entry 3888 (class 0 OID 0)
-- Dependencies: 277
-- Name: staff_id_staff_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.staff_id_staff_seq', 13, true);


--
-- TOC entry 3889 (class 0 OID 0)
-- Dependencies: 279
-- Name: telas_id_tela_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.telas_id_tela_seq', 25, true);


--
-- TOC entry 3890 (class 0 OID 0)
-- Dependencies: 281
-- Name: vendas_presenciais_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.vendas_presenciais_id_seq', 20, true);


--
-- TOC entry 3891 (class 0 OID 0)
-- Dependencies: 283
-- Name: vendedores_id_vendedor_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.vendedores_id_vendedor_seq', 1, true);


--
-- TOC entry 3892 (class 0 OID 0)
-- Dependencies: 285
-- Name: visitas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.visitas_id_seq', 10566, true);


--
-- TOC entry 3542 (class 2606 OID 16874)
-- Name: adicionais adicionais_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.adicionais
    ADD CONSTRAINT adicionais_pkey PRIMARY KEY (id_adicional);


--
-- TOC entry 3544 (class 2606 OID 16876)
-- Name: administradores administradores_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administradores
    ADD CONSTRAINT administradores_email_key UNIQUE (email);


--
-- TOC entry 3546 (class 2606 OID 16878)
-- Name: administradores administradores_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administradores
    ADD CONSTRAINT administradores_pkey PRIMARY KEY (id_admin);


--
-- TOC entry 3548 (class 2606 OID 16880)
-- Name: administradores administradores_usuario_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administradores
    ADD CONSTRAINT administradores_usuario_key UNIQUE (usuario);


--
-- TOC entry 3550 (class 2606 OID 16882)
-- Name: admins admins_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_email_key UNIQUE (email);


--
-- TOC entry 3552 (class 2606 OID 16885)
-- Name: admins admins_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.admins
    ADD CONSTRAINT admins_pkey PRIMARY KEY (id_admin);


--
-- TOC entry 3554 (class 2606 OID 16887)
-- Name: caixa_movimentacoes caixa_movimentacoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_movimentacoes
    ADD CONSTRAINT caixa_movimentacoes_pkey PRIMARY KEY (id_mov);


--
-- TOC entry 3556 (class 2606 OID 16889)
-- Name: caixa_sessoes caixa_sessoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.caixa_sessoes
    ADD CONSTRAINT caixa_sessoes_pkey PRIMARY KEY (id_sessao);


--
-- TOC entry 3558 (class 2606 OID 16891)
-- Name: cancelamentos cancelamentos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cancelamentos
    ADD CONSTRAINT cancelamentos_pkey PRIMARY KEY (id);


--
-- TOC entry 3560 (class 2606 OID 16893)
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id_categoria);


--
-- TOC entry 3562 (class 2606 OID 16895)
-- Name: clientes clientes_cpf_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_cpf_key UNIQUE (cpf);


--
-- TOC entry 3564 (class 2606 OID 16897)
-- Name: clientes clientes_email_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_email_key UNIQUE (email);


--
-- TOC entry 3566 (class 2606 OID 16899)
-- Name: clientes clientes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.clientes
    ADD CONSTRAINT clientes_pkey PRIMARY KEY (id_cliente);


--
-- TOC entry 3568 (class 2606 OID 16901)
-- Name: comanda_itens comanda_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comanda_itens
    ADD CONSTRAINT comanda_itens_pkey PRIMARY KEY (id_item);


--
-- TOC entry 3570 (class 2606 OID 16903)
-- Name: comanda_itens_removidos comanda_itens_removidos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comanda_itens_removidos
    ADD CONSTRAINT comanda_itens_removidos_pkey PRIMARY KEY (id);


--
-- TOC entry 3572 (class 2606 OID 16905)
-- Name: comandas comandas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comandas
    ADD CONSTRAINT comandas_pkey PRIMARY KEY (id_comanda);


--
-- TOC entry 3574 (class 2606 OID 16907)
-- Name: compra_itens compra_itens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compra_itens
    ADD CONSTRAINT compra_itens_pkey PRIMARY KEY (id_item);


--
-- TOC entry 3576 (class 2606 OID 16909)
-- Name: compras compras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.compras
    ADD CONSTRAINT compras_pkey PRIMARY KEY (id_compra);


--
-- TOC entry 3578 (class 2606 OID 16949)
-- Name: configuracoes configuracoes_chave_tenant_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_chave_tenant_unique UNIQUE (chave, id_tenant);


--
-- TOC entry 3580 (class 2606 OID 16913)
-- Name: configuracoes configuracoes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.configuracoes
    ADD CONSTRAINT configuracoes_pkey PRIMARY KEY (id);


--
-- TOC entry 3582 (class 2606 OID 16915)
-- Name: empresa empresa_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresa
    ADD CONSTRAINT empresa_pkey PRIMARY KEY (id);


--
-- TOC entry 3584 (class 2606 OID 16917)
-- Name: empresas_tenants empresas_tenants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas_tenants
    ADD CONSTRAINT empresas_tenants_pkey PRIMARY KEY (id_tenant);


--
-- TOC entry 3586 (class 2606 OID 16919)
-- Name: empresas_tenants empresas_tenants_subdominio_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.empresas_tenants
    ADD CONSTRAINT empresas_tenants_subdominio_key UNIQUE (subdominio);


--
-- TOC entry 3588 (class 2606 OID 16921)
-- Name: impressoras impressoras_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.impressoras
    ADD CONSTRAINT impressoras_pkey PRIMARY KEY (id);


--
-- TOC entry 3590 (class 2606 OID 16923)
-- Name: itens_compra itens_compra_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_compra
    ADD CONSTRAINT itens_compra_pkey PRIMARY KEY (id);


--
-- TOC entry 3592 (class 2606 OID 16925)
-- Name: itens_pedido itens_pedido_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.itens_pedido
    ADD CONSTRAINT itens_pedido_pkey PRIMARY KEY (id);


--
-- TOC entry 3598 (class 2606 OID 24604)
-- Name: locais_permitidos locais_permitidos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.locais_permitidos
    ADD CONSTRAINT locais_permitidos_pkey PRIMARY KEY (id);


--
-- TOC entry 3596 (class 2606 OID 16939)
-- Name: php_sessions php_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.php_sessions
    ADD CONSTRAINT php_sessions_pkey PRIMARY KEY (id);


--
-- TOC entry 3594 (class 2606 OID 16945)
-- Name: visitas visitas_session_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.visitas
    ADD CONSTRAINT visitas_session_id_unique UNIQUE (session_id);


--
-- TOC entry 3746 (class 0 OID 16473)
-- Dependencies: 231
-- Name: categorias; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.categorias ENABLE ROW LEVEL SECURITY;

--
-- TOC entry 3747 (class 0 OID 16483)
-- Dependencies: 233
-- Name: clientes; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.clientes ENABLE ROW LEVEL SECURITY;

--
-- TOC entry 3748 (class 0 OID 16712)
-- Dependencies: 263
-- Name: pedidos; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.pedidos ENABLE ROW LEVEL SECURITY;

--
-- TOC entry 3749 (class 0 OID 16769)
-- Dependencies: 274
-- Name: produtos; Type: ROW SECURITY; Schema: public; Owner: -
--

ALTER TABLE public.produtos ENABLE ROW LEVEL SECURITY;

-- Completed on 2026-04-23 19:09:04

--
-- PostgreSQL database dump complete
--

\unrestrict 98j0FpSpIs3IeNkhEnDfgKp4h8grpV2JgIvB2fgQb1JQPIaQYl98GLn46jfaOYk

