# SEO-plan Klusmaat

Doel: gevonden worden op "schilder Roosendaal", "loodgieter Bergen op Zoom" en op doe-het-zelf-vragen, zonder trucs die Google straft. Alleen Nederland, regio Roosendaal en Bergen op Zoom plus ongeveer 10 km.

## 1. Wat Google wil zien
- Elke pagina beantwoordt één zoekvraag en heeft eigen, echte inhoud.
- Plaatspagina's mogen alleen in de index als er echt iets te zeggen valt: eigen tekst en minstens één goedgekeurde klusser. Honderden bijna-gelijke pagina's met alleen een andere plaatsnaam zijn "doorway pages" en "scaled content abuse". Dat kan de hele site schaden.
- Snelle, schone, mobiele pagina's. Het thema is al licht: eigen lettertypes, geen zware scripts.
- Geen rich results beloven die er niet meer zijn: FAQ-resultaten zijn sinds mei 2026 weg en HowTo-resultaten staan al jaren niet meer in mobiel. De HowTo-markup van de blog blijft staan (geen kwaad), maar we rekenen er niet op.

## 2. URL-structuur
| Pagina | URL | Index? |
|---|---|---|
| Home | / | ja |
| Vakgebied | /schilder/ , /loodgieter/ , /tuinman/ , /elektricien/ , /monteur/ , /klusser/ | ja, bij genoeg eigen tekst |
| Vakgebied in plaats | /schilder/roosendaal/ enzovoort (6 x 10 = 60 mogelijk) | alleen als: genoeg unieke tekst én minstens 1 goedgekeurde klusser |
| Blog | /blog/ en /blog/<artikel>/ | ja |
| Klusser worden | /klusser-worden/ | ja |
| Hoe het werkt, privacy, voorwaarden, contact | eigen korte URL | ja |
| Werkbon, Mijn klus, Inloggen, Dashboard | /werkbon/ enz. | nee (noindex, niet in sitemap) |

Regels: korte woorden zonder jaartal of datum, kleine letters, streepjes, altijd met slash aan het eind, geen parameters in indexeerbare URL's (de blogfilters met ?soort= staan op noindex).

## 3. Zoekintenties en pagina's
- "schilder Roosendaal": vakgebied-in-plaats-pagina met uitleg, soorten klussen en een knop naar de werkbon.
- "lekkage hulp", "kraan vervangen zelf": blog (stappenplan of tip) met onderaan een link naar de vakgebied-pagina.
- "klusser worden Bergen op Zoom": /klusser-worden/.
Blogartikelen verwijzen naar de bijbehorende vakgebied-pagina en omgekeerd. Zo ontstaat een nette interne structuur zonder dubbele inhoud.

## 4. Technisch
- Eén titel (H1) per pagina, titel onder 60 tekens, beschrijving onder 155, canonieke URL, broodkruimels met BreadcrumbList.
- Schema: BreadcrumbList, WebPage, Service met areaServed (plaats) en BlogPosting/HowTo bij de blog. Geen sterren of beoordelingen die we niet hebben.
- Sitemap via WordPress zelf (wp-sitemap.xml), zonder gebruikers, termen en privépagina's, met de lokale pagina's die de index in mogen.
- robots.txt blijft standaard WordPress; privépagina's krijgen noindex via de meta-tag, niet via robots.txt (anders kan Google de noindex niet lezen).
- Taal nl-NL, één lang-attribuut (gecontroleerd).
- Later: Search Console koppelen, cookieloze statistiek (bijvoorbeeld Plausible of Simple Analytics), Core Web Vitals meten na livegang.

## 5. Zelf bouwen of een bestaande plugin
| Onderdeel | Keuze | Waarom |
|---|---|---|
| Titels, beschrijvingen, Open Graph, algemene sitemap | Bestaande plugin: Rank Math (gratis), alternatief SEOPress of Slim SEO | Dit is gewone standaard-SEO; die hoeven we niet na te bouwen. Maar één plugin, nooit twee. wordpress.org is hier geblokkeerd, dus installeren doet Co op de echte site. |
| Lokale pagina's, indexregels, interne links, schema van onze eigen pagina's | Zelf bouwen: plugin klusmaat-lokaal | Hangt aan onze eigen gegevens (plaatsen, soorten, klussers). Geen plugin kent die. |
| Blogmarkup | In klusmaat-blog | Hoort bij de artikelen |

klusmaat-lokaal werkt ook zonder SEO-plugin en regelt voor zijn eigen pagina's zelf titel, beschrijving, canonical, robots en schema. Gewone pagina's en blogartikelen laat het aan de SEO-plugin over.

## 6. Inhoudsplan
1. Start met de 5 vakgebieden + "klusser" en de 10 plaatsen (Roosendaal, Bergen op Zoom, Halsteren, Wouw, Heerle, Hoogerheide, Lepelstraat, Moerstraten, Nispen, Ossendrecht).
2. Schrijf eerst Roosendaal en Bergen op Zoom echt goed (minimaal 150 eigen woorden per combinatie: wat voor klussen komen daar vaak voor, hoe snel is er reactie, welke wijken). Pas daarna de dorpen.
3. Een combinatie gaat pas de index in als er een goedgekeurde klusser is die die plaats en dat vak heeft gekozen. Zo beloof je niets wat je niet kunt leveren.
4. Blog: begin met de 10 meest gezochte doe-het-zelf-klussen per vakgebied, met eigen foto's.

## 7. Meten
- Zichtbaarheid: vertoningen en klikken per pagina in Search Console.
- Ingevulde werkbonnen per pagina (bron van de bezoeker, zonder cookies).
- Aantal goedgekeurde klussers per plaats en vak (de echte beperking).
- Indexeerbare lokale pagina's: dit aantal staat in het beheerscherm Klusmaat > Lokaal.

## 8. Risico's
- Te veel dunne pagina's: opgevangen door de indexregel.
- Valse beloftes ("altijd binnen 1 uur"): niet gebruiken tot het klopt.
- Dubbele SEO-plugins: nooit twee tegelijk.
- Privacy: geen gegevens van klanten of klussers op indexeerbare pagina's.

## 9. Volgorde
1. Plugin klusmaat-lokaal (gebouwd): pagina's, regels, sitemap, schema.
2. Co schrijft of laat schrijven: teksten Roosendaal en Bergen op Zoom.
3. Livegang, Search Console, sitemap indienen.
4. Rank Math installeren en instellen (sitemap van Rank Math uit als WordPress' eigen sitemap blijft, of andersom: één sitemap kiezen).
5. Maandelijks kijken welke pagina's vertoningen krijgen en uitbreiden.
