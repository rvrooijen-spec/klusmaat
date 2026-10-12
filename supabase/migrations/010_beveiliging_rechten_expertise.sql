-- Klusmaat 010: rechten aanscherpen en openbare expertise-weergave.

-- 1. Oude, zwakkere indienfunctie onbereikbaar maken (maak_aanvraag is de enige weg naar binnen).
-- De functie zelf is bewust niet verwijderd; opruimen kan later met een aparte migratie.
revoke execute on function aanvraag_indienen(uuid, uuid, text, text, integer, text, text, text, text, text, text, boolean, text) from public, anon, authenticated;

-- 2. Minimale tabelrechten. RLS blijft de inhoudelijke bewaking; dit haalt rechten weg die nooit nodig zijn.
do $$
declare r record;
begin
  for r in select c.relname, c.relkind from pg_class c join pg_namespace n on n.oid = c.relnamespace
           where n.nspname = 'public' and c.relkind in ('r','v') loop
    execute format('revoke truncate, references, trigger on %I from anon, authenticated', r.relname);
    if r.relkind = 'v' then
      execute format('revoke insert, update, delete on %I from anon, authenticated', r.relname);
    else
      execute format('revoke insert, update, delete on %I from anon', r.relname);
    end if;
  end loop;
end $$;

-- Nieuwe tabellen krijgen standaard geen rechten meer: geef ze expliciet in de migratie die ze aanmaakt.
alter default privileges for role postgres in schema public revoke all on tables from anon, authenticated;
alter default privileges for role postgres in schema public revoke all on sequences from anon, authenticated;

-- 3. Triggerfuncties zijn niet bedoeld om aan te roepen.
revoke execute on function blog_tijdstempel() from public, anon, authenticated;
revoke execute on function logboek_onveranderlijk() from public, anon, authenticated;

-- 4. Openbare weergave: welke categorie bedient een goedgekeurde klusser (voor landingspagina's).
create view klusser_expertise_publiek as
  select distinct kk.klusser_id, kt.categorie_id
  from klusser_klustypen kk
  join klustypen kt on kt.id = kk.klustype_id
  join klussers k on k.id = kk.klusser_id
  where k.status = 'goedgekeurd';
revoke all on klusser_expertise_publiek from public, anon, authenticated;
grant select on klusser_expertise_publiek to anon, authenticated;
