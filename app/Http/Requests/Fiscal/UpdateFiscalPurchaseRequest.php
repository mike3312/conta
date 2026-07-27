<?php

namespace App\Http\Requests\Fiscal;

use App\Enums\FiscalDocumentDirection;

class UpdateFiscalPurchaseRequest extends FiscalDocumentRequest
{
    public function direction(): FiscalDocumentDirection
    {
        return FiscalDocumentDirection::PURCHASE;
    }
}
