<?php

namespace App\Exceptions;

use RuntimeException;

/** Faltan WHATSAPP_ACCESS_TOKEN o WHATSAPP_PHONE_NUMBER_ID en el .env. */
class WhatsAppNoConfiguradoException extends RuntimeException {}
