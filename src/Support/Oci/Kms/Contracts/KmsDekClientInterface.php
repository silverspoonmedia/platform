<?php

namespace App\Support\Oci\Kms\Contracts;

use App\Support\Oci\Kms\OciKmsDekResult;

interface KmsDekClientInterface
{
    public function generateDek(): OciKmsDekResult;

    public function decryptDek(string $wrappedDek): OciKmsDekResult;
}
