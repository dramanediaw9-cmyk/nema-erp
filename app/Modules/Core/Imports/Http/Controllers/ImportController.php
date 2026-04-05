<?php

namespace App\Modules\Core\Imports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Imports\Services\ImportService;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    public function index(CurrentWorkspace $workspace): View
    {
        abort_if(! $workspace->companyId(), 403);

        return view('imports.index', [
            'branch' => $workspace->branch(),
        ]);
    }

    public function downloadTemplate(string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['customers', 'suppliers', 'products', 'opening-stock', 'historical-sales', 'historical-purchases'], true), 404);

        $templates = [
            'customers' => ['modele-clients.csv', $this->importService->customerTemplate()],
            'suppliers' => ['modele-fournisseurs.csv', $this->importService->supplierTemplate()],
            'products' => ['modele-produits.csv', $this->importService->productTemplate()],
            'opening-stock' => ['modele-stock-initial.csv', $this->importService->openingStockTemplate()],
            'historical-sales' => ['modele-ventes-historiques.csv', $this->importService->historicalSalesTemplate()],
            'historical-purchases' => ['modele-achats-historiques.csv', $this->importService->historicalPurchasesTemplate()],
        ];

        [$filename, $rows] = $templates[$type];

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importCustomers(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->importService->importCustomers($data['file'], $companyId, $request->user());
        $this->activityLogger->log('imports.customers', 'Import clients CSV', null, $result);

        return redirect()->route('imports.index')->with('success', $result['count'].' client(s) importes ou mis a jour avec succes.');
    }

    public function importSuppliers(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->importService->importSuppliers($data['file'], $companyId, $request->user());
        $this->activityLogger->log('imports.suppliers', 'Import fournisseurs CSV', null, $result);

        return redirect()->route('imports.index')->with('success', $result['count'].' fournisseur(s) importes ou mis a jour avec succes.');
    }

    public function importProducts(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->importService->importProducts($data['file'], $companyId, $request->user());
        $this->activityLogger->log('imports.products', 'Import produits CSV', null, $result);

        return redirect()->route('imports.index')->with('success', $result['count'].' produit(s) importes ou mis a jour avec succes.');
    }

    public function importOpeningStock(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->importService->importOpeningStock($data['file'], $companyId, $branchId, $request->user());
        $this->activityLogger->log('imports.opening_stock', 'Import stock initial CSV', null, $result);

        return redirect()->route('imports.index')->with('success', $result['count'].' ligne(s) de stock initial importees avec succes.');
    }

    public function importHistoricalSales(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->importService->importHistoricalSales($data['file'], $companyId, $branchId, $request->user());
        $this->activityLogger->log('imports.historical_sales', 'Import ventes historiques CSV', null, $result);

        return redirect()->route('imports.index')->with('success', $result['count'].' facture(s) de vente historique importee(s) avec succes.');
    }

    public function importHistoricalPurchases(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        $branchId = $workspace->branchId();
        abort_if(! $companyId || ! $branchId, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $result = $this->importService->importHistoricalPurchases($data['file'], $companyId, $branchId, $request->user());
        $this->activityLogger->log('imports.historical_purchases', 'Import achats historiques CSV', null, $result);

        return redirect()->route('imports.index')->with('success', $result['count'].' facture(s) fournisseur historique importee(s) avec succes.');
    }
}