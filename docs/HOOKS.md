# Haken van klusmaat-core voor child-plugins

Child-plugins (klusmaat-blog, klusmaat-mail, klusmaat-lokaal) hangen aan de core met `Requires Plugins: klusmaat-core`. De core kent hen niet; de afhankelijkheid loopt één kant op.

| Haak | Soort | Argumenten |
|---|---|---|
| klusmaat_klus_geplaatst | actie | $id, $token, $email (de enige plek waar het token in leesbare vorm bestaat) |
| klusmaat_klusser_aangemeld | actie | $userId |
| klusmaat_klusser_status_gewijzigd | actie | $userId, 'goedgekeurd'/'afgekeurd'/'geblokkeerd', $beheerderId |
| klusmaat_klus_opgepakt | actie | $klusId, $userId, $resultaat (kosten, saldo) |
| klusmaat_klus_gesloten | actie | $klusId, 'klant'/'beheer' |
| klusmaat_controle_mislukt | actie | $meldingen |
| klusmaat_dagelijks | cron | - |
| klusmaat_dashboard_blokken | filter | $html, $userId; geeft html terug |

Bruikbare hulpen: `Klusmaat\Core\Wp\Plugin::url($sleutel, $args)`, `Plugin::kern()` (aanvragen, credits, klussers), `WpCatalogus::plaatsen()/soorten()/categorieen()/soortVan()/metKinderen()`.
Contactgegevens van klanten gaan nooit naar een klusser buiten het dashboard.
