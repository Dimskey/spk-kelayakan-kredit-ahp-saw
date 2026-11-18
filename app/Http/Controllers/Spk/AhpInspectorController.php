<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\AhpSession;
use App\Models\Criterion;
use App\Services\Spk\AhpCalculatorService;
use App\Services\Spk\SpkAssessmentOrchestratorService;
use Database\Seeders\SpkCriteriaSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class AhpInspectorController extends Controller
{
    public function __construct(
        private readonly AhpCalculatorService $ahpCalculator,
        private readonly SpkAssessmentOrchestratorService $orchestrator
    ) {}

    public function index(): View
    {
        if (Criterion::count() === 0 || ! AhpSession::where('is_active', true)->exists()) {
            app(SpkCriteriaSeeder::class)->run();
        }

        $activeSession = AhpSession::where('is_active', true)->firstOrFail();
        $criteria = Criterion::orderBy('sort_order')->get();

        $matrix = $activeSession->raw_matrix_snapshot
            ?: $this->ahpCalculator->buildReciprocalMatrix($criteria->count(), []);

        $ahpCalculation = $this->ahpCalculator->calculate($matrix);
        $sessionHistory = AhpSession::orderByDesc('id')->take(6)->get();

        return view('spk.ahp-inspector', [
            'activeSession' => $activeSession,
            'criteria' => $criteria,
            'ahpCalculation' => $ahpCalculation,
            'sessionHistory' => $sessionHistory,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'matrix' => ['required', 'array', 'size:5'],
            'matrix.*' => ['required', 'array', 'size:5'],
            'matrix.*.*' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $result = $this->ahpCalculator->calculate($validated['matrix']);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function apply(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'matrix' => ['required', 'array', 'size:5'],
            'matrix.*' => ['required', 'array', 'size:5'],
            'matrix.*.*' => ['required', 'numeric', 'gt:0'],
            'session_name' => ['nullable', 'string', 'max:120'],
        ]);

        $sessionName = trim((string) ($validated['session_name'] ?? ''))
            ?: 'Simulasi Kebijakan Manajemen (' . now()->format('d/m H:i') . ')';

        try {
            $applied = $this->orchestrator->applyAhpMatrix($validated['matrix'], $sessionName);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withErrors(['matrix' => $e->getMessage()]);
        }

        $message = sprintf(
            'Bobot AHP baru disimpan (CR = %.4f) dan %d pelanggan dikalkulasi ulang (%d NET_30, %d NET_14, %d CBD).',
            $applied['ahp']['cr'],
            $applied['saw_summary']['total'],
            $applied['saw_summary']['net_30'],
            $applied['saw_summary']['net_14'],
            $applied['saw_summary']['cbd']
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'ahp' => $applied['ahp'],
                'saw_summary' => $applied['saw_summary'],
                'session_id' => $applied['session']->id,
            ]);
        }

        return redirect()
            ->route('spk.ahp.inspector')
            ->with('status', $message);
    }

    public function reset(): RedirectResponse
    {
        app(SpkCriteriaSeeder::class)->run();
        $summary = $this->orchestrator->recalculateForActiveSession();

        return redirect()
            ->route('spk.ahp.inspector')
            ->with('status', sprintf(
                'Bobot AHP dikembalikan ke Baseline Default (CR = %.4f) dan %d pelanggan diperbarui.',
                $summary['cr'],
                $summary['total']
            ));
    }
}
