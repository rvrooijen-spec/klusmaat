create table email_wachtrij (
  id uuid primary key default gen_random_uuid(),
  soort text not null check (soort in ('aanvraag_bevestiging','chat_bericht','review_herinnering')),
  aanvraag_id uuid references aanvragen on delete cascade,
  chat_id uuid references chats on delete cascade,
  naar text not null,
  onderwerp text not null,
  inhoud text not null,
  status text not null default 'wachtend' check (status in ('wachtend','verzonden','mislukt','overgeslagen')),
  fout text,
  aangemaakt timestamptz not null default now(),
  verzonden_op timestamptz
);
create index on email_wachtrij (status, aangemaakt);
create index on email_wachtrij (chat_id, soort);
alter table email_wachtrij enable row level security;
create policy admin_alles on email_wachtrij for all using (is_admin()) with check (is_admin());

-- De persoonlijke link moet in e-mails kunnen staan, daarom bewaren we de token apart (alleen beheerders en de server).
create table aanvraag_geheimen (
  aanvraag_id uuid primary key references aanvragen on delete cascade,
  chat_token text not null
);
alter table aanvraag_geheimen enable row level security;
create policy admin_alles on aanvraag_geheimen for all using (is_admin()) with check (is_admin());

insert into instellingen (sleutel, waarde, omschrijving, publiek) values
 ('mail_actief', 'false', 'E-mail versturen aan (de wachtrij vult altijd, verzenden pas als dit aan staat)', false),
 ('review_herinnering_dagen', '7', 'Dagen na het begin van een chat voordat de klant een reviewherinnering krijgt', false),
 ('site_url', '"https://klusmaat-test-remco5.vercel.app"', 'Basisadres van de website, gebruikt in links in e-mails', false)
on conflict (sleutel) do nothing;

-- Aanvraag plaatsen: bewaart nu ook de token en zet de bevestigingsmail in de wachtrij.
create or replace function maak_aanvraag(
  p_plaats uuid, p_klustype uuid, p_omschrijving text, p_periode text, p_max int,
  p_naam text, p_adres text, p_postcode text, p_email text, p_telefoon text,
  p_bel text, p_privacy boolean, p_website text default null)
