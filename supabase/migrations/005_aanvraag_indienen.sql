-- Klanten (zonder account) plaatsen een aanvraag via deze functie; de tabel zelf blijft dicht.
create function aanvraag_indienen(
  p_plaats uuid, p_klustype uuid, p_omschrijving text, p_periode text, p_max int,
  p_naam text, p_adres text, p_postcode text, p_email text, p_telefoon text,
  p_bel text, p_privacy boolean, p_website text default null)
returns text language plpgsql security definer set search_path = public, extensions as $$
declare v_token text; v_woonplaats text;
begin
  if p_website is not null and p_website <> '' then return 'ok'; end if;  -- honeypot tegen bots
  if not coalesce(p_privacy, false) then raise exception 'Ga akkoord met de privacyverklaring'; end if;
  if length(coalesce(p_omschrijving, '')) < 10 or length(p_omschrijving) > 3000 then
    raise exception 'Omschrijving moet tussen 10 en 3000 tekens zijn'; end if;
  if length(coalesce(p_naam, '')) < 2 or length(coalesce(p_email, '')) < 5 or length(coalesce(p_telefoon, '')) < 6 then
    raise exception 'Vul naam, e-mail en telefoon in'; end if;
  select naam into v_woonplaats from plaatsen where id = p_plaats and actief;
  if v_woonplaats is null or not exists (select 1 from klustypen where id = p_klustype and actief) then
    raise exception 'Ongeldige plaats of klustype'; end if;
  if (select count(*) from aanvragen where lower(email) = lower(p_email) and aangemaakt > now() - interval '1 day') >= 5 then
    raise exception 'Te veel aanvragen vanaf dit e-mailadres, probeer het later opnieuw'; end if;

  v_token := replace(gen_random_uuid()::text || gen_random_uuid()::text, '-', '');
  insert into aanvragen (plaats_id, klustype_id, omschrijving, gewenste_periode, max_klussers, naam, straat_huisnr,
    postcode, woonplaats, email, telefoon, bel_tijdsbestek, privacy_akkoord_op, chat_token_hash, chat_verloopt_op)
  values (p_plaats, p_klustype, trim(p_omschrijving), p_periode, coalesce(p_max, 3), trim(p_naam), trim(p_adres),
    upper(trim(p_postcode)), v_woonplaats, trim(p_email), trim(p_telefoon), p_bel, now(),
    encode(digest(v_token, 'sha256'), 'hex'),
    now() + make_interval(days => coalesce((instelling('chat_link_dagen'))::int, 60)));
  return v_token;
end $$;
revoke all on function aanvraag_indienen(uuid, uuid, text, text, int, text, text, text, text, text, text, boolean, text) from public;
grant execute on function aanvraag_indienen(uuid, uuid, text, text, int, text, text, text, text, text, text, boolean, text) to anon, authenticated;
