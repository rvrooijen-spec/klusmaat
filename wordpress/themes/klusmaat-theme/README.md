# Klusmaat-thema

Blokthema in de stijl van het huisstijlboek (richting D, Werkbon). Alle kleuren, lettertypen en maten staan in `theme.json` en `assets/css/klusmaat.css`.

## Voorbeeld zonder WordPress

    python3 tools/preview.py /tmp/voorbeeld

## Updaten

1. Pas het thema aan en commit.
2. Maak een tag: `git tag theme-v0.2.0 && git push --tags`.
3. De workflow `theme-release` maakt `klusmaat-theme.zip` en zet die in een GitHub-release.
4. In WordPress verschijnt bij Weergave > Thema's "Nu bijwerken". Is de repository privé, zet dan in `wp-config.php` een alleen-lezen token: `define('KLUSMAAT_GITHUB_TOKEN', '...');`.

Eerste installatie: upload `klusmaat-theme.zip` (Weergave > Thema's > Nieuw > Thema uploaden).

## Status

Niet getest in een echte WordPress-installatie (daar is in de bouwomgeving geen toegang toe). Het statische voorbeeld is wel in Chromium bekeken. Lettertypen staan als woff (niet woff2) omdat de omzetting naar woff2 hier niet beschikbaar was.
