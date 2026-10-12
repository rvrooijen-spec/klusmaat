alter table chats add column uitgevoerd boolean;
alter table aanvragen add column klant_id uuid references auth.users on delete set null;
create index on aanvragen (klant_id);

insert into chats (aanvraag_id, klusser_id) select aanvraag_id, klusser_id from ontgrendelingen on conflict do nothing;

insert into instellingen (sleutel, waarde, omschrijving, publiek) values
 ('kwaliteit_automatisch', 'true', 'Klusser automatisch op "in beoordeling" zetten onder de drempel', false),
 ('chat_max_lengte', '2000', 'Maximale lengte van een chatbericht', false)
on conflict (sleutel) do nothing;

-- Systeemacties (kwaliteitsdrempel) mogen de status wijzigen.
create or replace function klusser_voor_update() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  if not is_admin() and coalesce(current_setting('app.systeem', true), '') <> '1' then
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

-- Ontgrendelen start nu ook een chat tussen klant en klusser.
create or replace function ontgrendel_aanvraag(p_aanvraag uuid, p_verwachte_kosten int default null) returns jsonb
language plpgsql security definer set search_path = public as $$
declare
  v_uid uuid := auth.uid();
  v_a aanvragen%rowtype;
  v_kosten int; v_saldo int; v_aantal int; v_fotos jsonb;
begin
  if v_uid is null then raise exception 'Niet ingelogd'; end if;
  perform 1 from klussers where id = v_uid and status = 'goedgekeurd' for update;
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

  insert into chats (aanvraag_id, klusser_id) values (p_aanvraag, v_uid) on conflict do nothing;

  select coalesce(jsonb_agg(pad), '[]'::jsonb) into v_fotos from aanvraag_fotos where aanvraag_id = p_aanvraag;
  return jsonb_build_object('naam', v_a.naam, 'adres', v_a.straat_huisnr, 'postcode', v_a.postcode,
    'woonplaats', v_a.woonplaats, 'email', v_a.email, 'telefoon', v_a.telefoon,
    'bel_tijdsbestek', v_a.bel_tijdsbestek, 'fotos', v_fotos);
end $$;

-- Toegang van de klant: unieke link (token) of ingelogd account.
create function klant_mag(p_aanvraag uuid, p_token text) returns boolean
language sql stable security definer set search_path = public as $$
  select exists (select 1 from aanvragen a where a.id = p_aanvraag and (
    (p_token is not null and a.chat_token_hash = encode(sha256(convert_to(p_token, 'utf8')), 'hex')
       and (a.chat_verloopt_op is null or a.chat_verloopt_op > now()))
    or (auth.uid() is not null and a.klant_id = auth.uid())))
$$;
revoke all on function klant_mag(uuid, text) from public, anon, authenticated;

create function claim_aanvraag(p_token text) returns boolean
language plpgsql security definer set search_path = public as $$
declare v_n int;
begin
  if auth.uid() is null then raise exception 'Niet ingelogd'; end if;
  update aanvragen set klant_id = auth.uid()
   where chat_token_hash = encode(sha256(convert_to(p_token, 'utf8')), 'hex') and klant_id is null;
  get diagnostics v_n = row_count;
  return v_n > 0;
end $$;

