<?php

namespace App\Exception;

/**
 * Action impossible sur un échange (règle métier non respectée).
 * Le message est destiné à l'utilisateur : il est affiché tel quel dans un flash.
 */
final class ExchangeException extends \DomainException
{
}
