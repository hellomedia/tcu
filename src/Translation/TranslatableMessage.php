<?php
namespace App\Translation;

use Symfony\Component\Translation\TranslatableMessage as SymfonyTranslatableMessage;

/**
 * Proxy class
 * This is a **text only** translatable message
 * thus should be html escaped
 *
 * php:
 *   $foo = new TranslatableMessage(
 *      'translation_key',
 *      ['%bar%' => $bar],
 *      'translation_domain'
 *   ));
 * twig:
 *  {{ foo|trans }}
 */
class TranslatableMessage extends SymfonyTranslatableMessage
{

}
