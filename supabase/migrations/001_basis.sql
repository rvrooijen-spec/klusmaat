-- Klusmaat: basis datamodel (fase 1)
-- Doel: Supabase (Postgres 15+). Alles wat prijs, aantallen en zichtbaarheid raakt staat in `instellingen`.

create type klusser_status as enum ('aangemeld','goedgekeurd','in_beoordeling','geblokkeerd');
create type aanvraag_status as enum ('open','afgerond','geannuleerd','verlopen');
create type credit_type as enum ('welkom','gekocht','gebruikt','teruggeboekt','handmatig');

-- Instellingen en beheerders --------------------------------------------------
create table instellingen (
  sleutel text primary key,
  waarde jsonb not null,
  omschrijving text,
  publiek boolean not null default false,   -- mag de website (anon) dit lezen?
  bijgewerkt timestamptz not null default now()
);

create table beheerders (user_id uuid primary key references auth.users on delete cascade);

create function is_admin() returns boolean
language sql stable security definer set search_path = public as
$$ select exists (select 1 from beheerders where user_id = auth.uid()) $$;

create function instelling(p_sleutel text) returns jsonb
language sql stable security definer set search_path = public as
$$ select waarde from instellingen where sleutel = p_sleutel $$;

-- Beheerbare lijsten ----------------------------------------------------------
create table plaatsen (
  id uuid primary key default gen_random_uuid(),
  naam text not null unique,
  slug text not null unique,
  actief boolean not null default true,
  volgorde int not null default 100
);

create table categorieen (
  id uuid primary key default gen_random_uuid(),
  naam text not null,
  slug text not null unique,
  omschrijving text,
  is_top5 boolean not null default false,
  volgorde int not null default 100,
  actief boolean not null default true
);

create table klustypen (
  id uuid primary key default gen_random_uuid(),
  categorie_id uuid not null references categorieen on delete cascade,
  naam text not null,
  slug text not null,
  credits_override int check (credits_override >= 0),  -- null = standaard uit instellingen
  volgorde int not null default 100,
  actief boolean not null default true,
  unique (categorie_id, slug)
);

-- Klussers --------------------------------------------------------------------
create table klussers (
  id uuid primary key references auth.users on delete cascade,
  bedrijfsnaam text not null,
  contactnaam text,
  email text not null,
  telefoon text,
  kvk text,
  website text,
  over text,
  logo_pad text,
  status klusser_status not null default 'aangemeld',
  status_reden text,
  aangemeld_op timestamptz not null default now(),
  goedgekeurd_op timestamptz
);

create table klusser_plaatsen (
  klusser_id uuid references klussers on delete cascade,
  plaats_id uuid references plaatsen on delete cascade,
  primary key (klusser_id, plaats_id)
);

create table klusser_klustypen (
  klusser_id uuid references klussers on delete cascade,
  klustype_id uuid references klustypen on delete cascade,
  primary key (klusser_id, klustype_id)
);

create table portfolio_projecten (
  id uuid primary key default gen_random_uuid(),
  klusser_id uuid not null references klussers on delete cascade,
  titel text not null,
  info text not null,
  beeld_pad text,
  status text not null default 'concept' check (status in ('concept','gepubliceerd')),
  aangemaakt timestamptz not null default now()
);

-- Aanvragen (klanten, zonder account; inserts alleen via de server met service role) ----
create table aanvragen (
  id uuid primary key default gen_random_uuid(),
  plaats_id uuid not null references plaatsen,
  klustype_id uuid not null references klustypen,
  omschrijving text not null,
  gewenste_periode text,
  max_klussers int not null default 3 check (max_klussers between 3 and 8),
  naam text not null,
  straat_huisnr text not null,
  postcode text not null check (postcode ~ '^[1-9][0-9]{3} ?[A-Za-z]{2}$'),
  woonplaats text not null,
  email text not null,
  telefoon text not null,
  bel_tijdsbestek text,
  privacy_akkoord_op timestamptz not null,
  chat_token_hash text unique,        -- alleen de hash opslaan, nooit de token zelf
  chat_verloopt_op timestamptz,
  status aanvraag_status not null default 'open',
  aangemaakt timestamptz not null default now()
);
create index on aanvragen (status, plaats_id, klustype_id);

create table aanvraag_fotos (
  id uuid primary key default gen_random_uuid(),
  aanvraag_id uuid not null references aanvragen on delete cascade,
  pad text not null
);

-- Credits ---------------------------------------------------------------------
create table ontgrendelingen (
  id uuid primary key default gen_random_uuid(),
  aanvraag_id uuid not null references aanvragen on delete cascade,
  klusser_id uuid not null references klussers on delete cascade,
  credits_betaald int not null default 0,
  teruggeboekt_op timestamptz,
  aangemaakt timestamptz not null default now(),
  unique (aanvraag_id, klusser_id)
);

