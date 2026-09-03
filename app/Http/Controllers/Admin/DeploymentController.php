<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DeploymentActionRequest;
use App\Http\Requests\Admin\DeploymentChunkRequest;
use App\Services\Deployment\DeploymentManager;
use App\Services\Deployment\DeploymentStateStore;
use App\Services\Deployment\PackageBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DeploymentController extends Controller
{
    public function index(DeploymentStateStore $states): Response
    {
        return Inertia::render('Admin/Deployments/Index', [
            'capabilities' => [
                'export' => config('deployment.export_enabled') && $this->configured(),
                'import' => config('deployment.import_enabled') && $this->configured(),
                'export_enabled' => (bool) config('deployment.export_enabled'),
                'import_enabled' => (bool) config('deployment.import_enabled'),
                'app_id_configured' => filled(config('deployment.app_id')),
                'signing_key_configured' => strlen((string) config('deployment.signing_key')) >= 32,
                'zip' => class_exists(\ZipArchive::class),
            ],
            'currentVersion' => $this->currentVersion(), 'history' => $states->recent(),
        ]);
    }
    public function chunk(DeploymentChunkRequest $request, DeploymentManager $manager): JsonResponse { $data=$request->validated(); return response()->json($manager->acceptChunk($request->user()->id,$data,$request->file('chunk')->getPathname())); }
    public function complete(Request $request, DeploymentManager $manager): JsonResponse { $data=$request->validate(['operation_id'=>['required','uuid']]); return response()->json($manager->complete($data['operation_id'],$request->user()->id)); }
    public function verify(Request $request, DeploymentManager $manager): JsonResponse { return response()->json($manager->verify((string)$request->route('deployment'),$request->user()->id)); }
    public function status(Request $request, DeploymentStateStore $states): JsonResponse { $state=$states->get((string)$request->route('deployment')); abort_unless((int)$state['user_id']===$request->user()->id,403); return response()->json($state); }
    public function apply(DeploymentActionRequest $request, DeploymentManager $manager): JsonResponse { return response()->json($manager->advance((string)$request->route('deployment'),$request->user()->id)); }
    public function rollback(DeploymentActionRequest $request, DeploymentManager $manager): JsonResponse { return response()->json($manager->rollback((string)$request->route('deployment'),$request->user()->id)); }
    public function export(PackageBuilder $builder): BinaryFileResponse { abort_unless(config('deployment.export_enabled'),404); $result=$builder->build(); return response()->download($result['path'],$result['name'])->deleteFileAfterSend(true); }
    public function report(Request $request, DeploymentStateStore $states): BinaryFileResponse { $state=$states->get((string)$request->route('deployment')); abort_unless((int)$state['user_id']===$request->user()->id,403); $path=storage_path('app/deployments/'.$state['id'].'/report.json'); File::put($path,json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); return response()->download($path,"deployment-{$state['id']}.json"); }
    public function cleanup(DeploymentStateStore $states): JsonResponse { return response()->json(['removed'=>$states->cleanup()]); }
    private function configured(): bool { return filled(config('deployment.app_id')) && strlen((string)config('deployment.signing_key'))>=32; }
    private function currentVersion(): ?string { $path=base_path('deployment-manifest.json'); if(!is_file($path)) return null; try{return json_decode((string)file_get_contents($path),true,flags:JSON_THROW_ON_ERROR)['version']??null;}catch(\Throwable){return null;} }
}
