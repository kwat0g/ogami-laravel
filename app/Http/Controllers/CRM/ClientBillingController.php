<?php

declare(strict_types=1);

namespace App\Http\Controllers\CRM;

use App\Domains\AR\Services\ClientPortalBillingService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ClientBillingController extends Controller
{
    public function __construct(
        private readonly ClientPortalBillingService $service
    ) {}

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->client_id) {
            return response()->json([
                'message' => 'No customer associated with this account.',
                'error_code' => 'CLIENT_ACCOUNT_NOT_LINKED',
            ], 403);
        }

        return response()->json([
            'data' => $this->service->summaryForClientId((int) $user->client_id),
        ]);
    }

    public function statementPdf(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->client_id) {
            return response()->json([
                'message' => 'No customer associated with this account.',
                'error_code' => 'CLIENT_ACCOUNT_NOT_LINKED',
            ], 403);
        }

        $payload = $this->service->statementOfAccountDataForClientId(
            (int) $user->client_id,
            $request->input('as_of')
        );

        $pdf = Pdf::loadView('ar.statement-of-account', [
            ...$payload,
            'settings' => app(\App\Services\SystemSettingService::class)->getCompanyInfo(),
        ])->setPaper('a4', 'portrait');

        $filename = sprintf(
            'soa-%s-%s.pdf',
            str_replace(' ', '-', strtolower($payload['customer']->name)),
            $payload['asOf']->format('Y-m-d'),
        );

        return $pdf->stream($filename);
    }
}
