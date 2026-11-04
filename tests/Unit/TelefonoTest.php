<?php

namespace Tests\Unit;

use App\Support\Telefono;
use PHPUnit\Framework\TestCase;

class TelefonoTest extends TestCase
{
    public function test_normaliza_al_formato_wa_id(): void
    {
        $this->assertSame('5213312345678', Telefono::normalizar('33 1234 5678'));
        $this->assertSame('5213312345678', Telefono::normalizar('+52 33 1234 5678'));
        $this->assertSame('5213312345678', Telefono::normalizar('5213312345678'));
        $this->assertSame('14155552671', Telefono::normalizar('+1 415 555 2671'));
        $this->assertNull(Telefono::normalizar('12345'));
        $this->assertNull(Telefono::normalizar(''));
    }
}
