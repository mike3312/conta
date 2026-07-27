<?php

namespace App\Http\Controllers\Fiscal;

use App\Enums\FiscalDocumentDirection;
use App\Http\Requests\Fiscal\StoreFiscalSaleRequest;
use App\Http\Requests\Fiscal\UpdateFiscalSaleRequest;
use Illuminate\Http\RedirectResponse;

class FiscalSaleController extends FiscalBookController
{
    public function store(StoreFiscalSaleRequest $request): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $document = $this->documentService->create($company->id, $this->direction(), $request->validated(), $request->user());

        return redirect()->route('fiscal-sales.show', $document->id)->with('success', 'Venta fiscal registrada correctamente.');
    }

    public function update(UpdateFiscalSaleRequest $request, string|int $document): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $fiscalDocument = $this->findDocument($company->id, (int) $document);
        $this->documentService->update($fiscalDocument, $request->validated(), $request->user());

        return redirect()->route('fiscal-sales.show', $fiscalDocument->id)->with('success', 'Venta fiscal actualizada correctamente.');
    }

    protected function direction(): FiscalDocumentDirection
    {
        return FiscalDocumentDirection::SALE;
    }

    protected function routePrefix(): string
    {
        return 'fiscal-sales';
    }
}
