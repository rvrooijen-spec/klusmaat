-- Klusmaat: startdata. Alles hierin is later aan te passen via admin.

insert into instellingen (sleutel, waarde, omschrijving, publiek) values
 ('betaalmodel_aan',        'false', 'Hoofdschakelaar betaalmodel (uit = alles gratis zichtbaar)', true),
 ('toon_credit_saldo',      'true',  'Credit-saldo tonen aan klussers', true),
 ('toon_credits_kopen',     'true',  'Knop "Credits kopen" tonen', true),
 ('toon_prijs_per_klus',    'true',  'Prijs per klus tonen', true),
 ('welkomstcredits',        '6',     'Gratis credits bij goedkeuring', false),
 ('credits_per_klus',       '1',     'Standaard credits per ontgrendelde klus', true),
 ('prijs_per_credit_cent',  '250',   'Prijs per credit in eurocent', true),
 ('min_klussers',           '3',     'Minimum aantal klussers per klus', true),
 ('max_klussers',           '8',     'Maximum aantal klussers per klus', true),
 ('chat_link_dagen',        '60',    'Geldigheid unieke chatlink in dagen', false),
 ('mail_pauze_minuten',     '15',    'Minimale pauze tussen e-mails per chat', false),
 ('reviews_publiek',        'false', 'Reviews openbaar zichtbaar', true),
 ('kwaliteit_drempel',      '3.5',   'Gemiddelde score waaronder klusser in beoordeling gaat', false),
 ('kwaliteit_min_reviews',  '5',     'Aantal reviews voordat het gemiddelde meetelt', false),
 ('spotlight_aan',          'false', 'Spotlight (betaalde zichtbaarheid) actief', true);

-- Plaatsen: controleer de afstand (max. 10 km van Roosendaal of Bergen op Zoom) voordat je live gaat.
insert into plaatsen (naam, slug, volgorde) values
 ('Roosendaal','roosendaal',1), ('Bergen op Zoom','bergen-op-zoom',2),
 ('Halsteren','halsteren',10), ('Heerle','heerle',10), ('Huijbergen','huijbergen',10),
 ('Kruisland','kruisland',10), ('Lepelstraat','lepelstraat',10), ('Moerstraten','moerstraten',10),
 ('Nispen','nispen',10), ('Ossendrecht','ossendrecht',10), ('Sint Willebrord','sint-willebrord',10),
 ('Wouw','wouw',10);

do $$
declare
  c_id uuid; d jsonb;
  data jsonb := '[
   {"n":"Schilderen & behangen","s":"schilderen-behangen","top":true,"t":["Binnen schilderen","Buiten schilderen","Behang","Kozijnen","Stucwerk"]},
   {"n":"Tuin & buiten","s":"tuin-buiten","top":true,"t":["Tuinonderhoud","Bestrating","Schutting","Snoeien","Gazon"]},
   {"n":"Montage & meubels","s":"montage-meubels","top":true,"t":["IKEA-meubels","Kasten","Keukens","TV ophangen","Vloeren"]},
   {"n":"Reparaties & onderhoud","s":"reparaties-onderhoud","top":true,"t":["Lekkage","Deuren en ramen","Tegelwerk","Kitwerk","Kleine reparaties"]},
   {"n":"Verhuizen & afvoeren","s":"verhuizen-afvoeren","top":true,"t":["Verhuishulp","Bouwafval","Opruimen","Zolder en schuur leeghalen"]},
   {"n":"Elektra","s":"elektra","top":false,"t":["Stopcontacten en schakelaars","Verlichting","Groepenkast"]},
   {"n":"Sanitair","s":"sanitair","top":false,"t":["Kraan of toilet","Verstopping","Badkamer"]},
   {"n":"Isolatie","s":"isolatie","top":false,"t":["Spouwmuur","Vloer","Dak"]},
   {"n":"Dak","s":"dak","top":false,"t":["Dakgoot","Dakreparatie","Dakkapel"]}]';
  i int := 0; j int; ty text;
begin
  for d in select * from jsonb_array_elements(data) loop
    i := i + 1;
    insert into categorieen (naam, slug, is_top5, volgorde)
    values (d->>'n', d->>'s', (d->>'top')::boolean, i) returning id into c_id;
    j := 0;
    for ty in select jsonb_array_elements_text(d->'t') loop
      j := j + 1;
      insert into klustypen (categorie_id, naam, slug, volgorde)
      values (c_id, ty, lower(regexp_replace(ty, '[^a-zA-Z0-9]+', '-', 'g')), j);
    end loop;
  end loop;
end $$;
