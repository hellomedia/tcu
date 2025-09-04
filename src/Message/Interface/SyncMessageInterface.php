<?php

namespace App\Message\Interface;

/**
 * Make message event classes implement this interface to be handled
 * by synchronous transport according to messenger.yaml routing config.
 * 
 * Actually, for sync message, we use the eventDispatcher, which
 * saves queries by allowing to pass doctrine entities.
 * (might want to double check this)
 */
interface SyncMessageInterface
{

}