create table credit_transacties (          -- grootboek: saldo = som van aantal
  id uuid primary key default gen_random_uuid(),
  klusser_id uuid not null references klussers on delete cascade,
  type credit_type not null,
  aantal int not null,                     -- + erbij, - eraf
  aanvraag_id uuid references aanvragen on delete set null,
  notitie text,
  aangemaakt timestamptz not null default now()
);
create index on credit_transacties (klusser_id);

create view credit_saldo with (security_invoker = true) as
  select klusser_id, sum(aantal)::int as saldo from credit_transacties group by klusser_id;

-- Triggers --------------------------------------------------------------------
create function klusser_voor_update() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if not is_admin() then
    if new.status is distinct from old.status
       or new.status_reden is distinct from old.status_reden
       or new.goedgekeurd_op is distinct from old.goedgekeurd_op then
      raise exception 'Alleen een beheerder mag de status wijzigen';
    end if;
  elsif new.status = 'goedgekeurd' and old.goedgekeurd_op is null then
    new.goedgekeurd_op := now();
  end if;
  return new;
end $$;
create trigger klusser_voor_update before update on klussers
  for each row execute function klusser_voor_update();

create function klusser_na_goedkeuring() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_credits int := coalesce((instelling('welkomstcredits'))::int, 0);
begin
  if old.goedgekeurd_op is null and new.goedgekeurd_op is not null and v_credits > 0 then
    insert into credit_transacties (klusser_id, type, aantal, notitie)
    values (new.id, 'welkom', v_credits, 'Welkomstcredits');
  end if;
  return new;
end $$;
create trigger klusser_na_goedkeuring after update on klussers
  for each row execute function klusser_na_goedkeuring();

-- Functies voor klussers ------------------------------------------------------
-- Overzicht: alleen stad, categorie, type, omschrijving (geen contactgegevens).
create function klussen_overzicht() returns table (
  id uuid, plaats text, categorie text, klustype text, omschrijving text,
  aangemaakt timestamptz, ontgrendeld boolean, aantal_ontgrendeld int, max_klussers int)
language sql stable security definer set search_path = public as $$
  select a.id, p.naam, c.naam, t.naam, a.omschrijving, a.aangemaakt,
    exists (select 1 from ontgrendelingen o where o.aanvraag_id = a.id and o.klusser_id = auth.uid()),
    (select count(*)::int from ontgrendelingen o where o.aanvraag_id = a.id),
    a.max_klussers
  from aanvragen a
  join plaatsen p on p.id = a.plaats_id
  join klustypen t on t.id = a.klustype_id
  join categorieen c on c.id = t.categorie_id
  where a.status = 'open'
    and exists (select 1 from klussers k where k.id = auth.uid() and k.status = 'goedgekeurd')
    and exists (select 1 from klusser_plaatsen kp where kp.klusser_id = auth.uid() and kp.plaats_id = a.plaats_id)
    and exists (select 1 from klusser_klustypen kt where kt.klusser_id = auth.uid() and kt.klustype_id = a.klustype_id)
  order by a.aangemaakt desc
$$;

-- Ontgrendelen: atomair, idempotent, respecteert maximum en saldo.
create function ontgrendel_aanvraag(p_aanvraag uuid, p_verwachte_kosten int default null) returns jsonb
language plpgsql security definer set search_path = public as $$
declare
  v_uid uuid := auth.uid();
  v_a aanvragen%rowtype;
  v_kosten int; v_saldo int; v_aantal int; v_fotos jsonb;
begin
  if v_uid is null then raise exception 'Niet ingelogd'; end if;
  perform 1 from klussers where id = v_uid and status = 'goedgekeurd' for update;  -- serialiseert saldo
  if not found then raise exception 'Account is niet goedgekeurd'; end if;

  select * into v_a from aanvragen where id = p_aanvraag for update;
  if not found or v_a.status <> 'open' then raise exception 'Klus is niet beschikbaar'; end if;

  if not exists (select 1 from klusser_plaatsen where klusser_id = v_uid and plaats_id = v_a.plaats_id)
     or not exists (select 1 from klusser_klustypen where klusser_id = v_uid and klustype_id = v_a.klustype_id) then
    raise exception 'Klus valt buiten jouw werkgebied of diensten';
  end if;

  if not exists (select 1 from ontgrendelingen where aanvraag_id = p_aanvraag and klusser_id = v_uid) then
    select count(*) into v_aantal from ontgrendelingen where aanvraag_id = p_aanvraag;
    if v_aantal >= v_a.max_klussers then raise exception 'Het maximum aantal klussers is bereikt'; end if;

    if coalesce((instelling('betaalmodel_aan'))::boolean, false) then
      v_kosten := coalesce((select credits_override from klustypen where id = v_a.klustype_id),
                           (instelling('credits_per_klus'))::int, 1);
    else
      v_kosten := 0;
    end if;
    -- De klusser heeft de getoonde kosten bevestigd; wijkt de prijs nu af, dan opnieuw laten bevestigen.
    if p_verwachte_kosten is not null and p_verwachte_kosten <> v_kosten then
      raise exception 'De kosten zijn gewijzigd, bevestig opnieuw';
    end if;

    select coalesce(sum(aantal), 0) into v_saldo from credit_transacties where klusser_id = v_uid;
    if v_saldo < v_kosten then raise exception 'Te weinig credits'; end if;

    insert into ontgrendelingen (aanvraag_id, klusser_id, credits_betaald) values (p_aanvraag, v_uid, v_kosten);
    perform log_actie('klusser', v_uid, v_uid, 'kosten_akkoord', p_aanvraag,
      jsonb_build_object('getoonde_kosten', p_verwachte_kosten, 'afgeschreven', v_kosten));
    if v_kosten > 0 then
      insert into credit_transacties (klusser_id, type, aantal, aanvraag_id) values (v_uid, 'gebruikt', -v_kosten, p_aanvraag);
    end if;
  end if;

  select coalesce(jsonb_agg(pad), '[]'::jsonb) into v_fotos from aanvraag_fotos where aanvraag_id = p_aanvraag;
  return jsonb_build_object('naam', v_a.naam, 'adres', v_a.straat_huisnr, 'postcode', v_a.postcode,
    'woonplaats', v_a.woonplaats, 'email', v_a.email, 'telefoon', v_a.telefoon,
    'bel_tijdsbestek', v_a.bel_tijdsbestek, 'fotos', v_fotos);