create function klant_overzicht(p_token text default null) returns jsonb
language plpgsql stable security definer set search_path = public as $$
declare v_min int := coalesce((instelling('kwaliteit_min_reviews'))::int, 5); v_res jsonb;
begin
  select coalesce(jsonb_agg(to_jsonb(x) order by x.aangemaakt desc), '[]'::jsonb) into v_res from (
    select a.id, a.aangemaakt, a.omschrijving, a.status, a.max_klussers, a.woonplaats, t.naam as klustype, c.naam as categorie,
      (select coalesce(jsonb_agg(jsonb_build_object(
          'chat_id', ch.id, 'bedrijfsnaam', k.bedrijfsnaam, 'over', k.over, 'logo', k.logo_pad, 'website', k.website,
          'gedempt', ch.gedempt, 'gesloten', ch.gesloten, 'uitgevoerd', ch.uitgevoerd,
          'heeft_review', exists (select 1 from reviews r where r.aanvraag_id = a.id and r.klusser_id = ch.klusser_id),
          'sterren', case when s.n >= v_min then round(s.gem, 1) end, 'aantal_reviews', s.n, 'sinds', ch.aangemaakt)
          order by ch.aangemaakt), '[]'::jsonb)
         from chats ch join klussers k on k.id = ch.klusser_id
         left join lateral (select avg(r.sterren) as gem, count(*)::int as n from reviews r
                             where r.klusser_id = ch.klusser_id and r.zichtbaar) s on true
         where ch.aanvraag_id = a.id) as reacties
    from aanvragen a join klustypen t on t.id = a.klustype_id join categorieen c on c.id = t.categorie_id
    where (p_token is not null and a.chat_token_hash = encode(sha256(convert_to(p_token, 'utf8')), 'hex')
             and (a.chat_verloopt_op is null or a.chat_verloopt_op > now()))
       or (p_token is null and auth.uid() is not null and a.klant_id = auth.uid())
  ) x;
  return v_res;
end $$;

create function chat_lees(p_chat uuid, p_token text default null) returns jsonb
language plpgsql stable security definer set search_path = public as $$
declare v_ch chats%rowtype;
begin
  select * into v_ch from chats where id = p_chat;
  if not found or not klant_mag(v_ch.aanvraag_id, p_token) then raise exception 'Geen toegang'; end if;
  return (select coalesce(jsonb_agg(jsonb_build_object('afzender', b.afzender, 'tekst', b.tekst, 'tijd', b.aangemaakt) order by b.aangemaakt), '[]'::jsonb)
          from berichten b where b.chat_id = p_chat);
end $$;

create function chat_stuur_klant(p_chat uuid, p_tekst text, p_token text default null) returns void
language plpgsql security definer set search_path = public as $$
declare v_ch chats%rowtype; v_max int := coalesce((instelling('chat_max_lengte'))::int, 2000);
begin
  select * into v_ch from chats where id = p_chat;
  if not found or not klant_mag(v_ch.aanvraag_id, p_token) then raise exception 'Geen toegang'; end if;
  if v_ch.gesloten then raise exception 'Deze chat is gesloten'; end if;
  if length(trim(coalesce(p_tekst, ''))) = 0 or length(p_tekst) > v_max then raise exception 'Bericht is leeg of te lang'; end if;
  insert into berichten (chat_id, afzender, tekst) values (p_chat, 'klant', trim(p_tekst));
end $$;

create function chat_instellen(p_chat uuid, p_gedempt boolean default null, p_gesloten boolean default null, p_token text default null) returns void
language plpgsql security definer set search_path = public as $$
declare v_ch chats%rowtype;
begin
  select * into v_ch from chats where id = p_chat;
  if not found or not klant_mag(v_ch.aanvraag_id, p_token) then raise exception 'Geen toegang'; end if;
  update chats set gedempt = coalesce(p_gedempt, gedempt), gesloten = coalesce(p_gesloten, gesloten) where id = p_chat;
end $$;

create function review_plaats(p_chat uuid, p_uitgevoerd boolean, p_sterren int default null, p_toelichting text default null, p_token text default null) returns void
language plpgsql security definer set search_path = public as $$
declare v_ch chats%rowtype;
begin
  select * into v_ch from chats where id = p_chat;
  if not found or not klant_mag(v_ch.aanvraag_id, p_token) then raise exception 'Geen toegang'; end if;
  if exists (select 1 from reviews where aanvraag_id = v_ch.aanvraag_id and klusser_id = v_ch.klusser_id) then
    raise exception 'Je hebt deze klusser al beoordeeld';
  end if;
  update chats set uitgevoerd = p_uitgevoerd where id = p_chat;
  if not p_uitgevoerd then return; end if;
  if p_sterren is null or p_sterren not between 1 and 5 then raise exception 'Kies 1 tot 5 sterren'; end if;
  insert into reviews (aanvraag_id, klusser_id, uitgevoerd_door_klusser, sterren, toelichting)
  values (v_ch.aanvraag_id, v_ch.klusser_id, true, p_sterren, nullif(left(trim(coalesce(p_toelichting, '')), 1000), ''));
