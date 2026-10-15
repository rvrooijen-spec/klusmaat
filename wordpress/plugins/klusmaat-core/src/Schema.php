<?php
declare(strict_types=1);

namespace Klusmaat\Core;

/**
 * Tabellen van Klusmaat. Twee varianten van dezelfde structuur:
 * - mysql(): voor WordPress (via dbDelta, dus met de opmaak die dbDelta verwacht)
 * - sqlite(): voor de tests
 * Wijzig je de een, wijzig dan ook de ander. Een test controleert de kolomnamen.
 */
final class Schema
{
    /** Kolommen per tabel; de tests vergelijken beide varianten hiermee. */
    public const KOLOMMEN = [
        'aanvragen' => ['id', 'token_hash', 'klustype_id', 'plaats_id', 'omschrijving', 'gewenst_moment', 'naam', 'email', 'telefoon', 'adres', 'postcode', 'status', 'max_klussers', 'opgepakt_aantal', 'aangemaakt_op', 'gesloten_op', 'geanonimiseerd_op'],
        'klussers' => ['user_id', 'status', 'saldo', 'aangemaakt_op'],
        'credit_boekingen' => ['id', 'user_id', 'delta', 'saldo_na', 'reden', 'toelichting', 'aanvraag_id', 'actor_id', 'aangemaakt_op'],
        'ontgrendelingen' => ['id', 'aanvraag_id', 'user_id', 'kosten', 'aangemaakt_op'],
        'logboek' => ['id', 'tijd', 'actor_type', 'actor_id', 'actie', 'object_type', 'object_id', 'details', 'hash'],
    ];

    /** @return list<string> */
    public static function mysql(string $voorvoegsel, string $charset): array
    {
        $p = $voorvoegsel . 'klusmaat_';
        return [
            "CREATE TABLE {$p}aanvragen (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  token_hash char(64) NOT NULL,
  klustype_id bigint(20) unsigned NOT NULL,
  plaats_id bigint(20) unsigned NOT NULL,
  omschrijving text NOT NULL,
  gewenst_moment varchar(20) NOT NULL,
  naam varchar(120) NOT NULL,
  email varchar(190) NOT NULL,
  telefoon varchar(40) NOT NULL,
  adres varchar(190) NOT NULL,
  postcode varchar(10) NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  max_klussers smallint(5) unsigned NOT NULL,
  opgepakt_aantal smallint(5) unsigned NOT NULL DEFAULT 0,
  aangemaakt_op datetime NOT NULL,
  gesloten_op datetime DEFAULT NULL,
  geanonimiseerd_op datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY token_hash (token_hash),
  KEY status_plaats (status,plaats_id)
) {$charset};",
            "CREATE TABLE {$p}klussers (
  user_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'nieuw',
  saldo int(11) NOT NULL DEFAULT 0,
  aangemaakt_op datetime NOT NULL,
  PRIMARY KEY  (user_id)
) {$charset};",
            "CREATE TABLE {$p}credit_boekingen (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  delta int(11) NOT NULL,
  saldo_na int(11) NOT NULL,
  reden varchar(40) NOT NULL,
  toelichting varchar(255) NOT NULL DEFAULT '',
  aanvraag_id bigint(20) unsigned DEFAULT NULL,
  actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
  aangemaakt_op datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) {$charset};",
            "CREATE TABLE {$p}ontgrendelingen (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  aanvraag_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  kosten int(11) NOT NULL,
  aangemaakt_op datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY aanvraag_user (aanvraag_id,user_id),
  KEY user_id (user_id)
) {$charset};",
            "CREATE TABLE {$p}logboek (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  tijd datetime NOT NULL,
  actor_type varchar(20) NOT NULL,
  actor_id bigint(20) unsigned NOT NULL,
  actie varchar(60) NOT NULL,
  object_type varchar(30) NOT NULL,
  object_id bigint(20) unsigned NOT NULL,
  details text NOT NULL,
  hash char(64) NOT NULL,
  PRIMARY KEY  (id),
  KEY object (object_type,object_id)
) {$charset};",
        ];
    }

    /**
     * MySQL-triggers die wijzigen en wissen van boekingen en logboek blokkeren.
     * Worden bij activatie geprobeerd; lukt dat niet (rechten van de host), dan blijft de code zelf de bewaker.
     *
     * @return list<string>
     */
    public static function mysqlTriggers(string $voorvoegsel): array
    {
        $p = $voorvoegsel . 'klusmaat_';
        $sql = [];
        foreach (['logboek', 'credit_boekingen'] as $tabel) {
            foreach (['UPDATE', 'DELETE'] as $actie) {
                $naam = strtolower("{$p}{$tabel}_blokkeer_{$actie}");
                $sql[] = "CREATE TRIGGER {$naam} BEFORE {$actie} ON {$p}{$tabel} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$tabel} is alleen toevoegen'";
            }
        }
        return $sql;
    }

    /** @return list<string> */
    public static function sqlite(string $voorvoegsel = ''): array
    {
        $p = $voorvoegsel . 'klusmaat_';
        $sql = [
            "CREATE TABLE {$p}aanvragen (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  token_hash TEXT NOT NULL UNIQUE,
  klustype_id INTEGER NOT NULL,
  plaats_id INTEGER NOT NULL,
  omschrijving TEXT NOT NULL,
  gewenst_moment TEXT NOT NULL,
  naam TEXT NOT NULL,
  email TEXT NOT NULL,
  telefoon TEXT NOT NULL,
  adres TEXT NOT NULL,
  postcode TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'open',
  max_klussers INTEGER NOT NULL,
  opgepakt_aantal INTEGER NOT NULL DEFAULT 0,
  aangemaakt_op TEXT NOT NULL,
  gesloten_op TEXT,
  geanonimiseerd_op TEXT
)",
            "CREATE TABLE {$p}klussers (
  user_id INTEGER PRIMARY KEY,
  status TEXT NOT NULL DEFAULT 'nieuw',
  saldo INTEGER NOT NULL DEFAULT 0,
  aangemaakt_op TEXT NOT NULL
)",
            "CREATE TABLE {$p}credit_boekingen (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  delta INTEGER NOT NULL,
  saldo_na INTEGER NOT NULL,
  reden TEXT NOT NULL,
  toelichting TEXT NOT NULL DEFAULT '',
  aanvraag_id INTEGER,
  actor_id INTEGER NOT NULL DEFAULT 0,
  aangemaakt_op TEXT NOT NULL
)",
            "CREATE TABLE {$p}ontgrendelingen (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  aanvraag_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  kosten INTEGER NOT NULL,
  aangemaakt_op TEXT NOT NULL,
  UNIQUE (aanvraag_id, user_id)
)",
            "CREATE TABLE {$p}logboek (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tijd TEXT NOT NULL,
  actor_type TEXT NOT NULL,
  actor_id INTEGER NOT NULL,
  actie TEXT NOT NULL,
  object_type TEXT NOT NULL,
  object_id INTEGER NOT NULL,
  details TEXT NOT NULL,
  hash TEXT NOT NULL
)",
        ];
        foreach (['logboek', 'credit_boekingen'] as $tabel) {
            foreach (['UPDATE', 'DELETE'] as $actie) {
                $sql[] = "CREATE TRIGGER {$p}{$tabel}_blokkeer_{$actie} BEFORE {$actie} ON {$p}{$tabel} BEGIN SELECT RAISE(ABORT, '{$tabel} is alleen toevoegen'); END";
            }
        }
        return $sql;
    }
}