returns text language plpgsql security definer set search_path = public as $$
declare v_token text; v_plaats text; v_id uuid; v_type text; v_site text := coalesce(instelling('site_url') #>> '{}', '');
begin
  if coalesce(p_website, '') <> '' then return 'ok'; end if;
  if not coalesce(p_privacy, false) then raise exception 'Ga akkoord met de privacyverklaring'; end if;
  if p_max is null or p_max not between 3 and 8 then raise exception 'Kies 3 tot 8 klussers'; end if;
  if length(coalesce(p_omschrijving,'')) not between 5 and 2000 then raise exception 'Omschrijving is te kort of te lang'; end if;
  if coalesce(p_naam,'') = '' or length(p_naam) > 120 or coalesce(p_adres,'') = '' or length(p_adres) > 160
     or coalesce(p_telefoon,'') = '' or length(p_telefoon) > 30 or length(coalesce(p_bel,'')) > 200
     or length(coalesce(p_periode,'')) > 100 then
    raise exception 'Controleer de ingevulde gegevens';
  end if;
  if p_email !~* '^[^@\s]+@[^@\s]+\.[^@\s]+$' or length(p_email) > 160 then raise exception 'Ongeldig e-mailadres'; end if;
  if (select count(*) from aanvragen where lower(email) = lower(p_email) and aangemaakt > now() - interval '1 hour') >= 3 then
    raise exception 'Te veel aanvragen achter elkaar, probeer het later opnieuw';
  end if;
  select naam into v_plaats from plaatsen where id = p_plaats and actief;
  if v_plaats is null then raise exception 'Onbekende plaats'; end if;
  select naam into v_type from klustypen where id = p_klustype and actief;
  if v_type is null then raise exception 'Onbekend type klus'; end if;

  v_token := replace(gen_random_uuid()::text || gen_random_uuid()::text, '-', '');
  insert into aanvragen (plaats_id, klustype_id, omschrijving, gewenste_periode, max_klussers, naam, straat_huisnr,
                         postcode, woonplaats, email, telefoon, bel_tijdsbestek, privacy_akkoord_op,
                         chat_token_hash, chat_verloopt_op)
  values (p_plaats, p_klustype, trim(p_omschrijving), p_periode, p_max, trim(p_naam), trim(p_adres),
          upper(trim(p_postcode)), v_plaats, trim(p_email), trim(p_telefoon), nullif(trim(p_bel), ''), now(),
          encode(sha256(convert_to(v_token, 'utf8')), 'hex'),
          now() + make_interval(days => coalesce((instelling('chat_link_dagen'))::int, 60)))
  returning id into v_id;
  insert into aanvraag_geheimen (aanvraag_id, chat_token) values (v_id, v_token);
  insert into email_wachtrij (soort, aanvraag_id, naar, onderwerp, inhoud)
  values ('aanvraag_bevestiging', v_id, trim(p_email), 'Je aanvraag bij Klusmaat is geplaatst',
    'Hallo ' || trim(p_naam) || E',\n\nJe aanvraag (' || v_type || ' in ' || v_plaats || E') is geplaatst. Via deze persoonlijke link zie je de reacties van klussers en chat je met ze:\n'
    || v_site || '/#/chat/' || v_token || E'\n\nBewaar deze link goed.\n\nKlusmaat');
  perform log_actie('klant', null, null, 'aanvraag_geplaatst', v_id, '{}');
  return v_token;
end $$;

-- Nieuw bericht van een klusser: maximaal een mail per chat binnen de ingestelde pauze.
create function chat_bericht_mail() returns trigger
language plpgsql security definer set search_path = public as $$
declare v_a aanvragen%rowtype; v_tok text; v_pauze int := coalesce((instelling('mail_pauze_minuten'))::int, 15);
        v_site text := coalesce(instelling('site_url') #>> '{}', '');
begin
  if new.afzender <> 'klusser' then return null; end if;
  select a.* into v_a from chats c join aanvragen a on a.id = c.aanvraag_id where c.id = new.chat_id;
  if not found then return null; end if;
  if exists (select 1 from email_wachtrij where chat_id = new.chat_id and soort = 'chat_bericht'
             and status in ('wachtend','verzonden') and aangemaakt > now() - make_interval(mins => v_pauze)) then
    return null;
  end if;
  select chat_token into v_tok from aanvraag_geheimen where aanvraag_id = v_a.id;
  insert into email_wachtrij (soort, aanvraag_id, chat_id, naar, onderwerp, inhoud)
  values ('chat_bericht', v_a.id, new.chat_id, v_a.email, 'Nieuw bericht van een klusser',
    'Hallo ' || v_a.naam || E',\n\nEen klusser heeft je een bericht gestuurd. Lees en beantwoord het hier:\n'
    || case when v_tok is not null then v_site || '/#/chat/' || v_tok else v_site || '/#/mijn' end || E'\n\nKlusmaat');
  return null;
end $$;
revoke all on function chat_bericht_mail() from public, anon, authenticated;
create trigger chat_bericht_mail after insert on berichten for each row execute function chat_bericht_mail();

-- Eenmalige reviewherinnering. Aan te roepen door een beheerder of later door een geplande taak.
create function plan_review_herinneringen() returns int
language plpgsql security definer set search_path = public as $$
declare v_dagen int := coalesce((instelling('review_herinnering_dagen'))::int, 7);
        v_site text := coalesce(instelling('site_url') #>> '{}', ''); n int;
begin
  if auth.uid() is not null and not is_admin() then raise exception 'Geen toegang'; end if;
  with ins as (
    insert into email_wachtrij (soort, aanvraag_id, chat_id, naar, onderwerp, inhoud)
    select 'review_herinnering', a.id, c.id, a.email, 'Is je klus uitgevoerd? Laat een beoordeling achter',
      'Hallo ' || a.naam || E',\n\nIs je klus (' || kt.naam || ') uitgevoerd door ' || k.bedrijfsnaam
      || E'? Laat via je persoonlijke link een beoordeling achter:\n' || v_site || '/#/chat/' || g.chat_token || E'\n\nKlusmaat'
    from chats c
    join aanvragen a on a.id = c.aanvraag_id
    join klustypen kt on kt.id = a.klustype_id
    join klussers k on k.id = c.klusser_id
    join aanvraag_geheimen g on g.aanvraag_id = a.id
    where c.aangemaakt < now() - make_interval(days => v_dagen) and c.uitgevoerd is null
      and not exists (select 1 from reviews r where r.aanvraag_id = a.id and r.klusser_id = c.klusser_id)
      and not exists (select 1 from email_wachtrij e where e.chat_id = c.id and e.soort = 'review_herinnering')
    returning 1)
  select count(*)::int into n from ins;
  return n;
end $$;
revoke all on function plan_review_herinneringen() from public, anon;
grant execute on function plan_review_herinneringen() to authenticated;
