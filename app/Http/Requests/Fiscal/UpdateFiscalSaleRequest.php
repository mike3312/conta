<?php

namespace App\Http\Requests\Fiscal;

use App\Enums\FiscalDocumentDirection;

class UpdateFiscalSaleRequest extends FiscalDocumentRequest
{
    public function direction(): FiscalDocumentDirection
    {
        return FiscalDocumentDirection::SALE;
    }
}
