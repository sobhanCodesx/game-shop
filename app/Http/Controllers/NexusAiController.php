<?php

namespace App\Http\Controllers;

use App\Services\NexusAiSettings;
use Inertia\Inertia;
use Inertia\Response;

class NexusAiController extends Controller
{
    public function __invoke(NexusAiSettings $settings): Response
    {
        $config = $settings->publicConfig();

        abort_unless($config['enabled'], 404);

        return Inertia::render('NexusAi/Index', [
            'nexusAi' => $config,
        ]);
    }
}
