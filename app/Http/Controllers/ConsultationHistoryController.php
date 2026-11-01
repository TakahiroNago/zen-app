<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ZenConsultation;
use App\Services\ConsultationDeletionService;
use App\Services\PublicConsultationShareService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ConsultationHistoryController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $selectedTopic = $request->validate([
            'topic' => ['nullable', 'string', Rule::in(ZenConsultation::TOPICS)],
        ])['topic'] ?? null;
        $user = $request->user();
        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $databaseConsultations = null;
        $historyUpgradePrompt = false;
        $databaseTopics = collect();

        if ($user instanceof User) {
            $historyQuery = $user->zenConsultations()
                ->orderByDesc('created_at')
                ->orderByDesc('id');
            if ($user->plan !== User::PLAN_PREMIUM) {
                $historyQuery->where('created_at', '>=', now()->subMonths(3));
            }

            $historyCount = $historyQuery->count();
            $displayLimit = $user->plan === User::PLAN_FREE ? min(50, $historyCount) : $historyCount;
            $historyUpgradePrompt = $user->plan === User::PLAN_FREE && $historyCount > 50;
            $visibleConsultationIds = null;
            if ($user->plan === User::PLAN_FREE) {
                $visibleConsultationIds = (clone $historyQuery)
                    ->select('id')
                    ->limit($displayLimit)
                    ->pluck('id')
                    ->all();
            }

            $topicQuery = clone $historyQuery;
            if ($visibleConsultationIds !== null) {
                $topicQuery->whereIn('id', $visibleConsultationIds);
            }
            $databaseTopics = $topicQuery
                ->reorder()
                ->whereNotNull('topic')
                ->distinct()
                ->pluck('topic');

            if ($selectedTopic !== null) {
                $filteredHistoryQuery = clone $historyQuery;
                if ($visibleConsultationIds !== null) {
                    $filteredHistoryQuery->whereIn('id', $visibleConsultationIds);
                }

                $historyQuery = $filteredHistoryQuery->where('topic', $selectedTopic);
                $displayLimit = $historyQuery->count();
            }

            $perPage = 20;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $databaseConsultations = new LengthAwarePaginator(
                $historyQuery
                    ->offset(($page - 1) * $perPage)
                    ->limit(min($perPage, max(0, $displayLimit - (($page - 1) * $perPage))))
                    ->get(),
                $displayLimit,
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $request->query()],
            );
        }

        $sessionConsultations = $this->sessionConsultations($request, $selectedTopic);

        $sessionTopics = collect($request->session()->get('zen_consultations', []))
            ->filter(fn (mixed $consultation): bool => $this->isConsultation($consultation)
                && in_array($consultation['topic'] ?? null, ZenConsultation::TOPICS, true))
            ->pluck('topic');
        $topics = collect(ZenConsultation::TOPICS)
            ->filter(fn (string $topic): bool => $databaseTopics->contains($topic)
                || $sessionTopics->contains($topic))
            ->values();

        return view('consultations.index', compact(
            'topics',
            'databaseConsultations',
            'historyUpgradePrompt',
            'selectedTopic',
            'sessionConsultations',
        ));
    }

    public function show(Request $request, string $consultation): View|RedirectResponse
    {
        if (preg_match('/^session-(\d+)$/', $consultation, $matches) === 1) {
            $sessionConsultation = $request->session()->get('zen_consultations.'.$matches[1]);
            abort_unless($this->isConsultation($sessionConsultation), 404);

            $consultationData = [
                'concern' => $sessionConsultation['concern'],
                'response' => $sessionConsultation['response'],
                'image_number' => $sessionConsultation['image_number'] ?? null,
            ];
        } else {
            $user = $request->user();

            if (! $user instanceof User) {
                return redirect()->route('login');
            }

            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            $consultationData = $this->visibleSavedConsultation($user, $consultation)
                ->only(['concern', 'response', 'image_number', 'created_at']);
        }

        return view('consultations.show', [
            'consultation' => $consultationData,
            'consultationId' => $consultation,
        ]);
    }

    /**
     * Issues (or returns the still active) public share URL when the owner explicitly asks to share.
     */
    public function share(Request $request, string $consultation, PublicConsultationShareService $shareService): JsonResponse
    {
        if (preg_match('/^session-(\d+)$/', $consultation, $matches) === 1) {
            $sessionKey = 'zen_consultations.'.$matches[1];
            $sessionConsultation = $request->session()->get($sessionKey);
            abort_unless($this->isConsultation($sessionConsultation), 404);

            $sharedConsultation = $shareService->shareSessionConsultation($sessionConsultation);
            $request->session()->put($sessionKey, $sharedConsultation);
            $token = $sharedConsultation['share_token'];
        } else {
            $user = $request->user();
            abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

            $token = $shareService->shareSavedConsultation($this->visibleSavedConsultation($user, $consultation));
        }

        return response()->json(['url' => route('consultations.share', ['token' => $token])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function destroy(
        Request $request,
        string $consultation,
        ConsultationDeletionService $deletionService,
        PublicConsultationShareService $shareService,
    ): RedirectResponse {
        if (preg_match('/^session-(\d+)$/', $consultation, $matches) === 1) {
            $this->revokeSessionShare($request->session()->pull('zen_consultations.'.$matches[1]), $shareService);
        } else {
            $user = $request->user();
            abort_unless($user instanceof User, 403);
            abort_unless(ctype_digit($consultation), 404);
            abort_if($deletionService->delete($user->zenConsultations()->whereKey($consultation)) === 0, 404);
        }

        return redirect()->route('consultations.index')->with('status', __('相談の記録を削除しました。'));
    }

    public function destroyAll(
        Request $request,
        ConsultationDeletionService $deletionService,
        PublicConsultationShareService $shareService,
    ): RedirectResponse {
        $sessionConsultations = $request->session()->pull('zen_consultations', []);
        foreach (is_array($sessionConsultations) ? $sessionConsultations : [] as $sessionConsultation) {
            $this->revokeSessionShare($sessionConsultation, $shareService);
        }

        $user = $request->user();
        if ($user instanceof User) {
            $deletionService->delete($user->zenConsultations()->getQuery());
        }

        return redirect()->route('consultations.index')->with('status', __('すべての相談の記録を削除しました。'));
    }

    /**
     * Finds a saved consultation within the history the user's plan can currently see.
     */
    private function visibleSavedConsultation(User $user, string $consultation): ZenConsultation
    {
        $consultations = $user->zenConsultations();
        if ($user->plan !== User::PLAN_PREMIUM) {
            $consultations->where('created_at', '>=', now()->subMonths(3));
        }

        $savedConsultation = $consultations->findOrFail($consultation);
        if ($user->plan === User::PLAN_FREE) {
            $newerConsultations = $user->zenConsultations()
                ->where(function ($query) use ($savedConsultation): void {
                    $query->where('created_at', '>', $savedConsultation->created_at)
                        ->orWhere(function ($query) use ($savedConsultation): void {
                            $query->where('created_at', $savedConsultation->created_at)
                                ->where('id', '>', $savedConsultation->id);
                        });
                })
                ->count();

            abort_if($newerConsultations >= 50, 404);
        }

        return $savedConsultation;
    }

    private function revokeSessionShare(mixed $sessionConsultation, PublicConsultationShareService $shareService): void
    {
        if (is_array($sessionConsultation) && is_string($sessionConsultation['share_token'] ?? null)) {
            $shareService->revoke($shareService->tokenHash($sessionConsultation['share_token']));
        }
    }

    /** @return Collection<int, array{id: string, concern: string, response: string}> */
    private function sessionConsultations(Request $request, ?string $selectedTopic): Collection
    {
        return collect($request->session()->get('zen_consultations', []))
            ->filter(fn (mixed $consultation): bool => $this->isConsultation($consultation)
                && ($selectedTopic === null || ($consultation['topic'] ?? null) === $selectedTopic))
            ->map(fn (array $consultation, int|string $index): array => [
                'id' => 'session-'.$index,
                'concern' => $consultation['concern'],
                'response' => $consultation['response'],
            ])
            ->reverse()
            ->values();
    }

    private function isConsultation(mixed $consultation): bool
    {
        return is_array($consultation)
            && is_string($consultation['concern'] ?? null)
            && is_string($consultation['response'] ?? null);
    }
}
