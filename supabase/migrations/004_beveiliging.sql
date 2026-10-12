-- Na controle met de Supabase-adviezen: onnodige API-toegang dichtgezet.
revoke all on function klusser_voor_update(), klusser_na_goedkeuring(), log_tabel_wijziging() from public, anon, authenticated;

-- instelling() draait met de rechten van de aanroeper: anon ziet alleen publieke instellingen.
create or replace function instelling(p_sleutel text) returns jsonb
language sql stable security invoker set search_path = public as
$$ select waarde from instellingen where sleutel = p_sleutel $$;

alter function logboek_onveranderlijk() set search_path = public;
