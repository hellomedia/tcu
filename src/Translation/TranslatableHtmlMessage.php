<?php
namespace App\Translation;

use Symfony\Component\Translation\TranslatableMessage;

/**
 *  Marquor class
 *
 *  Indicates that content of translatableMessage includes html
 *  thus should be rendered with raw filter
 *
 * php:
 *  $foo = new TranslatableHtmlMessage(
 *      'translation_key',
 *      ['%bar%' => $bar],
 *      'translation_domain'
 *   ));
 * twig:
 *  {{ foo|trans|raw }}
 */
class TranslatableHtmlMessage extends TranslatableMessage
{

}
