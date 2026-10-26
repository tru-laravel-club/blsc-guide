<?php

namespace TruLaravelClub\BlscGuide\Http;

use Illuminate\Routing\Controller;
use Illuminate\View\View;
use TruLaravelClub\BlscGuide\FeatureMap;

class GuideController extends Controller
{
    public function index(FeatureMap $map): View
    {
        return view('blsc-guide::index', [
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
        ]);
    }
}
