<?php

namespace App\Http\Controllers;

use App\Services\Privacy\OrganizationDataExporter;
use App\Support\Tenancy\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrganizationDataController extends Controller
{
    public function export(TenantContext $tenants, OrganizationDataExporter $exporter): StreamedResponse
    {
        return $exporter->download($tenants->requireOrganization());
    }
}
