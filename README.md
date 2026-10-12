# Klusmaat (werknaam)

Fase 1, stap 1: database. De Next.js-app volgt in stap 2.

## Opzetten
1. Maak een Supabase-project aan in een EU-regio.
2. Voer in de SQL-editor in volgorde uit: `supabase/migrations/001_basis.sql`, `002_voorbereid_fase2_3.sql`, `supabase/seed.sql`.
3. Maak jezelf beheerder: registreer een account en voer uit
   `insert into beheerders (user_id) select id from auth.users where email = 'jouw@mail.nl';`
4. Maak twee private storage-buckets: `logos` en `klusfotos`. Foto's van klanten worden alleen via signed URLs getoond, na een geslaagde ontgrendeling.

## Uitgangspunten
- Klanten hebben geen account. Aanvragen worden alleen door de server aangemaakt (service role) na spamcontrole, de chattoken wordt als hash opgeslagen.
- Klussers zien enkel stad, categorie, type en omschrijving (`klussen_overzicht()`). Contactgegevens komen pas na `ontgrendel_aanvraag()`, die in één transactie het maximum aantal klussers en het saldo bewaakt.
- Credits zijn een grootboek (`credit_transacties`). Het saldo is de som, nooit een los veld.
- Betaalmodel uit (`betaalmodel_aan = false`): ontgrendelen kost 0 credits.
- Niet getest: er was geen Postgres beschikbaar om dit uit te voeren. Draai de SQL eerst in een leeg testproject.

## Migraties in volgorde
`001_basis.sql`, `002_voorbereid_fase2_3.sql`, `003_logboek.sql`, `004_beveiliging.sql`, `005_aanvraag_indienen.sql`, `005_aanvraag_en_portfolio_publiek.sql`, `006_chat_reviews_kwaliteit.sql`, `007_mailwachtrij.sql`, `008_blog_faq.sql`, `009_meer_artikelen.sql`, `010_beveiliging_rechten_expertise.sql`, `011_search_path_blog.sql`, `012_storage_afbeeldingen.sql`, daarna `seed.sql`. Zie `UPDATES.md` voor werken na livegang.

## Testomgeving
Supabase-project `klusmaat-test` (eu-west-1) bevat 001 t/m 012 en de seed (de twee 005-bestanden zijn beide toegepast, in de volgorde van de lijst). De twee views `klusser_publiek` en `klusser_plaatsen_publiek` draaien bewust met eigenaarsrechten: ze tonen alleen openbare kolommen van goedgekeurde klussers.

## Uitrol via GitHub en Vercel
De website staat in `public/`; Vercel publiceert alleen die map (`vercel.json`). De map `supabase/` met de migraties wordt dus niet openbaar geserveerd.
Elke push naar `main` rolt automatisch uit. Databasewijzigingen komen als nieuwe migratie in `supabase/migrations/` (volgende is 013) en worden apart in Supabase toegepast.
