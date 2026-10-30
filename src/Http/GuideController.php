<?php

namespace TruLaravelClub\BlscGuide\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;
use TruLaravelClub\BlscGuide\FeatureMap;
use TruLaravelClub\BlscGuide\Progress;

class GuideController extends Controller
{
    public function index(FeatureMap $map): View
    {
        return view('blsc-guide::index', [
            'map' => $map,
        ]);
    }

    public function start(FeatureMap $map): View
    {
        return view('blsc-guide::start', [
            'map' => $map,
        ]);
    }

    public function show(FeatureMap $map, string $feature): View
    {
        $feature = $map->find($feature) ?? abort(404);

        return view('blsc-guide::show', [
            'map' => $map,
            'feature' => $feature,
            'repository' => config('blsc-guide.repository'),
            'progressFile' => Str::after(app(Progress::class)->fileFor($feature->key()), base_path().'/'),
        ]);
    }

    public function markDone(FeatureMap $map, Progress $progress, string $feature): RedirectResponse
    {
        $feature = $map->find($feature) ?? abort(404);
        $progress->mark($feature->key());

        return to_route('blsc-guide.show', $feature->key());
    }

    public function unmarkDone(FeatureMap $map, Progress $progress, string $feature): RedirectResponse
    {
        $feature = $map->find($feature) ?? abort(404);
        $progress->unmark($feature->key());

        return to_route('blsc-guide.show', $feature->key());
    }
}
