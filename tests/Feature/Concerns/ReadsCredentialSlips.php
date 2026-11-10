<?php

namespace Tests\Feature\Concerns;

use App\Support\CredentialSlip;

trait ReadsCredentialSlips
{
    /**
     * The temporary passwords from the slip created by the last import or reset, keyed by NIS or NIP.
     *
     * @return array<string, string>
     */
    protected function slipPasswords(): array
    {
        $slip = CredentialSlip::pull((string) session('credential_slip.id'));

        $this->assertNotNull($slip, 'Expected a password slip to be created.');

        return collect($slip['rows'])->pluck('password', 'identifier')->all();
    }
}
