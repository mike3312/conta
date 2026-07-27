<?php

namespace App\Http\Requests\Fiscal;

use App\Enums\FiscalDocumentDirection;

class StoreFiscalPurchaseRequest extends FiscalDocumentRequest
{
    public function direction(): FiscalDocumentDirection
    {
        return FiscalDocumentDirection::PURCHASE;
    }
}
