-- Klusmaat: tabellen voor fase 2 en 3, nu alvast aangemaakt zodat het datamodel niet meer hoeft te verhuizen.
-- Alle tabellen zijn standaard alleen voor beheerders; policies voor klussers volgen in de betreffende fase.

create table betalingen (
  id uuid primary key default gen_random_uuid(),
  klusser_id uuid not null references klussers on delete cascade,
  mollie_id text unique,
  bedrag_cent int not null check (bedrag_cent > 0),
  credits int not null check (credits > 0),
  status text not null default 'open',
  aangemaakt timestamptz not null default now()
);

create table chats (
  id uuid primary key default gen_random_uuid(),
  aanvraag_id uuid not null references aanvragen on delete cascade,
  klusser_id uuid not null references klussers on delete cascade,
  gedempt boolean not null default false,
  gesloten boolean not null default false,
  aangemaakt timestamptz not null default now(),
  unique (aanvraag_id, klusser_id)
);

create table berichten (
  id uuid primary key default gen_random_uuid(),
  chat_id uuid not null references chats on delete cascade,
  afzender text not null check (afzender in ('klant','klusser')),
  tekst text not null,
  aangemaakt timestamptz not null default now()
);

create table reviews (
  id uuid primary key default gen_random_uuid(),
  aanvraag_id uuid not null references aanvragen on delete cascade,
  klusser_id uuid not null references klussers on delete cascade,
  uitgevoerd_door_klusser boolean not null,
  sterren smallint not null check (sterren between 1 and 5),
  toelichting text,
  zichtbaar boolean not null default true,   -- admin kan verbergen
  aangemaakt timestamptz not null default now(),
  unique (aanvraag_id, klusser_id)
);

create table spotlights (
  id uuid primary key default gen_random_uuid(),
  klusser_id uuid not null references klussers on delete cascade,
  plaats_id uuid references plaatsen,
  categorie_id uuid references categorieen,
  start_op timestamptz not null,
  eind_op timestamptz not null,
  betaling_id uuid references betalingen
);

create table email_log (
  id uuid primary key default gen_random_uuid(),
  aanvraag_id uuid references aanvragen on delete cascade,
  chat_id uuid references chats on delete cascade,
  soort text not null,
  verzonden_op timestamptz not null default now()
);

do $$
declare t text;
begin
  foreach t in array array['betalingen','chats','berichten','reviews','spotlights','email_log'] loop
    execute format('alter table %I enable row level security', t);
    execute format('create policy admin_alles on %I for all using (is_admin()) with check (is_admin())', t);
  end loop;
end $$;
