-- Klusmaat: blog (doe-het-zelf handleidingen) en FAQ. Beheerbaar in admin.

create table blog_artikelen (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique check (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'),
  titel text not null,
  rubriek text,
  samenvatting text,
  inhoud text not null,
  beeld_pad text,
  status text not null default 'concept' check (status in ('concept','gepubliceerd')),
  gepubliceerd_op timestamptz,
  aangemaakt timestamptz not null default now(),
  bijgewerkt timestamptz not null default now()
);
create index on blog_artikelen (status, gepubliceerd_op desc);

create table faq_items (
  id uuid primary key default gen_random_uuid(),
  vraag text not null,
  antwoord text not null,
  volgorde int not null default 100,
  actief boolean not null default true
);

alter table blog_artikelen enable row level security;
alter table faq_items enable row level security;

create policy blog_publiek on blog_artikelen for select using (status = 'gepubliceerd');
create policy blog_admin on blog_artikelen for all using (is_admin()) with check (is_admin());
create policy faq_publiek on faq_items for select using (actief);
create policy faq_admin on faq_items for all using (is_admin()) with check (is_admin());

revoke all on blog_artikelen, faq_items from anon, authenticated;
grant select on blog_artikelen, faq_items to anon, authenticated;
grant insert, update, delete on blog_artikelen, faq_items to authenticated;  -- RLS beperkt dit tot beheerders

create function blog_tijdstempel() returns trigger language plpgsql as $$
begin
  new.bijgewerkt := now();
  if new.status = 'gepubliceerd' and new.gepubliceerd_op is null then new.gepubliceerd_op := now(); end if;
  return new;
end $$;
create trigger blog_tijd before insert or update on blog_artikelen for each row execute function blog_tijdstempel();

-- FAQ
insert into faq_items (vraag, antwoord, volgorde) values
('Wat kost het om een klus te plaatsen?', 'Niets. Een klus plaatsen is gratis en je hebt er geen account voor nodig. Je betaalt pas als je met een klusser een afspraak maakt, en dan alleen voor het werk zelf. De prijs spreek je rechtstreeks met de klusser af.', 10),
('Hoe werkt het precies?', 'Je kiest je plaats en het soort klus, beschrijft kort wat er moet gebeuren en laat je gegevens achter. Klussers uit jouw omgeving zien jouw klus en kunnen aangeven dat ze willen reageren. Je ziet wie dat doet en kunt via een veilige chat vragen stellen voordat je iets afspreekt.', 20),
('Hoeveel klussers nemen contact met me op?', 'Dat bepaal je zelf. Je kiest bij het aanvragen of maximaal 3 tot 8 klussers mogen reageren. Zo krijg je genoeg keuze zonder dat je telefoon niet meer stilstaat.', 30),
('Ben ik verplicht om met een klusser in zee te gaan?', 'Nee. Je bent nergens toe verplicht. Je vergelijkt de reacties, stelt vragen in de chat en kiest alleen een klusser als het voor jou goed voelt.', 40),
('Wie ziet mijn gegevens?', 'Klussers zien eerst alleen de plaats, het soort klus en jouw omschrijving. Je naam, adres, telefoonnummer en e-mailadres zijn pas zichtbaar voor klussers die op jouw klus reageren, en nooit voor meer klussers dan het aantal dat jij hebt gekozen.', 50),
('Moet ik een account aanmaken?', 'Nee. Na het plaatsen van je klus krijg je een persoonlijke link waarmee je reacties en chats terugvindt. Wil je liever inloggen, dan kun je daarna een account met eigen wachtwoord maken.', 60),
('Zijn de klussers gecontroleerd?', 'Elke klusser meldt zich aan en wordt door ons handmatig goedgekeurd voordat hij of zij klussen kan zien. Na afloop kun je een beoordeling achterlaten. Klussers die structureel te laag scoren, worden automatisch door ons beoordeeld en kunnen tijdelijk worden uitgesloten.', 70),
('In welke plaatsen werkt Klusmaat?', 'We starten in Roosendaal en Bergen op Zoom en de plaatsen binnen ongeveer 10 kilometer daaromheen. Staat jouw plaats er niet bij, dan werken we daar nog niet. Het werkgebied breiden we stap voor stap uit.', 80),
('Wat als ik geen reactie krijg?', 'Dan hoef je niets te doen en kost het je niets. Soms duurt het even voordat klussers reageren. Je kunt altijd een nieuwe klus plaatsen of de omschrijving duidelijker maken, bijvoorbeeld met afmetingen en materialen.', 90),
('Kan ik een klusser beoordelen?', 'Ja. Nadat de klus is gedaan, kun je via jouw persoonlijke link een beoordeling met 1 tot 5 sterren achterlaten. Zo help je andere klanten en houden we de kwaliteit hoog.', 100),
('Ik ben klusser. Hoe meld ik me aan?', 'Ga naar het tabblad Klusser en maak een account. Na goedkeuring vul je je bedrijfsprofiel, werkgebied en expertises in en zie je klussen in jouw omgeving.', 110);

-- Blogartikelen (startset, bewerkbaar in admin)
insert into blog_artikelen (slug, titel, rubriek, samenvatting, inhoud, status) values
('tv-ophangen-aan-de-muur', 'Een tv veilig aan de muur ophangen', 'Montage & meubels',
 'Stap voor stap een tv ophangen: de juiste beugel, de juiste plug en hoe je zeker weet dat hij recht en stevig hangt.',
$b$Een tv aan de muur geeft ruimte en ziet er strak uit. Met de juiste spullen en wat geduld doe je het zelf in een uur.

## Wat heb je nodig
- Een geschikte tv-beugel (let op de VESA-maat van je tv en het maximale gewicht)
- Boormachine met boor voor steen of hout
- Pluggen en schroeven die passen bij je muur
- Waterpas, potlood en een leiding- en metaaldetector

## Stap voor stap
1. Kies de hoogte. Het midden van het scherm hangt ongeveer op ooghoogte als je zit.
2. Controleer de muur. Beton en baksteen zijn geschikt. Bij een holle gipswand moet je in de stijlen schroeven of speciale zware-lastpluggen gebruiken.
3. Zoek eerst naar leidingen en kabels met een detector. Boor nooit zomaar op goed geluk.
4. Houd de wandplaat tegen de muur, zet hem waterpas en teken de gaatjes aan.
5. Boor de gaten, sla de pluggen erin en schroef de plaat stevig vast.
6. Bevestig de beugelarmen aan de achterkant van de tv en hang de tv op de plaat.
7. Sluit de kabels aan voordat je de tv ophangt, want daarna kom je er moeilijker bij.

## Goed om te weten
Heb je een zware tv, een gipswand of kun je niet zeker weten waar leidingen lopen? Laat het dan door een klusser doen. Dat voorkomt schade aan de muur en aan je tv.$b$, 'gepubliceerd'),
('lekkende-kraan-repareren', 'Een lekkende kraan zelf repareren', 'Reparaties & onderhoud',
 'Een druppelende kraan verspilt water en geld. In de meeste gevallen is het een kleine reparatie met een nieuw rubber of een nieuwe cartridge.',
$b$Een kraan die blijft druppelen is irritant, maar vaak snel op te lossen. Meestal is een versleten rubber of cartridge de oorzaak.

## Voorbereiding
- Draai de hoofdkraan of de afsluiter onder de wastafel dicht.
- Open de kraan om de restdruk eruit te laten lopen.
- Leg een doek in de wasbak, zodat er niets in het afvoerputje valt.

## Een kraan met een kruisgreep of draaiknop
1. Haal het dopje van de knop en draai de schroef los.
2. Trek de knop eraf en schroef het binnenwerk los met een steeksleutel.
3. Vervang het rubber of het keramische schijfje door een nieuw exemplaar van dezelfde maat. Neem het oude onderdeel mee naar de bouwmarkt.
4. Zet alles in omgekeerde volgorde terug en draai de water weer open.

## Een mengkraan met één hendel
Hier zit een cartridge in. Haal de hendel eraf, draai de ring los en trek de cartridge eruit. Koop een identiek exemplaar en plaats die terug.

## Wanneer een klusser of installateur
Lekt het bij de afsluiter, onder de kraan of in de muur, of zit er kalkaanslag die niet loskomt? Schakel dan een vakman in, zeker als je zelf geen water kunt afsluiten.$b$, 'gepubliceerd'),
('kitnaad-badkamer-vervangen', 'Kitnaden in de badkamer vervangen', 'Reparaties & onderhoud',
 'Vervuilde of loslatende kit in de badkamer? Zo verwijder je oude kit en breng je een strakke, waterdichte naad aan.',
$b$Oude kit kan gaan schimmelen of loslaten, waardoor water achter de tegels kan komen. Vervangen is een klus van een paar uur.

## Wat heb je nodig
- Kitmes of kitverwijderaar
- Sanitairkit (schimmelwerend)
- Kitpistool, afplaktape en een kitspatel of zeepsop
- Ontvetter of alcohol en een doek

## Werkwijze
1. Snijd de oude kit weg met een kitmes en trek de repen eruit. Haal ook alle resten weg.
2. Maak de naad schoon en ontvet. Laat alles volledig drogen, ook de sponning.
3. Plak aan weerszijden van de naad afplaktape voor een strakke lijn.
4. Snijd de kitspuit schuin af en spuit de naad in één beweging vol.
5. Strijk de kit glad met een spatel of een vinger met zeepsop.
6. Verwijder de tape direct, voordat de kit uithardt.
7. Laat de kit 24 uur drogen voordat je gaat douchen.

## Tip
Werk niet bij koude temperaturen en zorg voor ventilatie. Een natte of vuile ondergrond is de meest gemaakte fout.$b$, 'gepubliceerd'),
('binnenmuur-schilderen', 'Een binnenmuur schilderen in vijf stappen', 'Schilderen & behangen',
 'Een mooi resultaat begint met een goede voorbereiding. Zo schilder je een muur gelijkmatig en zonder streken.',
$b$Een frisse kleur op de muur verandert een kamer direct. Het resultaat staat of valt met de voorbereiding.

## Stap 1: voorbereiden
Haal meubels weg of dek ze af. Leg stucloper op de vloer en plak randen, stopcontacten en plinten af met schildertape.

## Stap 2: schoonmaken en repareren
Neem de muur af met lauw water en een beetje allesreiniger. Vul gaatjes en scheurtjes met vulmiddel, laat het drogen en schuur het glad.

## Stap 3: voorstrijken
Op een kale of gerepareerde muur eerst een grondverf of voorstrijk aanbrengen. Dat zorgt voor een gelijkmatige dekking en bespaart verf.

## Stap 4: schilderen
1. Begin met een kwast langs de randen en hoeken.
2. Rol de rest met een verfroller in banen van boven naar beneden.
3. Werk nat-in-nat, zodat je geen overgangen ziet.

## Stap 5: afwerken
Laat de eerste laag volledig drogen en breng een tweede laag aan. Haal de tape weg zolang de verf nog iets nat is.

## Wanneer inschakelen
Hoge plafonds, vochtplekken of grote oppervlakken? Een schilder werkt sneller en geeft een strakker resultaat.$b$, 'gepubliceerd');
