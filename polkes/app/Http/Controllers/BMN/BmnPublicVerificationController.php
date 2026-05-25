<?php

declare(strict_types=1);

namespace App\Http\Controllers\BMN;

use App\Http\Controllers\Controller;
use App\Http\Requests\BMN\VerifyPublicOtpRequest;
use App\Models\BMN\PeminjamanAsetBmn;
use App\Models\BMN\PengembalianAsetBmn;
use App\Services\BMN\BmnPublicVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BmnPublicVerificationController extends Controller
{
    public function __construct(
        private readonly BmnPublicVerificationService $verificationService
    ) {
    }

    public function show(string $type, int $id, string $token): View
    {
        $record = $this->resolveRecord($type, $id);
        abort_unless($this->verificationService->validateToken($record, $token), 404);

        return view('bmn.public.verification', [
            'type' => $type,
            'record' => $record,
            'token' => $token,
        ]);
    }

    public function verify(VerifyPublicOtpRequest $request, string $type, int $id, string $token): RedirectResponse
    {
        $record = $this->resolveRecord($type, $id);
        abort_unless($this->verificationService->validateToken($record, $token), 404);

        $result = $this->verificationService->verifyOtps(
            $record,
            (string) $request->validated()['email_otp'],
            (string) $request->validated()['wa_otp']
        );

        if (! $result['success']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    /**
     * @return PeminjamanAsetBmn|PengembalianAsetBmn
     */
    private function resolveRecord(string $type, int $id)
    {
        if ($type === 'peminjaman') {
            /** @var PeminjamanAsetBmn $record */
            $record = PeminjamanAsetBmn::query()->findOrFail($id);
            abort_unless($record->isPublicSource(), 404);

            return $record;
        }

        if ($type === 'pengembalian') {
            /** @var PengembalianAsetBmn $record */
            $record = PengembalianAsetBmn::query()->findOrFail($id);
            abort_unless($record->isPublicSource(), 404);

            return $record;
        }

        abort(404);
    }
}
