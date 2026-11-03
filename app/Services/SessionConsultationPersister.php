<?php

namespace App\Services;

use App\Models\User;
use App\Models\ZenConsultation;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class SessionConsultationPersister
{
    public function __construct(
        private readonly PublicConsultationShareService $shareService,
        private readonly ConsultationHighlights $highlights,
    ) {}

    public function persist(User $user, Request $request): void
    {
        $sessionConsultations = $request->session()->get('zen_consultations', []);
        if (! is_array($sessionConsultations)) {
            throw new UnexpectedValueException('Session Zen consultations must be an array.');
        }

        $consultations = [];
        foreach ($sessionConsultations as $consultation) {
            if (
                ! is_array($consultation)
                || ! is_string($consultation['concern'] ?? null)
                || ! is_string($consultation['response'] ?? null)
            ) {
                throw new UnexpectedValueException('Session Zen consultation has an invalid format.');
            }

            $hasTopic = array_key_exists('topic', $consultation);
            $hasSummary = array_key_exists('summary', $consultation);
            if ($hasTopic !== $hasSummary) {
                throw new UnexpectedValueException('Session consultation metadata has an invalid format.');
            }

            if (
                $hasTopic
                && (
                    ! is_string($consultation['topic'])
                    || ! in_array($consultation['topic'], ZenConsultation::TOPICS, true)
                    || ! is_string($consultation['summary'])
                    || trim($consultation['summary']) === ''
                    || mb_strlen($consultation['summary']) > ZenConsultation::SUMMARY_MAX_LENGTH
                )
            ) {
                throw new UnexpectedValueException('Session consultation metadata has an invalid format.');
            }

            $persistedConsultation = [
                'concern' => $consultation['concern'],
                'response' => $consultation['response'],
                ...array_filter($this->highlights->fromStored($consultation), fn (?string $value): bool => $value !== null),
            ];
            if (is_int($consultation['image_number'] ?? null)) {
                $persistedConsultation['image_number'] = $consultation['image_number'];
            }
            if ($hasTopic) {
                $persistedConsultation['topic'] = $consultation['topic'];
                $persistedConsultation['summary'] = $consultation['summary'];
            }
            if (
                is_string($consultation['share_token'] ?? null)
                && is_int($consultation['share_expires_at'] ?? null)
                && $consultation['share_expires_at'] > now()->getTimestamp()
            ) {
                $persistedConsultation['share_token'] = $consultation['share_token'];
                $persistedConsultation['share_token_hash'] = $this->shareService->tokenHash($consultation['share_token']);
                $persistedConsultation['share_expires_at'] = Carbon::createFromTimestamp($consultation['share_expires_at']);
            }

            $consultations[] = $persistedConsultation;
        }

        if ($consultations !== []) {
            DB::transaction(fn () => $user->zenConsultations()->createMany($consultations));
        }

        // Shared links of persisted guest consultations are now served from the saved rows.
        foreach ($consultations as $consultation) {
            $this->shareService->revoke($consultation['share_token_hash'] ?? null);
        }

        $request->session()->forget('zen_consultations');
    }
}
