<?php

namespace App\Support;

/**
 * Horní meze vstupů (S7): délky textů pro sloupce `text` a největší částky podle přesnosti sloupců `decimal`
 * (nad nimi by MariaDB ve strict módu vrátila chybu 500 a velké texty by nafukovaly odpovědi API).
 */
final class Limits
{
    /** Popisy, poznámky, obsah oznámení (sloupce `text`). */
    public const int TEXT = 5000;

    /** Komentář tiketu a hodnocení. */
    public const int COMMENT = 2000;

    /** decimal(10,2): nájemné, zálohy, kauce, platby, náklady, cena inventáře. */
    public const string AMOUNT = '99999999.99';

    /** decimal(12,2): pořizovací cena nemovitosti. */
    public const string PRICE = '9999999999.99';

    /** decimal(6,2): plocha v m². */
    public const string SIZE = '9999.99';

    /** decimal(12,3): stav měřiče. */
    public const string READING = '999999999.999';
}