end $$;

-- Klusser-kant
create function klusser_chats() returns jsonb
language sql stable security definer set search_path = public as $$
  select coalesce(jsonb_agg(jsonb_build_object('chat_id', ch.id, 'aanvraag_id', a.id, 'klustype', t.naam, 'plaats', a.woonplaats,
    'klant', a.naam, 'gedempt', ch.gedempt, 'gesloten', ch.gesloten, 'uitgevoerd', ch.uitgevoerd,
    'laatste', (select max(b.aangemaakt) from berichten b where b.chat_id = ch.id),
    'aantal', (select count(*) from berichten b where b.chat_id = ch.id)) order by ch.aangemaakt desc), '[]'::jsonb)
  from chats ch join aanvragen a on a.id = ch.aanvraag_id join klustypen t on t.id = a.klustype_id
  where ch.klusser_id = auth.uid()
$$;

create function chat_lees_klusser(p_chat uuid) returns jsonb
language plpgsql stable security definer set search_path = public as $$
begin
  if not exists (select 1 from chats where id = p_chat and klusser_id = auth.uid()) then raise exception 'Geen toegang'; end if;
  return (select coalesce(jsonb_agg(jsonb_build_object('afzender', b.afzender, 'tekst', b.tekst, 'tijd', b.aangemaakt) order by b.aangemaakt), '[]'::jsonb)
          from berichten b where b.chat_id = p_chat);
end $$;

create function chat_stuur_klusser(p_chat uuid, p_tekst text) returns void
language plpgsql security definer set search_path = public as $$
declare v_ch chats%rowtype; v_max int := coalesce((instelling('chat_max_lengte'))::int, 2000);
begin
  select * into v_ch from chats where id = p_chat and klusser_id = auth.uid();
  if not found then raise exception 'Geen toegang'; end if;
  if not exists (select 1 from klussers where id = auth.uid() and status = 'goedgekeurd') then raise exception 'Account is niet actief'; end if;
  if v_ch.gesloten then raise exception 'Deze chat is gesloten'; end if;
  if v_ch.gedempt then raise exception 'De klant heeft deze chat gedempt'; end if;
  if length(trim(coalesce(p_tekst, ''))) = 0 or length(p_tekst) > v_max then raise exception 'Bericht is leeg of te lang'; end if;
  insert into berichten (chat_id, afzender, tekst) values (p_chat, 'klusser', trim(p_tekst));
end $$;

create function klusser_reviews() returns jsonb
language sql stable security definer set search_path = public as $$
  select jsonb_build_object('gemiddelde', round(avg(r.sterren), 2), 'aantal', count(*)::int,
    'minimum', coalesce((instelling('kwaliteit_min_reviews'))::int, 5),
    'drempel', coalesce((instelling('kwaliteit_drempel'))::numeric, 3.5),
    'reviews', coalesce(jsonb_agg(jsonb_build_object('sterren', r.sterren, 'toelichting', r.toelichting,
       'datum', r.aangemaakt, 'klustype', t.naam) order by r.aangemaakt desc), '[]'::jsonb))
  from reviews r join aanvragen a on a.id = r.aanvraag_id join klustypen t on t.id = a.klustype_id
  where r.klusser_id = auth.uid() and r.zichtbaar
$$;