end $$;

-- Admin: credits terugboeken na "Meld probleem" (maximaal één keer per ontgrendeling).
create function admin_terugboeken(p_ontgrendeling uuid, p_notitie text) returns void
language plpgsql security definer set search_path = public as $$
declare v_o ontgrendelingen%rowtype;
begin
  if not is_admin() then raise exception 'Geen toegang'; end if;
  select * into v_o from ontgrendelingen where id = p_ontgrendeling for update;
  if not found or v_o.teruggeboekt_op is not null then raise exception 'Niet mogelijk'; end if;
  update ontgrendelingen set teruggeboekt_op = now() where id = p_ontgrendeling;
  if v_o.credits_betaald > 0 then
    insert into credit_transacties (klusser_id, type, aantal, aanvraag_id, notitie)
    values (v_o.klusser_id, 'teruggeboekt', v_o.credits_betaald, v_o.aanvraag_id, p_notitie);
  end if;
end $$;

revoke all on function klussen_overzicht(), ontgrendel_aanvraag(uuid, int), admin_terugboeken(uuid, text) from public, anon;
grant execute on function klussen_overzicht(), ontgrendel_aanvraag(uuid, int), admin_terugboeken(uuid, text) to authenticated;

-- Openbare weergave (geen contactgegevens) ------------------------------------
create view klusser_publiek as
  select id, bedrijfsnaam, over, logo_pad, website from klussers where status = 'goedgekeurd';
create view klusser_plaatsen_publiek as
  select kp.klusser_id, kp.plaats_id from klusser_plaatsen kp
  join klussers k on k.id = kp.klusser_id where k.status = 'goedgekeurd';
grant select on klusser_publiek, klusser_plaatsen_publiek to anon, authenticated;

-- Row Level Security ----------------------------------------------------------
do $$
declare t text;
begin
  foreach t in array array['instellingen','beheerders','plaatsen','categorieen','klustypen','klussers',
    'klusser_plaatsen','klusser_klustypen','portfolio_projecten','aanvragen','aanvraag_fotos',
    'ontgrendelingen','credit_transacties'] loop
    execute format('alter table %I enable row level security', t);
    execute format('create policy admin_alles on %I for all using (is_admin()) with check (is_admin())', t);
  end loop;
end $$;

create policy publiek_lezen on instellingen for select using (publiek);
create policy publiek_lezen on plaatsen for select using (actief);
create policy publiek_lezen on categorieen for select using (actief);
create policy publiek_lezen on klustypen for select using (actief);

create policy eigen_lezen on klussers for select using (id = auth.uid());
create policy eigen_aanmaken on klussers for insert with check (id = auth.uid() and status = 'aangemeld');
create policy eigen_wijzigen on klussers for update using (id = auth.uid()) with check (id = auth.uid());

create policy eigen on klusser_plaatsen for all using (klusser_id = auth.uid()) with check (klusser_id = auth.uid());
create policy eigen on klusser_klustypen for all using (klusser_id = auth.uid()) with check (klusser_id = auth.uid());
create policy eigen on portfolio_projecten for all using (klusser_id = auth.uid()) with check (klusser_id = auth.uid());
create policy publiek_gepubliceerd on portfolio_projecten for select using (
  status = 'gepubliceerd' and exists (select 1 from klussers k where k.id = klusser_id and k.status = 'goedgekeurd'));

create policy eigen_lezen on ontgrendelingen for select using (klusser_id = auth.uid());
create policy eigen_lezen on credit_transacties for select using (klusser_id = auth.uid());
-- aanvragen en aanvraag_fotos: geen eigen policies; klussers gebruiken uitsluitend de functies hierboven.
