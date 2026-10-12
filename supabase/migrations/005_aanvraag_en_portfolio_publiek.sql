-- Klant plaatst een aanvraag zonder account. Alleen via deze functie (met controles), nooit rechtstreeks op de tabel.
create function maak_aanvraag(
  p_plaats uuid, p_klustype uuid, p_omschrijving text, p_periode text, p_max int,
  p_naam text, p_adres text, p_postcode text, p_email text, p_telefoon text,
  p_bel text, p_privacy boolean, p_website text default null)
returns text language plpgsql security definer set search_path = public as $$
declare v_token text; v_plaats text; v_id uuid;
begin
  if coalesce(p_website, '') <> '' then return 'ok'; end if;  -- verborgen veld (spamval): doe alsof het gelukt is
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
  if not exists (select 1 from klustypen where id = p_klustype and actief) then raise exception 'Onbekend type klus'; end if;

  v_token := replace(gen_random_uuid()::text || gen_random_uuid()::text, '-', '');
  insert into aanvragen (plaats_id, klustype_id, omschrijving, gewenste_periode, max_klussers, naam, straat_huisnr,
                         postcode, woonplaats, email, telefoon, bel_tijdsbestek, privacy_akkoord_op,
                         chat_token_hash, chat_verloopt_op)
  values (p_plaats, p_klustype, trim(p_omschrijving), p_periode, p_max, trim(p_naam), trim(p_adres),
          upper(trim(p_postcode)), v_plaats, trim(p_email), trim(p_telefoon), nullif(trim(p_bel), ''), now(),
          encode(sha256(convert_to(v_token, 'utf8')), 'hex'),
          now() + make_interval(days => coalesce((instelling('chat_link_dagen'))::int, 60)))
  returning id into v_id;
  perform log_actie('klant', null, null, 'aanvraag_geplaatst', v_id, '{}');
  return v_token;
end $$;
revoke all on function maak_aanvraag(uuid, uuid, text, text, int, text, text, text, text, text, text, boolean, text) from public;
grant execute on function maak_aanvraag(uuid, uuid, text, text, int, text, text, text, text, text, text, boolean, text) to anon, authenticated;

-- Openbare portfolio-weergave (de RLS-policy op de tabel kan klussers niet lezen voor bezoekers).
create view portfolio_publiek as
  select p.id, p.klusser_id, p.titel, p.info, p.beeld_pad, p.aangemaakt
  from portfolio_projecten p join klussers k on k.id = p.klusser_id
  where p.status = 'gepubliceerd' and k.status = 'goedgekeurd';
grant select on portfolio_publiek to anon, authenticated;
