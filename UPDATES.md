# Werken met updates na de eerste livegang

## Omgevingen
- **Productie:** eigen Supabase-project (EU) en Vercel-productie. Hier komt alleen geteste code.
- **Staging:** tweede Supabase-project en Vercel-preview met nepdata. Elke wijziging gaat hier eerst langs.
- Geheimen (service role key, Mollie, mail) staan per omgeving in Vercel en Supabase, nooit in de code.

## Database
- Na livegang bewerk je **nooit** een bestaande migratie. Elke wijziging is een nieuw bestand: `004_naam.sql`, `005_...`.
- Wijzigingen zijn bij voorkeur toevoegend: nieuwe kolom eerst optioneel, dan de code aanpassen, pas later opruimen.
- Maak voor elke migratie op productie eerst een back-up (Supabase point-in-time recovery aanzetten).
- Terugdraaien doe je met een nieuwe migratie die het herstelt, niet door te bewerken.

## Code en release
1. Werk in een tak, open een pull request, Vercel maakt automatisch een preview.
2. Test de preview op staging met de checklist hieronder.
3. Merge naar `main` zet de wijziging live. Bij problemen: in Vercel terug naar de vorige deployment (direct).
4. Verhoog `app_versie` in de instellingen en voeg een regel toe aan `CHANGELOG.md`.
5. Nieuwe functies eerst achter een instelling (feature flag) in de tabel `instellingen`, dan aanzetten in admin.

## Smoke-test voor elke release
Klant plaatst een aanvraag, klusser aanmelden en goedkeuren, profiel en werkgebied opslaan, klus ontgrendelen met en zonder betaalmodel, bevestigingsmail komt aan, logboek-export werkt.

## Logboek en bewaren
- Alle acties van klussers komen automatisch in `logboek`, inclusief de app-versie. Beheerders exporteren het met `select logboek_export(null)` (of per klusser) en slaan de uitkomst op als .txt. De app krijgt hiervoor een downloadknop in admin.
- Het logboek bevat persoonsgegevens: bepaal met een jurist de bewaartermijn en leg die vast in de privacyverklaring.

## Onderhoud
Foutmonitoring (Sentry), uptime-monitor, Dependabot voor pakketupdates en elk kwartaal een restore-test van de back-up.

## Migratielijst (stand testproject)
001 basis, 002 voorbereiding fase 2/3, 003 logboek, 004 beveiliging, 005 aanvraag indienen, 005 aanvraag en portfolio publiek, 006 chat/reviews/kwaliteitsdrempel, 007 mailwachtrij (tabel staat klaar; e-mail versturen is nog uitgeschakeld), 008 blog en FAQ, 009 extra artikelen, 010 rechten aangescherpt en view klusser_expertise_publiek, 011 vaste search_path voor blog_tijdstempel, 012 opslag van afbeeldingen in Supabase Storage (bucket afbeeldingen). Nummers worden niet hergebruikt: nieuwe migraties beginnen bij 013.

Let op: sinds 010 krijgen nieuwe tabellen geen automatische rechten voor anon of authenticated. Geef in de migratie van een nieuwe tabel expliciet `grant select/insert/update/delete` en zet RLS aan. De oude functie aanvraag_indienen is alleen onbereikbaar gemaakt (execute ingetrokken), niet verwijderd.
