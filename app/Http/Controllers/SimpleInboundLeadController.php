<?php

namespace App\Http\Controllers;

use App\Models\InboundSource;
use App\Services\Leads\AdaptiveLeadPayload;
use App\Services\Leads\InboundDomainGuard;
use App\Services\Leads\IngestInboundLead;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SimpleInboundLeadController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        AdaptiveLeadPayload $adapter,
        InboundDomainGuard $domainGuard,
        IngestInboundLead $ingestor,
    ): JsonResponse {
        abort_unless(strlen($token) >= 48, 404);
        $source = InboundSource::withoutGlobalScopes()
            ->where('endpoint_token_hash', hash('sha256', $token))
            ->where('is_active', true)
            ->firstOrFail();

        abort_if(strlen($request->getContent()) > 1_048_576, 413, 'Payload troppo grande.');
        $payload = $request->isJson() ? $request->json()->all() : $request->all();
        abort_unless(is_array($payload) && $payload !== [] && ! array_is_list($payload), 422, 'Il payload deve essere un oggetto JSON o un form non vuoto.');
        abort_if($adapter->explicitPrivacyRefusal($payload), 422, 'Il consenso privacy risulta rifiutato.');
        $domainCheck = $domainGuard->verify($request, $payload, $source);
        $data = $adapter->normalize($payload, $source);
        $raw = $request->getContent() ?: json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadHash = hash('sha256', $raw);
        $idempotencyKey = Str::limit(
            (string) ($request->header('Idempotency-Key') ?: $data['external_id'] ?: 'auto-'.$payloadHash),
            255,
            '',
        );

        app(TenantContext::class)->set($source->organization()->firstOrFail());
        try {
            $result = $ingestor->handle($source, $data, $idempotencyKey, $payloadHash, $domainCheck);

            return response()->json([
                'status' => $result['status'],
                'lead_id' => $result['lead']->id,
                'domain_validation' => $domainCheck['mode'],
            ], $result['status'] === 'created' ? 201 : 200);
        } finally {
            app(TenantContext::class)->clear();
        }
    }
}
