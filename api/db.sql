
set search_path=public;
CREATE TYPE public.en_status AS ENUM (
    'act',
    'can',
    'com',
    'del',
    'log',
    'sus',
    'rev',
    'iac',
    'pro',
    'obs',
    'pre',
    'sac',
    'shp'
);

CREATE TYPE public.etic_status AS ENUM (
    'opn',
    'cls',
    'ini',
    'hol'
);


CREATE DOMAIN public.dm_etime AS timestamp without time zone DEFAULT now();



create schema _10009_1pl;
set search_path=_10009_1pl;
--
-- Name: msopl_amc; Type: TABLE; Schema: _10009_1pl; Owner: -
--


CREATE TABLE _10009_1pl.common (
    did smallint NOT NULL,
    dcode character varying(10),
    dname character varying(100),
    category character(15)
);

--
-- Name: msopl_mplan; Type: TABLE; Schema: _10009_1pl; Owner: -
--

CREATE TABLE _10009_1pl.msopl_mplan (
    mplan_id SERIAL PRIMARY KEY,
    branch_idf smallint NOT NULL,
    pstage_idf smallint NOT NULL,
    product_idf smallint NOT NULL,
    mtype_idf smallint NOT NULL,
    mref_no character varying(20) NOT NULL,
    start_date timestamp without time zone NOT NULL,
    end_date timestamp without time zone NOT NULL,
    desc_prob character varying(200) NOT NULL,
    attach_det character varying[],
    assign_to smallint NOT NULL,
    remark character varying(200),
    frequency character varying(200),
    pref_idf smallint NOT NULL,
    next_due timestamp without time zone,
    due_alert character(1),
    rstatus public.en_status NOT NULL,
    euser_idf smallint NOT NULL,
    e_time public.dm_etime
);


CREATE TABLE _10009_1pl.msopl_complaint (
    comp_id SERIAL PRIMARY KEY,
    branch_idf smallint NOT NULL,
    product_idf smallint NOT NULL,
    pref_idf smallint NOT NULL,
    cref_no character varying(20) NOT NULL,
    desc_prob character varying(200) NOT NULL,
    attach_det character varying[] DEFAULT NULL,
    comp_status public.etic_status NOT NULL,
    euser_idf smallint NOT NULL,
    e_time public.dm_etime
);

CREATE TABLE _10009_1pl.msopl_worder (
    wo_id SERIAL PRIMARY KEY,
    ref_idf integer,
    ref_cat varchar(1),
    assign_to smallint default null,
    wo_status public.etic_status NOT NULL,
    desc_prob character varying(200) default null,
    attach_det character varying[] default null,
    compl_dtime timestamp without time zone,
    compl_remarks character varying(200) default null,
    compl_attach_det character varying[],
    euser_idf smallint NOT NULL,
    e_time public.dm_etime
);
ALTER TABLE _10009_1pl.msopl_worder   ADD CONSTRAINT ref_cat_chk CHECK (ref_cat IN ('c','p'));


