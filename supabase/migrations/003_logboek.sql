-- Klusmaat: logboek (audit log). Alleen toevoegen, nooit wijzigen of verwijderen.
-- Legt acties van klussers vast; exporteerbaar als tekstbestand door een beheerder.

create table logboek (
  id bigint generated always as identity primary key,
  tijdstip timestamptz not null default now(),
  actor_type text not null check (actor_type in ('klusser','klant','admin','systeem')),
  actor_id uuid,
  klusser_id uuid,                 -- over welke klusser gaat dit (voor filteren en export)
  actie text not null,             -- bijv. klussers_update, klus_ontgrendeld, kosten_akkoord
  aanvraag_id uuid,
  details jsonb not null default '{}',
  app_versie text
);
create index on logboek (klusser_id, tijdstip);
create index on logboek (tijdstip);

alter table logboek enable row level security;
create policy admin_lezen on logboek for select using (is_admin());
revoke all on logboek from anon, authenticated;
grant select on logboek to authenticated;   -- RLS beperkt dit tot beheerders

create function logboek_onveranderlijk() returns trigger language plpgsql as
$$ begin raise exception 'Het logboek is alleen-toevoegen'; end $$;
create trigger logboek_geen_wijziging before update or delete on logboek
  for each row execute function logboek_onveranderlijk();

-- Handmatig vastleggen (door de server of andere functies).
create function log_actie(p_actor_type text, p_actor_id uuid, p_klusser uuid, p_actie text,
                          p_aanvraag uuid default null, p_details jsonb default '{}')
returns void language sql security definer set search_path = public as $$
  insert into logboek (actor_type, actor_id, klusser_id, actie, aanvraag_id, details, app_versie)
  values (p_actor_type, p_actor_id, p_klusser, p_actie, p_aanvraag, p_details,
          (select waarde #>> '{}' from instellingen where sleutel = 'app_versie'))
$$;
revoke all on function log_actie(text, uuid, uuid, text, uuid, jsonb) from public, anon, authenticated;

-- Automatisch loggen van alle wijzigingen die klussers (of beheerders) in de database doen.
create function log_tabel_wijziging() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_rij jsonb; v_klusser uuid;
begin
  v_rij := to_jsonb(case when tg_op = 'DELETE' then old else new end) - 'logo_pad' - 'beeld_pad';
  v_klusser := coalesce((v_rij->>'klusser_id')::uuid,
                        case when tg_table_name = 'klussers' then (v_rij->>'id')::uuid end);
  perform log_actie(
    case when auth.uid() is null then 'systeem' when is_admin() then 'admin' else 'klusser' end,
    auth.uid(), v_klusser, tg_table_name || '_' || lower(tg_op),
    (v_rij->>'aanvraag_id')::uuid, jsonb_build_object('rij', v_rij));
  return null;
end $$;

do $$
declare t text;
begin
  foreach t in array array['klussers','klusser_plaatsen','klusser_klustypen','portfolio_projecten',
                           'ontgrendelingen','credit_transacties','betalingen'] loop
    execute format('create trigger log_wijziging after insert or update or delete on %I
                    for each row execute function log_tabel_wijziging()', t);
  end loop;
end $$;

-- Export als tekst, één regel per actie. Alleen beheerders.
create function logboek_export(p_klusser uuid default null, p_van timestamptz default null, p_tot timestamptz default null)
returns text language plpgsql stable security definer set search_path = public as $$
declare v text;
begin
  if not is_admin() then raise exception 'Geen toegang'; end if;
  select coalesce(string_agg(
      to_char(l.tijdstip at time zone 'Europe/Amsterdam', 'YYYY-MM-DD HH24:MI:SS') || ' | ' || l.actor_type
      || ' | klusser ' || coalesce(l.klusser_id::text, '-') || ' | ' || l.actie
      || ' | aanvraag ' || coalesce(l.aanvraag_id::text, '-') || ' | v' || coalesce(l.app_versie, '?')
      || ' | ' || l.details::text, E'\n' order by l.tijdstip), '')
  into v from logboek l
  where (p_klusser is null or l.klusser_id = p_klusser)
    and (p_van is null or l.tijdstip >= p_van) and (p_tot is null or l.tijdstip < p_tot);
  return v;
end $$;
revoke all on function logboek_export(uuid, timestamptz, timestamptz) from public, anon;
grant execute on function logboek_export(uuid, timestamptz, timestamptz) to authenticated;

insert into instellingen (sleutel, waarde, omschrijving, publiek) values
  ('app_versie', '"0.1.0"', 'Huidige versie van de app, wordt in het logboek vastgelegd', false)
on conflict (sleutel) do nothing;
