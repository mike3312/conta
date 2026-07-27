<?php

namespace App\Http\Requests\Fiscal;

use App\Enums\FiscalDocumentDirection;

class StoreFiscalSaleRequest extends FiscalDocumentRequest
{
    public function direction(): FiscalDocumentDirection
    {
        return FiscalDocumentDirection::SALE;
    }
}