-- Kwaliteitsdrempel (alles instelbaar via instellingen)
create function beoordeel_kwaliteit(p_klusser uuid) returns void
language plpgsql security definer set search_path = public as $$
declare v_gem numeric; v_n int; v_drempel numeric; v_min int; v_status klusser_status;
begin
  if not coalesce((instelling('kwaliteit_automatisch'))::boolean, true) then return; end if;
  select avg(sterren)::numeric, count(*)::int into v_gem, v_n from reviews where klusser_id = p_klusser and zichtbaar;
  v_drempel := coalesce((instelling('kwaliteit_drempel'))::numeric, 3.5);
  v_min := coalesce((instelling('kwaliteit_min_reviews'))::int, 5);
  select status into v_status from klussers where id = p_klusser;
  if v_status = 'goedgekeurd' and v_n >= v_min and v_gem < v_drempel then
    perform set_config('app.systeem', '1', true);
    update klussers set status = 'in_beoordeling',
      status_reden = 'Automatisch: gemiddelde ' || round(v_gem, 2) || ' onder drempel ' || v_drempel || ' (' || v_n || ' reviews)'
     where id = p_klusser;
    perform set_config('app.systeem', '', true);
    perform log_actie('systeem', null, p_klusser, 'kwaliteitsdrempel_onder', null,
      jsonb_build_object('gemiddelde', round(v_gem, 2), 'drempel', v_drempel, 'aantal', v_n));
  end if;
end $$;
revoke all on function beoordeel_kwaliteit(uuid) from public, anon, authenticated;

create function reviews_na_wijziging() returns trigger
language plpgsql security definer set search_path = public as $$
begin
  perform beoordeel_kwaliteit(coalesce(new.klusser_id, old.klusser_id));
  return null;
end $$;
revoke all on function reviews_na_wijziging() from public, anon, authenticated;
create trigger reviews_kwaliteit after insert or update or delete on reviews
  for each row execute function reviews_na_wijziging();

create function kwaliteit_hercontrole() returns int
language plpgsql security definer set search_path = public as $$
declare r record; v_n int;
begin
  if not is_admin() then raise exception 'Geen toegang'; end if;
  for r in select id from klussers where status = 'goedgekeurd' loop perform beoordeel_kwaliteit(r.id); end loop;
  select count(*)::int into v_n from klussers where status = 'in_beoordeling' and status_reden like 'Automatisch%';
  return v_n;
end $$;

-- Logboek ook voor reviews en chats
create trigger log_wijziging after insert or update or delete on reviews for each row execute function log_tabel_wijziging();
create trigger log_wijziging after insert or update or delete on chats for each row execute function log_tabel_wijziging();

-- Klusser ziet eigen zichtbare reviews
create policy eigen_lezen on reviews for select using (klusser_id = auth.uid() and zichtbaar);

-- Openbare weergave met (optionele) sterrenscore
create or replace view klusser_publiek as
  select k.id, k.bedrijfsnaam, k.over, k.logo_pad, k.website,
    case when coalesce((instelling('reviews_publiek'))::boolean, false)
              and s.n >= coalesce((instelling('kwaliteit_min_reviews'))::int, 5) then round(s.gem, 1) end as gemiddelde,
    case when coalesce((instelling('reviews_publiek'))::boolean, false)
              and s.n >= coalesce((instelling('kwaliteit_min_reviews'))::int, 5) then s.n end as aantal_reviews
  from klussers k
  left join lateral (select avg(r.sterren) as gem, count(*)::int as n from reviews r
                     where r.klusser_id = k.id and r.zichtbaar) s on true
  where k.status = 'goedgekeurd';

-- Rechten
revoke all on function claim_aanvraag(text), klusser_chats(), chat_lees_klusser(uuid), chat_stuur_klusser(uuid, text), klusser_reviews(), kwaliteit_hercontrole() from public, anon;
grant execute on function claim_aanvraag(text), klusser_chats(), chat_lees_klusser(uuid), chat_stuur_klusser(uuid, text), klusser_reviews(), kwaliteit_hercontrole() to authenticated;
revoke all on function klant_overzicht(text), chat_lees(uuid, text), chat_stuur_klant(uuid, text, text), chat_instellen(uuid, boolean, boolean, text), review_plaats(uuid, boolean, int, text, text) from public;
grant execute on function klant_overzicht(text), chat_lees(uuid, text), chat_stuur_klant(uuid, text, text), chat_instellen(uuid, boolean, boolean, text), review_plaats(uuid, boolean, int, text, text) to anon, authenticated;
