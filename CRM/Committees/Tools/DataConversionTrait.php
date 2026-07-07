<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2021 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/
declare(strict_types = 1);

use CRM_Committees_ExtensionUtil as E;

/**
 * @todo once it is decided that this extension is purely php8.2 compatible, then this trait can be removed
 */
trait CRM_Committees_Tools_DataConversionTrait {

  /**
   * this function is used for backward compatibility to php < 8.2
   * php 8.2 provides ini_parse_quantity()
   * code copied from https://php.watch/versions/8.2/ini_parse_quantity
   */
  public function ini_parse_quantity(string $shorthand): int {
    $original_shorthand = $shorthand;
    $multiplier = 1;
    $sign = '';
    $return_value = 0;

    $shorthand = trim($shorthand);

    // Return 0 for empty strings.
    if ($shorthand === '') {
        return 0;
    }

    // Accept + and - as the sign.
    if ($shorthand[0] === '-' || $shorthand[0] === '+') {
        if ($shorthand[0] === '-') {
            $multiplier = -1;
            $sign = '-';
        }
        $shorthand = substr($shorthand, 1);
    }

    // If there is no suffix, return the integer value with the sign.
    if (preg_match('/^\d+$/', $shorthand, $matches)) {
        return $multiplier * intval($matches[0]);
    }

    // Return 0 with a warning if there are no leading digits
    if (preg_match('/^\d/', $shorthand) === 0) {
        trigger_error(sprintf('Invalid quantity "%s": no valid leading digits, interpreting as "0" for backwards compatibility', $original_shorthand), E_USER_WARNING);
        return $return_value;
    }

    // Removing whitespace characters.
    $shorthand = preg_replace('/\s/', '', $shorthand);

    $suffix = strtoupper(substr($shorthand, -1));
    switch ($suffix) {
        case 'K':
            $multiplier *= 1024;
          break;

        case 'M':
            $multiplier *= 1024 * 1024;
          break;

        case 'G':
            $multiplier *= 1024 * 1024 * 1024;
          break;

        default:
            preg_match('/\d+/', $shorthand, $matches);
            trigger_error(sprintf('Invalid quantity "%s": unknown multiplier "%s", interpreting as "%d" for backwards compatibility', $original_shorthand, $suffix, $sign . $matches[0]), E_USER_WARNING);
          return intval($matches[0]) * $multiplier;
    }

    $stripped_shorthand = preg_replace('/^(\d+)(\D.*)([kKmMgG])$/', '$1$3', $shorthand, -1, $count);
    if ($count > 0) {
        trigger_error(sprintf('Invalid quantity "%s", interpreting as "%s" for backwards compatibility', $original_shorthand, $sign . $stripped_shorthand), E_USER_WARNING);
    }

    preg_match('/\d+/', $shorthand, $matches);

    // NOTE: integer with value overflow will be converted to float by php
    $multiplied = intval($matches[0]) * $multiplier;

    /** @phpstan-ignore function.impossibleType */
    if (is_float($multiplied)) {
        trigger_error(sprintf('Invalid quantity "%s": value is out of range, using overflow result for backwards compatibility', $original_shorthand), E_USER_WARNING);
    }

    return intval($matches[0]) * $multiplier;
  }

}
