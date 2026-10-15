<?php
declare(strict_types=1);

namespace Klusmaat\Core\Wp;

/** Koppelt Klusmaat aan de privacytools van WordPress: inzage en verwijdering op verzoek. */
final class Privacy
{
    public static function haken(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'erasers']);
        add_action('admin_init', [self::class, 'beleidstekst']);
    }

    /** @param array<string,mixed> $lijst */
    public static function exporters(array $lijst): array
    {
        $lijst['klusmaat'] = ['exporter_friendly_name' => 'Klusmaat werkbonnen', 'callback' => [self::class, 'exporteer']];
        return $lijst;
    }

    /** @param array<string,mixed> $lijst */
    public static function erasers(array $lijst): array
    {
        $lijst['klusmaat'] = ['eraser_friendly_name' => 'Klusmaat werkbonnen', 'callback' => [self::class, 'wis']];
        return $lijst;
    }

    /** @return array{data:list<array<string,mixed>>, done:bool} */
    public static function exporteer(string $email): array
    {
        $data = [];
        foreach (Plugin::kern()->aanvragen->gegevensVanEmail($email) as $r) {
            $data[] = [
                'group_id' => 'klusmaat-werkbon', 'group_label' => 'Werkbonnen', 'item_id' => 'werkbon-' . $r['id'],
                'data' => [
                    ['name' => 'Soort klus', 'value' => WpCatalogus::naam((int) $r['klustype_id'], WpCatalogus::KLUSTYPE)],
                    ['name' => 'Plaats', 'value' => WpCatalogus::naam((int) $r['plaats_id'], WpCatalogus::PLAATS)],
                    ['name' => 'Omschrijving', 'value' => (string) $r['omschrijving']],
                    ['name' => 'Naam', 'value' => (string) $r['naam']],
                    ['name' => 'Telefoon', 'value' => (string) $r['telefoon']],
                    ['name' => 'Adres', 'value' => $r['adres'] . ', ' . $r['postcode']],
                    ['name' => 'Geplaatst op', 'value' => Weergave::datum((string) $r['aangemaakt_op'])],
                ],
            ];
        }
        return ['data' => $data, 'done' => true];
    }

    /** @return array{items_removed:bool, items_retained:bool, messages:list<string>, done:bool} */
    public static function wis(string $email): array
    {
        $n = Plugin::kern()->aanvragen->wisGegevensVanEmail($email, get_current_user_id());
        return ['items_removed' => $n > 0, 'items_retained' => false, 'messages' => $n > 0 ? [$n . ' werkbon(nen) gewist en gesloten.'] : [], 'done' => true];
    }

    public static function beleidstekst(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        wp_add_privacy_policy_content('Klusmaat', wp_kses_post(
            '<h2>Werkbonnen</h2><p>Als je een klus plaatst, bewaren we je naam, e-mailadres, telefoonnummer, adres en de omschrijving van de klus. Klussers zien je naam, telefoon, e-mail en adres pas als ze je klus oppakken. Gesloten klussen worden na de ingestelde bewaartermijn gewist.</p><h2>Klussers</h2><p>Als klusser bewaren we je naam, e-mailadres, telefoonnummer, werkgebied, soorten klussen en je credits.</p>'
        ));
    }
}
