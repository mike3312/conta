<?php

namespace App\Http\Controllers\Fiscal;

use App\Enums\FiscalDocumentDirection;
use App\Http\Requests\Fiscal\StoreFiscalPurchaseRequest;
use App\Http\Requests\Fiscal\UpdateFiscalPurchaseRequest;
use Illuminate\Http\RedirectResponse;

class FiscalPurchaseController extends FiscalBookController
{
    public function store(StoreFiscalPurchaseRequest $request): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $document = $this->documentService->create($company->id, $this->direction(), $request->validated(), $request->user());

        return redirect()->route('fiscal-purchases.show', $document->id)->with('success', 'Compra fiscal registrada correctamente.');
    }

    public function update(UpdateFiscalPurchaseRequest $request, string|int $document): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $fiscalDocument = $this->findDocument($company->id, (int) $document);
        $this->documentService->update($fiscalDocument, $request->validated(), $request->user());

        return redirect()->route('fiscal-purchases.show', $fiscalDocument->id)->with('success', 'Compra fiscal actualizada correctamente.');
    }

    protected function direction(): FiscalDocumentDirection
    {
        return FiscalDocumentDirection::PURCHASE;
    }

    protected function routePrefix(): string
    {
        return 'fiscal-purchases';
    }
}
