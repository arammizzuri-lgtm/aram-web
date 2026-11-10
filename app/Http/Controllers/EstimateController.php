<?php

namespace App\Http\Controllers;

use App\Support\CostEstimate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "What will your house cost?" — /estimate, and /ku/estimate.
 *
 * The answers live in the address (?plot=600&floors=2…), so an estimate can
 * be reloaded, bookmarked or shared, and the page works without JavaScript
 * (its button submits the form). With it, estimate.js asks calc() for the
 * result panel as each answer changes. Either way the sums are done here,
 * by CostEstimate, and nowhere else.
 *
 * Beside the figures, the house is drawn from the same answers
 * (estimate-house.js), and under them the studio's projects on plots like
 * the visitor's.
 */
class EstimateController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(CostEstimate::enabled(), 404);
        $inputs = CostEstimate::inputs($request->query());
        $estimate = $inputs ? CostEstimate::calculate($inputs) : null;

        return view('estimate', [
            'inputs' => $inputs,
            'estimate' => $estimate,
            'house' => CostEstimate::house($estimate),
            'plot' => $inputs['plot'] ?? 0,
            'similar' => CostEstimate::similarProjects($inputs['plot'] ?? 0),
            'levels' => CostEstimate::levels(),
            'maxFloors' => CostEstimate::maxFloors(),
            'coverage' => CostEstimate::num('coverage'),
        ]);
    }

    /**
     * The estimate as one sheet of paper — the answers, the figures, the
     * studio's details — laid out to print or save as a PDF from the
     * browser (which sets Kurdish and Arabic properly, where a PDF library on
     * the server would not join the letters). Reached from "Get this
     * estimate as a PDF" once the visitor has left a way to reach them;
     * ?for= puts their name on it, ?print=1 opens the print dialog.
     */
    public function sheet(Request $request): View|RedirectResponse
    {
        abort_unless(CostEstimate::enabled(), 404);
        $inputs = CostEstimate::inputs($request->query());
        $estimate = $inputs ? CostEstimate::calculate($inputs) : null;
        if (! $estimate) {
            return redirect(site_url('/estimate'));
        }

        return view('estimate-sheet', [
            'estimate' => $estimate,
            'inputs' => $inputs,
            'for' => mb_substr(trim(strip_tags((string) $request->query('for', ''))), 0, 80),
            'print' => $request->boolean('print'),
            'back' => site_url('/estimate').'?'.http_build_query(array_filter([
                'plot' => $inputs['plot'] ?: null, 'floors' => $inputs['floors'], 'built' => $inputs['built'],
                'basement' => $inputs['basement'] ? 1 : null, 'bedrooms' => $inputs['bedrooms'], 'bathrooms' => $inputs['bathrooms'],
                'finish' => $inputs['finish'], 'pool' => $inputs['pool'] ? 1 : null,
            ], fn ($v) => $v !== null)),
        ]);
    }

    /** The result panel for a set of answers, rendered, plus the built area the plot alone gives. */
    public function calc(Request $request): JsonResponse
    {
        abort_unless(CostEstimate::enabled(), 404);
        $inputs = CostEstimate::inputs($request->query());
        $estimate = $inputs ? CostEstimate::calculate($inputs) : null;
        $plot = $inputs['plot'] ?? 0;

        return response()->json([
            'ok' => $estimate !== null,
            'auto_built' => $inputs && $inputs['plot'] ? CostEstimate::autoBuilt($inputs['plot'], $inputs['floors']) : null,
            'html' => view('partials.estimate-result', ['estimate' => $estimate])->render(),
            'house' => CostEstimate::house($estimate),
            'similar' => view('partials.estimate-similar', ['similar' => CostEstimate::similarProjects($plot), 'plot' => $plot])->render(),
        ])->header('Cache-Control', 'no-store');
    }
}
